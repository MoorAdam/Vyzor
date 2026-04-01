# Checklist — Hotel UX elemzés automatizálása (Hotjar + Clarity)

## 0. Kutatás és döntéselőkészítés

- [x] Hotjar API/MCP megléte ellenőrizve → korlátozott, videó export nem lehetséges
- [x] Screenshot alapú megközelítés elvetve → nem skálázható
- [x] Microsoft Clarity megvizsgálva → ingyenes, teljes API, MCP szerver
- [x] Hotjar webhook payload megvizsgálva → recording metadata (rage click boolean, page_urls)
- [x] Javasolt stratégia: Hotjar (survey/NPS) + Clarity (viselkedéselemzés + AI automatizálás)
- [x] Döntéssegítő Excel elkészítve → `hotjar-clarity-osszehasonlitas.xlsx`

## 1. Stratégiai meeting ← KÖVETKEZŐ LÉPÉS

- [ ] **MEETING megtartva** (résztvevők: Pelczer D., Fárbás F., döntéshozó vezető)
  - Napirend: döntéssegítő Excel áttekintése, Clarity bevezetés zöld lámpája, pilot hotel kijelölése
  - Asana subtask: [GID 1213468881931053]
- [ ] Döntés rögzítve: Clarity bevezetés jóváhagyva igen/nem
- [ ] Pilot hotelok kijelölve (javasolt: 2-3 db)
- [ ] Felelősök és határidők meghatározva

## 2. Microsoft Clarity bevezetés (meeting után)

- [ ] Clarity fiók létrehozva (ingyenes regisztráció)
- [ ] Projektek létrehozva hotelenként a Clarity dashboardon
- [ ] Tracking kód telepítve a pilot hotel weboldalakra
- [ ] Adatok megjelennek a Clarity dashboardon (ellenőrzés ~30 perc után)
- [ ] Clarity API kulcs generálva (Settings → Data Export)

## 3. n8n workflow megépítése

- [ ] HTTP Request node: Clarity API lekérés (rage click, dead click, scroll depth, quickback per URL)
- [ ] Schedule trigger: heti automatikus futás
- [ ] Code node: adatok normalizálása, hotel-enkénti összesítés
- [ ] MySQL node: adatok mentése
- [ ] Claude AI Agent: UX riport generálás (hotel UX szakértő prompt)
- [ ] PDF ág: HTML template → Convert to PDF
- [ ] Email node: riport automatikus küldése Fanninak / account managernek

## 4. Claude prompt

- [ ] Prompt struktúra megírva (hotel UX szakértő szerep)
- [ ] JSON output séma definiálva (`executive_summary`, `ux_problems[]`, `recommendations[]`, `next_steps`)
- [ ] Prompt tesztelve + finomhangolva valódi Clarity adatokon

## 5. Riport template

- [ ] HTML riport template elkészítve (Morgens branding)
- [ ] PDF export tesztelve
- [ ] Riport struktúra validálva Fanni-val

## 6. Tesztelés és élesítés

- [ ] Pilot teszt: 2-3 hotel, valódi Clarity adatokkal
- [ ] Fanni visszajelzése → prompt / riport finomhangolás
- [ ] Fokozatos rollout: 150+ hotel partnerre

## Asana

- GID: 1213407669325996
- Link: [Asana task](https://app.asana.com/1/1200023430233857/project/1210000458782103/task/1213407669325996)
- Meeting subtask GID: 1213468881931053
