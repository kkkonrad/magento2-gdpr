<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Block\Adminhtml\Request;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\AuthorizationInterface;

class Toolbar extends Template
{
    /** @param array<string, mixed> $data */
    public function __construct(
        Context $context,
        private readonly AuthorizationInterface $authorization,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function canExport(): bool
    {
        return $this->authorization->isAllowed('Kkkonrad_Gdpr::requests_export');
    }

    public function canCreateCustomerExport(): bool
    {
        return $this->authorization->isAllowed('Kkkonrad_Gdpr::requests_export_on_behalf');
    }
}
