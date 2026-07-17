<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Ui\Component\Listing\Column;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class RequestActions extends Column
{
    /**
     * @param array<string, mixed> $components
     * @param array<string, mixed> $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @param array<string, mixed> $dataSource
     * @return array<string, mixed>
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items']) || !is_array($dataSource['data']['items'])) {
            return $dataSource;
        }
        foreach ($dataSource['data']['items'] as &$item) {
            if (!is_array($item) || !isset($item['request_id'])) {
                continue;
            }
            $item[$this->getData('name')] = [
                'view' => [
                    'href' => $this->urlBuilder->getUrl('kkkonrad_gdpr/request/view', [
                        'id' => (int)$item['request_id'],
                    ]),
                    'label' => __('View'),
                ],
            ];
        }
        unset($item);
        return $dataSource;
    }
}
