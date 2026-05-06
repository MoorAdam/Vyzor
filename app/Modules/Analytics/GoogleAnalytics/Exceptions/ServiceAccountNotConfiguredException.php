<?php

namespace App\Modules\Analytics\GoogleAnalytics\Exceptions;

class ServiceAccountNotConfiguredException extends GoogleAnalyticsException
{
    public static function notReadable(string $path): self
    {
        return new self("Google Analytics service account JSON not readable at: {$path}. Set GA_SERVICE_ACCOUNT_PATH or GA_SERVICE_ACCOUNT_JSON.");
    }

    public static function missing(): self
    {
        return new self('Google Analytics service account is not configured. Set GA_SERVICE_ACCOUNT_PATH or GA_SERVICE_ACCOUNT_JSON in your environment.');
    }

    public static function invalidJson(string $reason): self
    {
        return new self("Google Analytics service account JSON could not be parsed: {$reason}");
    }
}
