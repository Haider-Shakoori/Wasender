<?php

namespace App\Services;

use App\Enums\WhatsAppMessageStatus;
use App\Models\SystemHeartbeat;
use App\Models\WhatsAppMessage;
use App\Services\Campaigns\WhatsAppCampaignConnectorHealthQuery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Throwable;

final class SystemHealthService
{
    public function __construct(private WhatsAppCampaignConnectorHealthQuery $connector) {}

    public function checks(): array
    {
        $checks = [];
        try {
            DB::select('select 1');
            $checks['database'] = $this->result(true, 'Database connection is available.');
        } catch (Throwable) {
            $checks['database'] = $this->result(false, 'Database connection is unavailable.');
        }
        try {
            $key = 'health:'.bin2hex(random_bytes(6));
            Cache::put($key, true, 10);
            $ok = Cache::pull($key) === true;
            $checks['cache'] = $this->result($ok, $ok ? 'Cache read/write succeeded.' : 'Cache read/write failed.');
        } catch (Throwable) {
            $checks['cache'] = $this->result(false, 'Cache is unavailable.');
        }
        try {
            $queued = collect(config('operations.queues'))->sum(fn (string $queue): int => Queue::size($queue));
            $failed = DB::table('failed_jobs')->count();
            $checks['queue'] = $this->result(true, "{$queued} queued; {$failed} failed.", ['queued' => $queued, 'failed' => $failed]);
        } catch (Throwable) {
            $checks['queue'] = $this->result(false, 'Queue backend is unavailable.');
        }
        $outbound = WhatsAppMessage::whereIn('status', [WhatsAppMessageStatus::Queued, WhatsAppMessageStatus::Processing, WhatsAppMessageStatus::Sending])->count();
        $stale = WhatsAppMessage::whereIn('status', [WhatsAppMessageStatus::Processing, WhatsAppMessageStatus::Sending])->where('updated_at', '<', now()->subMinutes(5))->count();
        $checks['outbound_messages'] = $this->result($stale === 0, "{$outbound} active; {$stale} stale.", ['active' => $outbound, 'stale' => $stale]);
        $heartbeat = SystemHeartbeat::query()->find('scheduler');
        $fresh = $heartbeat?->ran_at?->greaterThan(now()->subMinutes(10)) ?? false;
        $checks['scheduler'] = $this->result($fresh, $fresh ? 'Scheduler heartbeat is current.' : 'Scheduler heartbeat is missing or stale.', ['last_seen' => $heartbeat?->ran_at?->toIso8601String()]);

        $connector = $this->connector->get();
        $remote = is_array($connector['remote'] ?? null) ? $connector['remote'] : [];
        $connectorOnline = ($remote['status'] ?? 'unavailable') === 'ok';
        $crashLoops = (int) ($remote['crash_loop_sessions'] ?? 0);
        $reconnecting = (int) ($remote['reconnecting_sessions'] ?? 0);
        $capacityUsed = (int) ($remote['worker_capacity_used_percent'] ?? 0);
        $diskCritical = (bool) ($remote['auth_disk_critical'] ?? false);
        $callbackBacklog = (int) ($remote['callback_backlog'] ?? 0);
        $connectorHealthy = $connectorOnline && $crashLoops === 0 && ! $diskCritical;

        $connectorMessage = match (true) {
            ! $connectorOnline => 'WhatsApp connector is unavailable.',
            $diskCritical => 'WhatsApp connector auth storage is critically low on free disk space.',
            $crashLoops > 0 => "WhatsApp connector is online but {$crashLoops} session worker(s) are in a crash loop.",
            $capacityUsed >= 90 => "WhatsApp connector is healthy but worker capacity is at {$capacityUsed}%.",
            default => 'WhatsApp connector is online.',
        };

        $checks['whatsapp_connector'] = $this->result(
            $connectorHealthy,
            $connectorMessage,
            [
                'isolation' => $remote['isolation'] ?? null,
                'owned_sessions' => (int) ($remote['owned_sessions'] ?? 0),
                'ready_sessions' => (int) ($remote['ready_sessions'] ?? 0),
                'reconnecting_sessions' => $reconnecting,
                'crash_loop_sessions' => $crashLoops,
                'uptime_seconds' => $remote['uptime_seconds'] ?? null,
                'memory_rss_mb' => $remote['memory_rss_mb'] ?? null,
                'worker_capacity' => (int) ($remote['worker_capacity'] ?? 0),
                'worker_capacity_remaining' => (int) ($remote['worker_capacity_remaining'] ?? 0),
                'worker_capacity_used_percent' => $capacityUsed,
                'auth_disk_used_percent' => $remote['auth_disk_used_percent'] ?? null,
                'auth_disk_free_mb' => $remote['auth_disk_free_mb'] ?? null,
                'auth_disk_critical' => $diskCritical,
                'callback_backlog' => $callbackBacklog,
            ],
        );

        $checks['whatsapp_capacity'] = $this->result(
            $capacityUsed < 95,
            $capacityUsed >= 95
                ? "WhatsApp worker capacity is critically high at {$capacityUsed}%."
                : "WhatsApp worker capacity is {$capacityUsed}% utilized.",
            [
                'worker_capacity_used_percent' => $capacityUsed,
                'worker_capacity_remaining' => (int) ($remote['worker_capacity_remaining'] ?? 0),
            ],
        );

        $checks['whatsapp_callback_backlog'] = $this->result(
            $callbackBacklog < 100,
            $callbackBacklog >= 100
                ? "WhatsApp callback backlog has grown to {$callbackBacklog} events."
                : "WhatsApp callback backlog is {$callbackBacklog}.",
            ['callback_backlog' => $callbackBacklog],
        );

        return $checks;
    }

    private function result(bool $healthy, string $message, array $meta = []): array
    {
        return ['healthy' => $healthy, 'message' => $message] + $meta;
    }
}
