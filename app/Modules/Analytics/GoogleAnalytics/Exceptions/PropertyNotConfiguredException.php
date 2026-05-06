<?php

namespace App\Modules\Analytics\GoogleAnalytics\Exceptions;

class PropertyNotConfiguredException extends GoogleAnalyticsException
{
    public static function forProject(int $projectId): self
    {
        return new self("Project {$projectId} has no Google Analytics property ID configured.");
    }
}
