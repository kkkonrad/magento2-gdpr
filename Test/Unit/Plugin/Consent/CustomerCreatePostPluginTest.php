<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Test\Unit\Plugin\Consent;

use Kkkonrad\Gdpr\Application\Consent\FormConsentHandler;
use Kkkonrad\Gdpr\Domain\Consent\ConsentLocation;
use Kkkonrad\Gdpr\Plugin\Consent\CustomerCreatePostPlugin;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\App\Request\Http;
use PHPUnit\Framework\TestCase;

class CustomerCreatePostPluginTest extends TestCase
{
    public function testAcceptsNullRedirectUrlFromMagentoAccountCreation(): void
    {
        $handler = $this->createMock(FormConsentHandler::class);
        $handler->expects(self::once())->method('validate')->with(ConsentLocation::REGISTRATION);
        $request = $this->createMock(Http::class);
        $request->method('getFullActionName')->willReturn('customer_account_createpost');
        $customer = $this->createMock(CustomerInterface::class);
        $plugin = new CustomerCreatePostPlugin($handler, $request);

        self::assertSame(
            [$customer, 'password', null],
            $plugin->beforeCreateAccount(
                $this->createMock(AccountManagementInterface::class),
                $customer,
                'password',
                null
            )
        );
    }
}
