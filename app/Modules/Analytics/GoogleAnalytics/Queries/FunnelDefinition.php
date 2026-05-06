<?php

namespace App\Modules\Analytics\GoogleAnalytics\Queries;

use Google\Analytics\Data\V1alpha\DateRange as AlphaDateRange;
use Google\Analytics\Data\V1alpha\Funnel;
use Google\Analytics\Data\V1alpha\FunnelEventFilter;
use Google\Analytics\Data\V1alpha\FunnelFieldFilter;
use Google\Analytics\Data\V1alpha\FunnelFilterExpression;
use Google\Analytics\Data\V1alpha\FunnelFilterExpressionList;
use Google\Analytics\Data\V1alpha\FunnelParameterFilter;
use Google\Analytics\Data\V1alpha\FunnelParameterFilterExpression;
use Google\Analytics\Data\V1alpha\FunnelStep as GaFunnelStep;
use Google\Analytics\Data\V1alpha\RunFunnelReportRequest;
use Google\Analytics\Data\V1alpha\StringFilter as AlphaStringFilter;
use Google\Analytics\Data\V1alpha\StringFilter\MatchType as AlphaMatchType;

/**
 * Composes a list of FunnelSteps into the alpha runFunnelReport request shape.
 *
 * "Closed" funnel (default): each step must follow the previous one in order.
 * "Open" funnel: any order — useful for "did the user do X *and* Y at all".
 */
final class FunnelDefinition
{
    /**
     * @param  list<FunnelStep>  $steps
     */
    public function __construct(
        public readonly array $steps,
        public readonly DateRange $dateRange,
        public readonly bool $isOpen = false,
        public readonly int $limit = 100,
    ) {
        if (count($steps) < 2) {
            throw new \InvalidArgumentException('Funnel needs at least 2 steps.');
        }
    }

    public function cacheSignature(): string
    {
        return sha1(json_encode([
            'steps'    => array_map(fn (FunnelStep $s) => $s->signature(), $this->steps),
            'open'     => $this->isOpen,
            'range'    => $this->dateRange->signature(),
            'limit'    => $this->limit,
        ]));
    }

    public function toGaRequest(string $property): RunFunnelReportRequest
    {
        $funnel = (new Funnel())
            ->setIsOpenFunnel($this->isOpen)
            ->setSteps(array_map(fn (FunnelStep $s) => $this->buildGaStep($s), $this->steps));

        return (new RunFunnelReportRequest())
            ->setProperty($property)
            ->setDateRanges([
                new AlphaDateRange([
                    'start_date' => $this->dateRange->startString(),
                    'end_date'   => $this->dateRange->endString(),
                ]),
            ])
            ->setFunnel($funnel)
            ->setLimit($this->limit);
    }

    private function buildGaStep(FunnelStep $step): GaFunnelStep
    {
        // Event filter — the eventName narrows what counts for this step.
        // Event parameter filters (page_location, etc.) attach inside the
        // event filter via funnelParameterFilterExpression — that's how the
        // funnel API expects parameter-based narrowing (NOT via dimensions).
        $eventFilter = (new FunnelEventFilter())->setEventName($step->eventName);

        if ($step->dimensionField !== null && $step->dimensionValue !== null) {
            if ($this->isEventParameter($step->dimensionField)) {
                $eventFilter->setFunnelParameterFilterExpression(
                    $this->buildParameterFilterExpression($step),
                );
            } else {
                // Non-parameter dimensions (e.g., country, deviceCategory) are
                // expressed at the FunnelFieldFilter level and ANDed with the event.
                $fieldFilter = (new FunnelFieldFilter())
                    ->setFieldName($step->dimensionField)
                    ->setStringFilter(new AlphaStringFilter([
                        'value'      => $step->dimensionValue,
                        'match_type' => $this->mapMatchType($step->matchType),
                    ]));

                return (new GaFunnelStep())
                    ->setName($step->name)
                    ->setFilterExpression(
                        (new FunnelFilterExpression())->setAndGroup(new FunnelFilterExpressionList([
                            'expressions' => [
                                (new FunnelFilterExpression())->setFunnelEventFilter($eventFilter),
                                (new FunnelFilterExpression())->setFunnelFieldFilter($fieldFilter),
                            ],
                        ])),
                    );
            }
        }

        return (new GaFunnelStep())
            ->setName($step->name)
            ->setFilterExpression(
                (new FunnelFilterExpression())->setFunnelEventFilter($eventFilter),
            );
    }

    private function buildParameterFilterExpression(FunnelStep $step): FunnelParameterFilterExpression
    {
        $paramFilter = (new FunnelParameterFilter())
            ->setEventParameterName($step->dimensionField)
            ->setStringFilter(new AlphaStringFilter([
                'value'      => $step->dimensionValue,
                'match_type' => $this->mapMatchType($step->matchType),
            ]));

        return (new FunnelParameterFilterExpression())->setFunnelParameterFilter($paramFilter);
    }

    /**
     * Heuristic: lowercase + underscore = event/item parameter name (page_location,
     * page_title, item_id, etc.). UpperCamel-ish = dimension (country,
     * deviceCategory). The split matters because GA routes them differently.
     */
    private function isEventParameter(string $field): bool
    {
        return str_contains($field, '_') || ctype_lower($field[0] ?? '');
    }

    private function mapMatchType(string $matchType): int
    {
        return match ($matchType) {
            FunnelStep::MATCH_EXACT       => AlphaMatchType::EXACT,
            FunnelStep::MATCH_CONTAINS    => AlphaMatchType::CONTAINS,
            FunnelStep::MATCH_BEGINS_WITH => AlphaMatchType::BEGINS_WITH,
            default                       => AlphaMatchType::EXACT,
        };
    }
}
