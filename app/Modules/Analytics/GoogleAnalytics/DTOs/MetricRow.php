<?php

namespace App\Modules\Analytics\GoogleAnalytics\DTOs;

/**
 * One row of a GA report — dimension values keyed by dimension name,
 * metric values keyed by metric name.
 */
final class MetricRow
{
    /**
     * @param  array<string,string>          $dimensions
     * @param  array<string,float|int|string> $metrics
     */
    public function __construct(
        public readonly array $dimensions,
        public readonly array $metrics,
    ) {}

    public function dimension(string $name): ?string
    {
        return $this->dimensions[$name] ?? null;
    }

    public function metric(string $name): float|int|string|null
    {
        return $this->metrics[$name] ?? null;
    }

    /**
     * Numeric coercion for metrics — GA always returns metric values as strings,
     * so this is the convenient typed accessor.
     */
    public function numeric(string $metricName): float
    {
        return (float) ($this->metrics[$metricName] ?? 0);
    }

    public function int(string $metricName): int
    {
        return (int) ($this->metrics[$metricName] ?? 0);
    }

    /**
     * Flat representation suitable for arrays/JSON. Dimensions then metrics.
     */
    public function toArray(): array
    {
        return [...$this->dimensions, ...$this->metrics];
    }
}
