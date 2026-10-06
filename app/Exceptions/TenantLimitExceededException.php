<?php

namespace App\Exceptions;

use RuntimeException;

final class TenantLimitExceededException extends RuntimeException
{
    public function __construct(public readonly string $limit)
    {
        parent::__construct('The workspace has reached its plan limit.');
    }
}
