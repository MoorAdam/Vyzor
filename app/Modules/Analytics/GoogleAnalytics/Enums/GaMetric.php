<?php

namespace App\Modules\Analytics\GoogleAnalytics\Enums;

/**
 * Curated subset of GA4 metric API names.
 *
 * String-backed values match the names accepted by GA's Data API.
 * For metrics not listed here, raw strings can still be passed to
 * ReportRequest — this enum exists for autocomplete and format hints.
 */
enum GaMetric: string
{
    // Audience
    case Sessions                = 'sessions';
    case TotalUsers              = 'totalUsers';
    case NewUsers                = 'newUsers';
    case ActiveUsers             = 'activeUsers';
    case SessionsPerUser         = 'sessionsPerUser';

    // Engagement
    case EngagedSessions         = 'engagedSessions';
    case EngagementRate          = 'engagementRate';
    case BounceRate              = 'bounceRate';
    case AverageSessionDuration  = 'averageSessionDuration';
    case UserEngagementDuration  = 'userEngagementDuration';

    // Pages
    case ScreenPageViews         = 'screenPageViews';
    case ScreenPageViewsPerUser  = 'screenPageViewsPerUser';

    // Events / conversions
    case EventCount              = 'eventCount';
    case EventValue              = 'eventValue';
    case EventCountPerUser       = 'eventCountPerUser';
    case Conversions             = 'conversions';

    // Ecommerce
    case TotalRevenue            = 'totalRevenue';
    case Transactions            = 'transactions';
    case PurchaseRevenue         = 'purchaseRevenue';

    /**
     * UI formatting hint — used by frontend components and the AI tool layer
     * to format the metric value appropriately.
     */
    public function format(): string
    {
        return match ($this) {
            self::EngagementRate, self::BounceRate
                => 'percentage_unit',
            self::AverageSessionDuration, self::UserEngagementDuration
                => 'duration_seconds',
            self::TotalRevenue, self::PurchaseRevenue, self::EventValue
                => 'currency',
            self::SessionsPerUser, self::ScreenPageViewsPerUser, self::EventCountPerUser
                => 'decimal',
            default => 'integer',
        };
    }
}
