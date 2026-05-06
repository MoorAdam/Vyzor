<?php

namespace App\Modules\Analytics\GoogleAnalytics\Auth;

use App\Modules\Analytics\GoogleAnalytics\Exceptions\ServiceAccountNotConfiguredException;
use Google\Analytics\Admin\V1beta\Client\AnalyticsAdminServiceClient;
use Google\Analytics\Data\V1alpha\Client\AlphaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;

class ServiceAccountClientFactory
{
    private ?BetaAnalyticsDataClient $dataClient = null;
    private ?AlphaAnalyticsDataClient $alphaClient = null;
    private ?AnalyticsAdminServiceClient $adminClient = null;

    public function make(): BetaAnalyticsDataClient
    {
        if ($this->dataClient === null) {
            $this->dataClient = new BetaAnalyticsDataClient([
                'credentials' => $this->resolveCredentials(),
            ]);
        }

        return $this->dataClient;
    }

    /**
     * The alpha SDK is what backs runFunnelReport. Funnels are still in the
     * v1alpha surface as of GA4's current API; treat them as preview-quality.
     */
    public function makeAlpha(): AlphaAnalyticsDataClient
    {
        if ($this->alphaClient === null) {
            $this->alphaClient = new AlphaAnalyticsDataClient([
                'credentials' => $this->resolveCredentials(),
            ]);
        }

        return $this->alphaClient;
    }

    public function makeAdmin(): AnalyticsAdminServiceClient
    {
        if ($this->adminClient === null) {
            $this->adminClient = new AnalyticsAdminServiceClient([
                'credentials' => $this->resolveCredentials(),
            ]);
        }

        return $this->adminClient;
    }

    /**
     * Returns either an absolute file path (string) or a decoded JSON array,
     * both forms are accepted by BetaAnalyticsDataClient's 'credentials' option.
     */
    private function resolveCredentials(): array|string
    {
        $rawJson = config('services.google_analytics.service_account_json');
        if (is_string($rawJson) && trim($rawJson) !== '') {
            try {
                return json_decode($rawJson, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw ServiceAccountNotConfiguredException::invalidJson($e->getMessage());
            }
        }

        $path = config('services.google_analytics.service_account_path');
        if (!is_string($path) || $path === '') {
            throw ServiceAccountNotConfiguredException::missing();
        }

        if (!is_readable($path)) {
            throw ServiceAccountNotConfiguredException::notReadable($path);
        }

        return $path;
    }
}
