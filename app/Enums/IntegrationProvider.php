<?php

namespace App\Enums;

enum IntegrationProvider: string
{
    case GenericApi = 'generic_api';
    case Webhook = 'webhook';
    case WordPress = 'wordpress';
    case WooCommerce = 'woocommerce';
    case Shopify = 'shopify';
}
