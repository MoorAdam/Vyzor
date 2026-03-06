import { BaseProvider } from "../base-provider";
import type {
  AuthState,
  NormalizedMetric,
  ProviderCapabilities,
  ProviderDataResponse,
  ProviderMeta,
} from "../types";
import type { ClarityInsightsParams, ClarityResponse } from "./types";
import { CLARITY_DIMENSIONS, CLARITY_METRICS } from "./types";
import { clarityResponseSchema } from "./schemas";
import { env } from "@/lib/config/env";
import { endpoints } from "@/lib/config/endpoints";

export class ClarityProvider extends BaseProvider {
  meta: ProviderMeta = {
    id: "clarity",
    name: "Microsoft Clarity",
    description: "Heatmaps, session recordings, and behavioral analytics",
    docsUrl: "https://learn.microsoft.com/en-us/clarity/",
    color: "#6C2BD9",
  };

  capabilities: ProviderCapabilities = {
    canQueryLiveInsights: true,
    canCreateExports: false,
    canQueryMetrics: false,
    canListSegments: false,
    canListGoals: false,
    supportedDimensions: [...CLARITY_DIMENSIONS],
    supportedMetrics: [...CLARITY_METRICS],
    maxDimensions: 3,
    rateLimitPerDay: 10,
  };

  async authenticate(): Promise<AuthState> {
    const token = env.CLARITY_API_TOKEN;
    this.authState = {
      isAuthenticated: !!token,
      error: token ? undefined : "CLARITY_API_TOKEN not configured",
    };
    return this.authState;
  }

  async healthCheck(): Promise<{ healthy: boolean; message: string }> {
    if (!env.CLARITY_API_TOKEN) {
      return { healthy: false, message: "CLARITY_API_TOKEN not configured" };
    }
    try {
      await this.fetchInsights({ numOfDays: 1 });
      return { healthy: true, message: "Connected" };
    } catch (error) {
      return {
        healthy: false,
        message: error instanceof Error ? error.message : String(error),
      };
    }
  }

  async fetchInsights(
    params: ClarityInsightsParams,
  ): Promise<ProviderDataResponse> {
    const searchParams = new URLSearchParams({
      numOfDays: String(params.numOfDays),
    });
    if (params.dimension1) searchParams.set("dimension1", params.dimension1);
    if (params.dimension2) searchParams.set("dimension2", params.dimension2);
    if (params.dimension3) searchParams.set("dimension3", params.dimension3);

    const base = endpoints.clarity.apiBase.replace(/\/+$/, "");
    const data = await this.apiFetch<ClarityResponse>(
      `${base}/project-live-insights?${searchParams}`,
      {},
      clarityResponseSchema,
    );

    return {
      providerId: "clarity",
      metrics: this.normalizeResponse(data),
      raw: data,
      fetchedAt: new Date().toISOString(),
    };
  }

  protected validateConfig(): void {
    // Clarity token is optional — provider reports unhealthy if missing
  }

  protected getAuthHeaders(): Record<string, string> {
    return env.CLARITY_API_TOKEN
      ? { Authorization: `Bearer ${env.CLARITY_API_TOKEN}` }
      : {};
  }

  /**
   * The Clarity API returns different fields per metric type. We pick a
   * primary value using known field names and store the rest as dimensions.
   */
  private normalizeResponse(data: ClarityResponse): NormalizedMetric[] {
    const primaryValueKeys = [
      "sessionsWithMetricPercentage",
      "averageScrollDepth",
      "averageEngagementTime",
      "visitsCount",
      "sessionsCount",
      "subTotal",
    ];

    return data.flatMap((group) =>
      group.information.map((info) => {
        let primaryValue: string | number = "";
        let primaryKey = "";
        for (const key of primaryValueKeys) {
          if (key in info && info[key] != null) {
            primaryValue = info[key] as string | number;
            primaryKey = key;
            break;
          }
        }

        const dimensions: Record<string, string> = {};
        for (const [key, val] of Object.entries(info)) {
          if (key !== primaryKey && val != null) {
            dimensions[key] = String(val);
          }
        }

        return {
          name: group.metricName,
          value: primaryValue,
          dimensions,
        };
      }),
    );
  }
}
