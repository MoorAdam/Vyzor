<?php

namespace App\Modules\Analytics\GoogleAnalytics\Commands;

use App\Modules\Analytics\GoogleAnalytics\Exceptions\GoogleAnalyticsException;
use App\Modules\Analytics\GoogleAnalytics\Services\PropertyDiscoveryService;
use Illuminate\Console\Command;

class ListGoogleAnalyticsProperties extends Command
{
    protected $signature = 'app:ga:list-properties {--refresh : Bypass cache and fetch fresh}';

    protected $description = 'Lists GA4 properties accessible to the configured service account.';

    public function handle(PropertyDiscoveryService $svc): int
    {
        try {
            $properties = $svc->listAccessibleProperties(forceRefresh: $this->option('refresh'));
        } catch (GoogleAnalyticsException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        if ($properties === []) {
            $this->warn('No properties accessible. Ensure the service account email is added as Viewer to at least one GA4 property.');
            return self::SUCCESS;
        }

        $this->info(count($properties) . ' property(ies) accessible:');
        $this->newLine();

        $this->table(
            ['Property ID', 'Property name', 'Account'],
            array_map(
                fn ($p) => [
                    str_replace('properties/', '', $p['property']),
                    $p['propertyName'],
                    $p['accountName'],
                ],
                $properties,
            ),
        );

        return self::SUCCESS;
    }
}
