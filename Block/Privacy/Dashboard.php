<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Block\Privacy;

use Kkkonrad\Gdpr\Api\FeatureManagerInterface;
use Kkkonrad\Gdpr\Domain\DataRights\Request\RequestType;
use Kkkonrad\Gdpr\Domain\Shared\Feature\FeatureCode;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\StoreManagerInterface;

class Dashboard extends Template
{
    /** @param array<string, mixed> $data */
    public function __construct(
        Template\Context $context,
        private readonly FeatureManagerInterface $featureManager,
        private readonly StoreManagerInterface $storeManager,
        private readonly CustomerSession $customerSession,
        private readonly ResourceConnection $resourceConnection,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /** @return array<int, array{type:string, label:string, description:string}> */
    public function getActions(): array
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        $definitions = [
            [RequestType::EXPORT, FeatureCode::EXPORT_REQUEST, (string)__('Export my data'),
                (string)__('Generate a private archive containing your account and order data.')],
            [RequestType::ANONYMIZE, FeatureCode::ANONYMIZATION_REQUEST, (string)__('Anonymize my data'),
                (string)__('Request irreversible replacement of personal data where legally permitted.')],
            [RequestType::ERASE, FeatureCode::ERASURE_REQUEST, (string)__('Delete my account'),
                (string)__('Request account deletion after administrative and legal review.')],
        ];
        $actions = [];
        foreach ($definitions as [$type, $feature, $label, $description]) {
            if ($this->featureManager->isEnabled($feature, $storeId)) {
                $actions[] = compact('type', 'label', 'description');
            }
        }

        return $actions;
    }

    /** @return array<int, array<string, mixed>> */
    public function getRequests(): array
    {
        $requestTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_request');
        $exportTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_export');

        return $this->resourceConnection->getConnection()->fetchAll(
            $this->resourceConnection->getConnection()->select()
                ->from(['request' => $requestTable], [
                    'request_id', 'public_id', 'type', 'status', 'public_reason', 'created_at', 'completed_at',
                ])
                ->joinLeft(['export' => $exportTable], 'export.request_id = request.request_id', [
                    'export_id', 'expires_at',
                ])
                ->where('request.customer_id = ?', (int)$this->customerSession->getCustomerId())
                ->order('request.created_at DESC')
                ->limit(50)
        );
    }

    /** @param array<string, mixed> $request */
    public function canDownload(array $request): bool
    {
        return isset($request['export_id'], $request['expires_at'])
            && strtotime((string)$request['expires_at']) > time();
    }
}
