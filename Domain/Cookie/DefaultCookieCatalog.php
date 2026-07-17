<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Domain\Cookie;

/**
 * Canonical catalog shipped with the module.
 *
 * Keeping this data outside the setup patch makes it available to diagnostic
 * and restore commands without coupling runtime code to Magento setup APIs.
 */
class DefaultCookieCatalog
{
    /** @return array<int, array{code:string,type:string,required:int,priority:int,name:string,description:string}> */
    public function groups(): array
    {
        return [
            [
                'code' => 'essential',
                'type' => 'essential',
                'required' => 1,
                'priority' => 10,
                'name' => 'Essential',
                'description' => 'Required for security and core store functionality.',
            ],
            [
                'code' => 'functionality',
                'type' => 'functionality',
                'required' => 0,
                'priority' => 20,
                'name' => 'Functionality',
                'description' => 'Used for optional personalization and enhanced storefront features.',
            ],
            [
                'code' => 'statistical',
                'type' => 'statistical',
                'required' => 0,
                'priority' => 30,
                'name' => 'Statistical',
                'description' => 'Used to understand storefront usage and performance.',
            ],
            [
                'code' => 'marketing',
                'type' => 'marketing',
                'required' => 0,
                'priority' => 40,
                'name' => 'Marketing',
                'description' => 'Used for advertising, remarketing and ad personalization.',
            ],
        ];
    }

    /**
     * @return array<int, array{name:string,pattern:string,type:string,lifetime:int|null,description:string}>
     */
    public function storage(): array
    {
        return [
            [
                'name' => 'PHP session',
                'pattern' => 'PHPSESSID',
                'type' => 'cookie',
                'lifetime' => null,
                'description' => 'Maintains the server-side storefront session.',
            ],
            [
                'name' => 'Form key',
                'pattern' => 'form_key',
                'type' => 'cookie',
                'lifetime' => null,
                'description' => 'Protects forms against cross-site request forgery.',
            ],
            [
                'name' => 'Magento vary',
                'pattern' => 'X-Magento-Vary',
                'type' => 'cookie',
                'lifetime' => null,
                'description' => 'Supports correct private content and full-page cache variants.',
            ],
            [
                'name' => 'Private content version',
                'pattern' => 'private_content_version',
                'type' => 'cookie',
                'lifetime' => null,
                'description' => 'Invalidates customer-specific browser content when required.',
            ],
            [
                'name' => 'Store view',
                'pattern' => 'store',
                'type' => 'cookie',
                'lifetime' => null,
                'description' => 'Stores the selected store view.',
            ],
            [
                'name' => 'Login redirect',
                'pattern' => 'login_redirect',
                'type' => 'cookie',
                'lifetime' => null,
                'description' => 'Returns the customer to the intended page after authentication.',
            ],
            [
                'name' => 'Flash messages',
                'pattern' => 'mage-messages',
                'type' => 'cookie',
                'lifetime' => 10,
                'description' => 'Displays one-time success and error messages.',
            ],
            [
                'name' => 'Cache session marker',
                'pattern' => 'mage-cache-sessid',
                'type' => 'cookie',
                'lifetime' => null,
                'description' => 'Triggers cleanup of local private-content storage.',
            ],
            [
                'name' => 'Cache storage',
                'pattern' => 'mage-cache-storage',
                'type' => 'local_storage',
                'lifetime' => null,
                'description' => 'Stores customer-specific storefront sections in the browser.',
            ],
            [
                'name' => 'Cache invalidation',
                'pattern' => 'mage-cache-storage-section-invalidation',
                'type' => 'local_storage',
                'lifetime' => null,
                'description' => 'Tracks invalidated customer-data sections.',
            ],
            [
                'name' => 'Section data IDs',
                'pattern' => 'section_data_ids',
                'type' => 'cookie',
                'lifetime' => null,
                'description' => 'Tracks versions of customer-data sections.',
            ],
        ];
    }
}
