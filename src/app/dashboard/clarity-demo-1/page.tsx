"use client";

import { useState, useCallback } from "react";
import { endpoints } from "@/lib/config/endpoints";

const MOCK_HEATMAP = {
  page_url: "/landing-page",
  clicks: [
    { x: 310, y: 175, count: 312, element: "CTA gomb - Próbáld ki" },
    { x: 160, y: 400, count: 104, element: "Navigáció - Funkciók" },
    { x: 470, y: 610, count: 41, element: "Footer - Kapcsolat" },
    { x: 250, y: 290, count: 221, element: "Hero banner" },
    { x: 85, y: 85, count: 289, element: "Logo" },
  ],
  scroll_depth: { "25%": 89, "50%": 65, "75%": 38, "100%": 14 } as Record<string, number>,
};

type HeatmapData = typeof MOCK_HEATMAP;

const MOCK_SESSIONS = [
  { id: "c1", duration: 165, pages: 5, dead_clicks: 3, rage_clicks: 1, excessive_scrolling: false, device: "desktop", country: "HU", browser: "Chrome" },
  { id: "c2", duration: 42, pages: 1, dead_clicks: 0, rage_clicks: 0, excessive_scrolling: false, device: "mobile", country: "DE", browser: "Safari" },
  { id: "c3", duration: 287, pages: 8, dead_clicks: 1, rage_clicks: 0, excessive_scrolling: true, device: "desktop", country: "HU", browser: "Edge" },
  { id: "c4", duration: 19, pages: 1, dead_clicks: 7, rage_clicks: 4, excessive_scrolling: false, device: "mobile", country: "AT", browser: "Chrome" },
  { id: "c5", duration: 210, pages: 6, dead_clicks: 2, rage_clicks: 1, excessive_scrolling: true, device: "tablet", country: "HU", browser: "Firefox" },
];

type SessionData = (typeof MOCK_SESSIONS)[number];

const MOCK_INSIGHTS = [
  { type: "rage_click", message: "Magas rage click arány a mobil felhasználóknál", severity: "high" },
  { type: "dead_click", message: "Sok dead click a hero szekción", severity: "medium" },
  { type: "scroll", message: "A felhasználók 62%-a nem görget a fold alá", severity: "medium" },
];

type InsightData = (typeof MOCK_INSIGHTS)[number];

function StatCard({ label, value, sub, color = "blue" }: { label: string; value: string | number; sub?: string; color?: string }) {
  const colors: Record<string, string> = {
    blue: "from-blue-500 to-blue-700",
    rose: "from-rose-500 to-rose-700",
    emerald: "from-emerald-500 to-emerald-700",
    violet: "from-violet-500 to-violet-700",
  };
  return (
    <div className={`rounded-2xl bg-gradient-to-br ${colors[color]} p-4 text-white shadow-lg`}>
      <div className="text-xs font-semibold uppercase tracking-widest opacity-80 mb-1">{label}</div>
      <div className="text-3xl font-bold">{value}</div>
      {sub && <div className="text-xs mt-1 opacity-70">{sub}</div>}
    </div>
  );
}

function ScrollBar({ label, pct }: { label: string; pct: number }) {
  return (
    <div className="mb-2">
      <div className="flex justify-between text-xs text-gray-500 mb-1">
        <span>{label}</span><span>{pct}%</span>
      </div>
      <div className="h-2 bg-gray-100 rounded-full overflow-hidden">
        <div className="h-full bg-blue-400 rounded-full transition-all duration-700" style={{ width: `${pct}%` }} />
      </div>
    </div>
  );
}

function InsightBadge({ type, message, severity }: { type: string; message: string; severity: string }) {
  const icons: Record<string, string> = { rage_click: "😤", dead_click: "👻", scroll: "📜" };
  const colors: Record<string, string> = { high: "bg-red-50 border-red-200 text-red-700", medium: "bg-amber-50 border-amber-200 text-amber-700", low: "bg-green-50 border-green-200 text-green-700" };
  return (
    <div className={`flex items-start gap-2 border rounded-xl p-3 text-sm ${colors[severity]}`}>
      <span>{icons[type]}</span>
      <span>{message}</span>
    </div>
  );
}

export default function ClarityDemo1() {
  const [projectId, setProjectId] = useState("");
  const [apiToken, setApiToken] = useState("");
  const [connected, setConnected] = useState(false);
  const [useDemo, setUseDemo] = useState(false);
  const [loading, setLoading] = useState(false);
  const [aiLoading, setAiLoading] = useState(false);
  const [heatmap, setHeatmap] = useState<HeatmapData | null>(null);
  const [sessions, setSessions] = useState<SessionData[] | null>(null);
  const [insights, setInsights] = useState<InsightData[] | null>(null);
  const [aiReport, setAiReport] = useState("");
  const [error, setError] = useState("");
  const [lastUpdated, setLastUpdated] = useState<Date | null>(null);

  const fetchData = useCallback(async (demo = false) => {
    setLoading(true);
    setError("");
    try {
      if (demo) {
        await new Promise(r => setTimeout(r, 800));
        setHeatmap(MOCK_HEATMAP);
        setSessions(MOCK_SESSIONS);
        setInsights(MOCK_INSIGHTS);
      } else {
        const headers = {
          "Authorization": `Bearer ${apiToken}`,
          "Content-Type": "application/json",
        };
        const [hmRes, sessRes] = await Promise.all([
          fetch(`${endpoints.clarityDirect.apiBase}/${projectId}/heatmaps`, { headers }),
          fetch(`${endpoints.clarityDirect.apiBase}/${projectId}/sessions`, { headers }),
        ]);
        if (!hmRes.ok || !sessRes.ok) throw new Error("API hiba – ellenőrizd a Project ID-t és API tokent.");
        const hmData = await hmRes.json();
        const sessData = await sessRes.json();
        setHeatmap(hmData);
        setSessions(sessData?.sessions || []);
        setInsights(sessData?.insights || []);
      }
      setConnected(true);
      setLastUpdated(new Date());
    } catch (e) {
      setError(e instanceof Error ? e.message : String(e));
    }
    setLoading(false);
  }, [projectId, apiToken]);

  const generateReport = async () => {
    if (!heatmap || !sessions) return;
    setAiLoading(true);
    setAiReport("");
    try {
      const prompt = `Te egy UX elemző szakértő vagy, aki Microsoft Clarity adatokat értelmez. Az alábbi adatok alapján készíts részletes magyar nyelvű riportot:

HEATMAP ADATOK:
- Oldal: ${heatmap.page_url}
- Top kattintások: ${JSON.stringify(heatmap.clicks)}
- Görgetési mélység: ${JSON.stringify(heatmap.scroll_depth)}

SESSION ADATOK (${sessions.length} session):
${JSON.stringify(sessions)}

CLARITY INSIGHTS:
${JSON.stringify(insights)}

Kérlek elemezd:
1. Mi a legfontosabb UX probléma?
2. Dead click és rage click minták – mi okozhatja?
3. Hol veszítünk felhasználókat (scroll depth alapján)?
4. Mobil vs desktop különbségek?
5. Konkrét, prioritizált akciólista (top 3 javaslat).

Legyen tömör, actionable és adatvezérelt.`;

      const res = await fetch(endpoints.anthropic.messagesUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          model: "claude-sonnet-4-20250514",
          max_tokens: 1000,
          messages: [{ role: "user", content: prompt }],
        }),
      });
      const data = await res.json();
      const text = data.content?.map((b: { text?: string }) => b.text || "").join("\n") || "Nem sikerült riportot generálni.";
      setAiReport(text);
    } catch (e) {
      setAiReport("Hiba a riport generálása közben: " + (e instanceof Error ? e.message : String(e)));
    }
    setAiLoading(false);
  };

  const avgDuration = sessions ? Math.round(sessions.reduce((a, s) => a + s.duration, 0) / sessions.length) : 0;
  const totalRage = sessions ? sessions.reduce((a, s) => a + s.rage_clicks, 0) : 0;
  const totalDead = sessions ? sessions.reduce((a, s) => a + s.dead_clicks, 0) : 0;

  return (
    <div className="min-h-screen bg-gray-50 p-6 font-sans">
      <div className="max-w-4xl mx-auto">

        {/* Header */}
        <div className="flex items-center justify-between mb-6">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">🔵 Clarity AI Dashboard</h1>
            <p className="text-sm text-gray-500">Microsoft Clarity elemzés Claude AI-val</p>
          </div>
          {connected && lastUpdated && (
            <div className="text-xs text-gray-400 text-right">
              <div>Utolsó frissítés:</div>
              <div className="font-medium">{lastUpdated.toLocaleTimeString("hu-HU")}</div>
            </div>
          )}
        </div>

        {/* Connection Panel */}
        {!connected && (
          <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
            <h2 className="font-semibold text-gray-700 mb-4">🔌 Kapcsolódás</h2>
            <div className="grid grid-cols-2 gap-3 mb-4">
              <div>
                <label className="text-xs text-gray-500 mb-1 block">Project ID</label>
                <input
                  type="text"
                  placeholder="pl. abc123xyz"
                  value={projectId}
                  onChange={e => setProjectId(e.target.value)}
                  className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300"
                />
              </div>
              <div>
                <label className="text-xs text-gray-500 mb-1 block">API Token</label>
                <input
                  type="password"
                  placeholder="Bearer token..."
                  value={apiToken}
                  onChange={e => setApiToken(e.target.value)}
                  className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300"
                />
              </div>
            </div>
            {error && <div className="text-red-500 text-sm mb-3">⚠️ {error}</div>}
            <div className="flex gap-3">
              <button
                onClick={() => fetchData(false)}
                disabled={!projectId || !apiToken || loading}
                className="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-40 transition"
              >
                {loading ? "Betöltés..." : "Kapcsolódás"}
              </button>
              <button
                onClick={() => { setUseDemo(true); fetchData(true); }}
                disabled={loading}
                className="border border-gray-300 text-gray-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 transition"
              >
                🎭 Demo mód
              </button>
            </div>
            <p className="text-xs text-gray-400 mt-3">
              {"API tokent itt találod: "}
              <span className="font-medium">{"clarity.microsoft.com → Settings → API"}</span>
            </p>
          </div>
        )}

        {/* Dashboard */}
        {connected && heatmap && sessions && (
          <>
            {/* Stats */}
            <div className="grid grid-cols-2 gap-3 mb-4 sm:grid-cols-4">
              <StatCard label="Sessions" value={sessions.length} sub="összesen" color="blue" />
              <StatCard label="Átl. időtartam" value={`${avgDuration}s`} sub="per session" color="emerald" />
              <StatCard label="Rage kattintás" value={totalRage} sub="összes" color="rose" />
              <StatCard label="Dead click" value={totalDead} sub="összes" color="violet" />
            </div>

            {/* Insights */}
            {insights && insights.length > 0 && (
              <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-4">
                <h3 className="font-semibold text-gray-700 mb-3">💡 Clarity Insights</h3>
                <div className="flex flex-col gap-2">
                  {insights.map((ins, i) => (
                    <InsightBadge key={i} {...ins} />
                  ))}
                </div>
              </div>
            )}

            {/* Heatmap + Sessions */}
            <div className="grid grid-cols-2 gap-4 mb-4">
              <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <h3 className="font-semibold text-gray-700 mb-3">📊 Görgetési mélység</h3>
                {Object.entries(heatmap.scroll_depth).map(([k, v]) => (
                  <ScrollBar key={k} label={k} pct={v} />
                ))}
              </div>
              <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <h3 className="font-semibold text-gray-700 mb-3">🖱️ Top kattintások</h3>
                {[...heatmap.clicks].sort((a, b) => b.count - a.count).map((c, i) => (
                  <div key={i} className="flex justify-between items-center py-1 border-b border-gray-50 last:border-0">
                    <span className="text-xs text-gray-600 truncate max-w-[160px]">{c.element}</span>
                    <span className="text-xs font-bold text-blue-600 ml-2">{c.count}x</span>
                  </div>
                ))}
              </div>
            </div>

            {/* Sessions table */}
            <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-4">
              <div className="flex justify-between items-center mb-3">
                <h3 className="font-semibold text-gray-700">📹 Session Recordings</h3>
                <button
                  onClick={() => fetchData(useDemo)}
                  className="text-xs text-blue-500 hover:text-blue-700 font-medium"
                >
                  🔄 Frissítés
                </button>
              </div>
              <div className="overflow-x-auto">
                <table className="w-full text-xs">
                  <thead>
                    <tr className="text-gray-400 border-b">
                      <th className="text-left py-2">ID</th>
                      <th className="text-left py-2">Időtartam</th>
                      <th className="text-left py-2">Oldalak</th>
                      <th className="text-left py-2">Dead</th>
                      <th className="text-left py-2">Rage</th>
                      <th className="text-left py-2">Scroll</th>
                      <th className="text-left py-2">Eszköz</th>
                      <th className="text-left py-2">Browser</th>
                    </tr>
                  </thead>
                  <tbody>
                    {sessions.map(s => (
                      <tr key={s.id} className="border-b border-gray-50 hover:bg-gray-50">
                        <td className="py-2 font-mono text-gray-400">{s.id}</td>
                        <td className="py-2">{s.duration}s</td>
                        <td className="py-2">{s.pages}</td>
                        <td className="py-2">
                          <span className={`px-1.5 py-0.5 rounded font-medium ${s.dead_clicks > 3 ? "bg-violet-100 text-violet-600" : "text-gray-500"}`}>
                            {s.dead_clicks}
                          </span>
                        </td>
                        <td className="py-2">
                          <span className={`px-1.5 py-0.5 rounded font-medium ${s.rage_clicks > 2 ? "bg-red-100 text-red-600" : "text-gray-500"}`}>
                            {s.rage_clicks}
                          </span>
                        </td>
                        <td className="py-2">{s.excessive_scrolling ? "⚠️" : "–"}</td>
                        <td className="py-2 capitalize">{s.device}</td>
                        <td className="py-2">{s.browser}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>

            {/* AI Report */}
            <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
              <div className="flex justify-between items-center mb-3">
                <h3 className="font-semibold text-gray-700">🤖 AI Elemzés {"&"} Riport</h3>
                <button
                  onClick={generateReport}
                  disabled={aiLoading}
                  className="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-40 transition"
                >
                  {aiLoading ? "Elemzés folyamatban..." : "Riport generálása"}
                </button>
              </div>
              {aiLoading && (
                <div className="flex items-center gap-2 text-sm text-gray-400 py-4">
                  <div className="w-4 h-4 border-2 border-blue-300 border-t-blue-600 rounded-full animate-spin" />
                  Claude elemzi az adatokat...
                </div>
              )}
              {aiReport && (
                <div className="bg-gray-50 rounded-xl p-4 text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">
                  {aiReport}
                </div>
              )}
              {!aiReport && !aiLoading && (
                <p className="text-sm text-gray-400">Kattints a gombra, hogy Claude AI elemezze az adatokat és javaslatokat adjon.</p>
              )}
            </div>

            <div className="mt-3 text-center">
              <button
                onClick={() => { setConnected(false); setHeatmap(null); setSessions(null); setInsights(null); setAiReport(""); }}
                className="text-xs text-gray-400 hover:text-gray-600"
              >
                Kijelentkezés / Újrakapcsolódás
              </button>
            </div>
          </>
        )}
      </div>
    </div>
  );
}
