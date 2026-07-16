<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Domain\Shared\Feature;

final class FeatureCode
{
    public const MODULE = 'module';
    public const DASHBOARD = 'data_rights.dashboard';
    public const EXPORT_REQUEST = 'data_rights.export_request';
    public const ANONYMIZATION_REQUEST = 'data_rights.anonymization_request';
    public const ERASURE_REQUEST = 'data_rights.erasure_request';
    public const RETENTION_OLD_ORDERS = 'data_rights.retention_old_orders';
    public const RETENTION_ABANDONED_ACCOUNTS = 'data_rights.retention_abandoned_accounts';
    public const CONSENT = 'consent';
    public const CONSENT_REGISTRATION = 'consent.registration';
    public const CONSENT_NEWSLETTER = 'consent.newsletter';
    public const CONSENT_CONTACT = 'consent.contact';
    public const CONSENT_CHECKOUT = 'consent.checkout';
    public const COOKIE = 'cookie';
    public const COOKIE_BANNER = 'cookie.banner';
    public const COOKIE_REJECTED_TRACKING = 'cookie.rejected_tracking';
    public const COOKIE_GEOLOCATION = 'cookie.geolocation';
    public const GOOGLE_CONSENT = 'google_consent';

    private function __construct()
    {
    }
}
