<?php

namespace App\Console\Commands;

use App\Services\PlatformIncidentService;
use App\Services\SystemHealthService;
use Illuminate\Console\Command;

final class DetectPlatformIncidents extends Command
{
    protected $signature = 'operations:detect-incidents';

    protected $description = 'Create, refresh, and resolve platform incidents from system health checks';

    public function handle(SystemHealthService $health, PlatformIncidentService $incidents): int
    {
        foreach ($health->checks() as $name => $check) {
            $key = "health:{$name}";
            $healthy = (bool) ($check['healthy'] ?? false);

            if ($healthy) {
                $incidents->resolve($key);

                continue;
            }

            $severity = in_array($name, ['database', 'scheduler', 'whatsapp_connector'], true)
                ? 'critical'
                : 'warning';

            $incidents->raise(
                $key,
                $severity,
                str($name)->replace('_', ' ')->headline()->toString().' health issue',
                (string) ($check['message'] ?? 'A platform health check is failing.'),
                collect($check)->except(['healthy', 'message'])->all(),
            );
        }

        return self::SUCCESS;
    }
}
