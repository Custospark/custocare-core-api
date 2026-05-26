<?php

declare(strict_types=1);

namespace App\Constants\Billing;

/**
 * Issuer details for Custocare subscription invoices and payment receipts.
 */
final class BillingIssuer
{
    public const LEGAL_NAME = 'Custospark Company Ltd';

    public const PRODUCT_NAME = 'Custocare';

    public const PRODUCT_TAGLINE = 'Continuous Care. Clinical Excellence.';

    public const CITY = 'Kampala';

    public const COUNTRY = 'Uganda';

    public const WEBSITE = 'https://www.custospark.com';

    public const WEBSITE_LABEL = 'www.custospark.com';

    public const DEFAULT_CURRENCY = 'USD';

    /**
     * @return array<string, mixed>
     */
    public static function toArray(): array
    {
        return [
            'legal_name'       => self::LEGAL_NAME,
            'product_name'     => self::PRODUCT_NAME,
            'product_tagline'  => self::PRODUCT_TAGLINE,
            'address_line'     => self::CITY . ', ' . self::COUNTRY,
            'city'             => self::CITY,
            'country'          => self::COUNTRY,
            'website'          => self::WEBSITE,
            'website_label'    => self::WEBSITE_LABEL,
            'product_of'       => self::PRODUCT_NAME . ' is a product of ' . self::LEGAL_NAME,
        ];
    }
}
