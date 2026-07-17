<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Test\Unit\Application\Notification;

use DateTimeImmutable;
use Kkkonrad\Gdpr\Api\ClockInterface;
use Kkkonrad\Gdpr\Application\Notification\NotificationOutbox;
use Kkkonrad\Gdpr\Application\Notification\NotificationSender;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Encryption\EncryptorInterface;
use PHPUnit\Framework\TestCase;

class NotificationOutboxTest extends TestCase
{
    public function testProcessPendingRecoversInterruptedDeliveryBeforeExpiringPayloads(): void
    {
        $resource = $this->createMock(ResourceConnection::class);
        $connection = $this->createMock(AdapterInterface::class);
        $select = $this->createMock(Select::class);
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-07-16 12:00:00'));

        $resource->method('getTableName')
            ->with('kkkonrad_gdpr_notification')
            ->willReturn('kkkonrad_gdpr_notification');
        $resource->method('getConnection')->willReturn($connection);

        $updates = [];
        $connection->expects(self::exactly(2))->method('update')->willReturnCallback(
            static function (string $table, array $data, array $where) use (&$updates): int {
                $updates[] = [$table, $data, $where];
                return count($updates) === 1 ? 2 : 1;
            }
        );
        $connection->method('select')->willReturn($select);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('order')->willReturnSelf();
        $select->method('limit')->willReturnSelf();
        $connection->method('fetchCol')->with($select)->willReturn([]);

        $outbox = new NotificationOutbox(
            $resource,
            $this->createStub(EncryptorInterface::class),
            $this->createStub(ScopeConfigInterface::class),
            $clock,
            $this->createStub(NotificationSender::class)
        );

        $result = $outbox->processPending(25);

        self::assertSame(['sent' => 0, 'failed' => 0, 'expired' => 1, 'recovered' => 2], $result);
        self::assertSame('failed', $updates[0][1]['status']);
        self::assertSame('delivery_interrupted', $updates[0][1]['error_code']);
        self::assertSame('sending', $updates[0][2]['status = ?']);
        self::assertSame('2026-07-16 11:45:00', $updates[0][2]['updated_at <= ?']);
        self::assertSame(['held', 'pending', 'failed'], $updates[1][2]['status IN (?)']);
    }
}
