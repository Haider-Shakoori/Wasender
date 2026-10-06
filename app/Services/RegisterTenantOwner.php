<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\RoleInitializer;
use App\Data\Auth\RegisterTenantOwnerData;
use App\Data\Auth\RegistrationResult;
use App\Enums\MembershipStatus;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

final class RegisterTenantOwner
{
    public function __construct(
        private readonly TenantSlugService $slugs,
        private readonly RoleInitializer $roles,
        private readonly AuditService $audit,
        private readonly SubscriptionBootstrapService $subscriptions,
    ) {}

    public function execute(RegisterTenantOwnerData $data): RegistrationResult
    {
        return DB::transaction(function () use ($data): RegistrationResult {
            $user = User::create([
                'name' => trim($data->name),
                'email' => mb_strtolower(trim($data->email)),
                'password' => Hash::make($data->password),
            ]);
            $tenant = $this->createTenant($user, $data);
            $ownerRole = $this->roles->initializeForTenant($tenant)->get('owner')
                ?? throw new RuntimeException('The tenant Owner role could not be initialized.');
            $membership = TenantMembership::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'role_id' => $ownerRole->id,
                'status' => MembershipStatus::Active,
                'joined_at' => now(),
            ]);
            $this->subscriptions->assignDefault($tenant);
            $user->forceFill(['last_active_tenant_id' => $tenant->id])->save();

            $this->audit->recordDomain('user.registered', $user, $tenant, $user);
            $this->audit->recordDomain('tenant.created', $user, $tenant, $tenant);
            $this->audit->recordDomain('tenant.membership_created', $user, $tenant, $membership);

            return new RegistrationResult($user, $tenant, $membership);
        }, 3);
    }

    private function createTenant(User $owner, RegisterTenantOwnerData $data): Tenant
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return Tenant::create([
                    'name' => trim($data->companyName),
                    'slug' => $this->slugs->generate($data->companyName),
                    'email' => $owner->email,
                    'timezone' => config('saas.default_timezone'),
                    'currency' => config('saas.default_currency'),
                    'locale' => config('saas.default_locale'),
                    'status' => config('saas.default_tenant_status'),
                    'is_active' => true,
                    'owner_id' => $owner->id,
                ]);
            } catch (QueryException $exception) {
                $message = $exception->getMessage();
                if (! str_contains($message, 'tenants_slug_uq') && ! str_contains($message, 'tenants.slug')) {
                    throw $exception;
                }
            }
        }
        throw new RuntimeException('A unique workspace address could not be generated.');
    }
}
