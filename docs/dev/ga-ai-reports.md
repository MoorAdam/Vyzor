# GA AI Reports — működés és implementáció

Ez a doksi azt írja le, hogyan generálódik egy Google Analytics 4 alapú AI riport — a felhasználó kattintásától a kész markdown szövegig. A célja, hogy egy új fejlesztő egy ülésben átlássa a teljes pipeline-t, és tudja **hova nyúljon** ha módosítani vagy bővíteni kell.

A GA flow egyetlen flavor a több közül (Clarity, Page és GA), ami ugyanazon a riport-modell + job + service infrastruktúrán fut. A doksi a GA-specifikus részekre fókuszál, de a shared bitseket is bemutatja, hogy érthető legyen a kontextus.

---

## 30 másodperces áttekintés

```
felhasználó ─► /google-analytics/report (Volt page)
                    │
                    └─► <livewire:ga-report-tab>          ◄─── GA-tagged preset választás
                            │ form submit
                            ▼
                       Report::create(...) + GenerateAiReport::dispatch(...)
                            │
                            ▼ (queue)
                       GenerateAiReport::handle()
                            │
                            ▼
                       ReportGeneratorService::generate()
                            │
                            ├─► loadPreset()          ── DB: ai_contexts (slug)
                            ├─► resolveFlavor()       ── preset.tags ⊃ 'ga' → 'ga'
                            ├─► resolveAgent()        ── new ReportAnalyst(project, 'ga-analyst-instructions')
                            └─► buildGaPrompt()
                                    │
                                    ├─ preset.context (a választott GA preset markdownja)
                                    ├─ custom_prompt (ha van)
                                    ├─ renderGaContext() ── élő GA4 lekérdezések → JSON blokk
                                    ├─ language instruction
                                    └─ output-format
                            ▼
                       agent->prompt(...)              ◄─── Laravel AI csomag
                            │
                            └─► OpenAI (default provider)
                                    │
                                    └─► (opcionálisan) GoogleAnalyticsTool function call ── drill-down GA-ra menet közben
                            ▼
                       Report::update(content, status: COMPLETED)
                            │
                            ▼
                       <livewire:recent-reports> wire:poll → új sor megjelenik
```

Failure case-ben az error a `Report.content` mezőbe íródik `Error: <message>` formában; a [`docs/dev/reports-ui.md`](reports-ui.md)-ben leírt report-view oldal renderel egy retry gombot, ami újrahúzza a folyamatot.

---

## Hol mi él

| Réteg | Fájl | Mit csinál |
|-------|------|------------|
| **Page** | [`resources/views/pages/⚡ga-report.blade.php`](../../resources/views/pages/⚡ga-report.blade.php) | `/google-analytics/report` route. Permission gate (VIEW_GOOGLE_ANALYTICS + CREATE_REPORT), GA-property check, beilleszti a tab-et és a recent-reports listát. |
| **Form** | [`resources/views/components/⚡ga-report-tab.blade.php`](../../resources/views/components/⚡ga-report-tab.blade.php) | Volt SFC. GA-tagged preset választó, dátumtartomány (default 30 nap), nyelv, custom prompt. Submitnél létrehozza a Report rekordot + dispatch-eli a job-ot. |
| **Persistence** | [`app/Modules/Reports/Models/Report.php`](../../app/Modules/Reports/Models/Report.php) | A riport rekord. `preset` (slug), `aspect_date_from/to`, `language`, `status`, `content`, `custom_prompt`, `include_ga`, `ai_model_name`. |
| **Status enum** | [`app/Modules/Reports/Enums/ReportStatusEnum.php`](../../app/Modules/Reports/Enums/ReportStatusEnum.php) | `DRAFT`, `PENDING`, `GENERATING`, `COMPLETED`, `FAILED`. |
| **Job** | [`app/Modules/Reports/Jobs/GenerateAiReport.php`](../../app/Modules/Reports/Jobs/GenerateAiReport.php) | `ShouldQueue`. `handle()` átadja a service-nek; `failed()` írja be az error-t és FAILED-re flippeli. `tries=1`, `timeout=300`. |
| **Generator service** | [`app/Modules/Reports/Services/ReportGeneratorService.php`](../../app/Modules/Reports/Services/ReportGeneratorService.php) | A flavor router — `loadPreset()`, `resolveFlavor()`, `resolveAgent()`, `buildPrompt()`. A `buildGaPrompt()` és `renderGaContext()` itt él. |
| **AI agent** | [`app/Modules/Ai/Agents/ReportAnalyst.php`](../../app/Modules/Ai/Agents/ReportAnalyst.php) | `Agent + HasTools`. `instructionsSlug` paraméterrel testreszabható — GA-nál `'ga-analyst-instructions'`. `tools()` array-t ad vissza (lásd [Library quirk](#library-quirk-tools-array-vs-iterable)). |
| **Function-calling tool** | [`app/Modules/Analytics/GoogleAnalytics/Tools/GoogleAnalyticsTool.php`](../../app/Modules/Analytics/GoogleAnalytics/Tools/GoogleAnalyticsTool.php) | A LLM ezzel kérhet drill-down adatot futás közben (más dátum, top-N, channel-bontás stb.). 11 query típus + filter támogatás. |
| **GA query layer** | [`app/Modules/Analytics/GoogleAnalytics/Services/GoogleAnalyticsQueryService.php`](../../app/Modules/Analytics/GoogleAnalytics/Services/GoogleAnalyticsQueryService.php) | Domain API a GA4 Data SDK fölé. Cache-elt, tipusos DTO-kkal. Részleteket lásd: [`docs/plans/google-analytics-integration.md`](../plans/google-analytics-integration.md). |
| **AI context modell** | [`app/Modules/Ai/Contexts/Models/AiContext.php`](../../app/Modules/Ai/Contexts/Models/AiContext.php) | Az `ai_contexts` táblát fed le. Típusok: `SYSTEM`, `INSTRUCTION`, `PRESET`. Címkék: `clarity`, `page_analyser`, `ga`. |
| **Tag enum** | [`app/Modules/Ai/Contexts/Enums/ContextTag.php`](../../app/Modules/Ai/Contexts/Enums/ContextTag.php) | `CLARITY`, `PAGE_ANALYSER`, `GA`. |
| **System instructions** | [`resources/ai-prompts/ga-analyst-instructions.md`](../../resources/ai-prompts/ga-analyst-instructions.md) | A GA agent „personality"-je — GA4 terminológia, prioritizálás, output-stílus. |
| **GA presetek** | [`resources/ai-prompts/presets/ga-*.md`](../../resources/ai-prompts/presets/) | 5 db jelenleg: `ga-traffic-overview`, `ga-conversion-funnel`, `ga-audience-insights`, `ga-deploy-impact`, `ga-acquisition-channels`. |
| **Output format** | [`resources/ai-prompts/output-format.md`](../../resources/ai-prompts/output-format.md) | A markdown formázási elvárás. Minden flavor megkapja. |
| **Seeder** | [`database/seeders/AiContextSeeder.php`](../../database/seeders/AiContextSeeder.php) | A markdown fájlokat tölti be az `ai_contexts` táblába `updateOrCreate`-tel. |
| **Recent reports** | [`resources/views/components/⚡recent-reports.blade.php`](../../resources/views/components/⚡recent-reports.blade.php) | Reaktív lista a GA oldalon — `:tag="ContextTag::GA->value"` szűréssel, saját poll-lal. Lásd: [`reports-ui.md`](reports-ui.md). |
| **Report view** | [`resources/views/pages/⚡report-view.blade.php`](../../resources/views/pages/⚡report-view.blade.php) | A kész riport megnyitása. Failure-nél retry gomb + error üzenet renderel. |

---

## A pipeline részletesen

### 1. A felhasználó megnyitja a `/google-analytics/report` oldalt

A page (`⚡ga-report.blade.php`) három guard-ot fut le:

1. `VIEW_GOOGLE_ANALYTICS` permission a kiválasztott projektre.
2. `CREATE_REPORT` permission a kiválasztott projektre (a form re-checkelje a submitnél is, mert a Livewire `wire:click` a page mount-ot megkerülheti).
3. A projektnek konfigurálva kell lennie GA-val (`hasGoogleAnalytics()` — azaz `ga_property_id` mező nem üres).

Ha bármelyik bukik, a felhasználó vagy egy 403-at kap, vagy egy „Configure in project settings" CTA-t lát.

### 2. A felhasználó kitölti a form-ot

A `⚡ga-report-tab.blade.php` Volt komponens betölti a GA-tagged preseteket:

```php
public function getPresetsProperty()
{
    return AiContext::active()
        ->ofType(AiContextType::PRESET)
        ->whereJsonContains('tags', ContextTag::GA->value)
        ->ordered()
        ->get();
}
```

A preset választó UI-t a [`<x-reports.preset-grid>`](../../resources/views/components/reports/preset-grid.blade.php) komponens renderel — egy kattintásra azt is megnézhető (preview), mit fog kapni az AI a kiválasztott preset szövegeként.

A dátum default `now()->subDays(29)` ... `now()` (30 napos ablak) — a Clarity flow 7 napja helyett, mert GA-n hosszabb ablakra van szignifikáns adatmennyiség.

### 3. A submit egy Report rekordot ír és dispatch-eli a job-ot

```php
$report = Report::create([
    'project_id'       => $project->id,
    'user_id'          => auth()->id(),
    'title'            => $presetTitle . ' - ' . $from->format('M d') . ' to ' . $to->format('M d, Y'),
    'preset'           => $this->preset,        // pl. 'ga-traffic-overview'
    'custom_prompt'    => $this->customPrompt ?: null,
    'include_heatmaps' => false,                // Clarity-only feature
    'include_ga'       => true,                 // bookkeeping; a GA flow-t a preset tag dönti el, nem ez a flag
    'aspect_date_from' => $this->dateFrom,
    'aspect_date_to'   => $this->dateTo,
    'status'           => ReportStatusEnum::PENDING,
    'language'         => $this->reportLanguage,
    'is_ai'            => true,
]);

GenerateAiReport::dispatch($report);
$this->redirectRoute('report.view', $report);
```

A submit átirányít a riport view oldalra — addigra a job már a queue-n vár. A view oldal `wire:poll.5s` direktívával frissít, amíg a status PENDING vagy GENERATING.

### 4. A queue worker felveszi a job-ot

A `GenerateAiReport::handle()` átadja a riportot a `ReportGeneratorService::generate()`-nek, ami:

1. `update(['status' => GENERATING])`
2. `loadPreset()` — egyetlen DB-lekérdezés, betölti a preset rekordot.
3. `resolveFlavor($report, $preset)` — visszaadja: `'page'` ha `page_url` van, `'ga'` ha a preset tag-jei közt van `ga`, különben `'clarity'`.
4. `resolveAgent($flavor, $project)` — GA-ra: `new ReportAnalyst($project, 'ga-analyst-instructions')`.
5. `buildPrompt($report, $flavor, $preset, $provider)` — flavor-router.
6. `$agent->prompt($prompt, provider: $provider)` — átadja a Laravel AI-nak.
7. `update(['content' => ..., 'status' => COMPLETED, 'ai_model_name' => $provider])`.

### 5. A GA prompt összeállása — `buildGaPrompt()`

A prompt négy fix blokkból áll, ebben a sorrendben:

```
[ preset.context ]                      ── a választott preset markdown szövege
[ ## Additional Instructions ... ]      ── csak ha custom_prompt nem null
[ ## Google Analytics (date-range) ]    ── élő GA4 lekérdezések eredménye, JSON-ben
[ ## Language Instruction ]             ── csak ha language != 'en'
[ output-format.md ]                    ── az utolsó blokk, formázási elvárás
```

A GA blokkot a `renderGaContext()` rakja össze. **5 GA query** fut le egy próbálkozásra:

| Query | Service metódus | Mit ad |
|-------|-----------------|--------|
| Traffic overview | `getTrafficOverview` | Teljes sessions, users, engaged sessions, pageviews, engagement rate, bounce rate |
| Period comparison | `comparePeriod` | Ugyanezeket a fenti metrikákat **két időszakra** (kiválasztott vs. előző azonos hosszúságú) |
| Top 10 pages | `getTopPages` | Page paths × pageviews + engagement |
| Acquisition by channel | `getAcquisitionBreakdown` | Default channel group × sessions |
| Device breakdown | `getDeviceBreakdown` | Device category × sessions/users/engagement |

Mind az 5 lekérdezés egy `try` blokkon belül megy, és ha bármelyik `GoogleAnalyticsException`-t dob, az egész GA blokk helyett egy fallback üzenet kerül a promptba: „GA data could not be loaded: …". Ez azt jelenti — a riport nem hal meg attól, hogy GA nem elérhető; az AI értelmezi a hibát és érdemi választ ad.

A GA query layer cache-elve van (`GoogleAnalyticsCache`, réteges TTL — lásd [`docs/plans/google-analytics-integration.md`](../plans/google-analytics-integration.md)), így ugyanaz a query 5 percen belül nem hív API-t kétszer.

### 6. Az agent

A `ReportAnalyst` a GA flavor-nál így instanciálódik:

```php
new ReportAnalyst($project, instructionsSlug: 'ga-analyst-instructions')
```

- `instructions()` az `ai_contexts` tábla `slug = 'ga-analyst-instructions'` SYSTEM rekordjából húzza a system promptot.
- `tools()` egy 1-elemű (vagy üres) **array**-t ad vissza — ha a project GA-konfigurált, egy `GoogleAnalyticsTool`-t. **Ne `yield`-elj itt** — lásd [Library quirk](#library-quirk-tools-array-vs-iterable).
- `#[Timeout(300)]` attribute — 5 perc, de a function-calling-os iterációk együtt is itt nyúlnak ki.

### 7. A LLM-hívás

A Laravel AI csomag a `config('ai.default')` providerrel hív (default `openai`). A pipeline:

1. System prompt = `ReportAnalyst::instructions()` → `ga-analyst-instructions.md`.
2. User message = a teljes `buildGaPrompt()` output.
3. Tools = `[GoogleAnalyticsTool]` (ha GA konfigurált).
4. Model = `TextGenerationOptions::forAgent($agent)` — ha az agent definiál modelt, az; különben provider default.

Az LLM válasza vagy direkt szöveg (és kész), vagy egy tool-call: kéri a `GoogleAnalyticsTool` egy konkrét `query` típusát konkrét paraméterekkel. A Laravel AI ilyenkor:

1. Meghívja a tool `handle()` metódusát a paraméterekkel.
2. A tool a `GoogleAnalyticsQueryService` megfelelő metódusát hívja (lásd [`Tools/GoogleAnalyticsTool.php`](../../app/Modules/Analytics/GoogleAnalytics/Tools/GoogleAnalyticsTool.php)).
3. Az eredményt JSON stringként visszaadja a model-nek.
4. A model folytatja a generálást — lehet újabb tool-call vagy végleges válasz.

A tool **projektre van zárva** a konstruktorban — az LLM nem tud másik project GA-jához hozzáférni rajta keresztül.

### 8. Mentés

A service a model-választ `Report.content`-be írja, statust `COMPLETED`-re flippeli, `ai_model_name`-be a provider nevet rakja (`'openai'`).

### 9. A felhasználó látja

A report-view oldalon (`⚡report-view.blade.php`) a `wire:poll.5s` minden 5 másodpercben új render-t triggerel, amíg PENDING vagy GENERATING. Amikor COMPLETED-re flippelt, a polling leáll, és a `<x-data="markdownRenderer">` Alpine komponens kirenderelni a markdown-t.

A recent-reports lista (`<livewire:recent-reports>` a GA oldalon) szintén poll-ol, de csak akkor, ha **van pending/generating** sor a látható listában. Ezzel a GA oldalra való visszanavigálás után is folyamatosan frissül.

---

## Failure handling + retry

### Mi történik bukásnál

Két lehetséges útvonal:

**Service-szintű hiba** (a `try` blokkon belül a service-ben — pl. tool exception, prompt-építési hiba, AI provider 4xx). Ekkor:

```php
catch (\Throwable $e) {
    $report->update([
        'content' => 'Error: ' . $e->getMessage(),
        'status'  => ReportStatusEnum::FAILED,
    ]);
}
```

**Job-szintű hiba** (timeout, `tries=1` után az exception bubble-up). A `GenerateAiReport::failed()` callback-je hív, ami logol + ugyanezt írja a Report-ba.

A két útvonal együtt biztosítja, hogy a report **soha ne maradjon stuck** PENDING-ben.

### A retry gomb

Amikor a status FAILED, a [`⚡report-view.blade.php`](../../resources/views/pages/⚡report-view.blade.php) renderel egy retry gombot. Az action:

```php
public function retry(): void
{
    abort_unless(auth()->user()->can('permission', [PermissionEnum::CREATE_REPORT, Project::current()]), 403);

    if (! $this->report->is_ai || $this->report->status !== ReportStatusEnum::FAILED) {
        return;
    }

    $this->report->update([
        'content'       => null,
        'ai_model_name' => null,
        'status'        => ReportStatusEnum::PENDING,
    ]);

    GenerateAiReport::dispatch($this->report);
}
```

A status flipje után a feltételes `wire:poll.5s` újra aktív, a felhasználó látja a futó re-runt. Nincs hard reload, nincs új report rekord — ugyanaz a sor frissül.

### Hibák debugjához

- **Logok**: a `GenerateAiReport::failed()` `Log::error('Report generation failed', […])`-t hív.
- **Queue**: `php artisan queue:listen` futtasd dev-en, hogy ne kelljen vakon dispatchelni.
- **A bemenő prompt**: gyors trükk — `Log::debug($prompt)` a `ReportGeneratorService::generate()` `agent->prompt(…)` hívása előtt.
- **A failure üzenet**: a Report `content` mezőjében olvasható az `Error: …` prefixszel.

---

## Új GA preset felvétele (recept)

Tegyük fel, kell egy új „SEO performance" preset GA-ra.

1. **Markdown** — `resources/ai-prompts/presets/ga-seo-performance.md` — a preset szöveg (mit elemezzen az AI). Mintának nézd meg az [`ga-traffic-overview.md`](../../resources/ai-prompts/presets/ga-traffic-overview.md)-t.

2. **Seeder** — `AiContextSeeder.php`-be új bejegyzés:
   ```php
   [
       'name'        => 'SEO performance',
       'slug'        => 'ga-seo-performance',
       'type'        => AiContextType::PRESET,
       'models'      => ['all'],
       'tags'        => [ContextTag::GA->value],
       'icon'        => 'magnifying-glass',
       'label_color' => '#0ea5e9',
       'description' => 'Organic search forrás, top kulcsszavakra optimalizált belépési oldalak.',
       'sort_order'  => 25,
       'context'     => file_get_contents(resource_path('ai-prompts/presets/ga-seo-performance.md')),
   ],
   ```

3. **Seed run** — `php artisan db:seed --class=AiContextSeeder`. `updateOrCreate` slug alapján — biztonságos újra-futtatni.

4. **Kész**. A `⚡ga-report-tab` automatikusan szedi az új presetet (mert `whereJsonContains('tags', 'ga')` szűr), a recent-reports szűrője is automatikusan elkapja.

Új flavor (új analitikai forrás) felvétele bonyolultabb — lásd [`reports-ui.md`](reports-ui.md) „Új flavor felvétele" szekció.

---

## Új GA query a tool-ban

Ha az AI-nak új lekérdezési képesség kell (pl. „goal completion by source"):

1. A `GoogleAnalyticsQueryService`-ben új metódus.
2. A `GoogleAnalyticsTool::description()`-ben új sor a „Allowed 'query' values" alatt.
3. A `GoogleAnalyticsTool::handle()` switch-ben új ág.
4. (Opcionálisan) `renderGaContext()`-ben is hozzáadható, ha minden GA riportnak alapból kelljen.

A tool description literálisan a model-nek megy promptban, így minden új ágat *érthetően* kell leírni.

---

## Library quirk: `tools()` array vs iterable

A `Laravel\Ai\Contracts\HasTools::tools()` interface `iterable`-t ír elő, **de** a [`OpenAiGateway::generateText()`](../../vendor/laravel/ai/src/Gateway/OpenAi/OpenAiGateway.php) szigorúan `array $tools = []`-t vár. A library `GeneratesText` middleware-je az `$agent->tools()`-t **iterátoron keresztül adja át**, nem `iterator_to_array()`-jel. Ezért:

```php
// ❌ Runtime hiba: "Argument #5 ($tools) must be of type array, Generator given"
public function tools(): iterable
{
    if ($this->project && $this->project->hasGoogleAnalytics()) {
        yield new GoogleAnalyticsTool(...);
    }
}

// ✅ Plain array, tényleg array — a library elfogadja.
public function tools(): iterable
{
    if ($this->project && $this->project->hasGoogleAnalytics()) {
        return [new GoogleAnalyticsTool(...)];
    }
    return [];
}
```

A return type annotációja maradhat `iterable` (kontraktus-konform), csak a fizikai típus legyen array. Ne refaktoráld vissza yield-re — a hiba GA-konfigurált projecten azonnal jelentkezik.

---

## Mit *nem* csinál a GA flow

- **Nem cache-eli a kész riportot.** Minden submit új riportot generál.
- **Nem futtat batch generálást.** Egy riport = egy job.
- **Nem küld notification-t** kész állapotban — a UI poll-ja értesít.
- **Nem ad rate-limitet** felhasználói szinten. Az LLM provider rate-limit-je az egyetlen plafon.
- **Nincs verzió-történet** — szerkesztés után az előző tartalom elveszik.

Ezek vagy szándékosan halasztva vannak, vagy egyszerűen még nem voltak fontosak. Ha valamelyikre szükség lesz, [`docs/plans/`](../plans/) alá írj egy proposalt.

---

## Kapcsolódó doksik

- [`docs/dev/reports-ui.md`](reports-ui.md) — a riport UI komponensek (preset-grid, preset-preview, recent-reports, report-card).
- [`docs/plans/google-analytics-integration.md`](../plans/google-analytics-integration.md) — a GA query service architektúrája és cache-stratégiája.
- [`docs/plans/google-analytics-future-work.md`](../plans/google-analytics-future-work.md) — a GA modul halasztott pontjai.
- [`docs/plans/ai-agent-configurator.md`](../plans/ai-agent-configurator.md) — terv az admin AI-konfigurátor UI-hoz, ami a most hardcode-olt slug-okat („`ga-analyst-instructions`", „`output-format`") kivezetné.
