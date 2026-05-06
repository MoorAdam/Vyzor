<?php

namespace App\Modules\Analytics\GoogleAnalytics\Queries;

use App\Modules\Analytics\GoogleAnalytics\Enums\GaDimension;
use App\Modules\Analytics\GoogleAnalytics\Enums\GaMetric;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\MetricAggregation;
use Google\Analytics\Data\V1beta\OrderBy;
use Google\Analytics\Data\V1beta\OrderBy\DimensionOrderBy;
use Google\Analytics\Data\V1beta\OrderBy\MetricOrderBy;
use Google\Analytics\Data\V1beta\RunReportRequest;

/**
 * Value object describing a GA4 Data API runReport call.
 *
 * Strings or enum values are both accepted for dimensions/metrics — they
 * are normalized to strings internally so cache keys are stable regardless
 * of whether the caller used the enum or the raw string.
 */
final class ReportRequest
{
    /** @var list<string> */
    public readonly array $dimensions;

    /** @var list<string> */
    public readonly array $metrics;

    /**
     * @param  array<int,GaDimension|string>  $dimensions
     * @param  array<int,GaMetric|string>     $metrics
     * @param  list<array{type:'metric'|'dimension',name:string,desc?:bool}>  $orderBy
     */
    public function __construct(
        array $dimensions,
        array $metrics,
        public readonly DateRange $dateRange,
        public readonly array $orderBy = [],
        public readonly int $limit = 50,
        public readonly int $offset = 0,
        public readonly bool $includeTotals = false,
        public readonly ?Filter $dimensionFilter = null,
        public readonly ?Filter $metricFilter = null,
    ) {
        $this->dimensions = array_values(array_map(
            fn ($d) => $d instanceof GaDimension ? $d->value : (string) $d,
            $dimensions,
        ));

        $this->metrics = array_values(array_map(
            fn ($m) => $m instanceof GaMetric ? $m->value : (string) $m,
            $metrics,
        ));
    }

    /**
     * Stable cache signature — identical requests produce identical hashes
     * regardless of array key ordering or string vs enum input.
     */
    public function cacheSignature(): string
    {
        $payload = [
            'd'  => $this->dimensions,
            'm'  => $this->metrics,
            'dr' => $this->dateRange->signature(),
            'o'  => $this->orderBy,
            'l'  => $this->limit,
            'of' => $this->offset,
            't'  => $this->includeTotals,
            'df' => $this->dimensionFilter?->signature(),
            'mf' => $this->metricFilter?->signature(),
        ];
        return sha1(json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    public function toGaRequest(string $property): RunReportRequest
    {
        $req = (new RunReportRequest())
            ->setProperty($property)
            ->setDateRanges([$this->dateRange->toGaDateRange()])
            ->setMetrics(array_map(fn ($m) => new Metric(['name' => $m]), $this->metrics))
            ->setLimit($this->limit)
            ->setOffset($this->offset);

        if ($this->dimensions !== []) {
            $req->setDimensions(array_map(fn ($d) => new Dimension(['name' => $d]), $this->dimensions));
        }

        if ($this->orderBy !== []) {
            $req->setOrderBys(array_map(fn ($spec) => $this->buildOrderBy($spec), $this->orderBy));
        }

        if ($this->includeTotals) {
            $req->setMetricAggregations([MetricAggregation::TOTAL]);
        }

        if ($this->dimensionFilter !== null) {
            $req->setDimensionFilter($this->dimensionFilter->toGaExpression());
        }

        if ($this->metricFilter !== null) {
            $req->setMetricFilter($this->metricFilter->toGaExpression());
        }

        return $req;
    }

    /**
     * @param  array{type:'metric'|'dimension',name:string,desc?:bool}  $spec
     */
    private function buildOrderBy(array $spec): OrderBy
    {
        $desc = (bool) ($spec['desc'] ?? false);

        if (($spec['type'] ?? 'metric') === 'dimension') {
            return new OrderBy([
                'dimension' => new DimensionOrderBy(['dimension_name' => $spec['name']]),
                'desc'      => $desc,
            ]);
        }

        return new OrderBy([
            'metric' => new MetricOrderBy(['metric_name' => $spec['name']]),
            'desc'   => $desc,
        ]);
    }
}
