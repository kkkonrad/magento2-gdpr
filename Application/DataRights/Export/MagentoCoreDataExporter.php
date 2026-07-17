<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\DataRights\Export;

use Kkkonrad\Gdpr\Api\DataRights\PersonalDataExporterInterface;
use Magento\Framework\App\ResourceConnection;

class MagentoCoreDataExporter implements PersonalDataExporterInterface
{
    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    public function getCode(): string
    {
        return 'magento_core';
    }

    public function getPriority(): int
    {
        return 10;
    }

    public function export(int $customerId, int $storeId): array
    {
        unset($storeId);
        $connection = $this->resourceConnection->getConnection();
        $customerTable = $this->resourceConnection->getTableName('customer_entity');
        $addressTable = $this->resourceConnection->getTableName('customer_address_entity');
        $orderTable = $this->resourceConnection->getTableName('sales_order');
        $orderAddressTable = $this->resourceConnection->getTableName('sales_order_address');
        $itemTable = $this->resourceConnection->getTableName('sales_order_item');
        $consentEvent = $this->resourceConnection->getTableName('kkkonrad_gdpr_consent_event');
        $consentVersion = $this->resourceConnection->getTableName('kkkonrad_gdpr_consent_version');
        $consentSubjectLink = $this->resourceConnection->getTableName('kkkonrad_gdpr_consent_subject_link');
        $cookieEvent = $this->resourceConnection->getTableName('kkkonrad_gdpr_cookie_consent_event');
        $cookiePolicy = $this->resourceConnection->getTableName('kkkonrad_gdpr_cookie_policy_version');
        $newsletter = $this->resourceConnection->getTableName('newsletter_subscriber');
        $wishlist = $this->resourceConnection->getTableName('wishlist');
        $wishlistItem = $this->resourceConnection->getTableName('wishlist_item');
        $reviewDetail = $this->resourceConnection->getTableName('review_detail');
        $review = $this->resourceConnection->getTableName('review');
        $paymentToken = $this->resourceConnection->getTableName('vault_payment_token');

        $customerColumns = [
            'entity_id', 'website_id', 'store_id', 'email', 'prefix', 'firstname', 'middlename', 'lastname',
            'suffix', 'dob', 'taxvat', 'gender', 'created_at', 'updated_at', 'is_active',
        ];
        $addressColumns = [
            'entity_id', 'parent_id', 'company', 'prefix', 'firstname', 'middlename', 'lastname', 'suffix',
            'street', 'city', 'region', 'region_id', 'postcode', 'country_id', 'telephone', 'fax', 'vat_id',
            'created_at', 'updated_at',
        ];
        $orderColumns = [
            'entity_id', 'increment_id', 'store_id', 'state', 'status', 'created_at', 'updated_at',
            'order_currency_code', 'subtotal', 'shipping_amount', 'tax_amount', 'discount_amount', 'grand_total',
            'total_qty_ordered', 'shipping_description', 'customer_email', 'customer_firstname',
            'customer_lastname', 'customer_note',
        ];
        $orderAddressColumns = [
            'entity_id', 'parent_id', 'address_type', 'company', 'prefix', 'firstname', 'middlename', 'lastname',
            'suffix', 'street', 'city', 'region', 'region_id', 'postcode', 'country_id', 'telephone', 'fax',
            'vat_id', 'email',
        ];
        $itemColumns = [
            'item_id', 'order_id', 'sku', 'name', 'product_type', 'qty_ordered', 'qty_invoiced', 'qty_shipped',
            'qty_refunded', 'price', 'tax_amount', 'discount_amount', 'row_total', 'created_at',
        ];
        $customer = $connection->fetchAll(
            $connection->select()->from($customerTable, $customerColumns)->where('entity_id = ?', $customerId)
        );
        $addresses = $connection->fetchAll(
            $connection->select()->from($addressTable, $addressColumns)->where('parent_id = ?', $customerId)
        );
        $orders = $connection->fetchAll(
            $connection->select()->from($orderTable, $orderColumns)->where('customer_id = ?', $customerId)
        );
        $orderIds = array_map(static fn (array $row): int => (int)$row['entity_id'], $orders);
        $orderAddresses = $orderIds === [] ? [] : $connection->fetchAll(
            $connection->select()->from($orderAddressTable, $orderAddressColumns)->where('parent_id IN (?)', $orderIds)
        );
        $items = $orderIds === [] ? [] : $connection->fetchAll(
            $connection->select()->from($itemTable, $itemColumns)->where('order_id IN (?)', $orderIds)
        );
        $consentColumns = [
            'event_id', 'decision', 'source', 'store_id', 'occurred_at', 'content_hash', 'content_snapshot',
        ];
        $consents = $connection->fetchAll(
            $connection->select()
                ->from(['event' => $consentEvent], ['event_id', 'decision', 'source', 'store_id', 'occurred_at'])
                ->joinInner(['version' => $consentVersion], 'version.version_id = event.version_id', [
                    'content_hash', 'content_snapshot',
                ])
                ->joinLeft(['subject_link' => $consentSubjectLink], 'subject_link.subject_key = event.subject_key', [])
                ->where('COALESCE(event.customer_id, subject_link.customer_id) = ?', $customerId)
        );
        $cookieConsentColumns = [
            'event_id', 'policy_public_id', 'policy_version', 'choices_json', 'region', 'store_id', 'occurred_at',
        ];
        $cookieConsents = $connection->fetchAll(
            $connection->select()
                ->from(['event' => $cookieEvent], ['event_id', 'choices_json', 'region', 'store_id', 'occurred_at'])
                ->joinInner(['policy' => $cookiePolicy], 'policy.policy_version_id = event.policy_version_id', [
                    'policy_public_id' => 'public_id', 'policy_version' => 'version',
                ])
                ->where('event.customer_id = ?', $customerId)
        );
        $newsletterColumns = [
            'subscriber_id', 'store_id', 'subscriber_email', 'subscriber_status', 'change_status_at',
        ];
        $subscriptions = $connection->fetchAll(
            $connection->select()->from($newsletter, $newsletterColumns)->where('customer_id = ?', $customerId)
        );
        $wishlistColumns = ['wishlist_id', 'customer_id', 'shared', 'updated_at'];
        $wishlists = $connection->fetchAll(
            $connection->select()->from($wishlist, $wishlistColumns)->where('customer_id = ?', $customerId)
        );
        $wishlistIds = array_map(static fn (array $row): int => (int)$row['wishlist_id'], $wishlists);
        $wishlistItemColumns = [
            'wishlist_item_id', 'wishlist_id', 'product_id', 'store_id', 'added_at', 'description', 'qty',
        ];
        $wishlistItems = $wishlistIds === [] ? [] : $connection->fetchAll(
            $connection->select()->from($wishlistItem, $wishlistItemColumns)->where('wishlist_id IN (?)', $wishlistIds)
        );
        $reviewColumns = [
            'review_id', 'store_id', 'status_id', 'entity_pk_value', 'created_at', 'title', 'detail', 'nickname',
        ];
        $reviews = $connection->fetchAll(
            $connection->select()
                ->from(['detail' => $reviewDetail], [
                    'review_id', 'store_id', 'title', 'detail', 'nickname',
                ])
                ->joinInner(['review' => $review], 'review.review_id = detail.review_id', [
                    'status_id', 'entity_pk_value', 'created_at',
                ])
                ->where('detail.customer_id = ?', $customerId)
        );
        $paymentTokenColumns = [
            'entity_id', 'payment_method_code', 'type', 'created_at', 'expires_at', 'is_active', 'is_visible',
        ];
        $paymentTokens = $connection->fetchAll(
            $connection->select()->from($paymentToken, $paymentTokenColumns)->where('customer_id = ?', $customerId)
        );

        $datasets = [
            'customer' => ['columns' => $customerColumns, 'rows' => $customer],
            'addresses' => ['columns' => $addressColumns, 'rows' => $addresses],
            'orders' => ['columns' => $orderColumns, 'rows' => $orders],
            'order-addresses' => ['columns' => $orderAddressColumns, 'rows' => $orderAddresses],
            'order-items' => ['columns' => $itemColumns, 'rows' => $items],
            'consents' => ['columns' => $consentColumns, 'rows' => $consents],
            'cookie-consents' => ['columns' => $cookieConsentColumns, 'rows' => $cookieConsents],
            'newsletter' => ['columns' => $newsletterColumns, 'rows' => $subscriptions],
            'wishlists' => ['columns' => $wishlistColumns, 'rows' => $wishlists],
            'wishlist-items' => ['columns' => $wishlistItemColumns, 'rows' => $wishlistItems],
            'reviews' => ['columns' => $reviewColumns, 'rows' => $reviews],
            'payment-token-metadata' => ['columns' => $paymentTokenColumns, 'rows' => $paymentTokens],
        ];

        foreach ($this->salesDocumentDefinitions() as $name => $definition) {
            $datasets[$name] = [
                'columns' => $definition['columns'],
                'rows' => $orderIds === [] ? [] : $connection->fetchAll(
                    $connection->select()
                        ->from($this->resourceConnection->getTableName($definition['table']), $definition['columns'])
                        ->where('order_id IN (?)', $orderIds)
                ),
            ];
        }

        return $datasets;
    }

    /** @return array<string, array{table:string,columns:string[]}> */
    private function salesDocumentDefinitions(): array
    {
        return [
            'invoices' => ['table' => 'sales_invoice', 'columns' => [
                'entity_id', 'order_id', 'increment_id', 'state', 'created_at', 'updated_at',
                'order_currency_code', 'subtotal', 'shipping_amount', 'tax_amount', 'discount_amount',
                'grand_total', 'total_qty', 'customer_note',
            ]],
            'shipments' => ['table' => 'sales_shipment', 'columns' => [
                'entity_id', 'order_id', 'increment_id', 'shipment_status', 'created_at', 'updated_at',
                'total_qty', 'total_weight', 'customer_note',
            ]],
            'creditmemos' => ['table' => 'sales_creditmemo', 'columns' => [
                'entity_id', 'order_id', 'invoice_id', 'increment_id', 'state', 'created_at', 'updated_at',
                'order_currency_code', 'subtotal', 'shipping_amount', 'tax_amount',
                'discount_amount', 'grand_total', 'customer_note',
            ]],
        ];
    }
}
