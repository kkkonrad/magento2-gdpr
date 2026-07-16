<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Domain\DataRights\Anonymization;

class PseudonymGenerator
{
    public function token(string $operationKey, string $entityType, int $entityId, int $length = 16): string
    {
        return substr(hash('sha256', $operationKey . '|' . $entityType . '|' . $entityId), 0, $length);
    }

    public function email(string $operationKey, string $entityType, int $entityId): string
    {
        return 'anon-' . $this->token($operationKey, $entityType, $entityId, 24) . '@example.invalid';
    }

    /** @return array<string, int|string|null> */
    public function address(string $operationKey, string $entityType, int $entityId): array
    {
        $token = $this->token($operationKey, $entityType, $entityId, 12);

        return [
            'prefix' => null,
            'firstname' => 'Anonymous',
            'middlename' => null,
            'lastname' => $token,
            'suffix' => null,
            'company' => null,
            'street' => 'Redacted ' . $token,
            'city' => 'Redacted',
            'region' => null,
            'region_id' => null,
            'postcode' => '00000',
            'country_id' => 'US',
            'telephone' => '000000000',
            'fax' => null,
            'vat_id' => null,
        ];
    }
}
