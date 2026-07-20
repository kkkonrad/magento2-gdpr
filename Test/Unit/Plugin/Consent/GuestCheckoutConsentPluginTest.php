<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Test\Unit\Plugin\Consent;

use Kkkonrad\Gdpr\Application\Consent\FormConsentHandler;
use Kkkonrad\Gdpr\Domain\Consent\ConsentLocation;
use Kkkonrad\Gdpr\Plugin\Consent\GuestCheckoutConsentPlugin;
use Magento\Checkout\Api\GuestPaymentInformationManagementInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use PHPUnit\Framework\TestCase;

class GuestCheckoutConsentPluginTest extends TestCase
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
            ->with(ConsentLocation::CHECKOUT)
            ->willReturnCallback(static function () use (&$events): void {
                $events[] = 'recorded';
            });
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getExtensionAttributes')->willReturn(null);
        $plugin = new GuestCheckoutConsentPlugin($handler);

        $result = $plugin->aroundSavePaymentInformationAndPlaceOrder(
            $this->createMock(GuestPaymentInformationManagementInterface::class),
            static function () use (&$events): int {
                $events[] = 'order';
                return 1002;
            },
            'masked-cart-id',
            'guest@example.com',
            $payment
        );

        self::assertSame(1002, $result);
        self::assertSame(['validated', 'recorded', 'order'], $events);
    }
}
