<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Test\Unit\Application\DataRights;

use DomainException;
use Kkkonrad\Gdpr\Application\DataRights\PasswordReauthentication;
use Magento\Customer\Model\AuthenticationInterface;
use PHPUnit\Framework\TestCase;

class PasswordReauthenticationTest extends TestCase
{
    public function testCredentialIsValidatedWithoutStartingAnotherLoginFlow(): void
    {
        $authentication = $this->createMock(AuthenticationInterface::class);
        $authentication->expects(self::once())
            ->method('authenticate')
            ->with(17, 'current-password');

        (new PasswordReauthentication($authentication))->reauthenticate(17, 1, 'current-password');
    }

    public function testEmptyCredentialIsRejectedBeforeAuthentication(): void
    {
        $authentication = $this->createMock(AuthenticationInterface::class);
        $authentication->expects(self::never())->method('authenticate');

        $this->expectException(DomainException::class);
        (new PasswordReauthentication($authentication))->reauthenticate(17, 1, null);
    }
}

