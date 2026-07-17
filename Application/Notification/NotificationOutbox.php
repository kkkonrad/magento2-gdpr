<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\Notification;

use DomainException;
use Kkkonrad\Gdpr\Api\ClockInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;
use Throwable;
use Zend_Db_Expr;

class NotificationOutbox
{
    private const XML_PATH_TTL = 'kkkonrad_gdpr/data_rights/notification_ttl_hours';

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly EncryptorInterface $encryptor,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ClockInterface $clock,
        private readonly NotificationSender $notificationSender
    ) {
    }

    /** @param array<string, mixed> $variables */
    public function prepare(
        ?int $requestId,
        string $notificationType,
        string $recipientEmail,
        string $recipientName,
        string $templateIdentifier,
        int $storeId,
        array $variables = [],
        bool $hold = false
    ): int {
        if (($requestId !== null && $requestId <= 0) || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('A valid recipient and optional request reference are required for a GDPR notification.');
        }
        $table = $this->resourceConnection->getTableName('kkkonrad_gdpr_notification');
        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();
        try {
            $existing = (int)$connection->fetchOne(
                $connection->select()
                    ->from($table, ['notification_id'])
                    ->where('request_id = ?', $requestId)
                    ->where('notification_type = ?', $notificationType)
                    ->forUpdate(true)
            );
            if ($existing > 0) {
                $connection->commit();
                return $existing;
            }
            $ttlHours = max(1, min(168, (int)$this->scopeConfig->getValue(
                self::XML_PATH_TTL,
                ScopeInterface::SCOPE_STORE,
                $storeId
            )));
            $connection->insert($table, [
                'request_id' => $requestId,
                'notification_type' => mb_substr($notificationType, 0, 64),
                'template_identifier' => mb_substr($templateIdentifier, 0, 128),
                'store_id' => $storeId,
                'recipient_email_encrypted' => $this->encryptor->encrypt($recipientEmail),
                'recipient_name_encrypted' => $this->encryptor->encrypt($recipientName),
                'variables_encrypted' => $this->encryptor->encrypt(json_encode($variables, JSON_THROW_ON_ERROR)),
                'status' => $hold ? 'held' : 'pending',
                'expires_at' => $this->clock->now()->modify('+' . $ttlHours . ' hours')->format('Y-m-d H:i:s'),
            ]);
            $id = (int)$connection->fetchOne('SELECT LAST_INSERT_ID()');
            $connection->commit();
            return $id;
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    public function sendForRequest(int $requestId, string $notificationType): bool
    {
        $table = $this->resourceConnection->getTableName('kkkonrad_gdpr_notification');
        $connection = $this->resourceConnection->getConnection();
        $connection->update($table, ['status' => 'pending', 'available_at' => $this->dbNow()], [
            'request_id = ?' => $requestId,
            'notification_type = ?' => $notificationType,
            'status = ?' => 'held',
            'expires_at > ?' => $this->dbNow(),
        ]);
        $id = (int)$connection->fetchOne(
            $connection->select()
                ->from($table, ['notification_id'])
                ->where('request_id = ?', $requestId)
                ->where('notification_type = ?', $notificationType)
                ->where('status IN (?)', ['pending', 'failed'])
                ->where('expires_at > ?', $this->dbNow())
                ->limit(1)
        );
        return $id > 0 && $this->send($id);
    }

    public function sendPrepared(int $notificationId): bool
    {
        return $notificationId > 0 && $this->send($notificationId);
    }

    /** @return array{sent:int,failed:int,expired:int,recovered:int} */
    public function processPending(int $limit = 50): array
    {
        $table = $this->resourceConnection->getTableName('kkkonrad_gdpr_notification');
        $connection = $this->resourceConnection->getConnection();
        $now = $this->dbNow();
        $staleBefore = $this->clock->now()->modify('-15 minutes')->format('Y-m-d H:i:s');
        $recovered = $connection->update($table, [
            'status' => 'failed',
            'error_code' => 'delivery_interrupted',
            'available_at' => $now,
        ], [
            'status = ?' => 'sending',
            'updated_at <= ?' => $staleBefore,
        ]);
        $expired = $connection->update($table, [
            'status' => 'expired',
            'recipient_email_encrypted' => null,
            'recipient_name_encrypted' => null,
            'variables_encrypted' => null,
            'error_code' => null,
        ], [
            'status IN (?)' => ['held', 'pending', 'failed'],
            'expires_at <= ?' => $now,
        ]);
        $ids = array_map('intval', $connection->fetchCol(
            $connection->select()
                ->from($table, ['notification_id'])
                ->where('status IN (?)', ['pending', 'failed'])
                ->where('available_at <= ?', $now)
                ->where('expires_at > ?', $now)
                ->order('notification_id ASC')
                ->limit(max(1, min(500, $limit)))
        ));
        $sent = 0;
        $failed = 0;
        foreach ($ids as $id) {
            try {
                $this->send($id) ? $sent++ : null;
            } catch (Throwable) {
                $failed++;
            }
        }
        return ['sent' => $sent, 'failed' => $failed, 'expired' => $expired, 'recovered' => $recovered];
    }

    private function send(int $notificationId): bool
    {
        $table = $this->resourceConnection->getTableName('kkkonrad_gdpr_notification');
        $connection = $this->resourceConnection->getConnection();
        $claimed = $connection->update($table, ['status' => 'sending'], [
            'notification_id = ?' => $notificationId,
            'status IN (?)' => ['pending', 'failed'],
            'available_at <= ?' => $this->dbNow(),
            'expires_at > ?' => $this->dbNow(),
        ]);
        if ($claimed !== 1) {
            return false;
        }
        $row = $connection->fetchRow(
            $connection->select()->from($table)->where('notification_id = ?', $notificationId)
        );
        if ($row === false) {
            return false;
        }
        try {
            $email = $this->encryptor->decrypt((string)$row['recipient_email_encrypted']);
            $name = $this->encryptor->decrypt((string)$row['recipient_name_encrypted']);
            $variables = json_decode(
                $this->encryptor->decrypt((string)$row['variables_encrypted']),
                true,
                32,
                JSON_THROW_ON_ERROR
            );
            $this->notificationSender->prepare(
                $email,
                $name,
                (string)$row['template_identifier'],
                (int)$row['store_id'],
                is_array($variables) ? $variables : []
            )->sendMessage();
            $connection->update($table, [
                'status' => 'sent',
                'recipient_email_encrypted' => null,
                'recipient_name_encrypted' => null,
                'variables_encrypted' => null,
                'error_code' => null,
                'sent_at' => $this->dbNow(),
                'attempt_count' => new Zend_Db_Expr('attempt_count + 1'),
            ], ['notification_id = ?' => $notificationId]);
            return true;
        } catch (Throwable $exception) {
            $connection->update($table, [
                'status' => 'failed',
                'error_code' => 'delivery_failed',
                'available_at' => $this->clock->now()->modify('+5 minutes')->format('Y-m-d H:i:s'),
                'attempt_count' => new Zend_Db_Expr('attempt_count + 1'),
            ], ['notification_id = ?' => $notificationId]);
            throw $exception;
        }
    }

    private function dbNow(): string
    {
        return $this->clock->now()->format('Y-m-d H:i:s');
    }
}
