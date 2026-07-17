<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Test\Unit\Application\DataRights;

use Kkkonrad\Gdpr\Api\FeatureManagerInterface;
use Kkkonrad\Gdpr\Api\JobSchedulerInterface;
use Kkkonrad\Gdpr\Api\RequestManagementInterface;
use Kkkonrad\Gdpr\Application\DataRights\AdminExportSubmission;
use Kkkonrad\Gdpr\Application\DataRights\EligibilityPolicy;
use Kkkonrad\Gdpr\Application\Notification\RequestNotification;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class AdminExportSubmissionTest extends TestCase
{
    public function testCreatesAuditableAdminRequestAndJobAtomically(): void
    {
        [$service, $connection, $requestManagement, $jobScheduler] = $this->service();
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('commit');
        $connection->expects(self::never())->method('rollBack');
        $requestManagement->expects(self::once())
            ->method('submit')
            ->with(11, 'export', 4, null, 'admin', 7)
            ->willReturn(33);
        $requestManagement->expects(self::exactly(2))->method('transition');
        $jobScheduler->expects(self::once())->method('schedule')->willReturn(91);

        self::assertSame(33, $service->submit(11, 7, 'Customer identity was verified.'));
    }

    public function testSchedulerFailureRollsBackAdminRequest(): void
    {
        [$service, $connection, $requestManagement, $jobScheduler] = $this->service();
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::never())->method('commit');
        $connection->expects(self::once())->method('rollBack');
        $requestManagement->method('submit')->willReturn(33);
        $jobScheduler->method('schedule')->willThrowException(new RuntimeException('queue unavailable'));

        $this->expectException(RuntimeException::class);
        $service->submit(11, 7, 'Customer identity was verified.');
    }

    /**
     * @return array{
     *     AdminExportSubmission,
     *     AdapterInterface,
     *     RequestManagementInterface,
     *     JobSchedulerInterface
     * }
     */
    private function service(): array
    {
        $featureManager = $this->createMock(FeatureManagerInterface::class);
        $featureManager->method('isEnabled')->willReturn(true);
        $requestManagement = $this->createMock(RequestManagementInterface::class);
        $jobScheduler = $this->createMock(JobSchedulerInterface::class);
        $eligibility = $this->createMock(EligibilityPolicy::class);
        $eligibility->method('evaluate')->willReturn([
            'eligible' => true,
            'code' => 'eligible',
            'message' => '',
            'admin_message' => '',
        ]);
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getStoreId')->willReturn(4);
        $customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $customerRepository->method('getById')->with(11)->willReturn($customer);
        $resource = $this->createMock(ResourceConnection::class);
        $connection = $this->createMock(AdapterInterface::class);
        $resource->method('getConnection')->willReturn($connection);

        return [
            new AdminExportSubmission(
                $featureManager,
                $requestManagement,
                $jobScheduler,
                $eligibility,
                $customerRepository,
                $this->createStub(RequestNotification::class),
                $this->createStub(LoggerInterface::class),
                $resource
            ),
            $connection,
            $requestManagement,
            $jobScheduler,
        ];
    }
}
