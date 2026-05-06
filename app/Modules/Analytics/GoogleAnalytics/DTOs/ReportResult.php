<?php

namespace App\Modules\Analytics\GoogleAnalytics\DTOs;

use Carbon\CarbonImmutable;
use Google\Analytics\Data\V1beta\RunRealtimeReportResponse;
use Google\Analytics\Data\V1beta\RunReportResponse;
use Illuminate\Support\Collection;

/**
 * Container for a GA4 report — rows + column metadata + totals + freshness.
 *
 * Designed to be consumed both by Livewire/Blade (rows() returns a Laravel
 * Collection ready for ->take()/->sortByDesc()/->map()) and by the AI tool
 * layer (toArray() flattens cleanly into JSON for prompts and tool responses).
 */
final class ReportResult
{
    /**
     * @param  Collection<int,MetricRow>           $rows
     * @param  list<array{name:string,type:string,format?:string}>  $columnDefinitions
     */
    public function __construct(
        public readonly Collection $rows,
        public readonly array $columnDefinitions,
        public readonly ?MetricRow $totals,
        public readonly int $rowCount,
        public readonly CarbonImmutable $fetchedAt,
    ) {}

    /** @return Collection<int,MetricRow> */
    public function rows(): Collection { return $this->rows; }

    public function totals(): ?MetricRow { return $this->totals; }

    /** @return list<array{name:string,type:string,format?:string}> */
    public function columnDefinitions(): array { return $this->columnDefinitions; }

    public function isEmpty(): bool { return $this->rows->isEmpty(); }
    public function count(): int { return $this->rows->count(); }
    public function totalRowCount(): int { return $this->rowCount; }
    public function fetchedAt(): CarbonImmutable { return $this->fetchedAt; }

    public function toArray(): array
    {
        return [
            'columns'    => $this->columnDefinitions,
            'rows'       => $this->rows->map(fn (MetricRow $r) => $r->toArray())->all(),
            'totals'     => $this->totals?->toArray(),
            'rowCount'   => $this->rowCount,
            'fetchedAt'  => $this->fetchedAt->toIso8601String(),
        ];
    }

    /**
     * Hydrate from the GA SDK response object.
     *
     * @param  list<string>  $dimensionNames  ordered, matches request
     * @param  list<string>  $metricNames     ordered, matches request
     * @param  array<string,string>  $metricFormats  metric name => format hint
     */
    public static function fromGa(
        RunReportResponse $response,
        array $dimensionNames,
        array $metricNames,
        array $metricFormats = [],
    ): self {
        $rows = collect($response->getRows())->map(function ($row) use ($dimensionNames, $metricNames) {
            $dims = [];
            foreach ($row->getDimensionValues() as $i => $val) {
                $dims[$dimensionNames[$i] ?? "dim_{$i}"] = $val->getValue();
            }
            $mets = [];
            foreach ($row->getMetricValues() as $i => $val) {
                $mets[$metricNames[$i] ?? "metric_{$i}"] = $val->getValue();
            }
            return new MetricRow($dims, $mets);
        })->values();

        $totals = null;
        $totalsRows = $response->getTotals();
        if (count($totalsRows) > 0) {
            $totalRow = $totalsRows[0];
            $mets = [];
            foreach ($totalRow->getMetricValues() as $i => $val) {
                $mets[$metricNames[$i] ?? "metric_{$i}"] = $val->getValue();
            }
            $totals = new MetricRow([], $mets);
        }

        $columns = [];
        foreach ($dimensionNames as $name) {
            $columns[] = ['name' => $name, 'type' => 'dimension', 'format' => 'text'];
        }
        foreach ($metricNames as $name) {
            $columns[] = [
                'name' => $name,
                'type' => 'metric',
                'format' => $metricFormats[$name] ?? 'integer',
            ];
        }

        return new self(
            rows:              $rows,
            columnDefinitions: $columns,
            totals:            $totals,
            rowCount:          $response->getRowCount(),
            fetchedAt:         CarbonImmutable::now(),
        );
    }

    /**
     * Hydrate from the realtime response (no totals, no rowCount header).
     *
     * @param  list<string>  $dimensionNames
     * @param  list<string>  $metricNames
     * @param  array<string,string>  $metricFormats
     */
    public static function fromGaRealtime(
        RunRealtimeReportResponse $response,
        array $dimensionNames,
        array $metricNames,
        array $metricFormats = [],
    ): self {
        $rows = collect($response->getRows())->map(function ($row) use ($dimensionNames, $metricNames) {
            $dims = [];
            foreach ($row->getDimensionValues() as $i => $val) {
                $dims[$dimensionNames[$i] ?? "dim_{$i}"] = $val->getValue();
            }
            $mets = [];
            foreach ($row->getMetricValues() as $i => $val) {
                $mets[$metricNames[$i] ?? "metric_{$i}"] = $val->getValue();
            }
            return new MetricRow($dims, $mets);
        })->values();

        $columns = [];
        foreach ($dimensionNames as $name) {
            $columns[] = ['name' => $name, 'type' => 'dimension', 'format' => 'text'];
        }
        foreach ($metricNames as $name) {
            $columns[] = [
                'name' => $name,
                'type' => 'metric',
                'format' => $metricFormats[$name] ?? 'integer',
            ];
        }

        return new self(
            rows:              $rows,
            columnDefinitions: $columns,
            totals:            null,
            rowCount:          $rows->count(),
            fetchedAt:         CarbonImmutable::now(),
        );
    }
}
