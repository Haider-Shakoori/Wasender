<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;

final class TenantPermissionDeniedException extends AuthorizationException
{
    public function __construct()
    {
        parent::__construct('This action is not authorized.');
    }
}
