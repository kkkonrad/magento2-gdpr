<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Domain\Shared\Feature;

class FeatureCode
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

    public const ALL = [
        self::MODULE,
        self::DASHBOARD,
        self::EXPORT_REQUEST,
        self::ANONYMIZATION_REQUEST,
        self::ERASURE_REQUEST,
        self::RETENTION_OLD_ORDERS,
        self::RETENTION_ABANDONED_ACCOUNTS,
        self::CONSENT,
        self::CONSENT_REGISTRATION,
        self::CONSENT_NEWSLETTER,
        self::CONSENT_CONTACT,
        self::CONSENT_CHECKOUT,
        self::COOKIE,
        self::COOKIE_BANNER,
        self::COOKIE_REJECTED_TRACKING,
        self::COOKIE_GEOLOCATION,
        self::GOOGLE_CONSENT,
    ];

    private function __construct()
    {
    }
}
