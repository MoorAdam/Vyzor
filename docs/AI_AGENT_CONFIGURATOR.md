# AI agent konfiguráció

## Cél

A projektben több AI agent fut (jelenleg `ReportAnalyst` és `PageAnalyst`). Adminok számára kell egy beállítások oldal, ahol agent szinten összerakható a promptot felépítő kontextusok halmaza, **kód módosítása nélkül**.

## Scope

- **Ki használja:** `ADMIN` szerepkör. Globális konfiguráció, projekt-szintű override **nem** része a v1-nek.
- **Mi része v1-nek:** meglévő agent-ek (`ReportAnalyst`, `PageAnalyst`) slot-alapú konfigurációja, a most hardcode-olt slug-hivatkozások (`report-analyst-instructions`, `output-format`, `heatmap-analysis`) kivezetése.
- **Mi NEM része v1-nek:** új agent osztályok generálása UI-ról, prompt-verziózás, projekt-szintű override.

## Háttér

A jelenlegi állapot:

- A kontextusokat az `AiContext` model tárolja három típussal: `PRESET`, `SYSTEM`, `INSTRUCTION` ([AiContextType.php](app/Modules/Ai/Contexts/Enums/AiContextType.php)).
- A `ContextTag` enum jelenleg `CLARITY` és `PAGE_ANALYSER` taggekkel kategorizálja a preseteket ([ContextTag.php](app/Modules/Ai/Contexts/Enums/ContextTag.php)).
- A prompt összeállítása `ReportGeneratorService::buildPrompt()`-ban történik, és **slug alapján hardcode-olva** húzza be a SYSTEM és INSTRUCTION kontextusokat ([ReportGeneratorService.php](app/Modules/Reports/Services/ReportGeneratorService.php)).
- Az agent osztályok `instructions()` metódusa szintén slug-alapon húzza a system contextet ([ReportAnalyst.php](app/Modules/Ai/Agents/ReportAnalyst.php)).

Ez a kötés akadályozza, hogy adminként ki/be kapcsolható, cserélhető vagy újrarendezhető legyen, mit kap az agent prompt-ban.

## Megoldás

A beállítások oldalon minden agentnek van egy fix slot-készlete. Egy slot meghatározza:

1. **Mit** lehet bele tenni — milyen `AiContextType` + opcionálisan milyen `ContextTag`.
2. **Mennyit** — `min` / `max` darabszám.
3. **Hol** jelenik meg a végső promptban — fix sorrend a slotok között.

### Slotok

| Slot | Típus szűrő | Tag szűrő | Min | Max | Kötelező | Megjegyzés |
|---|---|---|---|---|---|---|
| **Rendszer** | `SYSTEM` | — | 1 | 1 | igen | Az agent legalapvetőbb rendszer instrukciója. A legtöbb agent ugyanazt használja, ezért a globális default jelenik meg előválasztva. |
| **Alaptézis** | `SYSTEM` (alaptezis taggel) | `alaptezis` | 1 | 1 | igen | Az agent specifikus feladatát írja le (pl. „te egy Clarity adatelemző vagy"). Új tagként vezetjük be — lásd Migráció. |
| **Bemeneti adatok leírása** | `INSTRUCTION` (input taggel) | `input` | 0 | n | nem | Szöveges leírás az AI-nak arról, milyen adatokat kap és honnan. **Az adatok tényleges injektálását kód végzi (lásd Adatforrások alább).** |
| **Sablonok** | `PRESET` | agent által megadott (pl. `CLARITY` vagy `PAGE_ANALYSER`) | 0 | 1 (tag) | nem | Itt egy **tag**-et választ a felhasználó, amely a riport létrehozáskor felkínálja az adott taggel ellátott összes presetet. Nem minden agentnél értelmezett. |
| **Utasítás** | `INSTRUCTION` | — | 0 | n | nem | Output formázás, nyelvi instrukciók, egyéb kiegészítések. A jelenleg auto-injektált `output-format` és `heatmap-analysis` ide kerül. |

A slotok **fix sorrendben** kerülnek a promptba (Rendszer → Alaptézis → Bemeneti leírás → Sablon → adatforrások által termelt tartalom → Utasítás). Egy sloton belül több kontextus esetén az `AiContext.sort_order` dönt.

### Adatforrások (kód oldal)

A „Bemeneti adatok leírása" slot **csak szöveg** — leírja az AI-nak, mit jelent a kapott adat. A **tényleges adat lekérése** (Clarity API, HTML letöltés, heatmap CSV) kód által regisztrált `DataSource` osztályok feladata. Az adminok csak engedélyezhetik/letilthatják ezeket az agent szintjén.

Ez a kettéválasztás biztosítja, hogy:
- Új adatforrás hozzáadása = új `DataSource` osztály regisztrálása az `AiServiceProvider`-ben.
- A „mit jelent az adat" magyarázata UI-ról szerkeszthető szöveg marad.
- Nem keveredik a futási idejű lekérés a prompt szövegével.

### Agent registry

Ahhoz, hogy a beállítások oldal felismerje a meglévő agent-eket, kell egy registry. Javaslat: az `AiServiceProvider` regisztrálja a futtatható agent-eket egy konténer-bindolt `AgentRegistry`-be:

```php
$registry->register(ReportAnalyst::class, [
    'key' => 'report_analyst',
    'label' => __('Riport elemző'),
    'preset_tag' => ContextTag::CLARITY,
    'data_sources' => [ClarityDataSource::class, HeatmapDataSource::class],
]);
```

Új agent hozzáadása így: PHP osztály létrehozása + 1 sor regisztráció. A UI dinamikusan listázza.

### Adatmodell javaslat

Új tábla a slot-választások tárolására:

```
agent_configurations
- id
- agent_key                  string, unique  (pl. 'report_analyst')
- system_context_id          fk → ai_contexts.id, nullable
- alaptezis_context_id       fk → ai_contexts.id, nullable
- preset_tag                 string, nullable  (ContextTag value)
- created_at, updated_at
```

Több-az-egyhez kapcsolatok pivot táblában (a sorrend miatt `position` oszloppal):

```
agent_configuration_contexts
- agent_configuration_id     fk
- ai_context_id              fk
- slot                       enum ('input_description' | 'instruction')
- position                   integer
```

### Engedélyezett adatforrások

```
agent_configuration_data_sources
- agent_configuration_id     fk
- data_source_key            string  (pl. 'clarity', 'heatmap', 'page_html')
- enabled                    boolean
```

## UI

A `resources/views/pages/settings/` alatt új Volt oldal, az agent registry-ből listázott agent-ek mindegyikéhez egy szerkesztő nézettel. Slotonként:

- **Rendszer / Alaptézis:** `select` egy elemmel.
- **Sablonok:** tag választó (a registry-ben deklarált `preset_tag` az alapértelmezett).
- **Bemeneti leírás / Utasítás:** sorrendezhető, többválasztós lista (drag-and-drop vagy fel/le gombok).
- **Adatforrások:** checkbox lista a registry-ből.

A modell-kompatibilitást (lásd alább) inline figyelmeztetés jelzi, nem blokkolja a mentést.

## Migráció

A jelenleg hardcode-olt slug-okat seederrel kell az új struktúrára áthozni:

| Jelenlegi hardcode | Új besorolás |
|---|---|
| `report-analyst-instructions` (SYSTEM) | `report_analyst.system_context_id` |
| `page-analyst-instructions` (SYSTEM) | `page_analyst.system_context_id` |
| `output-format` (INSTRUCTION) | mindkét agent **Utasítás** slotja |
| `heatmap-analysis` (INSTRUCTION) | `report_analyst` **Utasítás** slot, csak akkor injektálódik, ha a `heatmap` adatforrás engedélyezett |

Az `Alaptézis` taghez új `ContextTag::ALAPTEZIS` esetet kell felvenni, és a meglévő SYSTEM kontextusokat áttagolni — vagy alternatívaként új `AiContextType::ALAPTEZIS` típust bevezetni. Az enum bővítés tisztább, de migrációs lépést igényel a `ai_contexts.type` oszlopon.

A `ReportGeneratorService::buildPrompt()` átalakul: a slug-alapú lekéréseket az `agent_configurations` betöltése váltja, és a slotokon végigiterál fix sorrendben.

## Nyitott kérdések

1. **Riport-reprodukálhatóság.** Ha egy admin átállítja `ReportAnalyst` konfigját, a régi riportok promptja már nem rekonstruálható. Két opció:
   - **(a)** A `Report` modellbe kerül egy `prompt_snapshot` mező (a teljes összerakott prompt) — egyszerű, de tárhely-igényes.
   - **(b)** `agent_configurations` verziózott (immutable revíziók), a `Report` egy `agent_configuration_revision_id`-t tárol.
   - **Javaslat:** v1-ben (a), mert a riportok átláthatók maradnak audit szempontból, és a tárhely nem szűk keresztmetszet.

2. **Modell-kompatibilitás validáció szigorúsága** 
A `scopeForModel()` jelenleg némán szűr. Mentésnél figyelmeztessünk, ha az aktív provider nem fedi a kiválasztott kontextusokat? Futáskor logoljunk warningot? **Javaslat:** mentésnél inline warning, futáskor néma kihagyás (jelenlegi viselkedés megtartása).

3. **Üres kötelező slot.** Ha valaki egy kötelező slotot üresen ment (pl. seedeléskor), futáskor mi történjen? **Javaslat:** mentésnél validáció blokkolja; futáskor exception.

4. **Per-project override.** Több ügyfél eltérő stílusú riportot szeretne. Kell-e projekt szintű felülírás? **Javaslat:** v2 — előbb derüljön ki, kell-e egyáltalán.

5. **`Sablonok` slot szemantikája.** Most a doksi szerint a Sablonok slot 1 taget választ ki, és a riport létrehozó űrlap az adott taggel jelölt összes presetet felkínálja. Ez egybeesik a jelenlegi viselkedéssel ([ReportGeneratorService.php:62-72](app/Modules/Reports/Services/ReportGeneratorService.php#L62-L72)) — ezt a slotot tehát az agent registry `preset_tag` mezője is fedhetné. Kérdés: maradjon-e külön slot a UI-ban, vagy elég a registry-ben deklarálni?
