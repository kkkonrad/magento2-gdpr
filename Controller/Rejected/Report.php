<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Controller\Rejected;

use Kkkonrad\Gdpr\Api\Cookie\CookieRegistryInterface;
use Kkkonrad\Gdpr\Api\ConfigProviderInterface;
use Kkkonrad\Gdpr\Api\FeatureManagerInterface;
use Kkkonrad\Gdpr\Domain\Cookie\CookiePatternMatcher;
use Kkkonrad\Gdpr\Domain\Shared\Feature\FeatureCode;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Store\Model\StoreManagerInterface;
use Zend_Db_Expr;

class Report implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly Http $request,
        private readonly JsonFactory $jsonFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly FeatureManagerInterface $featureManager,
        private readonly CookieRegistryInterface $cookieRegistry,
        private readonly CookiePatternMatcher $patternMatcher,
        private readonly StoreManagerInterface $storeManager,
        private readonly ResourceConnection $resourceConnection,
        private readonly CacheInterface $cache,
        private readonly ConfigProviderInterface $configProvider
    ) {
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $storeId = (int)$this->storeManager->getStore()->getId();
        if (!$this->featureManager->isEnabled(FeatureCode::COOKIE_REJECTED_TRACKING, $storeId)) {
            return $result->setHttpResponseCode(404)->setData(['success' => false]);
        }
        $rateKey = 'kkkonrad_gdpr_rejected_' . hash('sha256', (string)$this->request->getServer('REMOTE_ADDR'));
        $count = (int)$this->cache->load($rateKey);
        if ($count >= 20) {
            return $result->setHttpResponseCode(429)->setData(['success' => false]);
        }
        $this->cache->save((string)($count + 1), $rateKey, [], 3600);
        $input = json_decode((string)$this->request->getContent(), true);
        $names = is_array($input) && is_array($input['names'] ?? null) ? $input['names'] : [];
        $domain = is_array($input) && is_string($input['domain'] ?? null)
            ? mb_strtolower(mb_substr($input['domain'], 0, 255))
            : '';
        if ($domain !== '' && preg_match('/^[a-z0-9.-]+$/', $domain) !== 1) {
            $domain = '';
        }
        $patterns = [];
        foreach ($this->cookieRegistry->getGroups($storeId) as $group) {
            foreach ($group['cookies'] as $cookie) {
                $patterns[] = (string)$cookie['code_pattern'];
            }
        }
        $table = $this->resourceConnection->getTableName('kkkonrad_gdpr_rejected_cookie');
        $connection = $this->resourceConnection->getConnection();
        $unknownOnly = $this->configProvider->getString(
            'kkkonrad_gdpr/cookie/track_unknown_only',
            $storeId
        ) === '1';
        $validNames = array_values(array_unique(array_filter($names, 'is_string')));
        foreach (array_slice($validNames, 0, 50) as $name) {
            if (preg_match('/^[A-Za-z0-9_.-]{1,255}$/', $name) !== 1) {
                continue;
            }
            $known = false;
            foreach ($patterns as $pattern) {
                if ($this->patternMatcher->matches($pattern, $name)) {
                    $known = true;
                    break;
                }
            }
            if ($unknownOnly && $known) {
                continue;
            }
            $row = [
                'store_id' => $storeId,
                'cookie_name' => $name,
                'name_hash' => hash('sha256', $name),
                'domain' => $domain !== '' ? $domain : null,
                'domain_hash' => hash('sha256', $domain),
                'is_unknown' => (int)!$known,
                'occurrence_count' => 1,
            ];
            $affected = $connection->update($table, [
                'occurrence_count' => new Zend_Db_Expr('occurrence_count + 1'),
                'last_seen_at' => new Zend_Db_Expr('CURRENT_TIMESTAMP'),
                'is_unknown' => (int)!$known,
            ], [
                'store_id = ?' => $storeId,
                'name_hash = ?' => $row['name_hash'],
                'domain_hash = ?' => $row['domain_hash'],
            ]);
            if ($affected === 0) {
                try {
                    $connection->insert($table, $row);
                } catch (\Throwable) {
                    $connection->update($table, [
                        'occurrence_count' => new Zend_Db_Expr('occurrence_count + 1'),
                        'last_seen_at' => new Zend_Db_Expr('CURRENT_TIMESTAMP'),
                    ], [
                        'store_id = ?' => $storeId,
                        'name_hash = ?' => $row['name_hash'],
                        'domain_hash = ?' => $row['domain_hash'],
                    ]);
                }
            }
        }

        return $result->setData(['success' => true]);
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        $this->request->setParam('form_key', (string)$this->request->getHeader('X-Form-Key'));

        return $this->formKeyValidator->validate($request);
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }
}
