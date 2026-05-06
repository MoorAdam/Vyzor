# Vyzor - Projekt struktúra

A Vyzor egy Laravel 12 + Livewire 4 alapú analitikai platform, amely AI-alapú riportokat, Microsoft Clarity integrációt és heatmap elemzést kínál. A kódbázis **namespace-alapú moduláris monolitot** követ — a domain logika az `app/Modules/`-ban él, a megosztott infrastruktúra a gyökér `app/` névtérben marad.

---

## Alapelvek

1. **A modulok birtokolják a domain logikát.** Minden modul saját névtér alatt tartalmazza a modeljeit, enumjait, service-eit, commandjait és jobjait.
2. **A megosztott modelek megosztottak maradnak.** A `User`, `UserProfile`, `CustomerProfile` és `Permission` az `app/Models/`-ban élnek, mert minden modul függ tőlük.
3. **Modulok közötti hivatkozás egyszerű `use` utasítás.** Nincs absztrakciós réteg — csak `use App\Modules\Reports\Models\Report;` bárhonnan.
4. **A nézetek központosítottak.** Blade layoutok, Volt oldalak és UI komponensek a `resources/views/`-ban élnek. A modulok nem birtokolnak nézeteket.
5. **Minden modulnak van egy ServiceProvider-e**, amely a `bootstrap/providers.php`-ban van regisztrálva.

---

## Könyvtárszerkezet

```
app/
├── Models/                                          # Megosztott identity modelek
│   ├── User.php                                     # Autentikáció, szerepkörök, jogosultság-feloldás
│   ├── UserProfile.php                              # Belső (WEB) felhasználók profilja
│   ├── CustomerProfile.php                          # Külső (CUSTOMER) felhasználók profilja
│   └── Permission.php                               # Jogosultság slugok az adatbázisban
│
├── Providers/
│   └── AppServiceProvider.php                       # Gate definíciók, admin bypass
│
├── Http/
│   └── Controllers/
│       └── Controller.php                           # Alap controller (Laravel default)
│
└── Modules/
    │
    ├── Analytics/                                   # ── ANALYTICS MODUL ──
    │   ├── Clarity/                                 #    Almodul: Microsoft Clarity integráció
    │   │   ├── Models/
    │   │   │   ├── ClarityInsight.php               #    Clarity API metrika pillanatképek
    │   │   │   └── ClarityFetchCounter.php          #    Napi lekérdezés-számláló projektenként
    │   │   └── Commands/
    │   │       ├── FetchClarity.php                 #    Egy projekt Clarity adatainak lekérdezése
    │   │       └── FetchAllClarity.php              #    Összes projekt Clarity adatainak lekérdezése
    │   ├── Heatmaps/                                #    Almodul: Heatmap kezelés
    │   │   └── Models/
    │   │       └── Heatmap.php                      #    Feltöltött heatmap CSV adatok
    │   ├── GoogleAnalytics/                         #    Almodul: GA4 Data API integráció (on-demand + cache)
    │   │   ├── Auth/
    │   │   │   └── ServiceAccountClientFactory.php  #    Service account JSON → BetaAnalyticsDataClient
    │   │   ├── Services/
    │   │   │   ├── GoogleAnalyticsClient.php        #    Low-level wrapper a Data SDK kliens köré
    │   │   │   ├── GoogleAnalyticsCache.php         #    Réteges TTL stratégia, kulcsképzés
    │   │   │   └── GoogleAnalyticsQueryService.php  #    Domain API: getTrafficOverview, getTopPages, comparePeriod, ...
    │   │   ├── Queries/
    │   │   │   ├── DateRange.php                    #    Immutable dátum-tartomány value object
    │   │   │   ├── ReportRequest.php                #    runReport DTO + cacheSignature()
    │   │   │   └── RealtimeRequest.php              #    runRealtimeReport DTO
    │   │   ├── DTOs/
    │   │   │   ├── ReportResult.php                 #    Kollekciós eredmény + oszlop-metaadatok + fetchedAt
    │   │   │   ├── MetricRow.php                    #    Egy sor: dimensions + metrics
    │   │   │   ├── TrafficOverview.php              #    High-level traffic snapshot DTO
    │   │   │   ├── PeriodComparison.php             #    Két időszak deltákkal
    │   │   │   └── RealtimeSnapshot.php             #    Élő aktív userek + bontások
    │   │   ├── Enums/
    │   │   │   ├── GaMetric.php                     #    GA4 metrika nevek + UI format hint
    │   │   │   ├── GaDimension.php                  #    GA4 dimenzió nevek
    │   │   │   └── GaCacheTier.php                  #    TODAY | YESTERDAY | RECENT | HISTORICAL | REALTIME
    │   │   ├── Tools/
    │   │   │   └── GoogleAnalyticsTool.php          #    AI function-calling tool (action discriminator)
    │   │   ├── Commands/
    │   │   │   └── TestGoogleAnalyticsConnection.php #   `app:ga:test {project}` — hitelesítés-ellenőrző
    │   │   ├── Exceptions/
    │   │   │   ├── GoogleAnalyticsException.php
    │   │   │   ├── PropertyNotConfiguredException.php
    │   │   │   └── ServiceAccountNotConfiguredException.php
    │   │   └── GoogleAnalyticsServiceProvider.php   #    Singletonok + commandok regisztrációja
    │   └── AnalyticsServiceProvider.php             #    Clarity commandok regisztrálása
    │
    ├── Ai/                                          # ── AI MODUL ──
    │   ├── Contexts/                                #    Almodul: AI kontextusok
    │   │   ├── Models/
    │   │   │   ├── AiContext.php                    #    Újrafelhasználható AI prompt kontextusok
    │   │   │   └── LLMContextPreset.php             #    Legacy preset model (deprecated)
    │   │   └── Enums/
    │   │       ├── AiContextType.php                #    PRESET | SYSTEM | INSTRUCTION
    │   │       └── ContextTag.php                   #    CLARITY | PAGE_ANALYSER | GA
    │   ├── Agents/                                  #    Almodul: Laravel AI ágentek
    │   │   ├── ReportAnalyst.php                    #    Analytics riportokhoz, projekt-tudatos GA tool-lal
    │   │   └── PageAnalyst.php                      #    Oldalelemzés riportokhoz
    │   └── AiServiceProvider.php
    │
    ├── Reports/                                     # ── REPORTS MODUL ──
    │   ├── Models/
    │   │   └── Report.php                           #    AI és manuális riportok
    │   ├── Enums/
    │   │   └── ReportStatusEnum.php                 #    DRAFT → PENDING → GENERATING → COMPLETED | FAILED
    │   ├── Services/
    │   │   ├── ReportGeneratorService.php           #    AI riport generálás vezérlése
    │   │   └── HtmlFetcherService.php               #    HTML lekérdezés és tisztítás oldalelemzéshez
    │   ├── Jobs/
    │   │   └── GenerateAiReport.php                 #    Queue-ba küldött riport generálás
    │   ├── Commands/
    │   │   └── GenerateReports.php                  #    CLI: függőben lévő AI riportok feldolgozása
    │   └── ReportsServiceProvider.php               #    GenerateReports command regisztrálása
    │
    ├── Projects/                                    # ── PROJECTS MODUL ──
    │   ├── Models/
    │   │   ├── Project.php                          #    Projekt titkosított Clarity API kulccsal és GA property ID-vel
    │   │   └── ProjectPermission.php                #    Tulajdonos + együttműködők (JSON user ID tömb)
    │   ├── Enums/
    │   │   └── ProjectStatusEnum.php                #    ACTIVE | ABORTED | POSTPONED | COMPLETED | PRESENTATION
    │   └── ProjectsServiceProvider.php
    │
    └── Users/                                       # ── USERS MODUL ──
        ├── Livewire/
        │   ├── UserForm.php                         #    Belső felhasználó létrehozó form
        │   └── CustomerForm.php                     #    Ügyfél létrehozó form
        ├── Middleware/
        │   ├── SetLocale.php                        #    App locale beállítása session-ből
        │   └── EnsureUserRole.php                   #    Route middleware — szerepkör ellenőrzés
        ├── Enums/
        │   ├── UserRoleEnum.php                     #    WEB | CUSTOMER | ADMIN
        │   └── PermissionEnum.php                   #    Összes jogosultság slug, group(), description()
        └── UsersServiceProvider.php                 #    Livewire komponensek regisztrálása
```

```
bootstrap/
├── app.php                                          # Middleware konfig, route betöltés
└── providers.php                                    # Összes ServiceProvider regisztráció

routes/
├── web.php                                          # Összes web route (Volt oldal hivatkozások)
└── console.php                                      # Ütemezett Clarity lekérdezés (4 óránként)

config/
├── ai.php                                           # AI provider alapértelmezések (openai, gemini, stb.)
├── auth.php                                         # Eloquent User provider → App\Models\User
└── services.php                                     # Clarity API konfig, HTML fetcher konfig

resources/
├── views/
│   ├── layouts/
│   │   ├── app.blade.php                            # Fő layout: header + sidebar + jogosultság-szűrt nav
│   │   └── customer.blade.php                       # Egyszerűsített ügyfél layout
│   ├── pages/                                       # Livewire Volt teljes oldalas komponensek
│   │   ├── auth/                                    #    Login, Register
│   │   ├── project/                                 #    Create, Edit, List
│   │   ├── customer/                                #    Dashboard
│   │   └── settings/                                #    Presets (AI kontextus kezelés)
│   ├── components/
│   │   ├── ui/                                      # Újrafelhasználható Blade komponens könyvtár
│   │   └── *.blade.php                              # Domain Livewire komponensek (project-select, stb.)
│   └── livewire/
│       ├── user-form.blade.php                      # UserForm nézet
│       └── customer-form.blade.php                  # CustomerForm nézet
├── js/
│   ├── app.js                                       # Fő JS belépési pont
│   ├── bootstrap.js                                 # Inicializálás
│   ├── utils.js                                     # Segédfüggvények
│   ├── globals/modals.js                            # Modal kezelés
│   └── components/
│       ├── markdown-renderer.js                     # Markdown → HTML (marked + highlight.js)
│       └── select.js                                # Select komponens logika
└── css/
    └── app.css                                      # Tailwind CSS belépési pont

database/
├── migrations/                                      # Kronologikus migrációk
├── seeders/
│   ├── DatabaseSeeder.php                           # Teszt user, AiContextSeeder + PermissionSeeder
│   ├── AiContextSeeder.php                          # AI kontextusok seedelése
│   ├── PermissionSeeder.php                         # Jogosultsági mátrix seedelése
│   └── LLMContextPresetSeeder.php                   # Legacy seeder
└── factories/
    └── UserFactory.php                              # User model factory

lang/
└── hu.json                                          # Magyar fordítások (JSON kulcs-érték formátum)
```

---

## Modul referencia

### Modulok közötti kommunikáció

A modulok teljes namespace-szel hivatkoznak egymásra `use` utasításokkal. Nincs absztrakciós réteg — csak PHP névterek.

```php
// A Reports modulban, hivatkozás az Analytics és Ai modulokra:
use App\Modules\Analytics\Clarity\Models\ClarityInsight;
use App\Modules\Analytics\Heatmaps\Models\Heatmap;
use App\Modules\Ai\Contexts\Enums\AiContextType;
use App\Modules\Ai\Contexts\Models\AiContext;
use App\Modules\Ai\Agents\ReportAnalyst;
```

### Modul függőségi gráf

```
Users (enumok, middleware, livewire)
  ↑
  ├── Projects (modelek, enumok)
  │     ↑
  │     ├── Analytics (clarity modelek/commandok, heatmap modelek)
  │     └── Reports (modelek, service-ek, jobok, commandok)
  │           ↑
  │           └── Ai (kontextusok, ágentek)
  │
  └── app/Models/User  ← megosztott, minden modul használja
```

A `Reports` a legkeresztbe vágó modul — az Analytics-ból (Clarity adatok, heatmapek), Ai-ból (ágentek, kontextusok) és Projects-ből is húz.

---

## Megosztott modelek (`app/Models/`)

Ezek a modelek a modulokon kívül élnek, mert infrastruktúra, amitől minden függ.

| Model | Cél |
|-------|-----|
| `User` | Autentikáció, szerepkörök (WEB/CUSTOMER/ADMIN), jogosultság-feloldás |
| `UserProfile` | Belső (WEB) felhasználók bővített profilja |
| `CustomerProfile` | Külső (CUSTOMER) felhasználók profilja — `company_name`, `phone` |
| `Permission` | Jogosultság slugok tárolása az adatbázisban |

A `User` model a Users modul `UserRoleEnum`-ját használja cast-ként. Az auth konfig (`config/auth.php`) az `App\Models\User::class`-ra mutat.

---

## Autentikáció és jogosultságkezelés

### Szerepkörök

| Szerepkör | Viselkedés |
|-----------|------------|
| `ADMIN` | Minden jogosultság-ellenőrzést megkerül `Gate::before`-ral. Nem kell explicit jogosultság. |
| `WEB` | Belső felhasználó. Jogosultságok a `role_permission` táblában szerepkör szerint. |
| `CUSTOMER` | Külső felhasználó. Alapértelmezetten nincs hozzáférése, amíg jogosultságot nem kap. |

### Permission gate

Definiálva: `AppServiceProvider::boot()`:

```php
Gate::define('permission', function (User $user, PermissionEnum $permission, $project = null) { ... });
```

- Nem projekt-specifikus jogosultságok: ellenőrzi, hogy a felhasználó szerepköréhez tartozik-e a jogosultság slug.
- Projekt jogosultságok (`project.*`): emellett ellenőrzi a tulajdonosi vagy együttműködői státuszt a `ProjectPermission`-ön keresztül.
- Az együttműködők külön `collaborator` szerepkört kapnak a jogosultság-lekérdezéshez.

### Használat Blade-ben

```blade
{{-- Egyszerű jogosultság-ellenőrzés --}}
@can('permission', App\Modules\Users\Enums\PermissionEnum::VIEW_USERS)

{{-- Projekt-specifikus jogosultság-ellenőrzés --}}
@can('permission', [App\Modules\Users\Enums\PermissionEnum::VIEW_REPORTS, $currentProject])
```

---

## Riport generálási folyamat

A riportok lehetnek AI-generáltak vagy kézzel írottak. Az AI riportok a következő folyamaton mennek végig:

```
Felhasználó létrehozza a riportot (Volt oldal)
  → GenerateAiReport job a queue-ba kerül
    → ReportGeneratorService::generate()
      → Prompt összeállítása (oldalelemzés VAGY clarity elemzés)
      → Ágens kiválasztása (PageAnalyst vagy ReportAnalyst)
      → agent->prompt() hívás Laravel AI-on keresztül
      → Riport frissítése: státusz → COMPLETED vagy FAILED
```

### Két riport típus

| Típus | Kiváltó | Ágens | Adatforrás |
|-------|---------|-------|------------|
| **Oldalelemzés** | `page_url` ki van töltve | `PageAnalyst` | Letöltött HTML a `HtmlFetcherService`-en keresztül |
| **Clarity riport** | `page_url` üres | `ReportAnalyst` | `ClarityInsight` rekordok + opcionális heatmapek |

### Státusz életciklus

```
DRAFT → PENDING → GENERATING → COMPLETED
                             → FAILED
```

---

## Clarity integráció

A Microsoft Clarity adatokat az `app:fetch-clarity` command kérdezi le a Clarity Export API-n keresztül.

- **Ütemezve**: 4 óránként a `routes/console.php`-ban, minden `clarity_api_key`-jel rendelkező projektre.
- **Manuálisan**: `php artisan app:fetch-clarity {project} --days=1 --dimension1=Device`
- **Ratelimit**: A `ClarityFetchCounter` követi a napi lekérdezés-számot projektenként (limit: 10/nap, konfigurálható: `config/services.php`).
- **Tárolás**: Metrikák JSON-ként a `ClarityInsight`-ban dátumtartománnyal és legfeljebb 3 dimenzióval.

---

## Google Analytics 4 integráció

A GA Data API integráció **on-demand modell**-t használ — nem napi snapshot DB-be, mint a Clarity. A magas API quota (50k req/nap) miatt valós időben kérdezünk le, és réteges TTL-lel cache-elünk.

### Hitelesítés

- **Service account** alapú: a `storage/app/ga-service-account.json` (vagy `GA_SERVICE_ACCOUNT_JSON` env) tárolja a JSON kulcsot, amit a felhasználó hozzáad **Viewer** jogkörrel a saját GA4 property-jéhez.
- **Per-projekt property ID**: `Project.ga_property_id` (encrypted) tárolja a `properties/123456789` formátumot.
- **Egyszer beállítva**: minden új property-hez csak Viewer jogot kell adni, nincs külön kulcs vagy onboarding kód.

### Rétegek

| Réteg | Felelősség |
|-------|-----------|
| `BetaAnalyticsDataClient` (SDK) | Google PHP SDK — alacsony szintű gRPC kliens |
| `ServiceAccountClientFactory` | JSON kulcs feloldása (path vagy raw env), kliens cache-elés |
| `GoogleAnalyticsClient` | Vékony wrapper — exception normalizálás, jövőbeli logging/retry chokepoint |
| `GoogleAnalyticsCache` | TTL tier választás `DateRange` alapján, kulcsképzés |
| `GoogleAnalyticsQueryService` | Domain API — tipusos metódusok (`getTrafficOverview`, `getTopPages`, ...) |

### Cache TTL tierek (`GaCacheTier`)

| Tier | Mikor | TTL (default) |
|------|-------|---------------|
| `Today`     | Tartomány tartalmazza a mai napot | 15 min |
| `Yesterday` | Csak tegnap | 2 óra |
| `Recent`    | 2–7 napja zárult tartomány | 12 óra |
| `Historical`| 8+ napja zárult tartomány | 7 nap |
| `Realtime`  | Realtime API (utolsó 30 perc) | 30 mp |

A `services.google_analytics.cache.*_ttl` config kulcsokon konfigurálhatók.

### Public API (`GoogleAnalyticsQueryService`)

- `getTrafficOverview(Project, DateRange)` → `TrafficOverview` (sessions, users, engagement totals)
- `getTopPages / getLandingPages(Project, DateRange, limit, offset)` → `ReportResult`
- `getAcquisitionBreakdown / getDeviceBreakdown / getGeoBreakdown(Project, DateRange)` → `ReportResult`
- `getEvents(Project, DateRange, eventName?, limit)` → `ReportResult`
- `getDailyTimeline(Project, DateRange, metrics)` → `ReportResult` (date × metric)
- `comparePeriod(Project, current, previous, metrics)` → `PeriodComparison` (deltákkal)
- `getRealtimeUsers / getRealtimeEvents(Project)` → `RealtimeSnapshot` / `ReportResult`
- `runCustomReport(Project, ReportRequest)` → `ReportResult` (escape hatch tetszőleges dim/metric kombóhoz)

### AI integráció — két szinten

**A) Statikus prompt augmentáció**: ha a `Report.include_ga = true`, a `ReportGeneratorService::renderGaContext()` előre lekérdez egy snapshot-ot (overview + compare + top pages + acquisition + device) és JSON blokként a promptba illeszti.

**B) Function calling tool**: a `ReportAnalyst` agent felveszi a `GoogleAnalyticsTool`-t a tools listájába, amikor a riport projektje GA-konfigurált. A tool egy egyesített `query` action discriminator-on keresztül teszi elérhetővé az összes query service metódust az AI-nak. Ez a "bármikor hozzáférés" futási időben — az AI menet közben mélyíthet, ha kell.

### UI réteg (következő iteráció)

A query service már UI-fogyasztásra felkészített DTO-kat ad vissza (paginálás, oszlop-formattolási hint, `fetchedAt`). A 2. iterációs adatkijelző felületek:

- **Livewire oldal-komponensek** (pl. `pages::ga-overview`, `pages::ga-realtime`) reaktív dashboard-okhoz, a Clarity ⚡-prefixű oldalak mintájára.
- **Sheaf UI alapú részkomponensek** a meglévő `resources/views/components/ui/*` primitívekből építve (card, button, badge, table, separator). Plain Blade `<x-ga-...>` komponensek nem készülnek — Livewire reaktivitásra, Sheaf UI statikus kompozícióra.

### Verifikáció

```bash
php artisan app:ga:test {project}
```

Lefuttat egy traffic overview + top 5 pages + realtime check lekérdezést, és frissíti a `Project.ga_last_verified_at`-et.

---

## AI kontextus rendszer

Az `AiContext` model újrafelhasználható prompt-részleteket tárol az AI riport promptok összeállításához.

### Típusok (`AiContextType`)

| Típus | Cél |
|-------|-----|
| `PRESET` | Riport instrukciós sablonok — a felhasználó választja ki riport létrehozásakor |
| `SYSTEM` | Ágens rendszer instrukciók (pl. `report-analyst-instructions`, `page-analyst-instructions`) |
| `INSTRUCTION` | Megosztott prompt szekciók (pl. `output-format`, `heatmap-analysis`) |

### Tagek (`ContextTag`)

A tagek kategorizálják a preseteket riport típus szerint:
- `CLARITY` — Clarity adat riportokhoz
- `PAGE_ANALYSER` — oldalelemzés riportokhoz
- `GA` — Google Analytics riportokhoz

### Model kompatibilitás

Minden kontextusnak van egy `models` JSON tömbje. Értékek: `['all']`, `['openai']`, `['openai', 'gemini']`, stb. A `scopeForModel()` scope szűri az aktív AI providerrel kompatibilis kontextusokat.

### Lokalizáció

A kontextusok kétnyelvű tartalmat támogatnak a `name_hu` és `description_hu` mezőkkel. A `localizedName()` és `localizedDescription()` metódusok a megfelelő nyelvi verziót adják vissza.

---

## UI komponens könyvtár

Minden újrafelhasználható Blade komponens a `resources/views/components/ui/`-ban él. Tailwind CSS-t használnak teljes dark mode támogatással.

### Elérhető komponensek

| Komponens | Útvonal | Megjegyzés |
|-----------|---------|------------|
| Avatar | `ui/avatar/` | Felhasználó avatar |
| Badge | `ui/badge/` | Státusz/címke badge-ek |
| Brand | `ui/brand/` | Alkalmazás branding elem |
| Button | `ui/button/` | Több variáns (solid, outline, ghost), méretek, ikon támogatás |
| Card | `ui/card/` | Kártya konténer |
| Checkbox | `ui/checkbox/` | Jelölőnégyzet |
| Dropdown | `ui/dropdown/` | Elemekkel, elválasztókkal, almenükkel, checkbox/radio variánsokkal |
| Empty | `ui/empty/` | Üres állapot megjelenítések |
| Field | `ui/field/` | Form mező wrapper |
| Fieldset | `ui/fieldset/` | Form fieldset |
| Heading | `ui/heading/` | Tipográfia |
| Icon | `ui/icon/` | Phosphor ikon wrapper (`blade-phosphor-icons`) |
| Input | `ui/input/` | Szöveg input clearable, revealable, copyable, button slot variánsokkal |
| Kbd | `ui/kbd/` | Billentyűparancs megjelenítés |
| Layout | `ui/layout/` | Oldal layout struktúra (header-sidebar, bare) |
| Modal | `ui/modal/` | Modális dialógusok |
| Navbar | `ui/navbar/` | Felső navigációs sáv |
| Navlist | `ui/navlist/` | Oldalsáv navigáció (csoportok, elemek, összecsukható) |
| Popup | `ui/popup/` | Tooltip/popover |
| Radio | `ui/radio/` | Rádiógombok (group, item, indicator) |
| Select | `ui/select/` | Legördülő választó |
| Separator | `ui/separator/` | Vízszintes/függőleges elválasztó |
| Sidebar | `ui/sidebar/` | Oldalsáv konténer toggle és push viselkedéssel |
| Text | `ui/text/` | Szöveg elem |

---

## Konvenciók

### Enumok

Minden enum string-backed és a modulja `Enums/` könyvtárában él. Minden enum tartalmazza:
- `label(): string` — ember által olvasható név (`__()` fordítás támogatással)
- `color(): string` — Tailwind szín név UI rendereléshez
- Opcionálisan `hex(): string` (pl. `ProjectStatusEnum` chart színekhez)
- Opcionálisan `group(): string` és `description(): string` (pl. `PermissionEnum`)

### Adatbázis tábla nevek

A projekt az Eloquent konvencióit követi:
- Tábla nevek **többes számú snake_case**: `User` → `users`, `ClarityInsight` → `clarity_insights`
- Pivot táblák: `role_permission` (role + permission_id)
- Idegen kulcsok: `{model}_id` (pl. `project_id`, `user_id`, `owner_id`)
- Timestamps (`created_at`, `updated_at`) minden táblán

### Nyelvesítés

- Minden felhasználó-felé mutatott szöveg `__('Angol szöveg')` formátumot használ.
- Az angol az alapértelmezett és fallback nyelv.
- Magyar fordítások a `lang/hu.json`-ban, lapos JSON kulcs-érték formátumban.
- Nyelv váltás `POST /locale/{locale}` route-on keresztül, ami `session('locale')`-t állít.
- A `SetLocale` middleware minden kérésnél olvassa a session-t és hívja az `app()->setLocale()`-t.
- Támogatott nyelvek: `en`, `hu`.

---

## Artisan commandok és fájl-generálás

A moduláris struktúra miatt a standard `artisan make:*` parancsok nem a megfelelő helyre generálnak fájlokat. Az alábbiakban összefoglaljuk, hogyan kell a különböző fájltípusokat kezelni.

### Model létrehozása

Az `artisan make:model` az `app/Models/`-ba generál. Moduláris modelekhez **kézzel kell áthelyezni** és a namespace-t frissíteni.

```bash
# 1. Generálás (opcionálisan migrációval)
php artisan make:model ClarityInsight -m

# 2. Áthelyezés a megfelelő modulba
mv app/Models/ClarityInsight.php app/Modules/Analytics/Clarity/Models/

# 3. Namespace frissítése a fájlban:
#    App\Models → App\Modules\Analytics\Clarity\Models
```

Vagy hozd létre kézzel a fájlt közvetlenül a megfelelő helyen — a migráció generáláshoz:

```bash
php artisan make:migration create_clarity_insights_table
```

### Livewire komponens létrehozása

Az `artisan make:livewire` az `app/Livewire/`-ba és a `resources/views/livewire/`-ba generál. Moduláris komponensekhez:

```bash
# 1. Generálás
php artisan make:livewire UserForm

# 2. PHP osztály áthelyezése a modulba
mv app/Livewire/UserForm.php app/Modules/Users/Livewire/

# 3. Namespace frissítése a PHP fájlban:
#    App\Livewire → App\Modules\Users\Livewire

# 4. A Blade nézet MARAD a resources/views/livewire/ mappában

# 5. Regisztráció a modul ServiceProvider-ében (boot metódusban):
#    Livewire::component('users.user-form', UserForm::class);
```

**Volt oldalak** (teljes oldalas Livewire komponensek) nem igényelnek áthelyezést — ezek a `resources/views/pages/`-ben maradnak, mert a nézetek központosítottak.

### Artisan command létrehozása

Az `artisan make:command` az `app/Console/Commands/`-ba generál. Moduláris commandokhoz:

```bash
# 1. Generálás
php artisan make:command FetchClarity

# 2. Áthelyezés a megfelelő modulba
mv app/Console/Commands/FetchClarity.php app/Modules/Analytics/Clarity/Commands/

# 3. Namespace frissítése:
#    App\Console\Commands → App\Modules\Analytics\Clarity\Commands

# 4. Regisztráció a modul ServiceProvider-ében (register metódusban):
#    $this->commands([FetchClarity::class]);
```

A `register()` metódusban regisztrált commandokat a Laravel automatikusan felismeri — nincs szükség a `routes/console.php`-ban való hivatkozásra.

### Job létrehozása

```bash
# 1. Generálás
php artisan make:job GenerateAiReport

# 2. Áthelyezés
mv app/Jobs/GenerateAiReport.php app/Modules/Reports/Jobs/

# 3. Namespace frissítése:
#    App\Jobs → App\Modules\Reports\Jobs
```

A jobok nem igényelnek ServiceProvider regisztrációt — a queue a teljes namespace alapján oldja fel őket. **Fontos**: ha vannak már queue-ban lévő jobok az átnevezés előtti namespace-szel, azok hibát dobnak.

### Service osztály létrehozása

Service osztályokhoz nincs artisan command — kézzel kell létrehozni:

```bash
# Hozd létre a fájlt közvetlenül a megfelelő helyen
# app/Modules/Reports/Services/ReportGeneratorService.php
```

A service-ek nem igényelnek regisztrációt — a Laravel automatikusan megoldja a dependency injection-t a type-hint alapján.

### Middleware létrehozása

```bash
# 1. Generálás
php artisan make:middleware EnsureUserRole

# 2. Áthelyezés
mv app/Http/Middleware/EnsureUserRole.php app/Modules/Users/Middleware/

# 3. Namespace frissítése:
#    App\Http\Middleware → App\Modules\Users\Middleware

# 4. Regisztráció a bootstrap/app.php-ban:
#    $middleware->alias(['user_role' => EnsureUserRole::class]);
#    VAGY
#    $middleware->web(append: [SetLocale::class]);
```

### Enum létrehozása

Enumokhoz nincs artisan command — kézzel kell létrehozni a modul `Enums/` könyvtárában, a projekt enum konvencióit követve (ld. Konvenciók szekció).

### Migration létrehozása

A migrációk **nem moduláris** — maradnak a `database/migrations/`-ban kronologikus sorrendben:

```bash
php artisan make:migration create_heatmaps_table
php artisan make:migration add_page_url_to_reports_table
```

### Seeder létrehozása

A seederek szintén a `database/seeders/`-ben maradnak:

```bash
php artisan make:seeder AiContextSeeder
```

A seederekben a moduláris namespace-eket kell használni a `use` utasításoknál.

---

## Új modul hozzáadása

1. Hozd létre a könyvtárstruktúrát az `app/Modules/UjModul/` alatt.
2. Hozz létre egy `UjModulServiceProvider.php`-t, ami az `Illuminate\Support\ServiceProvider`-t extend-eli.
3. Regisztráld a provider-t a `bootstrap/providers.php`-ban.
4. A modeleket a `Models/`-ba, az enumokat az `Enums/`-ba, stb.
5. Ha a modulnak vannak Artisan commandjai, regisztráld őket a provider `register()` metódusában.
6. Ha a modulnak vannak Livewire komponensei, regisztráld őket a provider `boot()` metódusában.
7. Más modulokra `use` utasításokkal hivatkozz.

### Almodul hozzáadása

Az almodulok egyszerűen alkönyvtárak saját namespace szegmenssel. Például az `Analytics/Clarity/` és `Analytics/Heatmaps/` az Analytics almoduljai. A szülő modul ServiceProvider-ét osztják.

---

## Konfiguráció összefoglaló

| Konfig | Értékek |
|--------|---------|
| `config/ai.php` | Alapértelmezett provider: `openai`. Kép provider: `gemini`. |
| `config/services.php` | Clarity végpont, token, napi lekérdezés limit (10). HTML fetcher timeout (10mp), max body (2MB). |
| `config/auth.php` | Eloquent provider → `App\Models\User`. Session guard. |
| `config/app.php` | Időzóna: `Europe/Budapest`. Locale: `en`. Fallback locale: `en`. |

---

## Tech Stack

| Réteg | Technológia |
|-------|-------------|
| Framework | Laravel 12 |
| Frontend | Livewire 4, Volt (single-file komponensek) |
| Stílusozás | Tailwind CSS 4.2 dark mode-dal |
| Ikonok | Phosphor Icons (`blade-phosphor-icons`) |
| Build | Vite 7 |
| AI | Laravel AI 0.4.3 (OpenAI, Gemini providerek) |
| Adatbázis | PostgreSQL |
| Queue | Database driver |
| Markdown | marked + highlight.js |
