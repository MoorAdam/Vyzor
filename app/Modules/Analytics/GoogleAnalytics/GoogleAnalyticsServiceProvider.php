<?php

namespace App\Modules\Analytics\GoogleAnalytics;

use App\Modules\Analytics\GoogleAnalytics\Auth\ServiceAccountClientFactory;
use App\Modules\Analytics\GoogleAnalytics\Commands\ListGoogleAnalyticsProperties;
use App\Modules\Analytics\GoogleAnalytics\Commands\TestGoogleAnalyticsConnection;
use App\Modules\Analytics\GoogleAnalytics\Services\GoogleAnalyticsCache;
use App\Modules\Analytics\GoogleAnalytics\Services\GoogleAnalyticsClient;
use App\Modules\Analytics\GoogleAnalytics\Services\GoogleAnalyticsQueryService;
use App\Modules\Analytics\GoogleAnalytics\Services\PropertyDiscoveryService;
use Illuminate\Support\ServiceProvider;

class GoogleAnalyticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ServiceAccountClientFactory::class);
        $this->app->singleton(GoogleAnalyticsClient::class);
        $this->app->singleton(GoogleAnalyticsCache::class);
        $this->app->singleton(GoogleAnalyticsQueryService::class);
        $this->app->singleton(PropertyDiscoveryService::class);

        $this->commands([
            TestGoogleAnalyticsConnection::class,
            ListGoogleAnalyticsProperties::class,
        ]);
    }
}
