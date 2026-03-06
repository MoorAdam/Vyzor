import { env } from "@/lib/config/env";
import { endpoints } from "@/lib/config/endpoints";
import type { CSTokenResponse } from "./types";

export class ContentSquareAuthManager {
  private token: string | null = null;
  private expiresAt: number = 0;
  private cachedEndpoint: string = "";

  get currentToken(): string {
    if (!this.token) throw new Error("Not authenticated");
    return this.token;
  }

  get endpoint(): string {
    return this.cachedEndpoint;
  }

  isExpired(): boolean {
    return Date.now() >= this.expiresAt - 60_000;
  }

  async getToken(): Promise<CSTokenResponse> {
    if (this.token && !this.isExpired()) {
      return {
        access_token: this.token,
        token_type: "bearer",
        expires_in: Math.floor((this.expiresAt - Date.now()) / 1000),
        scope: "data-export metrics",
        project_id: 0,
        endpoint: this.cachedEndpoint,
      };
    }

    const clientId = env.CONTENTSQUARE_CLIENT_ID;
    const clientSecret = env.CONTENTSQUARE_CLIENT_SECRET;

    if (!clientId || !clientSecret) {
      throw new Error(
        "Missing CONTENTSQUARE_CLIENT_ID or CONTENTSQUARE_CLIENT_SECRET",
      );
    }

    const response = await fetch(
      endpoints.contentsquare.tokenUrl,
      {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({
          client_id: clientId,
          client_secret: clientSecret,
          grant_type: "client_credentials",
        }),
      },
    );

    if (!response.ok) {
      const text = await response.text();
      throw new Error(
        `ContentSquare OAuth failed: ${response.status} ${text}`,
      );
    }

    const data: CSTokenResponse = await response.json();
    this.token = data.access_token;
    this.expiresAt = Date.now() + data.expires_in * 1000;
    this.cachedEndpoint = data.endpoint;

    return data;
  }
}
