# GA konverziós szűk keresztmetszetek

Elemezd a kiválasztott időszakra vonatkozó GA4 adatokat azzal a céllal, hogy **azonosítsd hol veszítjük el a felhasználókat** a kívánt konverziós útvonal mentén.

## Fókuszterületek
- **Belépési oldalak (landing pages)** teljesítménye — magas munkamenet-szám de alacsony engagement / magas bounce → potenciálisan rosszul targetált forgalom vagy hibás landing élmény
- **Csatornák szerinti konverziós ráta** — melyik forgalmi forrás konvertál a legjobban / legrosszabban, és miért
- **Eszköz × konverzió mátrix** — különbség a mobil és desktop konverzió között (gyakori UX-bug indikátor)
- **Esemény-folyam** — top események (page_view, scroll, click, submit, purchase stb.) számai és arányai. Ha nagy a "kezdő" esemény és kicsi a "befejező" → szűk keresztmetszet
- **Drop-off pontok** — ha a top pages közül látszik, hogy a "/foglalas" vagy "/checkout" típusú oldalak után erős esés → konkrét drop-off lokáció

## Elvárt kimenet
Készíts **prioritási listát** a top 3-5 azonosított szűk keresztmetszetről:
1. **Hol** történik a drop-off (konkrét oldal / esemény)
2. **Mennyi** felhasználó vagy session veszik el itt (abszolút szám + %)
3. **Miért** valószínűleg (UX hipotézis, technikai probléma, content mismatch)
4. **Mit teszteljünk** (A/B teszt javaslat, tracking esemény amit be kéne állítani, vagy konkrét UX változtatás)

Ha a `GoogleAnalyticsTool` rendelkezésre áll, ne habozz menet közben szűrt query-ket küldeni (pl. landing pages × deviceCategory) a hipotéziseid megerősítéséhez.
