<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Test\Unit\Application\DataRights;

use DomainException;
use Kkkonrad\Gdpr\Api\ConfigProviderInterface;
use Kkkonrad\Gdpr\Api\FeatureManagerInterface;
use Kkkonrad\Gdpr\Api\JobSchedulerInterface;
use Kkkonrad\Gdpr\Api\RequestManagementInterface;
use Kkkonrad\Gdpr\Application\DataRights\EligibilityPolicy;
use Kkkonrad\Gdpr\Application\DataRights\RequestSubmission;
use Kkkonrad\Gdpr\Application\Notification\RequestNotification;
use Kkkonrad\Gdpr\Application\Notification\AdminNotification;
use Kkkonrad\Gdpr\Domain\DataRights\Request\RequestType;
use Magento\Framework\App\CacheInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use RuntimeException;

class RequestSubmissionTest extends TestCase
{
    public function testRateLimitStopsRequestBeforePersistence(): void
    {
        $featureManager = $this->createMock(FeatureManagerInterface::class);
        $featureManager->method('isEnabled')->willReturn(true);
        $requestManagement = $this->createMock(RequestManagementInterface::class);
        $requestManagement->expects(self::never())->method('submit');
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')->willReturn('5');
        $cache->expects(self::never())->method('save');
        $config = $this->createMock(ConfigProviderInterface::class);
        $config->method('getPositiveInt')->willReturn(5);
        $service = new RequestSubmission(
            $featureManager,
            $requestManagement,
            $this->createStub(JobSchedulerInterface::class),
            $this->createStub(EligibilityPolicy::class),
            $this->createStub(RequestNotification::class),
            $this->createStub(LoggerInterface::class),
            $cache,
            $config,
            $this->createStub(AdminNotification::class),
            $this->createStub(ResourceConnection::class)
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Too many privacy requests');
        $service->submit(11, RequestType::EXPORT, 2);
    }

    public function testExportRequestStateAndJobAreCommittedAtomically(): void
    {
        [$service, $connection, $jobScheduler] = $this->exportService();
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('commit');
        $connection->expects(self::never())->method('rollBack');
        $jobScheduler->expects(self::once())->method('schedule')->willReturn(91);

        self::assertSame(33, $service->submit(11, RequestType::EXPORT, 2));
    }

    public function testSchedulerFailureRollsBackRequestStateTransaction(): void
    {
        [$service, $connection, $jobScheduler] = $this->exportService();
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::never())->method('commit');
        $connection->expects(self::once())->method('rollBack');
        $jobScheduler->method('schedule')->willThrowException(new RuntimeException('queue unavailable'));

        $this->expectException(RuntimeException::class);
        $service->submit(11, RequestType::EXPORT, 2);
    }

    /** @return array{RequestSubmission,AdapterInterface,JobSchedulerInterface} */
    private function exportService(): array
    {
        $featureManager = $this->createMock(FeatureManagerInterface::class);
        $featureManager->method('isEnabled')->willReturn(true);
        $requestManagement = $this->createMock(RequestManagementInterface::class);
        $requestManagement->method('submit')->willReturn(33);
        $eligibility = $this->createMock(EligibilityPolicy::class);
        $eligibility->method('evaluate')->willReturn([
            'eligible' => true,
            'code' => 'eligible',
            'message' => '',
            'admin_message' => '',
        ]);
        $jobScheduler = $this->createMock(JobSchedulerInterface::class);
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')->willReturn(false);
        $config = $this->createMock(ConfigProviderInterface::class);
        $config->method('getPositiveInt')->willReturn(5);
        $resource = $this->createMock(ResourceConnection::class);
        $connection = $this->createMock(AdapterInterface::class);
        $resource->method('getConnection')->willReturn($connection);
        $service = new RequestSubmission(
            $featureManager,
            $requestManagement,
            $jobScheduler,
            $eligibility,
            $this->createStub(RequestNotification::class),
            $this->createStub(LoggerInterface::class),
            $cache,
            $config,
            $this->createStub(AdminNotification::class),
            $resource
        );
        return [$service, $connection, $jobScheduler];
    }
}
