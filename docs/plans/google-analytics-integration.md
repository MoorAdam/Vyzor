# Google Analytics 4 integráció — Vyzor

## Context

A Vyzor egy webfejlesztői segédeszköz, amely **nyers adatokat jelenít meg különböző nézőpontokból**, és ezekből **egyszerűsített, céltudatos információkat** ad ki — mind UI felületeken keresztül, mind AI-generált riportokban. A nyers adat-megjelenítés és a gyors „rátekintés különböző szempontok szerint" a termék **alaptermékélménye**; az AI riport egy plusz réteg ezen felül.

Jelenleg egyetlen analitikai forrás van bekötve (Microsoft Clarity), ami napi 10-szeres fetch limitre korlátozott, batch-orientált ("napi snapshot DB-be") modell. A Google Analytics 4 (GA Data API) ennél lényegesen nyitottabb: magas kvóták (50k req/nap projektenként), valós idejű API (utolsó 30 perc), gazdag dimenzió × metrika kombinációk.

**Cél**: olyan adatréteget biztosítani, amely
1. **bármikor**, on-demand kiszolgálja az aktuális UI felületeket (gyors rátekintés különböző szempontokból),
2. ugyanezeken a metódusokon át kiszolgálja az AI riport-generátort és tool-calling agenteket,
3. nem támaszkodik napi DB snapshotra — okos cache-eléssel friss marad.

Az első iteráció a **data layer + AI integráció (a meglévő riport pipeline-ba kötve)**. A nyers adatkijelző Livewire felületek (Clarity-szerű dashboard oldalak) **látható és tervezett következő fázis** — a query service ezért már most úgy készül, hogy közvetlenül kiszolgálja őket: tipusos DTO-k, paginálható/sortolható eredmények, UI-barát aggregáció.

A felhasználói döntések alapján:
- **Hitelesítés**: service account (per-property) — egy szerver-oldali JSON kulcs, amihez a felhasználó hozzáadja a Vyzor service email-jét Viewer jogkörrel a saját GA property-jén.
- **Tárolás**: nincs új analitikai DB tábla — `Cache::remember()` réteges TTL-lel.
- **Realtime**: igen, az első körben.

A megoldás **nem a Clarity tükörképe**. A Clarity-val ellentétben itt nincs `*_insights` tábla, nincs napi fetch parancs, nincs daily quota counter. A modul középpontjában egy **tipusos query service** áll, amelyet UI komponensek (Livewire pages, kártyák), AI agentek (function calling), és Artisan parancsok egyaránt ugyanazon a felületen hívhatnak.

---

## Architektúra áttekintés

```
┌─────────────────────────────────────────────────────────────┐
│  Consumers (mindegyik ugyanazt a query service API-t hívja) │
│  - Livewire pages / Blade view komponensek (a TERMÉK fő     │
│    felülete — adatkijelzés különböző szempontokból)         │
│  - ReportGeneratorService (AI riport — plusz réteg)         │
│  - AI Agent tools (function calling — opt-in)               │
│  - Artisan commands (debug, manuális query)                 │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│  GoogleAnalyticsQueryService (domain-szintű)                │
│  - getTrafficOverview()    - getAcquisitionBreakdown()      │
│  - getTopPages()           - getDeviceBreakdown()           │
│  - getEvents()             - getGeoBreakdown()              │
│  - getLandingPages()       - comparePeriod()                │
│  - getRealtimeUsers()      - getRealtimeEvents()            │
└────────────────┬────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────┐
│  GoogleAnalyticsCache  ← Laravel Cache facade               │
│  - tiered TTL (today/yesterday/recent/historical/realtime)  │
│  - cache key = hash(property_id + query_signature)          │
└────────────────┬────────────────────────────────────────────┘
                 │ miss
                 ▼
┌─────────────────────────────────────────────────────────────┐
│  GoogleAnalyticsClient (low-level wrapper)                  │
│  - runReport()      - runRealtimeReport()                   │
│  - batchRunReports() (jövőbeli optimalizáció)               │
└────────────────┬────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────┐
│  google/analytics-data PHP SDK                              │
│  Service Account credentials (singleton-ben cache-elve)     │
└─────────────────────────────────────────────────────────────┘
```

---

## Modul struktúra

```
app/Modules/Analytics/GoogleAnalytics/
├── GoogleAnalyticsServiceProvider.php
├── Auth/
│   └── ServiceAccountClientFactory.php
├── Services/
│   ├── GoogleAnalyticsClient.php
│   ├── GoogleAnalyticsQueryService.php
│   └── GoogleAnalyticsCache.php
├── Queries/
│   ├── ReportRequest.php           ← DTO: dimensions, metrics, dateRange, filters, orderBy, limit
│   ├── DateRange.php               ← DTO + named constructors (today, yesterday, last7Days, custom)
│   └── RealtimeRequest.php
├── DTOs/
│   ├── ReportResult.php            ← header + sorok kollekciója
│   ├── MetricRow.php
│   ├── PeriodComparison.php
│   └── RealtimeSnapshot.php
├── Enums/
│   ├── GaMetric.php                ← typed metric konstansok (sessions, engagedSessions, ...)
│   ├── GaDimension.php             ← typed dimension konstansok
│   └── GaCacheTier.php             ← TODAY, YESTERDAY, RECENT, HISTORICAL, REALTIME
├── Exceptions/
│   ├── GoogleAnalyticsException.php
│   ├── PropertyNotConfiguredException.php
│   └── ServiceAccountNotConfiguredException.php
├── Tools/                          ← AI agent function calling tool-ok
│   └── GaInsightsTool.php
└── Commands/
    ├── TestGoogleAnalyticsConnection.php  ← `app:ga:test {project}`
    └── DescribeProperty.php               ← `app:ga:describe {project}` — debug
```

---

## Konfiguráció

### `config/services.php`
Új blokk a `clarity` mellé:

```php
'google_analytics' => [
    // Service account JSON path VAGY raw JSON (env-ben tárolható)
    'service_account_path' => env('GA_SERVICE_ACCOUNT_PATH', storage_path('app/ga-service-account.json')),
    'service_account_json' => env('GA_SERVICE_ACCOUNT_JSON'),

    'cache' => [
        'today_ttl'       => env('GA_CACHE_TODAY_TTL', 60 * 15),        // 15 min
        'yesterday_ttl'   => env('GA_CACHE_YESTERDAY_TTL', 60 * 60 * 2), // 2 h
        'recent_ttl'      => env('GA_CACHE_RECENT_TTL', 60 * 60 * 12),   // 12 h (utóbbi 7 nap)
        'historical_ttl'  => env('GA_CACHE_HISTORICAL_TTL', 60 * 60 * 24 * 7), // 7 nap (régi adat ritkán változik)
        'realtime_ttl'    => env('GA_CACHE_REALTIME_TTL', 30),           // 30 s
    ],

    'limits' => [
        'max_rows_per_query' => 10_000,
        'default_top_n'      => 50,
    ],
],
```

### `.env.example` kiegészítés
```
GA_SERVICE_ACCOUNT_PATH=storage/app/ga-service-account.json
# vagy:
# GA_SERVICE_ACCOUNT_JSON='{"type":"service_account",...}'
```

### Composer dependency
```
composer require google/analytics-data
```
A `google/analytics-data` csomag könnyebb mint a teljes `google/apiclient` — csak a Data API client-jét hozza.

---

## Adatmodell változások

**Egyetlen migráció** — nincs új tábla, csak két oszlop a `projects` táblán:

```php
// database/migrations/2026_05_05_xxxxxx_add_google_analytics_to_projects.php
Schema::table('projects', function (Blueprint $table) {
    $table->string('ga_property_id')->nullable()->after('clarity_api_key');
    $table->timestamp('ga_last_verified_at')->nullable()->after('ga_property_id');
});
```

A `Project` modellbe:
```php
protected $fillable = [..., 'ga_property_id', 'ga_last_verified_at'];

protected function casts(): array {
    return [
        'clarity_api_key'      => 'encrypted',
        'ga_property_id'       => 'encrypted', // nem szigorúan titok, de konzisztens a clarity-vel
        'ga_last_verified_at'  => 'datetime',
    ];
}

public function hasGoogleAnalytics(): bool {
    return filled($this->ga_property_id);
}
```

**Megjegyzés**: a service account hitelesítő adatait *nem* tároljuk a DB-ben — szerver-oldali fájl/env, így minden projekthez ugyanaz. A property ID-t a felhasználó adja meg projekt szinten.

---

## Public API: a query service

A `GoogleAnalyticsQueryService` a modul **fő publikus felülete**. Minden metódus tipusos DTO-t ad vissza, automatikusan cache-eli az eredményt, és kötelezően kap egy `Project`-et.

```php
final class GoogleAnalyticsQueryService
{
    // ── Standard Data API ────────────────────────────────────────────

    public function getTrafficOverview(Project $p, DateRange $r): TrafficOverview;
    // → sessions, totalUsers, newUsers, engagedSessions, bounceRate, avgEngagementTime

    public function getTopPages(Project $p, DateRange $r, int $limit = 50): ReportResult;
    // → pagePath × screenPageViews, engagementRate, avgEngagementTime

    public function getLandingPages(Project $p, DateRange $r, int $limit = 50): ReportResult;
    // → landingPage × sessions, bounceRate, conversions

    public function getAcquisitionBreakdown(Project $p, DateRange $r): ReportResult;
    // → sessionDefaultChannelGroup × sessions, engagedSessions, conversions

    public function getDeviceBreakdown(Project $p, DateRange $r): ReportResult;
    // → deviceCategory × sessions, bounceRate, engagementRate

    public function getGeoBreakdown(Project $p, DateRange $r, int $limit = 25): ReportResult;
    // → country × sessions, engagedSessions

    public function getEvents(Project $p, DateRange $r, ?string $eventName = null, int $limit = 50): ReportResult;
    // → eventName × eventCount, eventValue (vagy konkrét esemény bontása)

    public function getDailyTimeline(Project $p, DateRange $r, array $metrics): ReportResult;
    // → date × választott metrikák (trend chartokhoz)

    public function comparePeriod(Project $p, DateRange $current, DateRange $previous, array $metrics): PeriodComparison;
    // → current + previous + delta% metrikánként

    // ── Realtime API ────────────────────────────────────────────────

    public function getRealtimeUsers(Project $p): RealtimeSnapshot;
    // → activeUsers, breakdown by country/device/page

    public function getRealtimeEvents(Project $p, int $limit = 20): ReportResult;
    // → most aktív események az utolsó 30 percben

    // ── Escape hatch ─────────────────────────────────────────────────

    public function runCustomReport(Project $p, ReportRequest $req): ReportResult;
    // direkt hozzáférés tetszőleges dimension/metric kombinációhoz
}
```

### Miért ezek a metódusok?

A query lista **két típusú fogyasztót szolgál ki egyszerre, ugyanazon az API-n**:

**(1) UI felületek** — gyors rátekintés különböző szempontok szerint (ez a Vyzor termék alapélménye, a következő iterációban Livewire dashboardokra kerül):
- *Forgalmi áttekintés kártya* → `getTrafficOverview`
- *Top oldalak táblázata* → `getTopPages` (paginálható, sortolható)
- *Csatornabontás (donut chart)* → `getAcquisitionBreakdown`
- *Eszközbontás (donut + táblázat)* → `getDeviceBreakdown`
- *Térkép / országlista* → `getGeoBreakdown`
- *Trend grafikon* → `getDailyTimeline`
- *Élő counter widget* → `getRealtimeUsers`
- *Időszak-összehasonlítás (heti/havi delta kártya)* → `comparePeriod`

**(2) AI riport / agent kérdések** — fejlesztői insight-igények:
- *"Hol veszítünk forgalmat az utolsó deploy óta?"* → `comparePeriod` + `getTopPages`
- *"Melyik csatorna konvertál a legjobban?"* → `getAcquisitionBreakdown`
- *"Mi történik most a release után?"* → `getRealtimeUsers` + `getRealtimeEvents`
- *"Mobilon miért rosszabb a UX?"* → `getDeviceBreakdown`

A `runCustomReport` escape hatch-ként marad mindkét fogyasztótípusnak (egyedi UI widget, vagy AI agent saját bontása).

### UI-barát design jegyek a DTO-kban (a következő iterációhoz előkészítve)

A `ReportResult` DTO az első iterációban már úgy készül, hogy közvetlenül beilleszthető legyen Livewire/Blade view-ba:
- **Kollekció jellegű API**: `->rows()` Laravel `Collection`-t ad (`->take(10)`, `->sortByDesc()`, `->map()` természetesen működik).
- **Header metadata**: `->totals()`, `->columnDefinitions()` (név, típus, formattolási hint — pl. `percentage`, `duration_seconds`, `count`) — a UI komponens automatikusan tudja formatálni.
- **Pagination ready**: a query metódusok `int $offset = 0, int $limit = 50` paramétert kapnak (a GA Data API supports `offset` + `limit`), így egy Livewire táblázat tudja lapozni.
- **Stale-while-revalidate hint**: a DTO tartalmaz egy `fetchedAt` időbélyeget, így a UI vissza tudja jelezni, hogy az adat mennyire friss (pl. "frissítve 4 perce — automatikus frissítés 11 perc múlva").

---

## Cache stratégia

A `GoogleAnalyticsCache` a TTL-t a query *időablaka* alapján választja:

| Query időablak | Tier | TTL | Indoklás |
|---|---|---|---|
| Tartalmazza a mai napot | `TODAY` | 15 min | Élő adat, gyakran változik |
| Csak tegnap | `YESTERDAY` | 2 h | Még finomodik, de már stabil |
| Utóbbi 2–7 nap | `RECENT` | 12 h | Stabilizálódott |
| 7+ nap régi | `HISTORICAL` | 7 nap | Lényegében immutable |
| Realtime | `REALTIME` | 30 s | Élő, de túl gyakori hívás drága |

**Cache kulcs**: `ga:{property_id}:{sha1(serialize(request))}`. A `Cache::remember()` Laravel facade-on át, az alapértelmezett DB cache driver elég kezdetnek (Redis ajánlott, ha forgalmas lesz).

**Cache invalidálás**: a felhasználó manuálisan tudja triggerelni egy `forceRefresh: true` paraméterrel (külön Livewire/AI tool gomb). Egyébként TTL alapján.

---

## Authentikáció (service account)

### `ServiceAccountClientFactory`
Singleton-ként ad vissza egy `BetaAnalyticsDataClient` példányt:

```php
public function make(): BetaAnalyticsDataClient
{
    return once(function () {
        $credentials = $this->resolveCredentials();
        return new BetaAnalyticsDataClient(['credentials' => $credentials]);
    });
}

private function resolveCredentials(): array|string
{
    if ($json = config('services.google_analytics.service_account_json')) {
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }
    $path = config('services.google_analytics.service_account_path');
    if (!is_readable($path)) {
        throw new ServiceAccountNotConfiguredException(...);
    }
    return $path;
}
```

### Onboarding flow (manuális, tisztán dokumentációs az 1. iterációban)
1. Admin létrehoz egy GCP projektet és service account-ot (egyszer, a Vyzor szerveren).
2. A service account JSON kulcs a szerverre kerül, env-ben tároljuk.
3. A felhasználó a saját GA admin felületén a service account email-jét hozzáadja `Viewer` jogkörrel a property-hez.
4. A felhasználó a Vyzor projekt beállításaiban beírja a GA property ID-t (`properties/123456789`).
5. `app:ga:test {project}` parancs ellenőrzi a kapcsolatot, frissíti `ga_last_verified_at`-et.

Egy későbbi UI iterációban ez a flow Livewire form-mal lesz, és lesz "Test connection" gomb.

---

## AI integráció — plusz réteg az adatrétegen

> **Kontextus**: az AI riport nem a fő fogyasztó — egy plusz réteg, amely ugyanazt a query service API-t hívja, amit a UI is hívni fog. Az "anytime access" ezt jelenti: nem napi snapshotból dolgozunk, hanem on-demand lekérdezésből, amit a UI és az AI is szabadon használhat.

### Két integráció két szinten:

**A) Statikus prompt augmentáció** (Clarity-szerű, default mód)
A `ReportGeneratorService::buildPrompt()` az aktuális Clarity-blokk mellé új `## Google Analytics Data` szekciót kap. A `Report` modellben legyen egy `include_ga` flag (vagy a kiválasztott `aspect_*` mezőkből származtatva). A buildPrompt:

```php
if ($report->include_ga && $report->project->hasGoogleAnalytics()) {
    $ga = app(GoogleAnalyticsQueryService::class);
    $range = DateRange::between($report->aspect_date_from, $report->aspect_date_to);
    $parts[] = $this->renderGaContext($report->project, $ga, $range);
}
```

A `renderGaContext()` tipikus snapshot-ot hív (overview + topPages + acquisitionBreakdown + deviceBreakdown), és Markdown/JSON formátumban a promptba illeszti. Ez a **gyors** út: működik bármelyik AI provider-rel, function calling nélkül.

**B) Dynamic tool calling** (a "bármikor hozzáfér" igazi értelme)
A meglévő `ReportAnalyst` (`app/Modules/Ai/Agents/`) Prism / OpenAI tool-okat tud regisztrálni. Új tool: `GaInsightsTool`, amely egy szelektív interface-t ad az AI-nak:

```php
final class GaInsightsTool
{
    public function getTrafficOverview(string $from, string $to): array;
    public function getTopPages(string $from, string $to, int $limit = 20): array;
    public function comparePeriod(string $currentFrom, string $currentTo, string $previousFrom, string $previousTo): array;
    public function getRealtimeUsers(): array;
    public function runCustomReport(array $dimensions, array $metrics, string $from, string $to): array;
}
```

Ezek a metódusok az aktuális kontextusban élő `Project`-re zárnak (a tool factory bekapja). Az AI agent — amikor egy riportot ír — saját maga dönti el, hogy mely lekérdezésekre van szüksége, és hív (cache-el a service mögötte). Ez ad **valódi "anytime access"-t**: az AI nem előre kapott statikus snapshotból dolgozik, hanem menet közben mélyíthet egy adott területen.

A két mód egymás mellett él: az `A` az alap, a `B` opt-in (Report flag, vagy default ha az agent supports function calling).

### Új AI Context tag
A `ContextTag` enumban (`app/Modules/Ai/Contexts/Enums/ContextTag.php`):
```php
case GA = 'ga';
case GA_REALTIME = 'ga-realtime';
```

Új preset példák a DB seeder-be (`AiContext` rekordok):
- `ga-traffic-analyst` — preset, általános forgalmi elemzés
- `ga-conversion-funnel` — preset, konverziós szűk keresztmetszetek
- `ga-deploy-impact` — preset, "az utolsó deploy után mi történt"

---

## Permissions

Új tagok a `PermissionEnum`-ban (`app/Modules/Users/Enums/PermissionEnum.php`):

```php
case VIEW_GOOGLE_ANALYTICS    = 'project.ga.view';
case CONFIGURE_GOOGLE_ANALYTICS = 'project.ga.configure';
case USE_GOOGLE_ANALYTICS_IN_REPORTS = 'project.ga.use-in-reports';
```

A query service minden public metódusa kapuzhat egy `abort_unless` ellenőrzést a hívó kontextusban, mint a Clarity-nél.

---

## Konkrétan érintett fájlok (újak / módosítandók)

### Új fájlok
- `app/Modules/Analytics/GoogleAnalytics/GoogleAnalyticsServiceProvider.php`
- `app/Modules/Analytics/GoogleAnalytics/Auth/ServiceAccountClientFactory.php`
- `app/Modules/Analytics/GoogleAnalytics/Services/GoogleAnalyticsClient.php`
- `app/Modules/Analytics/GoogleAnalytics/Services/GoogleAnalyticsQueryService.php`
- `app/Modules/Analytics/GoogleAnalytics/Services/GoogleAnalyticsCache.php`
- `app/Modules/Analytics/GoogleAnalytics/Queries/{ReportRequest,DateRange,RealtimeRequest}.php`
- `app/Modules/Analytics/GoogleAnalytics/DTOs/{ReportResult,MetricRow,TrafficOverview,PeriodComparison,RealtimeSnapshot}.php`
- `app/Modules/Analytics/GoogleAnalytics/Enums/{GaMetric,GaDimension,GaCacheTier}.php`
- `app/Modules/Analytics/GoogleAnalytics/Exceptions/{GoogleAnalyticsException,PropertyNotConfiguredException,ServiceAccountNotConfiguredException}.php`
- `app/Modules/Analytics/GoogleAnalytics/Tools/GaInsightsTool.php`
- `app/Modules/Analytics/GoogleAnalytics/Commands/{TestGoogleAnalyticsConnection,DescribeProperty}.php`
- `database/migrations/2026_05_05_xxxxxx_add_google_analytics_to_projects.php`
- `tests/Feature/GoogleAnalytics/QueryServiceTest.php`
- `tests/Unit/GoogleAnalytics/CacheTierResolverTest.php`

### Módosítandó fájlok
- `bootstrap/providers.php` → regisztráld a `GoogleAnalyticsServiceProvider`-t
- `config/services.php` → új `google_analytics` blokk
- `.env.example` → service account env-ek
- `composer.json` → `google/analytics-data` dependency
- `app/Modules/Projects/Models/Project.php` → `ga_property_id`, `ga_last_verified_at`, `hasGoogleAnalytics()`
- `app/Modules/Reports/Models/Report.php` → új `include_ga` boolean (migráció a `reports` táblán)
- `app/Modules/Reports/Services/ReportGeneratorService.php` → `buildPrompt()` GA blokkot is fűz hozzá; `renderGaContext()` helper
- `app/Modules/Ai/Contexts/Enums/ContextTag.php` → `GA`, `GA_REALTIME` esetek
- `app/Modules/Ai/Agents/ReportAnalyst.php` → opcionálisan registrálja a `GaInsightsTool`-t function calling-hez
- `app/Modules/Users/Enums/PermissionEnum.php` → új permission case-ek
- `database/seeders/AiContextSeeder.php` (vagy ennek megfelelője) → új preset-ek
- `docs/dev/project-structure.md` → modul leírása
- `docs/plans/google-analytics-integration.md` → ennek a tervnek a végleges helye

### Újrahasznált meglévő pattern-ek
- **Encrypted projekt kolumnák**: `app/Modules/Projects/Models/Project.php:29` (`'clarity_api_key' => 'encrypted'`) — pont ugyanígy a `ga_property_id`-vel.
- **`Project::current()` session pattern** (`app/Modules/Projects/Models/Project.php`) — a query service-ek és tool-ok a hívási kontextusból veszik.
- **Service provider regisztráció minta**: `app/Modules/Analytics/AnalyticsServiceProvider.php` (csak commands-et regisztrál, ezt másold).
- **AI Context rendszer**: `app/Modules/Ai/Contexts/Models/AiContext.php` és `ContextTag` enum — preset-ek és instruction-ek loadingjához.
- **Permission ellenőrzés minta**: `abort_unless(auth()->user()->can('permission', [PermissionEnum::X, Project::current()]), 403)`.
- **HTTP fake teszteléshez**: a Clarity tesztek (ha vannak) mintáját, vagy `Http::fake()` Laravel-standard mintát használj.

---

## Implementációs sorrend (ajánlott)

1. **Fundamentum** — composer require, service provider, config, migráció, Project modell update.
2. **Auth + low-level client** — `ServiceAccountClientFactory`, `GoogleAnalyticsClient` (csak `runReport` + `runRealtimeReport` proxy), `app:ga:test` parancs. Itt validálható, hogy a service account felállás működik egy real GA property-vel.
3. **Cache + DTO-k + enums** — `GoogleAnalyticsCache`, `ReportRequest`, `ReportResult`, `GaMetric`, `GaDimension`, `GaCacheTier`.
4. **Query service core** — `getTrafficOverview`, `getTopPages`, `getAcquisitionBreakdown`, `getDeviceBreakdown`, `runCustomReport`. (Az alap kompozitokat fedezi.)
5. **Query service kiegészítés** — geo, events, daily timeline, comparePeriod.
6. **Realtime** — `getRealtimeUsers`, `getRealtimeEvents`.
7. **Permissions + Project flow** — új permission enum-ok, `Project::hasGoogleAnalytics()`.
8. **AI integráció A (statikus)** — `ReportGeneratorService::renderGaContext()`, `Report.include_ga` flag, új AI Context preset-ek.
9. **AI integráció B (function calling)** — `GaInsightsTool`, regisztráció a `ReportAnalyst`-ben.
10. **Tesztek** — unit (cache tier resolver, DateRange), feature (query service `Http::fake` / mockolt SDK kliens).
11. **Dokumentáció** — `docs/dev/project-structure.md` frissítése, ennek a tervnek átmásolása `docs/plans/`-ba.

### Mit hagyunk explicit a következő iterációra (data display UI)

A jelen iteráció *nem* tartalmazza, de a query service már most kiszolgálja:
- **Livewire oldal-komponensek** (`pages::ga-overview`, `pages::ga-pages`, `pages::ga-realtime` stb.) reaktív adatkijelzéshez, a meglévő ⚡-prefixű Clarity oldalak mintájára (`resources/views/pages/⚡clarity-snapshot.blade.php`).
- **Sheaf UI alapú részkomponensek** a meglévő `resources/views/components/ui/*` primitívek (card, button, badge, table, separator stb.) felhasználásával — pl. egy traffic kártya `<x-ui.card>`-be ágyazva, donut chart, top-pages táblázat. **Plain Blade view komponens (csak `<x-ga-...>`) ne készüljön** — Livewire-be (ha reaktivitás kell) vagy Sheaf UI primitívekből (ha statikus kompozíció elég).
- Felhasználói property-onboarding flow Livewire form a property ID megadására + "Test connection" gomb (a jelen iterációban kézi/Artisan).
- Dashboard widget rendszer (a `app/Modules/Dashboard/Contracts/Widget.php` fájl, amit a tervezéskor megnyitva tartottál, valószínűleg ide tartozik — ennek tervezését külön feladatként érdemes kezelni, hogy a Clarity és a GA modul is egységes widget-felületet kapjon).

---

## Verification (end-to-end teszt)

### 1. Service account felállás
- Létrehozni egy teszt GCP service account-ot, JSON-t a `storage/app/`-ba.
- A teszt GA property-hez hozzáadni a service account email-jét `Viewer` jogkörrel.
- Egy projektre beállítani a property ID-t (`tinker`-ben: `Project::find(1)->update(['ga_property_id' => 'properties/...'])`).
- `php artisan app:ga:test 1` → **elvárt**: `Connected. Property: ..., last 7 days sessions: NNN`.

### 2. Query service unit / feature tesztek
```bash
php artisan test --filter=GoogleAnalytics
```
- `CacheTierResolverTest`: minden időablakra a megfelelő tier-t adja vissza (today/yesterday/recent/historical).
- `QueryServiceTest`: mockolt `BetaAnalyticsDataClient`-tel ellenőrzi, hogy a `getTopPages` jó dimensions/metrics-szel hívja az SDK-t, és a DTO helyesen van hidratálva.
- Cache hit teszt: kétszer hívva ugyanazt a metódust, az SDK csak egyszer hívódik.

### 3. AI riport integráció
- `tinker`-ben: `Report::create([..., 'project_id' => 1, 'include_ga' => true, ...])` majd `app(ReportGeneratorService::class)->generate($report)`.
- **Elvárt**: a `Report.content`-ben hivatkozás van GA-specifikus adatokra (top page-ek, csatornák) — nemcsak Clarity-re.
- Function calling oldal: log szinten ellenőrizni, hogy az agent meghívta-e a `GaInsightsTool` valamelyik metódusát.

### 4. Realtime
- `php artisan tinker`-ben: `app(GoogleAnalyticsQueryService::class)->getRealtimeUsers(Project::find(1))` → **elvárt**: `RealtimeSnapshot` DTO az aktív user count-tal. (Ha nincs forgalom, 0; ez is jó válasz.)

### 5. Cache invalidation
- Sorozat: hívás → módosítás GA-ban (vagy idő múlva) → 30s múlva realtime hívás → új adat.
- Standard report: kétszeri hívás 5 percen belül → DB cache log szerint csak egy SDK hívás.

---

## Megfontolások / Trade-off-ok

- **Quota kontroll**: a GA Data API alapból 50k req/nap projekt szinten. A cache-réteg miatt ezt nehéz lenne kimeríteni, de érdemes egy egyszerű in-memory rate limiter-t (Laravel `RateLimiter` facade) tenni a low-level client elé, hogy 429-hibák ne tudjanak DOS-szerűen hibázni.
- **Service account biztonság**: a JSON kulcsot semmiképpen ne commitold, az env file vagy egy szigorú jogkörökkel rendelkező storage path az ajánlott. Egy későbbi iterációban érdemes lehet a Laravel encrypted environment file-t használni.
- **Property ID validáció**: a `app:ga:test` parancs egy minimal lekérdezést indít — ez kiszűri a tipikus konfigurációs hibákat (rossz property formátum, hiányzó Viewer jog, lejárt service account kulcs).
- **AI tool biztonság**: a `GaInsightsTool` minden metódusa az aktuális `Project::current()`-re zár — más projekt adataihoz az AI nem fér hozzá. A `runCustomReport`-ban is csak read-only Data API hívások mennek, írás nem lehetséges (a Data API csak runReport/runRealtimeReport).
- **Későbbi hibrid mód**: ha kiderül, hogy egyes "dashboard" típusú lekérdezések napi rendszerességgel ugyanúgy futnak (pl. egy heti report mindig a múlt 7 nap top page-ét nézi), érdemes lesz egy *opcionális* napi snapshot job-ot bevezetni — de ez explicit második iteráció, nem ennek a tervnek a része.
