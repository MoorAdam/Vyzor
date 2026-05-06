<?php

namespace App\Modules\Analytics\GoogleAnalytics\DTOs;

use Carbon\CarbonImmutable;
use Google\Analytics\Data\V1alpha\RunFunnelReportResponse;

/**
 * Per-step active user counts + cumulative and step-over-step completion rates.
 *
 * The funnel API returns rows grouped by funnelStepName + activeUsers; we
 * also compute conversion (vs step 1) and step-over-step rates because GA's
 * raw response doesn't include them.
 */
final class FunnelResult
{
    /**
     * @param  list<array{
     *     stepIndex:int,
     *     stepName:string,
     *     activeUsers:int,
     *     conversionFromFirst:float,
     *     conversionFromPrevious:float,
     *     dropOffFromPrevious:int
     * }>  $steps
     */
    public function __construct(
        public readonly array $steps,
        public readonly CarbonImmutable $fetchedAt,
    ) {}

    public function toArray(): array
    {
        return [
            'steps'     => $this->steps,
            'fetchedAt' => $this->fetchedAt->toIso8601String(),
        ];
    }

    /**
     * Hydrate from the alpha API response. Funnel response has a "FunnelTable"
     * with rows of (funnelStepName, activeUsers). We pivot it into a step-ordered
     * list and compute conversion rates.
     *
     * @param  list<string>  $expectedStepNames  step names in the order we sent them
     */
    public static function fromGa(RunFunnelReportResponse $response, array $expectedStepNames): self
    {
        $byStepName = [];
        $table = $response->getFunnelTable();

        if ($table !== null) {
            // Find the index of the dimension column that holds the step name.
            $stepNameDimIndex = null;
            foreach ($table->getDimensionHeaders() as $i => $header) {
                if ($header->getName() === 'funnelStepName') {
                    $stepNameDimIndex = $i;
                    break;
                }
            }
            // Find the metric column for activeUsers.
            $activeUsersIndex = null;
            foreach ($table->getMetricHeaders() as $i => $header) {
                if ($header->getName() === 'activeUsers') {
                    $activeUsersIndex = $i;
                    break;
                }
            }

            foreach ($table->getRows() as $row) {
                $stepName = $stepNameDimIndex !== null
                    ? $row->getDimensionValues()[$stepNameDimIndex]->getValue()
                    : ($row->getDimensionValues()[0]?->getValue() ?? '');
                $users    = $activeUsersIndex !== null
                    ? (int) $row->getMetricValues()[$activeUsersIndex]->getValue()
                    : (int) ($row->getMetricValues()[0]?->getValue() ?? 0);

                // Funnel rows arrive with a leading "N - " prefix on the step name.
                // Strip it so we can match against the names we sent.
                $clean = preg_replace('/^\d+\s*-\s*/', '', $stepName);
                $byStepName[$clean] = $users;
            }
        }

        $steps = [];
        $firstUsers = null;
        $prevUsers  = null;

        foreach ($expectedStepNames as $i => $name) {
            $users = $byStepName[$name] ?? 0;
            if ($i === 0) {
                $firstUsers = $users;
                $prevUsers  = $users;
                $steps[] = [
                    'stepIndex'              => $i,
                    'stepName'               => $name,
                    'activeUsers'            => $users,
                    'conversionFromFirst'    => 1.0,
                    'conversionFromPrevious' => 1.0,
                    'dropOffFromPrevious'    => 0,
                ];
                continue;
            }

            $convFromFirst = $firstUsers > 0 ? ($users / $firstUsers) : 0.0;
            $convFromPrev  = $prevUsers > 0 ? ($users / $prevUsers) : 0.0;
            $drop          = max(0, ($prevUsers ?? 0) - $users);

            $steps[] = [
                'stepIndex'              => $i,
                'stepName'               => $name,
                'activeUsers'            => $users,
                'conversionFromFirst'    => $convFromFirst,
                'conversionFromPrevious' => $convFromPrev,
                'dropOffFromPrevious'    => $drop,
            ];
            $prevUsers = $users;
        }

        return new self($steps, CarbonImmutable::now());
    }
}
