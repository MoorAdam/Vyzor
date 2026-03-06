export const CLARITY_DIMENSIONS = [
  "Browser",
  "Device",
  "Country",
  "OS",
  "Source",
  "Medium",
  "Campaign",
  "Channel",
  "URL",
] as const;

export type ClarityDimension = (typeof CLARITY_DIMENSIONS)[number];

export const CLARITY_METRICS = [
  "ScrollDepth",
  "EngagementTime",
  "Traffic",
  "PopularPages",
  "DeadClickCount",
  "RageClickCount",
  "QuickbackClick",
  "ScriptErrorCount",
  "ErrorClickCount",
  "ExcessiveScroll",
] as const;

export type ClarityMetric = (typeof CLARITY_METRICS)[number];

export interface ClarityInsightsParams {
  numOfDays: 1 | 2 | 3;
  dimension1?: ClarityDimension;
  dimension2?: ClarityDimension;
  dimension3?: ClarityDimension;
}

/**
 * Each information item is a flexible record — the Clarity API returns
 * different fields per metric type (e.g. sessionsCount, averageScrollDepth,
 * url, visitsCount, name, etc.).
 */
export type ClarityMetricInfo = Record<string, string | number | null>;

export interface ClarityMetricGroup {
  metricName: string;
  information: ClarityMetricInfo[];
}

export type ClarityResponse = ClarityMetricGroup[];
