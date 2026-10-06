<?php

declare(strict_types=1);

namespace App\Exceptions;

use LogicException;

final class TenantNotResolvedException extends LogicException
{
    public function __construct()
    {
        parent::__construct('No tenant has been resolved for the current request.');
    }
}
