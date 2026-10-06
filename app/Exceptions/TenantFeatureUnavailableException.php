<?php

namespace App\Exceptions;

use RuntimeException;

final class TenantFeatureUnavailableException extends RuntimeException
{
    public function __construct(public readonly string $feature)
    {
        parent::__construct('This feature is not available on the current plan.');
    }
}
