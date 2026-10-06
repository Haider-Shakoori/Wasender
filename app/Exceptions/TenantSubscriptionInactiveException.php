<?php

namespace App\Exceptions;

use RuntimeException;

final class TenantSubscriptionInactiveException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This workspace subscription is not active.');
    }
}
