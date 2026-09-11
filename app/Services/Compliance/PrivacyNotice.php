<?php

declare(strict_types=1);

namespace App\Services\Compliance;

/**
 * Versioned privacy notice (Act 2019 Sec 13, 9 mandatory items).
 *
 * The version is the fresh-consent tripwire: consents store the notice
 * version they were granted under (patient_consents.consent_form_version),
 * so any content change here obliges re-consent. Never edit an item
 * in place for a new meaning - bump the version instead.
 */
class PrivacyNotice
{
    public static function version(): string
    {
        return (string) config('privacy-notice.version', '1.0.0');
    }

    public static function effectiveAt(): string
    {
        return (string) config('privacy-notice.effective_at', '');
    }

    /**
     * @return list<array{key: string, title: string, body: string}>
     */
    public static function items(): array
    {
        return config('privacy-notice.items', []);
    }

    public static function document(): array
    {
        return [
            'version'      => self::version(),
            'effective_at' => self::effectiveAt(),
            'controller'   => [
                'name'    => config('privacy-notice.controller_name'),
                'contact' => config('privacy-notice.controller_contact'),
            ],
            'dpo_contact'        => config('privacy-notice.dpo_contact'),
            'complaints_contact' => config('privacy-notice.complaints_contact'),
            'items'              => self::items(),
        ];
    }

    /**
     * A stored consent predates the current notice and needs refresh when
     * its recorded version differs (fresh consent on policy change).
     */
    public static function isStale(?string $recordedVersion): bool
    {
        if ($recordedVersion === null || trim($recordedVersion) === '') {
            return true;
        }

        return trim($recordedVersion) !== self::version();
    }
}
