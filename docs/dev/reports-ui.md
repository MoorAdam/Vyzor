# Reports UI — komponensek

Fejlesztői referencia a riport-oldalak újrahasználható komponenseihez. Ez a dokumentum a `Reports` modul UI rétegét írja le — mit használj, mikor, és hogy bővítsd új flavor-rel (pl. egy új analitikai forrás riportja).

---

## Mit nyer a fejlesztő

Három riport-oldal él a kódbázisban (`/clarity-report`, `/google-analytics/report`, `/ai-reports`), és három riport-fül (Clarity, GA, Page). Mindegyik oldalon ugyanaz az építőelem-készlet ismétlődik: preset-rács, preset-előnézet, riport-lista. Hogy ezek ne sokszorozódjanak, ki vannak emelve önálló komponensekké:

| Komponens | Típus | Hol él | Mit csinál |
|-----------|-------|--------|------------|
| `<x-reports.preset-grid>` | Statikus Blade | [`resources/views/components/reports/preset-grid.blade.php`](../../resources/)views/components/reports/preset-grid.blade.php) | Preset választó kártya-rács (radio inputok), kiemelt eye-gombbal a preview-hoz |
| `<x-reports.preset-preview>` | Statikus Blade | [`resources/views/components/reports/preset-preview.blade.php`](../../resources/)views/components/reports/preset-preview.blade.php) | Sticky oldalsáv: vagy a kiválasztott preset tartalmát mutatja, vagy egy üres placeholdert |
| `<x-reports.report-card>` | Statikus Blade | [`resources/views/components/reports/report-card.blade.php`](../../resources/)views/components/reports/report-card.blade.php) | Egyetlen riport sor (cím + meta + státusz badge), kattintható kártya |
| `<livewire:recent-reports>` | Volt SFC | [`resources/views/components/⚡recent-reports.blade.php`](../../resources/)views/components/⚡recent-reports.blade.php) | Reaktív lista a legutóbbi riportokról; saját poll-ja, opcionális tag-szűrő |

> **Tervezési alapelv:** ha egy darabnak nincs saját állapota, statikus Blade komponens (`<x-foo>`), ami kizárólag Sheaf UI primitívekből (`<x-ui.*>`) van összerakva. Ha saját állapota van (pl. polling), Livewire komponens. Ne vegyíts: ne legyen rejtett state egy statikus komponensben.

---

## `<x-reports.preset-grid>` — preset választó

Radio-card grid az aktív preset-ekhez. A kiválasztás állapota a *szülő* Livewire komponensén él (`$preset` property), a grid csak megjeleníti és bind-eli.

### Props

| Név | Típus | Default | Leírás |
|-----|-------|---------|--------|
| `presets` | `Collection<AiContext>` | **kötelező** | Megjelenítendő preset-ek (pl. tag-szűrt lista) |
| `selected` | `string` | `''` | A pillanatnyi kiválasztás slug-ja — vizuális kiemelés + eye-gomb láthatóság |
| `model` | `string` | `'preset'` | A szülő Livewire komponens property-jének neve, amibe `wire:model.live` köti a választást |
| `previewAction` | `string` | `'previewPreset'` | A szülő `wire:click` action neve az eye-gombnál. Üres string letiltja az eye-gombot |
| `emptyMessage` | `?string` | `null` | Üres collection esetén megjelenő szöveg. `null` = semmit ne mutass |

### Példa

```blade
<x-reports.preset-grid
    :presets="$this->presets"
    :selected="$preset"
    :emptyMessage="__('No presets available. Create one tagged GA in settings.')"
/>
```

### Mit *kell* a szülőnek implementálnia

1. Egy public property (default `$preset`).
2. Egy `previewPreset(string $slug)` action (default).
3. (Opcionálisan) egy `closePreview()` action a preview komponenshez.

A defaultok mindenhol megegyeznek, ezért általában elég a `:presets` és `:selected` átadása.

---

## `<x-reports.preset-preview>` — preset előnézet

Sticky kártya oldalsávra. Két állapota van: nyitva (preset neve + tartalma) vagy zárva (placeholder + ikon). A láthatóságot, nevet, tartalmat a *szülő* állapota kontrollálja.

### Props

| Név | Típus | Default | Leírás |
|-----|-------|---------|--------|
| `visible` | `bool` | `false` | Nyitva vagy zárva van-e a preview |
| `name` | `string` | `''` | Preset neve (heading) — csak nyitott állapotban |
| `content` | `string` | `''` | Preset tartalma (markdown forrás) — `<pre>`-ben renderelődik |
| `accent` | `string` | `'blue'` | Bal-szegély színe. Egy a következőkből: `blue`, `amber`, `emerald`, `violet`, `rose`, `neutral` |
| `closeAction` | `string` | `'closePreview'` | A szülő `wire:click` action neve a bezárás gombnál |

### Példa

```blade
<x-reports.preset-preview
    :visible="$showPresetPreview"
    :name="$presetPreviewName"
    :content="$presetPreviewContent"
    accent="amber"
/>
```

### Színek és Tailwind

Az `accent` prop egy literál szót vesz, ami egy `match` blokkon át fix Tailwind class-szá alakul (`border-l-amber-500` stb.). Ez azért van így, mert a Tailwind statikus analízissel gyűjti a class-okat — `border-l-{$color}-500` típusú dinamikus class **nem kerülne be a build-be**. Ha új színt akarsz felvenni, vedd fel a `match`-be a fájlon belül.

---

## `<x-reports.report-card>` — egyetlen riport sor

Tiszta nézet komponens egy `Report` modellhez. Megjelenít: ikon (AI vagy ember), cím, preset chip (ha van), dátumtartomány, page URL (ha van), relatív idő, státusz badge. Kattintható kártya, ami `/reports/{id}` címre visz.

### Props

| Név | Típus | Default | Leírás |
|-----|-------|---------|--------|
| `report` | `App\Modules\Reports\Models\Report` | **kötelező** | A megjelenítendő riport |

### Példa

```blade
@foreach ($reports as $report)
    <x-reports.report-card :report="$report" />
@endforeach
```

Általában nem közvetlenül használod — `<livewire:recent-reports>` alól kerül elő. De akárhol jó, ahol egyetlen riport sor kell (pl. dashboard widget).

---

## `<livewire:recent-reports>` — reaktív lista

Önálló Livewire komponens (Volt SFC). Lekérdezi a legutóbbi `$limit` riportot a jelenlegi project-re (session `current_project_id`), opcionálisan tag-re szűrve, majd `<x-reports.report-card>`-ekkel renderel.

**Saját pollingja van:** ha a látható listán bármelyik riport `PENDING` vagy `GENERATING` státuszban van, 10 másodpercenként újrarenderel. Ha mindegyik kész, nem poll-ol. A szülő oldalnak nem kell `wire:poll`-t tennie a wrapper div-re.

### Props

| Név | Típus | Default | Leírás |
|-----|-------|---------|--------|
| `tag` | `?string` | `null` | `ContextTag` érték (pl. `ContextTag::GA->value`) — csak olyan riportokat mutat, amelyek preset-je ezzel a tag-gel rendelkezik. `null` = nincs szűrés |
| `limit` | `int` | `5` | Hány sort mutasson |
| `heading` | `?string` | `__('Recent Reports')` | A lista feletti címke |
| `emptyMessage` | `?string` | `__('No reports yet.')` | Üres állapotban megjelenő szöveg |
| `viewAllUrl` | `?string` | `'/reports'` | A "View All" gomb cél URL-je. Pass `null` to hide the button |

### Példa — Clarity oldalon (nincs tag-szűrés)

```blade
<livewire:recent-reports
    :emptyMessage="__('No reports yet. Request an AI report above.')"
/>
```

### Példa — GA oldalon (csak GA-tagged preset-ek)

```blade
<livewire:recent-reports
    :tag="ContextTag::GA->value"
    :heading="__('Recent GA Reports')"
    :emptyMessage="__('No GA reports yet. Request an AI report above.')"
/>
```

### Hogyan működik a tag-szűrés

A komponens először lekérdezi az `ai_contexts` táblából azokat a preset slug-okat, amelyek `tags` tömbjében szerepel a megadott érték (`whereJsonContains`), majd a `reports.preset` slug-mezőre `whereIn`-nel szűr. Tehát egy riport akkor látszik, ha a preset-je *jelenleg* tag-elve van — ha utólag eltávolítod a tag-et, a régi riport is eltűnik a szűrt listából.

### Project scope

A komponens session-alapú (`session('current_project_id')`). Ha nincs project kiválasztva, üres collection-t ad vissza — nem ugat fel. Project-váltáskor a normál Livewire re-render kezeli.

---

## Új flavor felvétele (recept)

Tegyük fel, beépítenél egy harmadik analitikai forrást (pl. Plausible). Lépések:

1. **Tag** — `App\Modules\Ai\Contexts\Enums\ContextTag`-be új case (pl. `PLAUSIBLE = 'plausible'`).
2. **System context** — egy új system instructions markdown a `resources/ai-prompts/`-ban + seeder bejegyzés `AiContextType::SYSTEM` típussal és `[ContextTag::PLAUSIBLE]` taggel (lásd [`AiContextSeeder.php`](../../database/)seeders/AiContextSeeder.php)).
3. **Preset(ek)** — markdown(ok) `resources/ai-prompts/presets/` alatt + seeder bejegyzés(ek) `AiContextType::PRESET` típussal és `[ContextTag::PLAUSIBLE]` taggel.
4. **Flavor a service-ben** — `ReportGeneratorService::resolveFlavor()` és `resolveAgent()` kapjon egy `'plausible'` ágat. Az agent maradhat `ReportAnalyst` egy másik `instructionsSlug`-gal — nem kell külön agent osztály. Egy `buildPlausiblePrompt()` metódus a forrás-specifikus adat-blokkhoz.
5. **Form-fül** — új Volt SFC `resources/views/components/⚡plausible-report-tab.blade.php`. Csak a flavor-specifikus mezők egyediek — a preset-rácshoz és preview-hoz használd a meglévő komponenseket:
   ```blade
   <x-reports.preset-grid :presets="$this->presets" :selected="$preset" />
   <x-reports.preset-preview :visible="$showPresetPreview" :name="$presetPreviewName" :content="$presetPreviewContent" accent="rose" />
   ```
6. **Oldal** — új Volt SFC `resources/views/pages/⚡plausible-report.blade.php` (használd a Clarity vagy GA oldalt mintának), `<livewire:recent-reports :tag="ContextTag::PLAUSIBLE->value" ... />`-vel a lista.
7. **Route** + **navigáció** — egy `Route::livewire(...)` a `routes/web.php`-ban + egy `<x-ui.navlist.item>` a `resources/views/layouts/app.blade.php`-ban.

A 4. ponton túl nincs UI-szintű kódduplikáció — a komponensek mindent fednek.

---

## Mit ne csinálj

- **Ne használd az új komponenseket statikus, project-független helyeken** — a `<livewire:recent-reports>` session-alapú project scope-ot feltételez. Ha valaha multi-project nézet kell, a komponensbe property-t kell felvenni (`projectId`), mert most session-tól függ.
- **Ne adj a `preset-grid`-be saját preview-state-et.** A preview állapota direkt a szülő Volt SFC-jén él, hogy a két komponens ne legyen csatolva — kicserélhető legyen az egyik a másik nélkül.
- **Ne építs új `<x-foo>` Blade wrapert pusztán azért, hogy elnevezz pár Sheaf primitívet.** Ha nincs valódi újrafelhasználás, inline a `<x-ui.*>`-t. Ezek a komponensek (`reports.*`) három helyen visszatérnek — ezért éri meg.
- **Ne dinamizáld a Tailwind class neveket.** `border-l-{{ $color }}-500` nem fog renderelni; a build-time analízis nem találja meg. Mindig literál szóként szerepeljen a class.

---

## Gyors keresési térkép

Ha keresed a kódot a következőkhöz:

| Téma | Hol nézd |
|------|----------|
| Új preset definíció | [`database/seeders/AiContextSeeder.php`](../../database/)seeders/AiContextSeeder.php) |
| Preset metaadat-modell | [`app/Modules/Ai/Contexts/Models/AiContext.php`](../../app/)Modules/Ai/Contexts/Models/AiContext.php) |
| Tag enum | [`app/Modules/Ai/Contexts/Enums/ContextTag.php`](../../app/)Modules/Ai/Contexts/Enums/ContextTag.php) |
| Riport model | [`app/Modules/Reports/Models/Report.php`](../../app/)Modules/Reports/Models/Report.php) |
| Generator + flavor router | [`app/Modules/Reports/Services/ReportGeneratorService.php`](../../app/)Modules/Reports/Services/ReportGeneratorService.php) |
| AI agent (Clarity & GA) | [`app/Modules/Ai/Agents/ReportAnalyst.php`](../../app/)Modules/Ai/Agents/ReportAnalyst.php) |
| AI agent (Page) | [`app/Modules/Ai/Agents/PageAnalyst.php`](../../app/)Modules/Ai/Agents/PageAnalyst.php) |
