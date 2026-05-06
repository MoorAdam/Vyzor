<?php

namespace App\Modules\Analytics\GoogleAnalytics\Commands;

use App\Modules\Analytics\GoogleAnalytics\Exceptions\GoogleAnalyticsException;
use App\Modules\Analytics\GoogleAnalytics\Services\GoogleAnalyticsClient;
use App\Modules\Projects\Models\Project;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\RunRealtimeReportRequest;
use Google\Analytics\Data\V1beta\RunReportRequest;
use Illuminate\Console\Command;

class TestGoogleAnalyticsConnection extends Command
{
    protected $signature = 'app:ga:test {project : The project ID to test the GA connection for}';

    protected $description = 'Verifies that the configured GA service account can read the project\'s GA4 property.';

    public function handle(GoogleAnalyticsClient $client): int
    {
        $project = Project::find($this->argument('project'));

        if (!$project) {
            $this->error('Project not found.');
            return self::FAILURE;
        }

        if (!$project->hasGoogleAnalytics()) {
            $this->error("Project \"{$project->name}\" has no GA property ID configured.");
            $this->line('Set it with:  Project::find(' . $project->id . ')->update([\'ga_property_id\' => \'properties/123456789\']);');
            return self::FAILURE;
        }

        $property = $project->gaPropertyResource();
        $this->info("Testing GA connection for project \"{$project->name}\" ({$property})");

        try {
            $totals = $this->fetchTotals($client, $property);
            $top = $this->fetchTopPages($client, $property);
            $realtime = $this->fetchRealtime($client, $property);
        } catch (GoogleAnalyticsException $e) {
            $this->error('FAILED: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->newLine();
        $this->line('Last 7 days totals:');
        $this->line('  Sessions:        ' . $totals['sessions']);
        $this->line('  Total users:     ' . $totals['users']);
        $this->line('  Page views:      ' . $totals['views']);
        $this->line('  Engagement rate: ' . round($totals['engagement'] * 100, 2) . '%');

        $this->newLine();
        $this->line('Top 5 pages (last 7 days):');
        if ($top === []) {
            $this->line('  (no page data)');
        } else {
            foreach ($top as $row) {
                $this->line(sprintf('  %-8s %s', $row['views'], $row['path']));
            }
        }

        $this->newLine();
        $this->line("Realtime active users: {$realtime}");

        $project->update(['ga_last_verified_at' => now()]);

        $this->newLine();
        $this->info('OK: connection works.');
        return self::SUCCESS;
    }

    /**
     * @return array{sessions:string,users:string,views:string,engagement:float}
     */
    private function fetchTotals(GoogleAnalyticsClient $client, string $property): array
    {
        $req = (new RunReportRequest())
            ->setProperty($property)
            ->setDateRanges([new DateRange(['start_date' => '7daysAgo', 'end_date' => 'today'])])
            ->setMetrics([
                new Metric(['name' => 'sessions']),
                new Metric(['name' => 'totalUsers']),
                new Metric(['name' => 'screenPageViews']),
                new Metric(['name' => 'engagementRate']),
            ]);

        $response = $client->runReport($req);

        if (count($response->getRows()) === 0) {
            return ['sessions' => '0', 'users' => '0', 'views' => '0', 'engagement' => 0.0];
        }

        $row = $response->getRows()[0];
        $vals = $row->getMetricValues();
        return [
            'sessions'   => $vals[0]->getValue(),
            'users'      => $vals[1]->getValue(),
            'views'      => $vals[2]->getValue(),
            'engagement' => (float) $vals[3]->getValue(),
        ];
    }

    /**
     * @return list<array{views:string,path:string}>
     */
    private function fetchTopPages(GoogleAnalyticsClient $client, string $property): array
    {
        $req = (new RunReportRequest())
            ->setProperty($property)
            ->setDateRanges([new DateRange(['start_date' => '7daysAgo', 'end_date' => 'today'])])
            ->setDimensions([new Dimension(['name' => 'pagePath'])])
            ->setMetrics([new Metric(['name' => 'screenPageViews'])])
            ->setLimit(5);

        $response = $client->runReport($req);

        $out = [];
        foreach ($response->getRows() as $row) {
            $out[] = [
                'views' => $row->getMetricValues()[0]->getValue(),
                'path'  => $row->getDimensionValues()[0]->getValue(),
            ];
        }
        return $out;
    }

    private function fetchRealtime(GoogleAnalyticsClient $client, string $property): string
    {
        $req = (new RunRealtimeReportRequest())
            ->setProperty($property)
            ->setMetrics([new Metric(['name' => 'activeUsers'])]);

        $response = $client->runRealtimeReport($req);

        if (count($response->getRows()) === 0) {
            return '0';
        }
        return $response->getRows()[0]->getMetricValues()[0]->getValue();
    }
}
