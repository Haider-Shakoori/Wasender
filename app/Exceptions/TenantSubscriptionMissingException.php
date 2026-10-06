<?php

namespace App\Exceptions;

use RuntimeException;

final class TenantSubscriptionMissingException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This workspace does not have a current subscription.');
    }
}
