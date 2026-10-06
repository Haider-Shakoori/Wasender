<?php

namespace App\Console\Commands;

use App\Models\PlatformRole;
use App\Models\User;
use App\Services\PlatformRoleAssignmentService;
use Illuminate\Console\Command;

final class GrantPlatformRole extends Command
{
    protected $signature = 'platform:grant-role {email} {role=super-admin}';

    protected $description = 'Grant a platform role to a user';

    public function handle(PlatformRoleAssignmentService $assignments): int
    {
        $user = User::query()->where('email', $this->argument('email'))->firstOrFail();
        $role = PlatformRole::query()->where('slug', $this->argument('role'))->firstOrFail();
        $assignments->grant($user, $role);
        $this->info("Granted {$role->name} to {$user->email}.");

        return self::SUCCESS;
    }
}
