<?php

namespace App\Console\Commands;

use App\Models\ClarityFetchCounter;
use App\Models\ClarityInsight;
use App\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class FetchClarity extends Command
{
    protected $signature = 'app:fetch-clarity
        {project : The project ID to pull Clarity data for}
        {--days=1 : Number of days to pull (1, 2, or 3)}
        {--dimension1= : First dimension (Browser, Device, Country/Region, OS, Source, Medium, Campaign, Channel, URL)}
        {--dimension2= : Second dimension}
        {--dimension3= : Third dimension}';

    protected $description = 'Pulls clarity data for a project and saves it to the database';

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

        $days = (int) $this->option('days');
        $dimension1 = $this->option('dimension1');
        $dimension2 = $this->option('dimension2');
        $dimension3 = $this->option('dimension3');

        if (!in_array($days, [1, 2, 3])) {
            $this->error('Days must be 1, 2, or 3.');
            return self::FAILURE;
        }

        $dimensions = array_filter([$dimension1, $dimension2, $dimension3]);
        foreach ($dimensions as $dim) {
            if (!in_array($dim, self::VALID_DIMENSIONS)) {
                $this->error("Invalid dimension: {$dim}");
                $this->info('Valid dimensions: ' . implode(', ', self::VALID_DIMENSIONS));
                return self::FAILURE;
            }
        }

        $this->info("Fetching Clarity data for \"{$project->name}\"...");

        $data = $this->fetchClarityData($project->clarity_api_key, $days, $dimension1, $dimension2, $dimension3);

        if ($data === null) {
            return self::FAILURE;
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

        $response = Http::withToken($token)
            ->acceptJson()
            ->get(config('services.clarity.endpoint'), $params);

        if ($response->failed()) {
            $this->error("API returned {$response->status()}: {$response->body()}");
            return null;
        }

        return $response->json();
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
