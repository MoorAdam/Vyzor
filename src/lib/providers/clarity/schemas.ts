import { z } from "zod/v4";

/**
 * Clarity API returns different fields per metric type in the `information`
 * array, so we use a flexible record schema rather than fixed field names.
 *
 * Examples:
 *   DeadClickCount → { sessionsCount, sessionsWithMetricPercentage, ... }
 *   ScrollDepth    → { averageScrollDepth }
 *   PopularPages   → { url, visitsCount }
 *   ReferrerUrl    → { name, sessionsCount }
 */
export const clarityMetricInfoSchema = z.record(
  z.string(),
  z.union([z.string(), z.number(), z.null()]),
);

export const clarityMetricGroupSchema = z.object({
  metricName: z.string(),
  information: z.array(clarityMetricInfoSchema),
});

export const clarityResponseSchema = z.array(clarityMetricGroupSchema);

export const clarityInsightsQuerySchema = z.object({
  numOfDays: z.enum(["1", "2", "3"]),
  dimension1: z.string().optional(),
  dimension2: z.string().optional(),
  dimension3: z.string().optional(),
});
