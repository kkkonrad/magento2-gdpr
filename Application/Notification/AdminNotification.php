<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\Notification;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class AdminNotification
{
    private const XML_PATH_ENABLED = 'kkkonrad_gdpr/email/admin_alerts_enabled';
    private const XML_PATH_ROLES = 'kkkonrad_gdpr/email/admin_alert_role_ids';

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly NotificationOutbox $notificationOutbox,
        private readonly LoggerInterface $logger
    ) {
    }

    public function erasureRequested(int $requestId, int $storeId): int
    {
        $requestTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_request');
        $request = $this->resourceConnection->getConnection()->fetchRow(
            $this->resourceConnection->getConnection()->select()
                ->from($requestTable, ['public_id', 'type', 'due_at'])
                ->where('request_id = ?', $requestId)
        );
        if ($request === false) {
            return 0;
        }
        return $this->notify(
            $requestId,
            'admin.erasure',
            'kkkonrad_gdpr/email/admin_erasure_template',
            'kkkonrad_gdpr_admin_erasure',
            $storeId,
            [
                'request_id' => (string)$request['public_id'],
                'request_type' => (string)$request['type'],
                'due_at' => (string)$request['due_at'],
            ]
        );
    }

    public function automationFailed(
        int $jobId,
        ?int $requestId,
        string $jobType,
        int $storeId,
        string $errorCode
    ): int {
        return $this->notify(
            $requestId,
            'admin.failure.' . $jobId,
            'kkkonrad_gdpr/email/admin_failure_template',
            'kkkonrad_gdpr_admin_failure',
            $storeId,
            [
                'job_id' => (string)$jobId,
                'job_type' => $jobType,
                'error_code' => $errorCode,
            ]
        );
    }

    /** @param array<string, string> $variables */
    private function notify(
        ?int $requestId,
        string $typePrefix,
        string $templatePath,
        string $defaultTemplate,
        int $storeId,
        array $variables
    ): int {
        if (!$this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, $storeId)) {
            return 0;
        }
        $template = (string)$this->scopeConfig->getValue($templatePath, ScopeInterface::SCOPE_STORE, $storeId);
        $template = $template !== '' ? $template : $defaultTemplate;
        $sent = 0;
        foreach ($this->recipients() as $recipient) {
            try {
                $id = $this->notificationOutbox->prepare(
                    $requestId,
                    mb_substr($typePrefix . '.' . $recipient['user_id'], 0, 64),
                    $recipient['email'],
                    trim($recipient['firstname'] . ' ' . $recipient['lastname']),
                    $template,
                    $storeId,
                    $variables
                );
                if ($this->notificationOutbox->sendPrepared($id)) {
                    $sent++;
                }
            } catch (Throwable $exception) {
                $this->logger->warning('A GDPR administrator alert could not be queued.', [
                    'admin_user_id' => $recipient['user_id'],
                    'notification_type' => $typePrefix,
                    'exception_class' => $exception::class,
                ]);
            }
        }
        return $sent;
    }

    /** @return array<int, array{user_id:int,email:string,firstname:string,lastname:string}> */
    private function recipients(): array
    {
        $roleIds = array_values(array_filter(array_map(
            'intval',
            explode(',', (string)$this->scopeConfig->getValue(self::XML_PATH_ROLES))
        )));
        if ($roleIds === []) {
            return [];
        }
        $connection = $this->resourceConnection->getConnection();
        $roleTable = $this->resourceConnection->getTableName('authorization_role');
        $userTable = $this->resourceConnection->getTableName('admin_user');
        return array_map(
            static fn (array $row): array => [
                'user_id' => (int)$row['user_id'],
                'email' => (string)$row['email'],
                'firstname' => (string)$row['firstname'],
                'lastname' => (string)$row['lastname'],
            ],
            $connection->fetchAll(
                $connection->select()
                    ->from(['role' => $roleTable], [])
                    ->joinInner(['user' => $userTable], 'user.user_id = role.user_id', [
                        'user_id', 'email', 'firstname', 'lastname',
                    ])
                    ->where('role.parent_id IN (?)', $roleIds)
                    ->where('role.role_type = ?', 'U')
                    ->where('user.is_active = ?', 1)
                    ->group('user.user_id')
            )
        );
    }
}
