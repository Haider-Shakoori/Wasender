<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Builder;

interface ContactAudienceResolver
{
    public function query(int $tenantId, ?array $segment = null): Builder;
}
