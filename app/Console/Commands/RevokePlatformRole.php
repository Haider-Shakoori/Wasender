<?php

namespace App\Console\Commands;

use App\Models\PlatformRole;
use App\Models\User;
use App\Services\PlatformRoleAssignmentService;
use Illuminate\Console\Command;

final class RevokePlatformRole extends Command
{
    protected $signature = 'platform:revoke-role {email} {role=super-admin}';

    protected $description = 'Revoke a platform role from a user';

    public function handle(PlatformRoleAssignmentService $assignments): int
    {
        $user = User::query()->where('email', $this->argument('email'))->firstOrFail();
        $role = PlatformRole::query()->where('slug', $this->argument('role'))->firstOrFail();
        $assignments->revoke($user, $role);
        $this->info("Revoked {$role->name} from {$user->email}.");

        return self::SUCCESS;
    }
}
