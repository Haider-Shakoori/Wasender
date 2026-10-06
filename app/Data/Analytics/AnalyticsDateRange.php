<?php

namespace App\Data\Analytics;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final readonly class AnalyticsDateRange
{
    public function __construct(public CarbonImmutable $from, public CarbonImmutable $to, public string $timezone)
    {
        if ($from->gt($to) || $from->diffInDays($to) > 365) {
            throw ValidationException::withMessages(['range' => 'Analytics ranges must be chronological and no longer than 365 days.']);
        }
    }

    public static function fromFilters(array $filters, string $timezone): self
    {
        $now = CarbonImmutable::now($timezone);
        $preset = $filters['preset'] ?? 'last_30_days';
        [$from, $to] = match ($preset) {
            'today' => [$now->startOfDay(), $now->endOfDay()], 'yesterday' => [$now->subDay()->startOfDay(), $now->subDay()->endOfDay()],
            'last_7_days' => [$now->subDays(6)->startOfDay(), $now->endOfDay()], 'last_30_days' => [$now->subDays(29)->startOfDay(), $now->endOfDay()],
            'this_month' => [$now->startOfMonth(), $now->endOfDay()], 'last_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            'custom' => [CarbonImmutable::parse($filters['from'], $timezone)->startOfDay(), CarbonImmutable::parse($filters['to'], $timezone)->endOfDay()],
            default => throw ValidationException::withMessages(['preset' => 'Unsupported analytics date preset.']),
        };

        return new self($from->utc(), $to->utc(), $timezone);
    }

    public function previous(): self
    {
        $seconds = $this->to->diffInSeconds($this->from) + 1;

        return new self($this->from->subSeconds($seconds), $this->from->subSecond(), $this->timezone);
    }

    public function hash(): string
    {
        return hash('sha256', $this->from->toIso8601String().'|'.$this->to->toIso8601String().'|'.$this->timezone);
    }
}
