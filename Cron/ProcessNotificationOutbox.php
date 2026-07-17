<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Cron;

use Kkkonrad\Gdpr\Application\Notification\NotificationOutbox;
use Psr\Log\LoggerInterface;

class ProcessNotificationOutbox
{
    public function __construct(
        private readonly NotificationOutbox $notificationOutbox,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        $result = $this->notificationOutbox->processPending(50);
        if ($result['failed'] > 0) {
            $this->logger->warning('Some GDPR outbox notifications could not be delivered.', [
                'failed_count' => $result['failed'],
            ]);
        }
        if ($result['recovered'] > 0) {
            $this->logger->warning('Interrupted GDPR outbox notifications were recovered for retry.', [
                'recovered_count' => $result['recovered'],
            ]);
        }
    }
}
