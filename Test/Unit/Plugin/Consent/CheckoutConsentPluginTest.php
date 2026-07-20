<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Test\Unit\Plugin\Consent;

use Kkkonrad\Gdpr\Application\Consent\FormConsentHandler;
use Kkkonrad\Gdpr\Domain\Consent\ConsentLocation;
use Kkkonrad\Gdpr\Plugin\Consent\CheckoutConsentPlugin;
use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use PHPUnit\Framework\TestCase;

class CheckoutConsentPluginTest extends TestCase
{
    public function testConsentIsRecordedBeforeOrderPlacement(): void
    {
        $events = [];
        $handler = $this->createMock(FormConsentHandler::class);
        $handler->expects(self::once())
            ->method('validateSubmitted')
            ->with(ConsentLocation::CHECKOUT, [])
            ->willReturnCallback(static function () use (&$events): void {
                $events[] = 'validated';
            });
        $handler->expects(self::once())
            ->method('record')
            ->with(ConsentLocation::CHECKOUT, 42)
            ->willReturnCallback(static function () use (&$events): void {
                $events[] = 'recorded';
            });
        $quote = $this->createMock(CartInterface::class);
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn(42);
        $quote->method('getCustomer')->willReturn($customer);
        $cartRepository = $this->createMock(CartRepositoryInterface::class);
        $cartRepository->expects(self::once())->method('getActive')->with(7)->willReturn($quote);
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getExtensionAttributes')->willReturn(null);
        $plugin = new CheckoutConsentPlugin($handler, $cartRepository);

        $result = $plugin->aroundSavePaymentInformationAndPlaceOrder(
            $this->createMock(PaymentInformationManagementInterface::class),
            static function () use (&$events): int {
                $events[] = 'order';
                return 1001;
            },
            7,
            $payment
        );

        self::assertSame(1001, $result);
        self::assertSame(['validated', 'recorded', 'order'], $events);
    }
}
