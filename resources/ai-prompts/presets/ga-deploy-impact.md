# GA deploy / kampány hatás-elemzés

Hasonlítsd össze a kiválasztott időszakot az előző azonos hosszúságú időszakkal, és **azonosítsd a jelentős változásokat**. Tipikus felhasználási eset: egy új deploy, design változtatás vagy marketing kampány utáni "mit változtatott meg" elemzés.

## Fókuszterületek
- **Mutatók szintű deltákat** számszerűsítsd: sessions, users, engagement rate, bounce rate, conversion — minden esetben **abszolút változás + %**
- **Csatorna-szintű változások**: melyik csatorna nőtt / csökkent leginkább. Ha egy csatorna hirtelen visszaesett, az gyakran tracking probléma vagy SEO hatás
- **Oldal-szintű változások**: mely oldalak forgalma esett be / kapott boost-ot. Az új vagy újratervezett oldalak engagementje az előzőhöz képest
- **Eszköz-szintű különbségek**: ha mobil engagement zuhant de desktop nem → mobil-specifikus probléma (UX, performance)
- **Földrajzi kiugrások**: új ország jelenik meg vagy egy meglévő esett vissza

## Elvárt kimenet
Strukturált változás-jelentés:

1. **Összegzés**: 1-2 mondatos overall verdict — javult, romlott, vegyes
2. **Top 3 pozitív változás** (mit nyertünk)
3. **Top 3 negatív változás** (mit veszítettünk) — ezek a legfontosabbak, kiemelve
4. **Hipotézisek**: minden negatív változáshoz adj 2-3 lehetséges magyarázatot (deploy okozhatta? tracking elveszett? külső hatás?)
5. **Mit ellenőrizzünk most**: konkrét lépések a hipotézisek validálására (pl. "Nyisd meg a /pricing oldalt különböző böngészőkben", "Nézd meg a console hibákat mobilon")

Statisztikai megbízhatóság: ha a változás <10% és a minta-méret kicsi (<100 sessions), jelezd, hogy az eltérés statisztikai zaj is lehet.
