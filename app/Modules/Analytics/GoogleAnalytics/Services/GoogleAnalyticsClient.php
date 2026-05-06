<?php

namespace App\Modules\Analytics\GoogleAnalytics\Services;

use App\Modules\Analytics\GoogleAnalytics\Auth\ServiceAccountClientFactory;
use App\Modules\Analytics\GoogleAnalytics\Exceptions\GoogleAnalyticsException;
use Google\Analytics\Data\V1alpha\RunFunnelReportRequest;
use Google\Analytics\Data\V1alpha\RunFunnelReportResponse;
use Google\Analytics\Data\V1beta\BatchRunReportsRequest;
use Google\Analytics\Data\V1beta\RunRealtimeReportRequest;
use Google\Analytics\Data\V1beta\RunRealtimeReportResponse;
use Google\Analytics\Data\V1beta\RunReportRequest;
use Google\Analytics\Data\V1beta\RunReportResponse;
use Google\ApiCore\ApiException;

/**
 * Thin wrapper around the GA Data SDK client.
 *
 * Responsible for: dispatching prepared request objects, normalizing SDK
 * exceptions into application-level GoogleAnalyticsException, and being
 * the single chokepoint for adding cross-cutting concerns later
 * (logging, rate limiting, retries) without touching every caller.
 */
class GoogleAnalyticsClient
{
    public function __construct(
        private readonly ServiceAccountClientFactory $factory,
    ) {}

    public function runReport(RunReportRequest $request): RunReportResponse
    {
        try {
            return $this->factory->make()->runReport($request);
        } catch (ApiException $e) {
            throw $this->wrap($e);
        }
    }

    public function runRealtimeReport(RunRealtimeReportRequest $request): RunRealtimeReportResponse
    {
        try {
            return $this->factory->make()->runRealtimeReport($request);
        } catch (ApiException $e) {
            throw $this->wrap($e);
        }
    }

    /**
     * Run multiple report requests in a single GA call (max 5 per batch per
     * GA's API limits — the caller is responsible for chunking if needed).
     * All requests must target the same property.
     *
     * Returns the responses in the same order as the input requests.
     *
     * @param  list<RunReportRequest>  $requests
     * @return list<RunReportResponse>
     */
    public function batchRunReports(string $property, array $requests): array
    {
        if (count($requests) > 5) {
            throw new \InvalidArgumentException(
                'batchRunReports accepts at most 5 reports per call (GA API limit). Got ' . count($requests) . '.',
            );
        }

        // GA's batch endpoint expects each sub-request to declare its property
        // explicitly even though the outer request also has it. Set defensively.
        foreach ($requests as $req) {
            if ($req->getProperty() === '') {
                $req->setProperty($property);
            }
        }

        $batchReq = (new BatchRunReportsRequest())
            ->setProperty($property)
            ->setRequests($requests);

        try {
            $response = $this->factory->make()->batchRunReports($batchReq);
            return iterator_to_array($response->getReports(), preserve_keys: false);
        } catch (ApiException $e) {
            throw $this->wrap($e);
        }
    }

    /**
     * Funnel reports use the v1alpha surface — preview-quality but stable
     * enough for product use; the SDK is shipped with the same package.
     */
    public function runFunnelReport(RunFunnelReportRequest $request): RunFunnelReportResponse
    {
        try {
            return $this->factory->makeAlpha()->runFunnelReport($request);
        } catch (ApiException $e) {
            throw $this->wrap($e);
        }
    }

    private function wrap(ApiException $e): GoogleAnalyticsException
    {
        $message = $e->getMessage();
        $hint = match (true) {
            str_contains($message, 'PERMISSION_DENIED') =>
                __('Service account is not added as Viewer on the GA property, or the GA Data API is not enabled on the GCP project.'),
            str_contains($message, 'NOT_FOUND') =>
                __('Property not found — check the property ID and that it is a GA4 (not Universal Analytics) property.'),
            str_contains($message, 'UNAUTHENTICATED') =>
                __('Service account credentials are invalid or revoked. Re-issue the JSON key in GCP.'),
            str_contains($message, 'RESOURCE_EXHAUSTED') =>
                __('Google Analytics quota exhausted. Try again later or increase quota tokens.'),
            default => null,
        };

        $msg = $hint ? "{$hint} (upstream: {$message})" : $message;
        return new GoogleAnalyticsException($msg, (int) $e->getCode(), $e);
    }
}
