# Hotjar AI-alapú elemzés

**Létrehozva**: 2026-02-27
**Frissítve**: 2026-02-27
**Státusz**: aktív
**Felelős**: Pelczer Dániel + Fárbás Fanni

## Cél

Fárbás Fanni manuális Hotjar session-elemzési munkáját AI-alapú automatizációval részben kiváltani. Hotel partner weboldalakhoz UX elemzési riportokat generálni gyorsabban és egységes formátumban.

## Valóságos helyzet (API kutatás alapján)

Hotjar session felvételek közvetlenül nem elemezhetők AI-val:

| Lehetőség | Státusz | Ok |
|-----------|---------|-----|
| Hotjar REST API | ⚠️ korlátozott | Nincs recording events export; csak metadata CSV, surveys, feedback |
| Hotjar MCP szerver | ❌ nincs | Csak 3rd party (Zapier/Pipedream), recording adatot nem ad |
| Videó export API | ❌ nem lehetséges | Hotjar recording DOM event stream, nem valódi videófájl |
| Claude + videó | ❌ nem lehetséges | Claude nem tud videófájlt elemezni, csak képeket |

**Következmény**: az AI a videónézést nem váltja ki — azt helyettesíti, hogy Fanni maga írja meg a teljes riportot.

## Hibrid megközelítés

1. Fanni megnézi a Hotjar felvételeket (marad)
2. Kulcsmomentumokról screenshotot készít (heatmap export, recording pillanatkép)
3. Az n8n form-ban rögzíti a megfigyeléseit + feltölti a képeket
4. Claude Vision + AI Agent profi, egységes formátumú riportot generál
5. Riport PDF-ben és/vagy Google Doc-ban landol emailen

## Egyetlen workflow architektúra

```
[Form trigger: Fanni indítja]
        ↓
[Switch: Van-e feltöltött fájl? (kép / CSV)]
    ├── A: CSV/kép feldolgozás (Code node)
    └── B: Csak form adatok
        ↓
[Code: Adatok normalizálása, Claude-nak előkészítés]
        ↓
[MySQL: Raw data mentés → hotjar_raw_inputs]
        ↓
[Switch: Van-e képfájl?]
    ├── Igen → Claude Vision (Messages API)
    └── Nem  → Claude AI Agent (szöveges elemzés)
        ↓
[Code: Elemzés egységesítése JSON sémára]
        ↓
[MySQL: AI eredmény mentés → hotjar_ai_results]
        ↓
[Switch: Riport formátum]
    ├── PDF   → HTML template → Convert to PDF
    └── Google Doc → Google Docs API
        ↓
[Email: Riport elküldése Fanninak]
```

## Form mezők (Fanni tölti ki)

- Hotel neve + domain
- Elemzés típusa: `onboarding_audit` | `havi_riport`
- Dátumtartomány
- Hotjar Site ID
- Megfigyelések (szabad szöveges mező)
- Fájl feltöltés (opcionális): heatmap képek, recording screenshot, CSV export

## Riport struktúra

1. **Összefoglaló** — 3-5 mondatos executive summary
2. **Forgalmi adatok** — session szám, eszközök, legnépszerűbb oldalak
3. **UX problémák** — rage clicks, form abandonment, kilépési pontok
4. **Fejlesztési javaslatok** — Kritikus / Fontos / Nice-to-have
5. **Következő lépések** — konkrét teendők

## DB séma (MySQL, 2 tábla)

**`hotjar_raw_inputs`**: hotel adatok, elemzés típus, dátumtartomány, raw JSON, fájl referenciák

**`hotjar_ai_results`**: executive summary, traffic_data JSON, ux_problems JSON, recommendations JSON, next_steps, report_url

## Döntések

- 2026-02-27: Két workflow helyett egyetlen workflow tervezve
- 2026-02-27: n8n-alapú architektúra, Claude AI elemzés (Vision + Agent)
- 2026-02-27: Hotjar API korlátozott → hibrid megközelítés (form + screenshot feltöltés)
- 2026-02-27: Riport kimenet: PDF + Google Doc (workflow switch-el)

## Következő lépések

1. MySQL adatbázis táblák létrehozása
2. n8n workflow megépítése
3. Claude prompt megírása (strukturált UX elemzés sablonnal)
4. PDF/Google Doc template elkészítése
5. Teszt: egy valódi hotel esetén Fanni-val validálni

## Részletes terv

`~/.claude/plans/majestic-doodling-cloud.md`
