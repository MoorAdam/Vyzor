# Google Analytics — jövőbeli feladatok / továbbfejlesztések

A jelen állapot (iter 3 + cache/UX optimalizáció) működő, élesben használt rendszer. Ez a doksi a megnyitott pontokat és a lehetséges következő lépéseket gyűjti össze, hogy ha bármikor visszatérünk hozzájuk, ne kelljen újból átgondolni.

> **Ne felejtsd**: a teljes architektúra leírása a [google-analytics-integration.md](google-analytics-integration.md)-ben van. Ez a fájl csak a teendőkre fókuszál.

---

## Mi van már most kész

A háttér a teljességhez (mit ne építs újra):

- **Hitelesítés**: service account JSON, `ServiceAccountClientFactory` v1beta + v1alpha + Admin klienseket épít
- **Cache**: réteges TTL (`GaCacheTier`: today/yesterday/recent/historical/realtime), `forgetForProperty()` per-property invalidáció
- **Query Service**: 13 domain metódus + `runCustomReport` escape hatch + `runBatch` GA `batchRunReports` hívással
- **Filter támogatás**: `Filter` rekurzív DTO (and/or/not + leaf operators), beépítve a `ReportRequest`-be és a `GoogleAnalyticsTool`-ba
- **Property discovery**: Admin API alapú dropdown a project edit form-ban + `app:ga:list-properties` parancs
- **Funnel backend**: `FunnelStep`, `FunnelDefinition`, `FunnelResult` DTO-k + `getFunnel()` metódus (v1alpha API)
- **AI integráció**: statikus prompt augmentáció (`include_ga` flag) + dinamikus `GoogleAnalyticsTool` function calling
- **UI oldalak**: Overview, Pages, Audience, Realtime — Refresh gombbal, wire:loading indikátorral, wire:navigate SPA váltással
- **Tesztek**: 21 unit + feature teszt (DateRange, ReportRequest signature, CacheTier resolution)

---

## Halasztott vagy nem implementált

### Fázis 3 — Pre-warming scheduler (~30 perc)

**Cél**: napközbeni first visit warm cache-en jön, ne 1-2 másodperc várakozással.

**Mit jelent**: nightly Laravel scheduler job ami minden GA-konfigurált projektre lefuttatja a leggyakoribb range-eket warm cache-be:
- `last_7` és `last_30` range-ekre az `ga-overview` 5-os batch-jét
- Ugyanezekre az `ga-audience` 6-os + 2-es batch-jeit (drill-down a top countryre)

Hely: `routes/console.php`-ban egy új `Schedule::call(...)`. Reggel 6:00 körül a legjobb (a 24h előtti adat már stabilizálódott, és napközben friss cache-ből jön minden user).

**Kód-vázlat**:
```php
Schedule::call(function () {
    $svc = app(GoogleAnalyticsQueryService::class);
    $projects = Project::whereNotNull('ga_property_id')->get();
    foreach ($projects as $project) {
        foreach ([DateRange::last7Days(), DateRange::last30Days()] as $range) {
            // Build the same request map as ga-overview / ga-audience
            $svc->runBatch($project, [...overview reports...]);
            $svc->runBatch($project, [...audience reports...]);
        }
    }
})->dailyAt('06:00')->name('ga-prewarm')->withoutOverlapping();
```

**Buktatók**: a kérés-kombókat a Livewire oldalakkal duplikálnánk → érdemes egy közös "preset request builder" service-be (`AudiencePagePresetBuilder`?) emelni, hogy egy helyen legyen igazság.

---

### Funnel UI

A backend kész (`getFunnel`, `FunnelStep`, `FunnelDefinition`, `FunnelResult`). Hiányzik a Livewire oldal.

**Tervezett felület**:
- Új route: `/google-analytics/funnel` → `pages::ga-funnel`
- Sidebar item: GA csoport végén
- Form: 2-5 lépés definiálható, mindegyikhez név + esemény-típus választó:
  - Page view (path-szal, contains/exact match)
  - Custom event (eseménynév szabad szöveggel)
- Date range picker (preset + custom)
- Open/Closed funnel kapcsoló (default: open — szelíd, valósághűbb)
- Submit gomb → szerver oldali `getFunnel()` hívás
- Eredmény: vízszintes bar chart + táblázat oszlopokkal: lépés sorszám, név, aktív userek, conv % (1. lépéshez), conv % (előző lépéshez), drop-off

**Buktatók (fontos!)**: a `FunnelStep::pageView()` jelenleg `page_location` event paramétert használ, aminek a teljes URL az értéke (pl. `https://www.villavolgy.hu/foglalas`). Tehát a CONTAINS match a default — EXACT match csak akkor működik, ha a teljes URL-t adja meg a felhasználó. Ezt a UI-on jelezni kell tooltippal vagy placeholder-rel.

A funnel backend első tesztje 0 sort hozott vissza closed funnel + EXACT match-csel. A UI alapértelmezetten **open funnel + CONTAINS match**-re kell, hogy állítsa magát, hogy ne legyen üres az eredmény.

---

### Path / flow analysis

GA4-nek van `previousPagePath` és `nextPagePath` dimenziója — *"mit néznek a userek a /pricing előtt és után"*. Most semmilyen UI nem használja.

**Mit lehet csinálni**:
- Új query metódus: `getPagePathFlow(Project, DateRange, string $pagePath, string $direction = 'next')` ahol direction = 'next' vagy 'previous'
- Beépíthető a `ga-pages` oldalra: ha kattintasz egy oldalra, jobb oldalt felugrik a "ezekre megy a user innen / ezekről jön ide" lista
- VAGY teljesen új `ga-flow` oldal

**Effort**: ~2 óra ha új page, ~1 óra ha a Pages oldalra ráépül.

---

### Cohort analysis

GA4 `cohortSpec` lehetővé teszi *"akik 1. héten jöttek először, mennyien tértek vissza a 2-3-4. héten"* típusú elemzést.

A `runReport` API natívan támogatja egy `CohortSpec` paraméteren keresztül (`firstSessionDate` cohort, `cohortReportSettings` accumulate flag stb.).

**Mit lehet csinálni**:
- `CohortSpec` DTO + `RetentionRequest` DTO
- `getCohortRetention(Project, DateRange, granularity)` metódus
- Új `ga-retention` oldal vagy az audience oldalon új tab

**Effort**: ~4 óra (komplex, GA4 cohort API speciális struktúrát vár).

---

### Pivot reports

GA4-nek külön `runPivotReport` van — országonként × eszközönként cella-mátrix. Most nincs lefedve.

**Mit lehet csinálni**:
- `PivotRequest` DTO + `runPivotReport` a kliensben
- `getPivot(Project, DateRange, dimensions, metrics, pivotConfig)` query metódus
- UI: kétdimenziós heatmap-table

**Effort**: ~3 óra. Talán kihagyható — a GA UI-ban natívan jól megy, kérdéses hogy érdemes-e Vyzorba is építeni.

---

### `wire:navigate` a többi sidebar item-re

Most csak a GA + Clarity csoport itemek SPA-szerűek. A többi (Projects, Reports, Users, Settings, brand link) még full page reload-ot csinál.

**Mit jelent**: hozzáadni `wire:navigate` attribute-ot az alábbi linkekre a `resources/views/layouts/app.blade.php`-ben:
- `<x-ui.brand href="/clarity/snapshot">` (header brand)
- `Projects` (General csoport)
- `Write Report`, `All Reports` (Reports csoport)
- `Users | Customers`
- `Settings` és `Contexts` alatta

**Effort**: 5 perc. Apró kockázat: ha valamelyik oldal mount-ja Livewire-rel inkompatibilis (pl. külső redirect), ott eltávolíthatod. A többi GA + Clarity már tesztelt.

---

### Custom dimensions/metrics felfedezés

GA4 property-nként lehetnek custom esemény-paraméterek (`customEvent:button_id`, `customUser:plan_tier` stb.). Az Admin API `getMetadata` endpoint-jával listázhatóak.

**Mit lehet csinálni**:
- `app:ga:describe {project}` parancs ami a property metadata-ját kiírja
- A project edit form alatt egy "Custom dimensions/metrics" expandable szekció ami listázza őket
- Az AI tool description-jébe beépíthetjük a custom dimensions listáját (akkor az LLM tud róluk)

**Effort**: ~1 óra.

---

## Operációs / kiegészítések

### Redis cache

Most database driver — minden cache hit egy SQL lekérés. Redis-szel ~10× gyorsabb lenne (kb. 1 ms helyett 10 ms-os DB query helyett tizedmillisec memóriaművelet).

**Mit jelent**: `CACHE_STORE=redis` az `.env`-ben + Redis service. A `GoogleAnalyticsCache::forgetForProperty()` jelenleg csak database driver-rel működik (DB.cache tábla LIKE query) — Redis-re át kell írni `Cache::tags()` + `Cache::tags()->flush()` mintára VAGY `SCAN`-alapú prefix-törlésre.

**Effort**: ~1 óra (a tags-alapú megoldás cleanebb, de minden remember()-be `tags()` kell).

---

### CSV export a táblákról

A `ga-pages` és `ga-audience` táblákat sokszor szeretné exportálni a felhasználó (Excel-be, PowerPoint-ba, kliensnek e-mailben).

**Mit jelent**: gomb minden táblázat fölé, ami a Livewire `download` response-szal CSV-t küld. A `ReportResult` `toArray()`-jét lehet alapba venni.

**Effort**: ~1 óra (per oldal nagyjából 15 perc).

---

### Compare period full-page UI mód

A `ga-overview` most KPI-tile-ekben mutatja a deltákat (small badges). De a Clarity-ben van egy **side-by-side compare mód** — két időszak tabella-szinten egymás mellett. Az `ga-overview`-n is tehetnénk ilyet.

**Mit jelent**: új radio item a `ga-overview` mód-választójára (Single / Compare), és Compare módban két oszlop: bal oldali current range + jobb oldali previous range, sor szinten összehasonlítva.

**Effort**: ~2 óra.

---

### Multi-property aggregálás

Ha egy projektnek több GA property-je van (pl. teszt + éles, vagy több domain), most csak egyet tudunk konfigurálni (`Project.ga_property_id` egyetlen string).

**Mit jelent**:
- Új tábla: `project_ga_properties` (project_id × ga_property_id)
- A `Project::hasGoogleAnalytics()` és `gaPropertyResource()` átalakítása listára
- A `GoogleAnalyticsQueryService` minden metódusába egy aggregáló logika ami több property eredményét összegzi

**Effort**: ~6 óra. **Csak akkor érdemes**, ha jön olyan ügyfél akinek tényleg több property-je van — egyébként YAGNI.

---

### Onboarding wizard

Most a project edit form alján van a GA property dropdown + Test connection. De a teljes onboarding flow ("hogyan adj hozzá a service account email-t a GA property-edhez") még mindig manuális dokumentációra szorul.

**Mit jelent**: egy 3-lépéses Livewire wizard a project létrehozása után:
1. "Másold ki ezt az email-t" (a service account email)
2. Lépésről lépésre képeken (gif/screenshot) mutatja hogy hol kell hozzáadni GA Property Access Management-be
3. Refresh + property dropdown — válassza ki a felhasználó

**Effort**: ~3 óra. Erősen UX-fókuszú, picture-alapú dokumentációval.

---

## Apróságok / loose ends

### Az `app:ga:test` parancsban a "verified" időbélyeg

A `Project.ga_last_verified_at` mező a `app:ga:test` parancs sikere esetén frissül. De a Livewire oldali `testGaConnection()` action **nem** frissíti — csak in-memory toast üzenetet mutat. Érdemes oda is befűzni:

```php
$this->project->update(['ga_last_verified_at' => now()]);
```

**Effort**: 5 perc.

---

### A `GoogleAnalyticsCache::forgetForProperty()` csak database driver-rel működik

```php
if (config('cache.default') !== 'database') {
    return 0;
}
```

Ez most konzisztens a projekt aktuális beállításával (`CACHE_STORE=database`). Ha valaha Redis-re vált a projekt, a Refresh gomb csendesen megszűnik működni. Vagy adjunk hozzá Redis-támogatást (Cache::tags), vagy adjunk vissza warning üzenetet a unsupported driver-en.

**Effort**: ~30 perc Redis-támogatáshoz, 5 perc warning-hoz.

---

### A `GoogleAnalyticsTool` `runCustomReport` ágának nincs filter támogatása

Most a tool-ban a 9 named query (`top_pages`, `acquisition` stb.) kapja meg a `filter` paramétert, de a `daily_timeline` és `compare_period` még nem. Ezek a `filteredReportRequest` match-ben `default => null` ágat találják.

**Effort**: ~30 perc — adjuk hozzá a hiányzó kettőt.

---

## Összefoglaló priorítás

Ha sorrendben kéne haladni, ez tűnik a legjobb sorrendnek:

1. **`wire:navigate` mindenhova** (5 perc) — UX win, alacsony kockázat
2. **Apróságok / loose ends** (~1 óra) — verified timestamp, tool filter coverage
3. **Pre-warming scheduler** (~30 perc) — az utolsó 1-2 másodperces cold-start élmény eltüntetése
4. **CSV export** (~3 óra, három oldal × 1 óra) — felhasználói kérés mindenképp jönni fog
5. **Funnel UI** (~3 óra) — backend kész, csak rákapcsolni
6. **Compare period side-by-side mód** (~2 óra)
7. **Custom dimensions/metrics felfedezés** (~1 óra)
8. **Path / flow analysis** (~2 óra)
9. **Onboarding wizard** (~3 óra)
10. **Redis cache** (~1 óra) — csak amikor a database cache lassul (jövőbeli probléma)
11. **Cohort, Pivot, Multi-property** — csak konkrét felhasználói igényre

---

## Kapcsolódó fájlok / belépési pontok

Ha visszatérsz egy feladathoz, ezeket a fájlokat érdemes először megnézni:

- **Backend modul**: [`app/Modules/Analytics/GoogleAnalytics/`](../../app/Modules/Analytics/GoogleAnalytics)
- **Service provider**: [`GoogleAnalyticsServiceProvider.php`](../../app/Modules/Analytics/GoogleAnalytics/GoogleAnalyticsServiceProvider.php)
- **Livewire oldalak**: [`resources/views/pages/⚡ga-*.blade.php`](../../resources/views/pages/)
- **Layout / sidebar**: [`resources/views/layouts/app.blade.php`](../../resources/views/layouts/app.blade.php)
- **Routes**: [`routes/web.php`](../../routes/web.php)
- **Tervek**: ez a fájl + [`google-analytics-integration.md`](google-analytics-integration.md)
- **Architektúra leírás**: [`docs/dev/project-structure.md`](../dev/project-structure.md)
- **Tesztek**: [`tests/Unit/GoogleAnalytics/`](../../tests/Unit/GoogleAnalytics) és [`tests/Feature/GoogleAnalytics/`](../../tests/Feature/GoogleAnalytics)
