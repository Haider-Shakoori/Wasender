<?php

namespace App\Data\Analytics;

final readonly class AnalyticsMetric
{
    public function __construct(public string $key, public int|float $value, public int|float|null $previousValue = null, public ?float $changePercent = null) {}
}
