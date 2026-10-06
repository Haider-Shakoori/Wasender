<?php

namespace App\Services\Analytics;

use App\Data\Analytics\AnalyticsMetric;

final class AnalyticsMath
{
    public static function rate(int|float $numerator, int|float $denominator): ?float
    {
        return $denominator > 0 ? round(($numerator / $denominator) * 100, 2) : null;
    }

    public static function metric(string $key, int|float $value, int|float|null $previous = null): AnalyticsMetric
    {
        return new AnalyticsMetric($key, $value, $previous, $previous && $previous != 0 ? round((($value - $previous) / abs($previous)) * 100, 2) : null);
    }

    public static function limit(int $requested): int
    {
        return min(50, max(1, $requested));
    }
}
