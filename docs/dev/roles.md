# Szerepkorok

A projektben nehany szerepkor letezik, amelyek a jogosultsagi szintjuktol fuggoen az alkalmazas kulonbozo reszeihez ferhetnek hozza.

- **Admin**: 
Az oldalon mindent lathat, szerkeszthet, hozzaadhat es kezelhet. Az egyetlen felhasznalo, aki mas felhasznalokat letrehozhat.
Az adminnak nincsenek kulon jogosultsagi sorai az adatbazisban — a `Gate::before` automatikusan teljes hozzaferest biztosit.

- **Web**:
Letrehozhatja es kezelheti a sajat projektjeit, ugyfelprofilt hozhat letre, es AI riportokat kerhet.
A web felhasznalok kezelhetik az ugyfeleket (letrehozas, szerkesztes, torles), de **nem** kezelhetnek mas web felhasznalokat — csak az adminok hozhatnak letre, szerkeszthetnek vagy torolhetnek web felhasznalokat.
A web felhasznalok nem lathatjak az osszes projektet (`VIEW_ALL_PROJECTS`), csak a sajatjaikat es azokat, amelyeken kozremukodokent vesznek reszt.

- **Ugyfel (Customer)**: 
Az ugyfelfiok egy ugyfelszervezetet kepvisel. Az ugyfeleknek nincs webes hozzaferesuk — csak a sajat ugyfel-vezerlopultjukat lathatjak. Alapertelmezetten nincsenek jogosultsagok hozzarendelve az ugyfel szerepkorhoz.

- **Kozremukodo (Collaborator)**: 
A kozremukodo **nem** szerepkor a `UserRoleEnum`-ban — ez egy projekt szintu virtualis szerepkor. A felhasznalo alap szerepkore tovabbra is `web`, de amikor egy olyan projektet er el, amelyen kozremukodokent vesz reszt, a jogosultsagi rendszer az effektiv szerepkoret `collaborator`-ra valtja az adott projekt jogosultsagi ellenorzeseihez.

A projekt tulajdonosa jelolheti ki a kozremukodoket. A kozremukodonek nagyreszben ugyanazok a projekt szintu jogosultsagai vannak, mint a tulajdonosnak, a kovetkezo kivetelekkel:
  - Nem torolhet vagy hozhat letre projekteket
  - Nem kezelhet felhasznalokat vagy ugyfeleket
  - Nem tekintheti meg es nem kezelheti a kontextusokat

Csak a projekt tulajdonosa valtoztathatja meg a tulajdonost es a kozremukodok listajat.

### Jogosultsagok

Egy szerepkorhoz tobb jogosultsag tartozik, amelyek a `role_permission` tablaban vannak definialva az adatbazisban.
A rendszer ellenorzi, hogy a felhasznalonak megvannak-e a megfelelo jogosultsagai a szerepkore alapjan.

Projekt szintu jogosultsagok eseten (amelyek `project.` prefixszel rendelkeznek), a rendszer a projekt jogosultsagi modelljat ellenorzi:
- Ha a felhasznalo a **tulajdonos**, az alap szerepkore (`web`) kerul felhasznalasra a jogosultsag ellenorzeshez
- Ha a felhasznalo **kozremukodo**, az effektiv szerepkor `collaborator`-ra valt
- Ha a felhasznalo **sem** tulajdonos, sem kozremukodo, a hozzaferes megtagadasra kerul a jogosultsagoktol fuggetlenul

A jogosultsagkezelo magjaban, ha a felhasznalonak admin szerepkore van, automatikusan mindent felold kulon jogosultsagi sorok nelkul.

Ha egy felhasznalonak nincs jogosultsaga a projekt egyes reszeihez, a gombok letiltasra kerulnek.

A szerepkorok jogosultsagai a `role_permission` tablaban adhatoak hozza es tavolithatoak el az adatbazisban.

Az alabbiakban a jogosultsagok es az azokkal rendelkezo szerepkorok:

- alapok:
    - projektek megtekintese (web, kozremukodo)
- felhasznalok:
    - felhasznalok listajanak megtekintese (web)
    - felhasznalo letrehozasa (csak admin)
    - felhasznalok szerkesztese (csak admin)
    - felhasznalok torlese (csak admin)
    - ugyfel letrehozasa (web)
    - ugyfel szerkesztese (web)
    - ugyfel torlese (web)
- projekt:
    - osszes projekt megtekintese (csak admin)
    - sajat projektek megtekintese (web)
    - kozremukodoi projektek megtekintese (web, kozremukodo)
    - projekt statusz valtoztatasa (web, kozremukodo)
    - projekt reszleteinek szerkesztese (web, kozremukodo)
    - projekt torlese (web)
    - projekt letrehozasa (web)
    - clarity:
        - clarity pillanatkepek megtekintese (web, kozremukodo)
        - clarity trendek megtekintese (web, kozremukodo)
        - clarity adatok lekerese (web, kozremukodo)
    - riport:
        - riportok megtekintese (web, kozremukodo)
        - riport keres/letrehozas (web, kozremukodo)
        - riport szerkesztese (web, kozremukodo)
        - riport torlese (web, kozremukodo)
    - hoterkep:
        - hoterkep feltoltese (web, kozremukodo)
        - hoterkepek megtekintese (web, kozremukodo)
        - hoterkepek szerkesztese (web, kozremukodo)
        - hoterkepek torlese (web, kozremukodo)
- kontextus:
    - kontextusok oldal megtekintese (web)
    - kontextusok szerkesztese (web)
    - kontextusok hozzaadasa (web)
