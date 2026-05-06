# Vyzor — dokumentáció

Ez a könyvtár a Vyzor projekt dokumentációját tartalmazza, három kategóriába rendezve.

## [usage/](usage/) — Termék-dokumentáció

Felhasználóknak / új belépőknek arról, **mit tud** a Vyzor.

| Fájl | Tartalom |
|------|----------|
| [`usage/product-tour.md`](usage/product-tour.md) | Magyar termékbemutató — modulok, navigáció, képernyőképek |

## [dev/](dev/) — Fejlesztői referencia

A kódbázis **jelenlegi állapotának** leírása. Akkor olvasd, ha új vagy a projekten, vagy egy konkrét területhez nyúlsz.

| Fájl | Tartalom |
|------|----------|
| [`dev/project-structure.md`](dev/project-structure.md) | Moduláris monolit felépítés — namespace-ek, modulok, fájl-térkép |
| [`dev/tech.md`](dev/tech.md) | Tech-stack inventár (PHP, Laravel, Livewire, AI providerek, …) |
| [`dev/roles.md`](dev/roles.md) | Szerepkörök és jogosultsági modell — `Gate::before`, `UserRoleEnum`, collaborator pattern |
| [`dev/reports-ui.md`](dev/reports-ui.md) | Riport-oldalak újrahasználható komponensei (preset-grid, preset-preview, recent-reports, …) |
| [`dev/ga-ai-reports.md`](dev/ga-ai-reports.md) | A GA AI riport pipeline — page → form → job → service → AI agent + tool, failure handling, retry |

## [plans/](plans/) — Tervek és proposalok

Készülő, halasztott vagy javasolt funkciók. Akkor olvasd, ha valami **épülő** dologhoz nyúlsz, vagy a kontextust keresed egy nemrég hozott döntéshez.

| Fájl | Tartalom |
|------|----------|
| [`plans/ai-agent-configurator.md`](plans/ai-agent-configurator.md) | Admin AI-agent konfigurátor UI — scope, current state, megoldási javaslat |
| [`plans/hotjar-analysis.md`](plans/hotjar-analysis.md) | Hotjar AI-elemzés — kutatás, hibrid megközelítés, státusz |
| [`plans/google-analytics-integration.md`](plans/google-analytics-integration.md) | GA4 integráció architektúrája és implementációs terve |
| [`plans/google-analytics-future-work.md`](plans/google-analytics-future-work.md) | GA — halasztott / nem implementált pontok |

## [images/](images/) — Termékbemutató képek

A `usage/product-tour.md` által hivatkozott screenshot-ok. Új doc-ban való hivatkozás formája: `![alt](../images/image-N.png)`.

---

## Hova tegyek új doksit?

- **„Hogy néz ki a kód most?"** → `dev/`. Ha egy modul, komponens-csoport vagy konvenció leírása.
- **„Mit fogunk csinálni?"** → `plans/`. Ha proposal, design-dok, vagy nyitott kérdések listája.
- **„Mit tud a termék?"** → `usage/`. Ha végfelhasználó / kliens szempontjából írod.

A repo gyökerében az [`agents.md`](../agents.md) a contributor-szabályokat (kódkonvenciók, git-szabályok) tartja — abba akkor írj, ha **hogyan** dolgozunk a kódbázisban.
