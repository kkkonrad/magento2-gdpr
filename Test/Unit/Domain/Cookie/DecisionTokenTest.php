<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Test\Unit\Domain\Cookie;

use DomainException;
use Kkkonrad\Gdpr\Domain\Cookie\DecisionToken;
use Magento\Framework\App\DeploymentConfig;
use PHPUnit\Framework\TestCase;

class DecisionTokenTest extends TestCase
{
    public function testRoundTripAndSignatureVerification(): void
    {
        $deploymentConfig = $this->createMock(DeploymentConfig::class);
        $deploymentConfig->method('get')->with('crypt/key')->willReturn('unit-test-secret');
        $service = new DecisionToken($deploymentConfig);
        $token = $service->create([
            'choices' => ['essential' => true],
            'expires_at' => time() + 60,
        ]);

        self::assertSame(['essential' => true], $service->verify($token)['choices']);

        $this->expectException(DomainException::class);
        $service->verify($token . 'changed');
    }
}
