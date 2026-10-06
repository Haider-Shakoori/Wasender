<?php

declare(strict_types=1);

use App\Enums\TenantStatus;

return [
    'product_name' => env('SAAS_PRODUCT_NAME', 'Wasender'),
    'product_tagline' => env('SAAS_PRODUCT_TAGLINE', 'WhatsApp messaging, campaigns and automation for your business.'),
    'support_email' => env('SAAS_SUPPORT_EMAIL', 'support@businessos.af'),
    'default_timezone' => env('SAAS_DEFAULT_TIMEZONE', 'UTC'),
    'default_currency' => env('SAAS_DEFAULT_CURRENCY', 'USD'),
    'default_locale' => env('SAAS_DEFAULT_LOCALE', 'en'),
    'default_tenant_status' => env('SAAS_DEFAULT_TENANT_STATUS', TenantStatus::Active->value),
    'tenant_invitation_expiry_hours' => (int) env('TENANT_INVITATION_EXPIRY_HOURS', 72),
];
