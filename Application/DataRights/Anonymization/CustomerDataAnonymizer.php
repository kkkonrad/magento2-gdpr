<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\DataRights\Anonymization;

use Kkkonrad\Gdpr\Domain\DataRights\Anonymization\PseudonymGenerator;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Encryption\EncryptorInterface;

class CustomerDataAnonymizer
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly PseudonymGenerator $pseudonymGenerator,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    /** @return array<string, int> */
    public function anonymize(int $customerId, string $operationKey): array
    {
        $connection = $this->resourceConnection->getConnection();
        $counts = [];
        $customerTable = $this->resourceConnection->getTableName('customer_entity');
        $addressTable = $this->resourceConnection->getTableName('customer_address_entity');
        $quoteTable = $this->resourceConnection->getTableName('quote');
        $quoteAddressTable = $this->resourceConnection->getTableName('quote_address');
        $orderTable = $this->resourceConnection->getTableName('sales_order');
        $orderAddressTable = $this->resourceConnection->getTableName('sales_order_address');

        $addressIds = array_map('intval', $connection->fetchCol(
            $connection->select()->from($addressTable, ['entity_id'])->where('parent_id = ?', $customerId)
        ));
        foreach ($addressIds as $addressId) {
            $counts['customer_addresses'] = ($counts['customer_addresses'] ?? 0) + $connection->update(
                $addressTable,
                $this->pseudonymGenerator->address($operationKey, 'customer_address', $addressId),
                ['entity_id = ?' => $addressId]
            );
        }

        $quoteIds = array_map('intval', $connection->fetchCol(
            $connection->select()->from($quoteTable, ['entity_id'])->where('customer_id = ?', $customerId)
        ));
        foreach ($quoteIds as $quoteId) {
            $token = $this->pseudonymGenerator->token($operationKey, 'quote', $quoteId, 12);
            $counts['quotes'] = ($counts['quotes'] ?? 0) + $connection->update($quoteTable, [
                'customer_email' => $this->pseudonymGenerator->email($operationKey, 'quote', $quoteId),
                'customer_prefix' => null,
                'customer_firstname' => 'Anonymous',
                'customer_middlename' => null,
                'customer_lastname' => $token,
                'customer_suffix' => null,
                'customer_dob' => null,
                'customer_note' => null,
                'customer_taxvat' => null,
                'remote_ip' => null,
                'password_hash' => null,
            ], ['entity_id = ?' => $quoteId]);
            $quoteAddressIds = array_map('intval', $connection->fetchCol(
                $connection->select()->from($quoteAddressTable, ['address_id'])->where('quote_id = ?', $quoteId)
            ));
            foreach ($quoteAddressIds as $addressId) {
                $data = $this->pseudonymGenerator->address($operationKey, 'quote_address', $addressId);
                $data['email'] = $this->pseudonymGenerator->email($operationKey, 'quote_address', $addressId);
                $data['customer_notes'] = null;
                $counts['quote_addresses'] = ($counts['quote_addresses'] ?? 0) + $connection->update(
                    $quoteAddressTable,
                    $data,
                    ['address_id = ?' => $addressId]
                );
            }
        }

        $orderIds = array_map('intval', $connection->fetchCol(
            $connection->select()->from($orderTable, ['entity_id'])->where('customer_id = ?', $customerId)
        ));
        foreach ($orderIds as $orderId) {
            $token = $this->pseudonymGenerator->token($operationKey, 'order', $orderId, 12);
            $counts['orders'] = ($counts['orders'] ?? 0) + $connection->update($orderTable, [
                'customer_email' => $this->pseudonymGenerator->email($operationKey, 'order', $orderId),
                'customer_prefix' => null,
                'customer_firstname' => 'Anonymous',
                'customer_middlename' => null,
                'customer_lastname' => $token,
                'customer_suffix' => null,
                'customer_dob' => null,
                'customer_taxvat' => null,
                'customer_note' => null,
                'remote_ip' => null,
                'x_forwarded_for' => null,
                'customer_id' => null,
            ], ['entity_id = ?' => $orderId]);
            $orderAddressIds = array_map('intval', $connection->fetchCol(
                $connection->select()->from($orderAddressTable, ['entity_id'])->where('parent_id = ?', $orderId)
            ));
            foreach ($orderAddressIds as $addressId) {
                $data = $this->pseudonymGenerator->address($operationKey, 'order_address', $addressId);
                $data['email'] = $this->pseudonymGenerator->email($operationKey, 'order_address', $addressId);
                $data['customer_id'] = null;
                $data['customer_address_id'] = null;
                $counts['order_addresses'] = ($counts['order_addresses'] ?? 0) + $connection->update(
                    $orderAddressTable,
                    $data,
                    ['entity_id = ?' => $addressId]
                );
            }
        }

        $newsletterTable = $this->resourceConnection->getTableName('newsletter_subscriber');
        $counts['newsletter'] = $connection->delete($newsletterTable, ['customer_id = ?' => $customerId]);
        $reviewTable = $this->resourceConnection->getTableName('review_detail');
        $counts['reviews'] = $connection->update($reviewTable, [
            'customer_id' => null,
            'nickname' => 'Anonymous',
        ], ['customer_id = ?' => $customerId]);
        $counts['customer'] = $connection->update($customerTable, [
            'email' => $this->pseudonymGenerator->email($operationKey, 'customer', $customerId),
            'prefix' => null,
            'firstname' => 'Anonymous',
            'middlename' => null,
            'lastname' => $this->pseudonymGenerator->token($operationKey, 'customer', $customerId, 12),
            'suffix' => null,
            'dob' => null,
            'taxvat' => null,
            'gender' => null,
            'rp_token' => null,
            'rp_token_created_at' => null,
            'confirmation' => null,
            'password_hash' => $this->encryptor->getHash(bin2hex(random_bytes(32)), true),
            'session_cutoff' => time(),
        ], ['entity_id = ?' => $customerId]);

        return $counts;
    }
}
