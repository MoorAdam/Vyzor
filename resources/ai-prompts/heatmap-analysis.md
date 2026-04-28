## Hőtérkép elemzési utasítások

A Microsoft Clarity-ből exportált kattintás/érintés hőtérkép adatokat kaptál. Elemezd ezeket az adatokat, és a riportodban szerepeltesd az alábbiakat:
- **Legtöbbet kattintott elemek**: Azonosítsd a legfontosabb interaktív elemeket, és azt, hogy mit sugallnak a felhasználói szándékról és prioritásokról.
- **Navigációs minták**: Mely menüpontok, linkek és CTA-k kapják a legtöbb figyelmet? A felhasználók megtalálják, amit keresnek?
- **Cookie hozzájárulás hatása**: Számszerűsítsd, hogy az interakciók mekkora hányadát viszik el a cookie bannerek (pl. Cookiebot) a tényleges oldaltartalomhoz képest.
- **CTA hatékonyság**: Hasonlítsd össze az elsődleges CTA-k (foglalási gombok, űrlapok) kattintási arányát a másodlagos elemekkel. A fő konverziós műveletek elegendő kattintást kapnak?
- **Halott kattintások / dühös kattintások**: Keresd a nem interaktív elemeken (szöveg, kép, konténer) történő kattintásokat, amelyek arra utalnak, hogy a felhasználók kattinthatónak gondolják ezeket — ezek UX problémák.
- **Mobil-specifikus minták**: Ha az adat mobilról származik, jelezd a tapintás-specifikus problémákat, mint a túl kicsi érintési felületek vagy a véletlen érintések.
- **Görgetési mélység jelek**: Az oldal mélyén lévő, mégis kattintásokat kapó elemek elkötelezett felhasználókat jeleznek; ha csak a tetején lévő elemek kapnak figyelmet, az arra utal, hogy a felhasználók nem görgetnek.
- **Megvalósítható javaslatok**: A hőtérkép mintái alapján javasolj konkrét UI/UX fejlesztéseket.

A CSV oszlopai: Rangsor, Gomb (CSS szelektor), Érintések/Kattintások, Az összes érintés/kattintás %-a. A CSS szelektorok az elem DOM-on belüli pozícióját írják le — az osztálynevek és ID-k alapján következtess arra, hogy mi az elem (pl. a `.btn-yellow` valószínűleg egy elsődleges CTA, az `#accordion` egy GYIK szekció, a `.slick-arrow` pedig egy carousel navigáció).
