<?php

/**
 * Standalone GA4 connection test.
 *
 * Run with:
 *   php tools/test-ga-connection.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\RunRealtimeReportRequest;
use Google\Analytics\Data\V1beta\RunReportRequest;

$propertyId      = '349999392';
$credentialsPath = __DIR__ . '/../storage/app/ga-service-account.json';

if (!is_readable($credentialsPath)) {
    fwrite(STDERR, "JSON not readable at: {$credentialsPath}\n");
    exit(1);
}

echo "Property:       properties/{$propertyId}\n";
echo "Credentials:    {$credentialsPath}\n";
echo str_repeat('-', 60) . "\n";

try {
    $client = new BetaAnalyticsDataClient([
        'credentials' => $credentialsPath,
    ]);

    // ── Test 1: standard report — last 7 days totals ────────────────
    $req = (new RunReportRequest())
        ->setProperty('properties/' . $propertyId)
        ->setDateRanges([
            new DateRange(['start_date' => '7daysAgo', 'end_date' => 'today']),
        ])
        ->setMetrics([
            new Metric(['name' => 'sessions']),
            new Metric(['name' => 'totalUsers']),
            new Metric(['name' => 'screenPageViews']),
            new Metric(['name' => 'engagementRate']),
        ]);

    $response = $client->runReport($req);

    echo "\n[Test 1] Last 7 days totals:\n";
    if (count($response->getRows()) === 0) {
        echo "  No data returned (property might be new or have no traffic).\n";
    } else {
        foreach ($response->getRows() as $row) {
            $vals = $row->getMetricValues();
            echo "  Sessions:        " . $vals[0]->getValue() . "\n";
            echo "  Total users:     " . $vals[1]->getValue() . "\n";
            echo "  Page views:      " . $vals[2]->getValue() . "\n";
            echo "  Engagement rate: " . round((float) $vals[3]->getValue() * 100, 2) . "%\n";
        }
    }

    // ── Test 2: top pages — verify dimensions work ──────────────────
    $topReq = (new RunReportRequest())
        ->setProperty('properties/' . $propertyId)
        ->setDateRanges([
            new DateRange(['start_date' => '7daysAgo', 'end_date' => 'today']),
        ])
        ->setDimensions([new Dimension(['name' => 'pagePath'])])
        ->setMetrics([new Metric(['name' => 'screenPageViews'])])
        ->setLimit(5);

    $top = $client->runReport($topReq);

    echo "\n[Test 2] Top 5 pages (last 7 days):\n";
    if (count($top->getRows()) === 0) {
        echo "  No page data returned.\n";
    } else {
        foreach ($top->getRows() as $row) {
            $path  = $row->getDimensionValues()[0]->getValue();
            $views = $row->getMetricValues()[0]->getValue();
            echo "  {$views}\t{$path}\n";
        }
    }

    // ── Test 3: realtime — verify realtime API works ────────────────
    $rtReq = (new RunRealtimeReportRequest())
        ->setProperty('properties/' . $propertyId)
        ->setMetrics([new Metric(['name' => 'activeUsers'])]);

    $realtime = $client->runRealtimeReport($rtReq);

    echo "\n[Test 3] Realtime active users (last 30 min):\n";
    if (count($realtime->getRows()) === 0) {
        echo "  0 active users right now.\n";
    } else {
        foreach ($realtime->getRows() as $row) {
            echo "  Active users: " . $row->getMetricValues()[0]->getValue() . "\n";
        }
    }

    echo "\n" . str_repeat('-', 60) . "\n";
    echo "OK: connection works.\n";
    exit(0);
} catch (\Throwable $e) {
    echo "\n" . str_repeat('-', 60) . "\n";
    fwrite(STDERR, "FAILED: " . get_class($e) . "\n");
    fwrite(STDERR, "Message: " . $e->getMessage() . "\n");

    $msg = $e->getMessage();
    if (str_contains($msg, 'PERMISSION_DENIED') || str_contains($msg, '403')) {
        fwrite(STDERR, "\nLikely cause: the service account email is not added as Viewer\n");
        fwrite(STDERR, "to property {$propertyId} in Google Analytics.\n");
    } elseif (str_contains($msg, 'NOT_FOUND') || str_contains($msg, '404')) {
        fwrite(STDERR, "\nLikely cause: wrong property ID, or the property is GA Universal (UA),\n");
        fwrite(STDERR, "not GA4 (Data API only works with GA4).\n");
    } elseif (str_contains($msg, 'UNAUTHENTICATED') || str_contains($msg, '401')) {
        fwrite(STDERR, "\nLikely cause: invalid or revoked service account JSON key.\n");
    } elseif (str_contains($msg, 'API has not been used') || str_contains($msg, 'SERVICE_DISABLED')) {
        fwrite(STDERR, "\nLikely cause: Google Analytics Data API is not enabled on the GCP project.\n");
        fwrite(STDERR, "Go to GCP Console -> APIs & Services -> Library -> 'Google Analytics Data API' -> Enable.\n");
    }

    exit(1);
}
