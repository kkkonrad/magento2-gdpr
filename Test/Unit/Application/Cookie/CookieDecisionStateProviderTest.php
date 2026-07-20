<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Test\Unit\Application\Cookie;

use DomainException;
use Kkkonrad\Gdpr\Api\Cookie\CookieRegistryInterface;
use Kkkonrad\Gdpr\Application\Cookie\CookieDecisionStateProvider;
use Kkkonrad\Gdpr\Domain\Cookie\DecisionToken;
use Magento\Framework\Stdlib\CookieManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CookieDecisionStateProviderTest extends TestCase
{
    private CookieManagerInterface&MockObject $cookieManager;
    private DecisionToken&MockObject $decisionToken;
    private CookieRegistryInterface&MockObject $cookieRegistry;
    private CookieDecisionStateProvider $provider;

    protected function setUp(): void
    {
        $this->cookieManager = $this->createMock(CookieManagerInterface::class);
        $this->decisionToken = $this->createMock(DecisionToken::class);
        $this->cookieRegistry = $this->createMock(CookieRegistryInterface::class);
        $this->provider = new CookieDecisionStateProvider(
            $this->cookieManager,
            $this->decisionToken,
            $this->cookieRegistry
        );
    }

    public function testReturnsOnlyAValidDecisionForTheCurrentStoreAndPolicy(): void
    {
        $this->cookieManager->method('getCookie')->willReturn('signed-token');
        $this->decisionToken->method('verify')->with('signed-token')->willReturn([
            'store_id' => 2,
            'policy' => 'policy-2',
            'choices' => ['essential' => true, 'marketing' => false],
            'expires_at' => 2000000000,
        ]);
        $this->cookieRegistry->method('getGroups')->with(2)->willReturn([
            ['code' => 'essential', 'is_required' => true],
            ['code' => 'marketing', 'is_required' => false],
        ]);

        self::assertSame([
            'policy' => 'policy-2',
            'choices' => ['essential' => true, 'marketing' => false],
            'expires_at' => 2000000000,
        ], $this->provider->getVerifiedDecision(2, 'policy-2'));
    }

    public function testRejectsATokenWithAnInvalidSignature(): void
    {
        $this->cookieManager->method('getCookie')->willReturn('forged-token');
        $this->decisionToken->method('verify')->willThrowException(
            new DomainException('Invalid cookie consent token signature.')
        );
        $this->cookieRegistry->expects(self::never())->method('getGroups');

        self::assertNull($this->provider->getVerifiedDecision(1, 'current-policy'));
    }

    public function testRejectsADecisionFromAnotherStoreOrPolicy(): void
    {
        $this->cookieManager->method('getCookie')->willReturn('signed-token');
        $this->decisionToken->method('verify')->willReturn([
            'store_id' => 3,
            'policy' => 'old-policy',
            'choices' => ['essential' => true],
            'expires_at' => 2000000000,
        ]);
        $this->cookieRegistry->expects(self::never())->method('getGroups');

        self::assertNull($this->provider->getVerifiedDecision(2, 'current-policy'));
    }

    public function testRejectsMalformedOrIncompleteChoices(): void
    {
        $this->cookieManager->method('getCookie')->willReturn('signed-token');
        $this->decisionToken->method('verify')->willReturn([
            'store_id' => 2,
            'policy' => 'policy-2',
            'choices' => ['essential' => false, 'unknown' => true],
            'expires_at' => 2000000000,
        ]);
        $this->cookieRegistry->method('getGroups')->willReturn([
            ['code' => 'essential', 'is_required' => true],
            ['code' => 'marketing', 'is_required' => false],
        ]);

        self::assertNull($this->provider->getVerifiedDecision(2, 'policy-2'));
    }
}
