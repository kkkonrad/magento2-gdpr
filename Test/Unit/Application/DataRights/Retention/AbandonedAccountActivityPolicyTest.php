<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Test\Unit\Application\DataRights\Retention;

use Kkkonrad\Gdpr\Application\DataRights\Retention\AbandonedAccountActivityPolicy;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;

class AbandonedAccountActivityPolicyTest extends TestCase
{
    public function testLatestActivityPreventsDelayedErasureAfterAccountWasUpdated(): void
    {
        $policy = $this->policy(
            ['created_at' => '2020-01-01 00:00:00', 'updated_at' => '2026-07-15 12:00:00', 'store_id' => 2],
            '2020-05-01 00:00:00',
            '2020-06-01 00:00:00'
        );

        self::assertFalse($policy->isStillInactive(
            17,
            2,
            '2026-01-01 00:00:00',
            'latest_activity'
        ));
    }

    public function testOldLastOrderRemainsEligibleForDefaultReference(): void
    {
        $policy = $this->policy(
            ['created_at' => '2020-01-01 00:00:00', 'updated_at' => '2026-07-15 12:00:00', 'store_id' => 2],
            '2020-05-01 00:00:00',
            '2026-07-15 12:00:00'
        );

        self::assertTrue($policy->isStillInactive(
            17,
            2,
            '2026-01-01 00:00:00',
            'last_order_or_created'
        ));
    }

    /** @param array{created_at:string,updated_at:string,store_id:int} $customer */
    private function policy(array $customer, string $lastOrder, string $lastLogin): AbandonedAccountActivityPolicy
    {
        $resource = $this->createMock(ResourceConnection::class);
        $connection = $this->createMock(AdapterInterface::class);
        $select = $this->createMock(Select::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnCallback(static fn (string $name): string => $name);
        $connection->method('select')->willReturn($select);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $connection->method('fetchRow')->willReturn($customer);
        $connection->method('fetchOne')->willReturnOnConsecutiveCalls($lastOrder, $lastLogin);
        return new AbandonedAccountActivityPolicy($resource);
    }
}
