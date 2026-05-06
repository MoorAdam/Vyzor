<?php

namespace App\Modules\Analytics\GoogleAnalytics\Queries;

use App\Modules\Analytics\GoogleAnalytics\Enums\GaDimension;
use App\Modules\Analytics\GoogleAnalytics\Enums\GaMetric;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\RunRealtimeReportRequest;

/**
 * Value object describing a GA4 Realtime API runRealtimeReport call.
 *
 * The Realtime API has a smaller surface than the standard Data API —
 * no date ranges (always last 30 minutes) and a more limited dimension/metric set.
 */
final class RealtimeRequest
{
    /** @var list<string> */
    public readonly array $dimensions;

    /** @var list<string> */
    public readonly array $metrics;

    /**
     * @param  array<int,GaDimension|string>  $dimensions
     * @param  array<int,GaMetric|string>     $metrics
     */
    public function __construct(
        array $dimensions,
        array $metrics,
        public readonly int $limit = 50,
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

    public function cacheSignature(): string
    {
        return sha1(json_encode([
            'd' => $this->dimensions,
            'm' => $this->metrics,
            'l' => $this->limit,
        ], JSON_UNESCAPED_SLASHES));
    }

    public function toGaRequest(string $property): RunRealtimeReportRequest
    {
        $req = (new RunRealtimeReportRequest())
            ->setProperty($property)
            ->setMetrics(array_map(fn ($m) => new Metric(['name' => $m]), $this->metrics))
            ->setLimit($this->limit);

        if ($this->dimensions !== []) {
            $req->setDimensions(array_map(fn ($d) => new Dimension(['name' => $d]), $this->dimensions));
        }

        return $req;
    }
}
