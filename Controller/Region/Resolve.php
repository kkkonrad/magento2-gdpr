<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Controller\Region;

use Kkkonrad\Gdpr\Api\Geo\RegionResolverInterface;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;

class Resolve implements HttpGetActionInterface
{
    public function __construct(
        private readonly RegionResolverInterface $regionResolver,
        private readonly JsonFactory $jsonFactory
    ) {
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $result->setHeader('Cache-Control', 'private, no-store, max-age=0', true);
        $result->setHeader('X-Content-Type-Options', 'nosniff', true);
        return $result->setData($this->regionResolver->resolve());
    }
}
