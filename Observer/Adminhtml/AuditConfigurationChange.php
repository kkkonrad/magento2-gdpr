<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Observer\Adminhtml;

use Kkkonrad\Gdpr\Application\Audit\AuditWriter;
use Magento\Backend\Model\Auth\Session as AdminSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class AuditConfigurationChange implements ObserverInterface
{
    public function __construct(
        private readonly AuditWriter $auditWriter,
        private readonly AdminSession $adminSession,
        private readonly RequestInterface $request
    ) {
    }

    public function execute(Observer $observer): void
    {
        $groups = $this->request->getParam('groups', []);
        $changedFields = [];
        if (is_array($groups)) {
            foreach ($groups as $groupCode => $group) {
                if (!is_array($group) || !is_array($group['fields'] ?? null)) {
                    continue;
                }
                foreach (array_keys($group['fields']) as $fieldCode) {
                    $changedFields[] = (string)$groupCode . '/' . (string)$fieldCode;
                }
            }
        }
        $user = $this->adminSession->getUser();
        $this->auditWriter->write(
            'configuration.changed',
            'configuration',
            'kkkonrad_gdpr',
            'admin',
            $user !== null ? (int)$user->getId() : null,
            null,
            null,
            ['changed_fields' => $changedFields]
        );
    }
}
