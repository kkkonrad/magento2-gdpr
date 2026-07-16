<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\Notification;

use Magento\Framework\App\Area;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Mail\TransportInterface;

class NotificationSender
{
    public function __construct(private readonly TransportBuilder $transportBuilder)
    {
    }

    /** @param array<string, mixed> $variables */
    public function prepare(
        string $recipientEmail,
        string $recipientName,
        string $templateIdentifier,
        int $storeId,
        array $variables = []
    ): TransportInterface {
        return $this->transportBuilder
            ->setTemplateIdentifier($templateIdentifier)
            ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $storeId])
            ->setTemplateVars($variables)
            ->setFromByScope('general', $storeId)
            ->addTo($recipientEmail, $recipientName)
            ->getTransport();
    }
}
