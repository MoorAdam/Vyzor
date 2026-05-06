# Vyzor — Tech Stack

## Backend

| Technológia | Verzió | Leírás |
|-------------|--------|--------|
| **PHP** | 8.2+ | Szerver oldali nyelv |
| **Laravel** | 12 | Backend keretrendszer |
| **Livewire** | 4.2 | Szerver-vezérelt reaktív komponensek (SPA-szerű élmény teljes oldal újratöltés nélkül) |
| **Laravel AI** | 0.4.3 | AI szolgáltató integráció (több LLM provider támogatás) |
| **Laravel Tinker** | 2.10 | Interaktív REPL shell a Laravel alkalmazáshoz |
| **Sheaf CLI** | 1.3 | Laravel segédeszközök |

### Backend fejlesztői eszközök

| Technológia | Verzió | Leírás |
|-------------|--------|--------|
| **PHPUnit** | 11.5 | Unit és feature tesztelés |
| **Laravel Pint** | 1.24 | PHP kód formázó (PSR-12) |
| **Laravel Pail** | 1.2 | Valós idejű log figyelő |
| **Laravel Sail** | 1.41 | Docker fejlesztői környezet |
| **Mockery** | 1.6 | Mock objektumok teszteléshez |
| **Faker** | 1.23 | Teszt adat generálás |
| **Collision** | 8.6 | Szebb hibajelentések CLI-ben |

---

## Frontend

| Technológia | Verzió | Leírás |
|-------------|--------|--------|
| **Tailwind CSS** | 4.2 | Utility-first CSS keretrendszer |
| **Vite** | 7.0 | Frontend build eszköz és dev szerver (HMR) |
| **Blade** | — | Laravel natív template motor |
| **Marked** | 18.0 | Markdown renderelés (report megjelenítés és szerkesztés) |
| **Highlight.js** | 11.11 | Szintaxis kiemelés kódblokkokban |
| **Axios** | 1.11 | HTTP kliens (API hívások a frontendről) |
| **@tailwindcss/typography** | 0.5 | Prose stílusok markdown tartalomhoz |
| **@sheaf/rover** | 1.0 | Navigáció és routing segéd |
| **Concurrently** | 9.0 | Több dev process párhuzamos futtatása |

### UI komponensek

| Technológia | Leírás |
|-------------|--------|
| **Blade Heroicons** | Heroicons SVG ikon készlet Blade komponensként |
| **Blade Phosphor Icons** | Phosphor Icons SVG ikon készlet Blade komponensként |

---

## Adatbázis

| Környezet | Technológia | Leírás |
|-----------|-------------|--------|
| **Fejlesztés** | SQLite | Egyszerű lokális fejlesztéshez |
| **Produkció** | PostgreSQL 15 | Docker-alapú, Alpine image |

A queue rendszer, session kezelés és cache szintén adatbázis-alapú.

---

## AI szolgáltatók

A Vyzor a **Laravel AI** csomagon keresztül több AI szolgáltatót támogat. Az alapértelmezett provider az **OpenAI**.

| Szolgáltató | Driver | Felhasználás |
|-------------|--------|--------------|
| **OpenAI** | `openai` | Alapértelmezett szöveg, audió, transzkripció |
| **Anthropic** | `anthropic` | Alternatív szöveg generálás |
| **Google Gemini** | `gemini` | Alapértelmezett kép feldolgozás |
| **Groq** | `groq` | Gyors inferencia |
| **Azure OpenAI** | `azure` | Vállalati OpenAI hozzáférés |
| **DeepSeek** | `deepseek` | Alternatív LLM |
| **Mistral** | `mistral` | Alternatív LLM |
| **Cohere** | `cohere` | Alapértelmezett reranking |
| **Ollama** | `ollama` | Lokális modellek futtatása |
| **OpenRouter** | `openrouter` | Több modell egyetlen API-n keresztül |
| **Jina** | `jina` | Embedding és keresés |
| **VoyageAI** | `voyageai` | Embedding |
| **xAI** | `xai` | Alternatív LLM |
| **ElevenLabs** | `eleven` | Szöveg-hang konverzió |

---

## Külső integrációk

| Szolgáltatás | Leírás |
|--------------|--------|
| **Microsoft Clarity** | Felhasználói viselkedés elemzés — API-n keresztüli adatlekérés (snapshot, trendek) |

---

## Infrastruktúra és DevOps

| Technológia | Leírás |
|-------------|--------|
| **Docker** | Konténerizáció (Dockerfile + docker-compose) |
| **Docker Compose** | PostgreSQL szolgáltatás orkesztráció |
| **Laravel Queue** | Aszinkron feladatkezelés (report generálás) adatbázis driverrel |
| **Vite HMR** | Hot Module Replacement fejlesztés közben |

---

## Fejlesztési parancsok

```bash
# Telepítés (PHP + Node függőségek, migráció, build)
composer setup

# Fejlesztői szerver indítás (Laravel + Queue + Pail + Vite párhuzamosan)
composer dev

# Tesztek futtatása
composer test

# Frontend build
npm run build
```

---

## Lokalizáció

| Nyelv | Kód |
|-------|-----|
| Magyar | `hu` |
| Angol | `en` |

A nyelvváltás a felületen a jobb felső sarokban elérhető. Az AI kontextusok (nevek, leírások) szintén kétnyelvűek.
