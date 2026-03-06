/**
 * Centralized API endpoint configuration.
 *
 * Edit this file to change any external API base URLs used throughout the app.
 * All provider implementations and demo pages import from here so you only
 * need to update URLs in one place.
 */

export const endpoints = {
  /** Microsoft Clarity – export-data API (server-side provider) */
  clarity: {
    /** Base URL for the Clarity export-data API */
    apiBase: "https://www.clarity.ms/export-data/api/v1/",
  },

  /** Microsoft Clarity – demo / direct API (client-side demo pages) */
  clarityDirect: {
    /** Base URL for the Clarity v1 project API (heatmaps, sessions, etc.) */
    apiBase: "https://www.clarity.ms/api/v1",
  },

  /** ContentSquare */
  contentsquare: {
    /** OAuth 2.0 token endpoint */
    tokenUrl: "https://api.contentsquare.com/v1/oauth/token",
  },

  /** Anthropic (Claude AI) */
  anthropic: {
    /** Messages API endpoint */
    messagesUrl: "https://api.anthropic.com/v1/messages",
  },
} as const;
