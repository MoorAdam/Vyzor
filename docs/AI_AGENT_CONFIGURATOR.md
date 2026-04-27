# AI agent konfiguráció

## Probléma

A project tartalmaz több AI agent-et is. Mindegyik agent-nek van tobb reétegű kontextusa, amelyeket külön-külön lehet kezelni a kontextuskezelőben. 

Viszont pillanatnyilag nem lehetséges ezeket a kontextusokat variálni. Pl nem lehet kezelni az output formázást, pedig érdemes lenne kezelhetővé tenni, hogy könnyű legyen a konfigutálás.

## Megoldás

Lesz egy beállítások oldal, ahol agent szinten lehet variálni az innyektált contextusokat. 
Minden agent-nek ezek a socket-jei lesznek ahova lehet betenni a megfelelő kontextusokat

- **Rendszer**: A legalapabb rendszer beállítás. Ebből egyet lehet vlasztani, és a legtöbb alkalommal minden agent ugyan azt használja, így a fő elem mindenhol alapból lesz választava. A *Rendszer* kontextusokat lehet kiválasztani
- **Alaptézis**: Ebből csak 1-et lehet választani. Ez írja le az agent alap feladatát. Az *alaptézis* tag-el ellátott kontextusokat lehet kiválasztani itt
- **Bemeneti adatok leírása**: Ezekkel a kontextusokkal lehet leírni milyen adatokat, honnan tud lekérni. Ebből lehet többet is adni, de nem célszerű. az *Adat csatorna* kontextusokat lehet it választani
- **Sablonok**: Nem minden agent-nél elérhető. Nincs midnen agent-nél szükség rá. De ha mégis, akkor egy tag-et lehet választani rá, amely behúzza az összes sablont amin ez a tag, és a Preset tag van rajta
- **Utasítás**: Ebből többet is hozzá lehet adni. Ezekkel lehet a kimeneti adatok formázását beállítani, vagy egyéb utastításokat kiadni