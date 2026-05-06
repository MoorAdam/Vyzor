# GA közönség-elemzés

Elemezd ki **kik a látogatók** és **honnan jönnek** a kiválasztott időszakban. Cél: a fejlesztő/marketing csapat jobban megértse a célközönséget és azt, hogy a tartalom/UX optimalizálást milyen csoportokra kell hangolni.

## Fókuszterületek
- **Demográfia** — kor (userAgeBracket) és nem (userGender) eloszlás. Ha az "unknown" aránya >50%, jelezd hogy a Google Signals valószínűleg nincs bekapcsolva, és ezért az adatok korlátozottak
- **Eszköz × felbontás** — top képernyőméretek és eszköz-kategóriák. Ez fontos a fejlesztőnek, mert ezekre kell elsősorban tesztelni / optimalizálni
- **Böngésző × OS** — top kombinációk (pl. Chrome on Android, Safari on iOS), kompatibilitási prioritás-sorrend
- **Földrajzi eloszlás** — országok, régiók, városok. Ha a forgalom egy adott régióra koncentrálódik (pl. Magyarország 90%), érdemes lokális tartalmat / nyelvi változatot megerősíteni
- **Új vs visszatérő** felhasználók aránya — visszatérők magas aránya = jó márkahűség, alacsony = retention probléma

## Elvárt kimenet
Készíts közönség-portrét: ki a tipikus látogató, milyen eszközről jön, honnan? Adj **konkrét cselekvési javaslatokat**:
- **Tesztelési prioritás**: melyik eszköz/böngésző/felbontás kombinációkra kell elsősorban tesztelni
- **Tartalom-targetálás**: milyen demográfiai csoportra szól a meglévő tartalom és milyenre kéne (ha van eltérés)
- **Lokalizáció**: ha jelentős külföldi forgalom van, érdemes-e nyelvi változat
- **Adat-minőség**: ha a demográfia hiányos, javaslat (Google Signals bekapcsolása, vagy custom event paraméterek)
