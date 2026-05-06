<?php

namespace App\Modules\Analytics\GoogleAnalytics\Services;

use App\Modules\Analytics\GoogleAnalytics\Auth\ServiceAccountClientFactory;
use App\Modules\Analytics\GoogleAnalytics\Exceptions\GoogleAnalyticsException;
use Google\Analytics\Admin\V1beta\ListAccountSummariesRequest;
use Google\ApiCore\ApiException;
use Illuminate\Support\Facades\Cache;

/**
 * Lists GA4 properties accessible to the configured service account.
 *
 * Used during onboarding so users can pick their property from a dropdown
 * instead of typing a numeric ID. Cached for 10 minutes — listing the
 * service account's hierarchy doesn't change often.
 */
class PropertyDiscoveryService
{
    private const CACHE_KEY = 'ga:admin:account-summaries';
    private const CACHE_TTL = 600; // 10 min

    public function __construct(
        private readonly ServiceAccountClientFactory $factory,
    ) {}

    /**
     * Flat list of properties grouped by account, ready for a dropdown.
     *
     * @return list<array{
     *     property: string,
     *     propertyName: string,
     *     account: string,
     *     accountName: string,
     *     label: string
     * }>
     */
    public function listAccessibleProperties(bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return $this->fetchProperties();
        });
    }

    private function fetchProperties(): array
    {
        try {
            $client = $this->factory->makeAdmin();
            $pages  = $client->listAccountSummaries(new ListAccountSummariesRequest());
        } catch (ApiException $e) {
            throw new GoogleAnalyticsException(
                'Could not list GA accounts. The service account may not have access to any property yet, or the GA Admin API is not enabled. (upstream: ' . $e->getMessage() . ')',
                (int) $e->getCode(),
                $e,
            );
        }

        $out = [];
        foreach ($pages as $accountSummary) {
            $accountResource = $accountSummary->getAccount();          // "accounts/123"
            $accountName     = $accountSummary->getDisplayName() ?: $accountResource;

            foreach ($accountSummary->getPropertySummaries() as $prop) {
                $resource = $prop->getProperty();                     // "properties/123456789"
                $name     = $prop->getDisplayName() ?: $resource;
                $out[] = [
                    'property'     => $resource,
                    'propertyName' => $name,
                    'account'      => $accountResource,
                    'accountName'  => $accountName,
                    'label'        => "{$accountName} — {$name}",
                ];
            }
        }

        // Sort alphabetically by label so the dropdown is predictable.
        usort($out, fn ($a, $b) => strcasecmp($a['label'], $b['label']));

        return $out;
    }
}
