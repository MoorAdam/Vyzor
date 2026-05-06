# Vyzor

A Vyzor a Morgens egységes weblap analizáló felülete. A rendszer körbeölel különböző külső és belső elemző rendszereket, mint a Microsoft Clarity, valamint AI alapú report író eszközöket.

---

A rendszer több modulra van felosztva. Ezeket a modulokat folyamatosan bővítjük több elemző eszközzel is.

Minden modul projecthez kötött. Ez azt jelenti, hogy egy project kiválasztása után érhetőek csak el, és a modulokat project szinten kell konfigurálni.

> A felület két nyelven érhető el: **magyar** és **angol**. A nyelv a jobb felső sarokban váltható.

---

## Navigáció

A bal oldali navigáció az alábbi struktúrát követi:

| Csoport | Oldal | Leírás |
|---------|-------|--------|
| **Általános** | Projektek | Projektek listázása, létrehozása, kezelése |
| **Projekt > Clarity** | Pillanatkép | Aktuális Clarity metrikák |
| | Trendek | Időszakos változások elemzése |
| **Projekt > Jelentések** | Új jelentés | Report kérelem indítása |
| | Összes jelentés | Korábbi reportok listája |
| **Projekt > Hőtérképek** | Feltöltés | CSV hőtérkép feltöltése |
| | Összes hőtérkép | Feltöltött hőtérképek kezelése |
| **Rendszer** | Felhasználók / Ügyfelek | Felhasználók és ügyfelek kezelése |
| | Beállítások > Kontextusok | AI promptok konfigurálása |

> A **Prezentációk** menüpont jelenleg fejlesztés alatt áll.

---

## Projektek

A projekt a Vyzor központi szervező egysége. Minden adat (Clarity metrikák, reportok, hőtérképek) egy-egy projekthez tartozik.

Egy projektnek a következő tulajdonságai vannak:

- **Név** és **leírás**
- **Domain** — a weblap címe, amelyhez a projekt tartozik
- **Clarity API kulcs** — a Microsoft Clarity integráció azonosítója (titkosítva tárolt)
- **Státusz** — a projekt aktuális állapota:

| Státusz | Szín | Jelentés |
|---------|------|----------|
| Aktív | Kék | Folyamatban lévő projekt |
| Befejezett | Zöld | Lezárt projekt |
| Elhalasztott | Sárga | Átmenetileg szüneteltetett |
| Megszakított | Piros | Véglegesen leállított |
| Prezentáció | Lila | Bemutató állapotban |

### Tulajdonos és közreműködők

Minden projektnek van egy **tulajdonosa** (owner), valamint lehetőség van **közreműködőket** (collaborator) hozzárendelni. A jogosultsági rendszer megkülönbözteti, hogy egy felhasználó saját, közreműködői, vagy az összes projektet láthatja-e.

---

## Clarity

A Microsoft Clarity egy felhasználói viselkedés elemző szoftver. A Vyzor API-n keresztül kér le viselkedési információkat a Clarity rendszeréből, és használja fel elemzésre.

### Pillanatkép (Snapshot)

A rendszer pillanatképekre osztja fel a lekért adatokat. Ezek egy adott időpontra vonatkozó lekérések a Clarityből, amelyek a következő metrikákat tartalmazzák:

- **Áttekintés** — munkamenetek, egyedi felhasználók, oldalak/munkamenet, átlagos görgetési mélység
- **Felhasználói elköteleződés** — teljes idő, aktív idő
- **UX jelzések** — halott kattintások, dühös kattintások, gyors visszalépések, túlzott görgetés, szkript hibák, hiba kattintások
- **Böngészők, eszközök, operációs rendszerek** — részletes bontás

![clarity-snapshot](../images/image-3.png)

Minden nap a Clarity **10 lekérést** engedélyez. A rendszer automatikusan indít lekérést az adott napra, így a felhasználó nem marad le nagyobb történésekről.
Amennyiben a felhasználó mégis szeretne manuálisan lekérni, az **Adatok lekérése** gombbal meg tudja tenni.

Lehet látni az utóbbi lekért adatok idejét és periódusát. A felhasználó maximum az elmúlt 3 nap adatait kérheti le.

![clarity-snapshot-lekérés-modal](../images/image-5.png)

### Trendek

Átfogó táblázatok különböző jelenségeknek a változásáról egy adott időszakban.

![clarity-trends](../images/image-2.png)

---

## Reportok

A felhasználó képes a többi modul információival AI alapú elemzéseket kérni.
Minden reportot egy előre megadott **kontextus** (sablon) alapján lehet lekérni, amely meghatározza a report elemzési szempontjait és kimeneti formátumát.
[A kontextusokról bővebben lentebb](#kontextusok).

### Report állapotok

Egy report a következő állapotokon mehet keresztül:

| Állapot | Szín | Jelentés |
|---------|------|----------|
| Piszkozat | Szürke | Kezdeti állapot |
| Várakozik | Sárga | Sorba állítva generálásra |
| Generálás | Kék | Feldolgozás alatt |
| Kész | Zöld | Sikeresen elkészült |
| Sikertelen | Piros | Hiba történt a generálás során |

### Report kérelem

Ezen az oldalon lehet report kérelmet indítani, valamint sajátot írni. A lekérésnek jelenleg 2 módja van: **Clarity** és **Oldal**.

#### Clarity

Ebben a módban a rendszer a Clarity által lekért adatokat kéri be az adatbázisból, és adja át az AI agentnek elemzésre. Itt [hőtérképeket](#hőtérképek) is lehet csatolni, amelyek segítségével a rendszer elemzi a weblap kattintásokat.

![report-clarity](../images/image-4.png)

#### Oldal

Ezen a fülön lehet specifikus weblapok felépítését elemeztetni.
Lehetséges a projekt weblapjai között válogatni, vagy saját URL-t beilleszteni. A rendszer letölti a weblap tartalmát, megtisztítja, majd átadja az AI-nak elemzésre.

![report-oldal](../images/image-6.png)

#### Report írás

Ha a felhasználó saját reportot szeretne írni, azt is megteheti. A rendszer felismeri a **markdown** nyelvezetet.

![report-manuális-írás](../images/image-7.png)

### Report olvasó

Amint a felhasználó kért egy reportot, a rendszer átlépteti a report olvasó oldalra. Itt megvárhatja, amíg a report generálása befejeződik.

![report-várakozás](../images/image-8.png)

Amint befejeződött a generálás, a felhasználó át tudja nézni, valamint szerkeszteni markdown nyelvezetben.

![report-olvaso](../images/image-9.png)

![report-szerkesztés](../images/image-10.png)

### Report lista

Itt található az összes generált és írt report.
A felhasználó szűrhet:

- **Típus** — kézi vagy AI generált
- **Sablon** — melyik kontextussal készült
- **Állapot** — generálás, kész, sikertelen, stb.
- **Időtartam** — dátum szerinti szűrés

![report-lista](../images/image-11.png)

### Beépített report sablonok

A Vyzor az alábbi előre elkészített elemzési sablonokkal rendelkezik:

| Sablon | Leírás |
|--------|--------|
| Traffic Overview | Forgalmi minták áttekintése |
| Weekly Summary | Heti elemzés összefoglaló |
| Conversion Optimisation | Konverziós tölcsér elemzés |
| Content Performance | Tartalom hatékonyság |
| Page Performance | Oldal betöltés és interakció elemzés |
| Accessibility Review | Akadálymentesítési problémák |
| Content Quality | Tartalom minőség értékelés |
| SEO Audit | SEO elemzés |
| UX Issues | Felhasználói élmény problémák |
| Device & Browser Analysis | Eszköz és böngésző bontás |
| Engagement Analysis | Felhasználói elköteleződés metrikák |

---

## Hőtérképek

A Clarityből lehet exportálni CSV formátumban weblap hőtérkép elemzéseket. Ezeket fel lehet tölteni a Vyzorra, és csatolni az AI elemzésekhez. Ezzel a rendszer képes értelmezni például elemek kattintási mennyiségét, ezzel elemezve az oldalon a látogató vezetését.

### Feltöltés

A feltöltés egy egyszerű fájl feltöltésből áll. A fájl tartalmaz minden szükséges információt, így a feldolgozás nagyrésze automatikus.

> A rendszer csak **CSV** fájlokat fogad el, valamint jelenleg csak a **Clarity formátumát** olvassa!

![hőtérkép-feltöltés](../images/image-12.png)

### Összes hőtérkép

Ezen a lapon lehet kezelni a feltöltött hőtérképeket. Át lehet őket nevezni, letölteni, valamint törölni. Szűrni lehet fájlnév és dátumtartomány alapján.

![hőtérkép-lista](../images/image-13.png)

---

## Kontextusok

A rendszerben sok AI alapú lekérés történik, így érdemes ezeket a kontextusokat rugalmasan kezelni.
A **Beállítások > Kontextusok** oldalon a felhasználók képesek az AI promptokat szerkeszteni, törölni, letiltani (nem jelenik meg a lehetőségek között) és újat hozzáadni.

![kontextus-oldal](../images/image-14.png)

### Kontextus típusok

| Típus | Szín | Leírás |
|-------|------|--------|
| **Rendszer** (System) | Lila | Alapértelmezett AI viselkedéseket szabályoz. Ezek minden lekérés elé illesztődnek be. |
| **Utasítás** (Instruction) | Sárga | Körbefoglaló felülírásokat takarnak, pl. a kimeneti formátum leírását, vagy a hőtérkép értelmezési útmutatót. |
| **Sablon** (Preset) | Kék | Ezek a kontextusok választhatók a report lekérésekor. Meghatározzák, hogy a report milyen analitikát készítsen az elérhető adatokból. |

### Használati címkék

A kontextusokhoz **címkék** tartoznak, amelyek meghatározzák, melyik report rendszerhez illeszkednek:

- **Clarity** — Clarity adatelemzéshez tartozó kontextusok
- **Oldal elemzés** (Page Analyser) — weboldal felépítés elemzéséhez tartozó kontextusok

> Jelenleg a sablonon kívül a rendszer- és utasítás típusú kontextusok be vannak építve. Hozzáadni és eltávolítani nem lehetséges — fejlesztői beavatkozás szükséges.

---

## Felhasználók és jogosultságok

### Felhasználói szerepkörök

A rendszer három szerepkört különböztet meg:

| Szerepkör | Leírás |
|-----------|--------|
| **Admin** | Teljes hozzáférés minden funkcióhoz, minden projektet lát |
| **Web** | Normál felhasználó, jogosultság alapú hozzáféréssel |
| **Ügyfél** (Customer) | Külső szervezet felhasználója, saját dashboarddal |

### Jogosultsági rendszer

A Web szerepkörű felhasználók részletes, jogosultság alapú hozzáférés-kezelést kapnak. A fontosabb jogosultsági területek:

- **Projektek** — létrehozás, szerkesztés, törlés, saját/közreműködői/összes projekt megtekintése
- **Clarity** — pillanatképek és trendek megtekintése, adatok lekérése
- **Reportok** — megtekintés, létrehozás, szerkesztés, törlés
- **Hőtérképek** — feltöltés, megtekintés, szerkesztés, törlés
- **Kontextusok** — megtekintés, szerkesztés, hozzáadás
- **Felhasználók** — felhasználók és ügyfelek listázása, létrehozása, szerkesztése, törlése

---

## Tervek

### Google Analytics implementáció

Szeretnénk a Google Analytics rendszerét is bevonni a Vyzorba.

### Élő kontextus menedzser

A felhasználónak lehetősége legyen teljesen kezelni az AI működését.

### Több AI modell bevezetése

Lehetőség legyen modellek között váltani, így javítani a reportok minőségét.

### Prezentációk

A hőtérképekhez és reportokhoz kapcsolódó prezentációs nézet.
