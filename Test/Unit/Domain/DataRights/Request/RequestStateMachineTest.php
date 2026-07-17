<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Test\Unit\Domain\DataRights\Request;

use DomainException;
use Kkkonrad\Gdpr\Domain\DataRights\Request\RequestStateMachine;
use Kkkonrad\Gdpr\Domain\DataRights\Request\RequestStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RequestStateMachineTest extends TestCase
{
    #[DataProvider('validTransitions')]
    public function testAllowsConfiguredTransitions(string $from, string $to): void
    {
        self::assertTrue((new RequestStateMachine())->canTransition($from, $to));
        (new RequestStateMachine())->assertCanTransition($from, $to);
        self::addToAssertionCount(1);
    }

    public static function validTransitions(): array
    {
        return [
            [RequestStatus::SUBMITTED, RequestStatus::VALIDATION],
            [RequestStatus::VALIDATION, RequestStatus::BLOCKED],
            [RequestStatus::VALIDATION, RequestStatus::PENDING_APPROVAL],
            [RequestStatus::VALIDATION, RequestStatus::QUEUED],
            [RequestStatus::PENDING_APPROVAL, RequestStatus::REJECTED],
            [RequestStatus::PENDING_APPROVAL, RequestStatus::QUEUED],
            [RequestStatus::QUEUED, RequestStatus::PROCESSING],
            [RequestStatus::PROCESSING, RequestStatus::COMPLETED],
            [RequestStatus::PROCESSING, RequestStatus::PARTIALLY_COMPLETED],
            [RequestStatus::PROCESSING, RequestStatus::FAILED],
            [RequestStatus::FAILED, RequestStatus::QUEUED],
            [RequestStatus::PARTIALLY_COMPLETED, RequestStatus::QUEUED],
            [RequestStatus::COMPLETED, RequestStatus::EXPIRED],
        ];
    }

    #[DataProvider('invalidTransitions')]
    public function testRejectsInvalidAndTerminalTransitions(string $from, string $to): void
    {
        $this->expectException(DomainException::class);
        (new RequestStateMachine())->assertCanTransition($from, $to);
    }

    public static function invalidTransitions(): array
    {
        return [
            [RequestStatus::SUBMITTED, RequestStatus::COMPLETED],
            [RequestStatus::COMPLETED, RequestStatus::QUEUED],
            [RequestStatus::REJECTED, RequestStatus::QUEUED],
            [RequestStatus::BLOCKED, RequestStatus::VALIDATION],
            [RequestStatus::EXPIRED, RequestStatus::QUEUED],
            [RequestStatus::QUEUED, RequestStatus::COMPLETED],
            ['unknown', RequestStatus::VALIDATION],
        ];
    }
}
