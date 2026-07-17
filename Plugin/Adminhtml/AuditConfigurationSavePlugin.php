<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Plugin\Adminhtml;

use Kkkonrad\Gdpr\Api\CorrelationIdProviderInterface;
use Kkkonrad\Gdpr\Application\Audit\AuditWriter;
use Magento\Backend\Model\Auth\Session as AdminSession;
use Magento\Config\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;

class AuditConfigurationSavePlugin
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly StoreManagerInterface $storeManager,
        private readonly AdminSession $adminSession,
        private readonly CorrelationIdProviderInterface $correlationIdProvider,
        private readonly AuditWriter $auditWriter
    ) {
    }

    public function aroundSave(Config $subject, callable $proceed): Config
    {
        if ($subject->getSection() !== 'kkkonrad_gdpr') {
            return $proceed();
        }
        [$scope, $scopeId] = $this->resolveScope($subject);
        $before = $this->loadScopeValues($scope, $scopeId);
        $result = $proceed();
        $after = $this->loadScopeValues($scope, $scopeId);
        $changes = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $path) {
            $oldValue = $before[$path] ?? null;
            $newValue = $after[$path] ?? null;
            if ($oldValue === $newValue) {
                continue;
            }
            $changes[$path] = [
                'old' => $this->safeValue($path, $oldValue),
                'new' => $this->safeValue($path, $newValue),
            ];
        }
        if ($changes !== []) {
            $user = $this->adminSession->getUser();
            $this->auditWriter->write(
                'configuration.changed',
                'configuration',
                $scope . ':' . $scopeId,
                'admin',
                $user !== null ? (int)$user->getId() : null,
                $scope === 'stores' ? $scopeId : null,
                $this->correlationIdProvider->get(),
                ['changes' => $changes]
            );
        }
        return $result;
    }

    /** @return array{string, int} */
    private function resolveScope(Config $config): array
    {
        $storeCode = trim($config->getStore());
        if ($storeCode !== '') {
            return ['stores', (int)$this->storeManager->getStore($storeCode)->getId()];
        }
        $websiteCode = trim($config->getWebsite());
        if ($websiteCode !== '') {
            return ['websites', (int)$this->storeManager->getWebsite($websiteCode)->getId()];
        }
        return ['default', 0];
    }

    /** @return array<string, string> */
    private function loadScopeValues(string $scope, int $scopeId): array
    {
        $table = $this->resourceConnection->getTableName('core_config_data');
        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchPairs(
            $connection->select()
                ->from($table, ['path', 'value'])
                ->where('scope = ?', $scope)
                ->where('scope_id = ?', $scopeId)
                ->where('path LIKE ?', 'kkkonrad_gdpr/%')
        );
        return array_map('strval', $rows);
    }

    /** @return string|array{sha256:string,length:int} */
    private function safeValue(string $path, ?string $value): string|array
    {
        if ($value === null) {
            return '[inherited]';
        }
        if (preg_match('/(?:password|secret|token|private|credential|api[_-]?key)/i', $path) === 1) {
            return '[redacted]';
        }
        if (mb_strlen($value) <= 128 && preg_match('/^[\pL\pN_.,:@\/-]*$/u', $value) === 1) {
            return $value;
        }
        return ['sha256' => hash('sha256', $value), 'length' => mb_strlen($value)];
    }
}
