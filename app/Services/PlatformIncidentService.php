<?php

namespace App\Services;

use App\Models\PlatformIncident;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

final class PlatformIncidentService
{
    public function raise(string $dedupeKey, string $severity, string $title, string $message, array $context = []): PlatformIncident
    {
        $incident = DB::transaction(function () use ($dedupeKey, $severity, $title, $message, $context): PlatformIncident {
            $existing = PlatformIncident::query()->where('dedupe_key', $dedupeKey)->lockForUpdate()->first();

            if (! $existing) {
                return PlatformIncident::create([
                    'uuid' => (string) Str::uuid(),
                    'dedupe_key' => $dedupeKey,
                    'severity' => $severity,
                    'status' => 'open',
                    'title' => $title,
                    'message' => $message,
                    'context' => $this->sanitizeContext($context),
                    'occurrences' => 1,
                    'first_seen_at' => now(),
                    'last_seen_at' => now(),
                ]);
            }

            $existing->update([
                'severity' => $severity,
                'status' => 'open',
                'title' => $title,
                'message' => $message,
                'context' => $this->sanitizeContext($context),
                'occurrences' => $existing->occurrences + 1,
                'last_seen_at' => now(),
                'resolved_at' => null,
            ]);

            return $existing->refresh();
        }, 3);

        $this->notifyIfNeeded($incident);

        return $incident;
    }

    public function resolve(string $dedupeKey): void
    {
        PlatformIncident::query()
            ->where('dedupe_key', $dedupeKey)
            ->where('status', 'open')
            ->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'last_seen_at' => now(),
            ]);
    }

    private function notifyIfNeeded(PlatformIncident $incident): void
    {
        $recipient = trim((string) config('operations.alert_email'));
        if ($recipient === '') {
            return;
        }

        if (! in_array($incident->severity, ['warning', 'critical'], true)) {
            return;
        }

        $cooldown = max(1, (int) config('operations.alert_cooldown_minutes', 30));
        if ($incident->last_notified_at?->greaterThan(now()->subMinutes($cooldown))) {
            return;
        }

        try {
            Mail::raw(
                "{$incident->title}\n\n{$incident->message}\n\nSeverity: {$incident->severity}\nOccurrences: {$incident->occurrences}",
                fn ($mail) => $mail->to($recipient)->subject("[Wasender {$incident->severity}] {$incident->title}"),
            );

            $incident->update(['last_notified_at' => now()]);
        } catch (Throwable) {
            // Alert delivery failure must never prevent the incident itself from being recorded.
        }
    }

    private function sanitizeContext(array $context): array
    {
        return collect($context)
            ->except(['secret', 'token', 'password', 'cookie', 'qr', 'path', 'authorization'])
            ->map(fn ($value) => is_scalar($value) || $value === null ? $value : json_decode(json_encode($value), true))
            ->all();
    }
}
