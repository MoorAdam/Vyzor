<?php

namespace App\Modules\Analytics\GoogleAnalytics\Enums;

/**
 * Curated subset of GA4 dimension API names.
 *
 * String-backed values match the names accepted by GA's Data API.
 * For dimensions not listed here, raw strings can still be passed to
 * ReportRequest — this enum exists for autocomplete and discoverability.
 */
enum GaDimension: string
{
    // Page
    case PagePath           = 'pagePath';
    case PageTitle          = 'pageTitle';
    case PageLocation       = 'pageLocation';
    case LandingPage        = 'landingPage';
    case PagePathPlusQueryString = 'pagePathPlusQueryString';

    // Time
    case Date               = 'date';
    case DateHour           = 'dateHour';
    case DayOfWeek          = 'dayOfWeek';

    // Tech
    case DeviceCategory     = 'deviceCategory';
    case OperatingSystem    = 'operatingSystem';
    case Browser            = 'browser';
    case ScreenResolution   = 'screenResolution';

    // Geo / locale
    case Country            = 'country';
    case Region             = 'region';
    case City               = 'city';
    case Language           = 'language';

    // Acquisition (session-scoped)
    case SessionDefaultChannelGroup = 'sessionDefaultChannelGroup';
    case SessionSource      = 'sessionSource';
    case SessionMedium      = 'sessionMedium';
    case SessionCampaignName = 'sessionCampaignName';
    case SessionSourceMedium = 'sessionSourceMedium';

    // Events
    case EventName          = 'eventName';

    // User
    case NewVsReturning     = 'newVsReturning';

    // Demographics — populated only when Google Signals is enabled on the GA4 property.
    // GA also applies a privacy threshold: small audiences show up as "(not set)".
    case UserAgeBracket     = 'userAgeBracket';
    case UserGender         = 'userGender';
}
