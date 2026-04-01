<?php

namespace App;

enum ReportPresetEnum: string
{
    case TRAFFIC_OVERVIEW = 'traffic-overview';
    case UX_ISSUES = 'ux-issues';
    case ENGAGEMENT_ANALYSIS = 'engagement-analysis';
    case DEVICE_BROWSER_ANALYSIS = 'device-browser-analysis';
    case CONTENT_PERFORMANCE = 'content-performance';
    case WEEKLY_SUMMARY = 'weekly-summary';

    public function label(): string
    {
        return match ($this) {
            self::TRAFFIC_OVERVIEW => 'Traffic Overview',
            self::UX_ISSUES => 'UX Issues',
            self::ENGAGEMENT_ANALYSIS => 'Engagement Analysis',
            self::DEVICE_BROWSER_ANALYSIS => 'Device & Browser Analysis',
            self::CONTENT_PERFORMANCE => 'Content Performance',
            self::WEEKLY_SUMMARY => 'Weekly Summary',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::TRAFFIC_OVERVIEW => '#3b82f6',
            self::UX_ISSUES => '#f43f5e',
            self::ENGAGEMENT_ANALYSIS => '#8b5cf6',
            self::DEVICE_BROWSER_ANALYSIS => '#06b6d4',
            self::CONTENT_PERFORMANCE => '#f59e0b',
            self::WEEKLY_SUMMARY => '#22c55e',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::TRAFFIC_OVERVIEW => 'chart-line-up',
            self::UX_ISSUES => 'warning',
            self::ENGAGEMENT_ANALYSIS => 'chart-bar',
            self::DEVICE_BROWSER_ANALYSIS => 'device-mobile',
            self::CONTENT_PERFORMANCE => 'article',
            self::WEEKLY_SUMMARY => 'calendar-check',
        };
    }
}
