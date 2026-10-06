<?php

declare(strict_types=1);

namespace App\Policies;

use App\Contracts\TenantAuthorization;
use App\Contracts\TenantContext;
use App\Models\Tenant;
use App\Models\User;

final class TenantPolicy
{
    public function __construct(private TenantAuthorization $auth, private TenantContext $context) {}

    public function view(User $user, Tenant $tenant): bool
    {
        return $tenant->is($this->context->get()) && $this->auth->allows('tenant.view');
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $tenant->is($this->context->get()) && $this->auth->allows('tenant.update');
    }

    public function viewSettings(User $user, Tenant $tenant): bool
    {
        return $this->view($user, $tenant);
    }

    public function manageSettings(User $user, Tenant $tenant): bool
    {
        return $tenant->is($this->context->get()) && $this->auth->allows('settings.manage');
    }
}
