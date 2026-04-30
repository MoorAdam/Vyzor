<?php

namespace App\Modules\Analytics\Clarity\Commands;

use App\Modules\Analytics\Clarity\Models\ClarityFetchCounter;
use App\Modules\Analytics\Clarity\Models\ClarityInsight;
use App\Modules\Projects\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class FetchClarity extends Command
{
    protected $signature = 'app:fetch-clarity
        {project : The project ID to pull Clarity data for}
        {--dimension1= : First dimension (Browser, Device, Country/Region, OS, Source, Medium, Campaign, Channel, URL)}
        {--dimension2= : Second dimension}
        {--dimension3= : Third dimension}';

    protected $description = 'Pulls clarity data for a project and saves it to the database';

    // Clarity's API supports 1/2/3-day windows but only returns one aggregated row per call;
    // we always fetch the 1-day window so each snapshot represents a single day cleanly.
    private const FETCH_DAYS = 1;

    private const VALID_DIMENSIONS = [
        'Browser',
        'Device',
        'Country/Region',
        'OS',
        'Source',
        'Medium',
        'Campaign',
        'Channel',
        'URL',
    ];

    public function handle(): int
    {
        $project = Project::find($this->argument('project'));

        if (!$project) {
            $this->error('Project not found.');
            return self::FAILURE;
        }

        if (!$project->clarity_api_key) {
            $this->error("Project \"{$project->name}\" has no Clarity API key configured.");
            return self::FAILURE;
        }

        $days = self::FETCH_DAYS;
        $dimension1 = $this->option('dimension1');
        $dimension2 = $this->option('dimension2');
        $dimension3 = $this->option('dimension3');

        $dimensions = array_filter([$dimension1, $dimension2, $dimension3]);
        foreach ($dimensions as $dim) {
            if (!in_array($dim, self::VALID_DIMENSIONS)) {
                $this->error("Invalid dimension: {$dim}");
                $this->info('Valid dimensions: ' . implode(', ', self::VALID_DIMENSIONS));
                return self::FAILURE;
            }
        }

        $this->info("Fetching Clarity data for \"{$project->name}\"...");

        // Allow PHP to notice if the user navigated/cancelled while we were waiting on the API.
        ignore_user_abort(false);

        $data = $this->fetchClarityData($project->clarity_api_key, $days, $dimension1, $dimension2, $dimension3);

        if ($data === null) {
            return self::FAILURE;
        }

        // If the client disconnected (e.g. user clicked Cancel) while Clarity was responding,
        // don't waste a DB write — and don't burn a daily fetch slot.
        if (connection_aborted()) {
            $this->info('Client disconnected — discarding fetched data without saving.');
            return self::SUCCESS;
        }

        $this->saveToDatabase($project, $data, $days, $dimension1, $dimension2, $dimension3);

        $this->onUpCounter();

        $this->info('Clarity data extraction completed successfully.');
        return self::SUCCESS;
    }

    private function onUpCounter(): void
    {
        $projectId = $this->argument('project');
        $today = now()->toDateString();

        $counter = ClarityFetchCounter::where('project_id', $projectId)
            ->where('date', $today)
            ->first();

        if ($counter) {
            ClarityFetchCounter::where('project_id', $projectId)
                 ->where('date', $today)
                ->where('id', $counter->id)
                ->increment('fetch_count');
        } else {
            ClarityFetchCounter::insert([
                'project_id' => $projectId,
                'date' => $today,
                'fetch_count' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function fetchClarityData(string $token, int $days, ?string $dimension1, ?string $dimension2, ?string $dimension3): ?array
    {
        $params = ['numOfDays' => $days];

        if ($dimension1) {
            $params['dimension1'] = $dimension1;
        }
        if ($dimension2) {
            $params['dimension2'] = $dimension2;
        }
        if ($dimension3) {
            $params['dimension3'] = $dimension3;
        }

        $connectTimeout = (int) config('services.clarity.connect_timeout', 10);
        $timeout = (int) config('services.clarity.timeout', 90);

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->connectTimeout($connectTimeout)
                ->timeout($timeout)
                ->get(config('services.clarity.endpoint'), $params);
        } catch (ConnectionException $e) {
            $this->error("Could not reach Clarity (after {$timeout}s): {$e->getMessage()}");
            return null;
        } catch (\Throwable $e) {
            $this->error("Unexpected error contacting Clarity: {$e->getMessage()}");
            return null;
        }

        if ($response->failed()) {
            $this->error($this->formatUpstreamError($response->status(), $response->body()));
            return null;
        }

        return $response->json();
    }

    private function formatUpstreamError(int $status, string $body): string
    {
        $hint = match (true) {
            in_array($status, [502, 503, 504], true) => __('Clarity is temporarily unavailable. Please try again in a minute.'),
            $status === 429 => __('Clarity rate limit hit. Please wait before retrying.'),
            $status === 401 || $status === 403 => __('Clarity rejected the API key for this project.'),
            $status >= 500 => __('Clarity returned a server error. Please try again later.'),
            default => null,
        };

        // Strip HTML tags / collapse whitespace so we never dump nginx error pages at users.
        $clean = trim(preg_replace('/\s+/', ' ', strip_tags($body)));
        if (mb_strlen($clean) > 200) {
            $clean = mb_substr($clean, 0, 200) . '…';
        }

        if ($hint) {
            return $clean === ''
                ? "{$hint} (HTTP {$status})"
                : "{$hint} (HTTP {$status}: {$clean})";
        }

        return "API returned {$status}" . ($clean === '' ? '' : ": {$clean}");
    }

    private function saveToDatabase(Project $project, array $data, int $days, ?string $dimension1, ?string $dimension2, ?string $dimension3): void
    {
        $now = now();
        $fetchedFor = $now->copy()->startOfHour();
        $dateTo = $now->toDateString();
        $dateFrom = $now->copy()->subDays($days - 1)->toDateString();

        foreach ($data as $metric) {
            $metricName = $metric['metricName'] ?? 'Unknown';

            ClarityInsight::updateOrCreate(
                [
                    'project_id' => $project->id,
                    'metric_name' => $metricName,
                    'dimension1' => $dimension1,
                    'dimension2' => $dimension2,
                    'dimension3' => $dimension3,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                ],
                [
                    'data' => $metric['information'] ?? [],
                    'fetched_for' => $fetchedFor,
                ]
            );

            $this->line("Saved metric: {$metricName}");
        }
    }
}
