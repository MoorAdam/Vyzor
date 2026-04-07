# Vyzor

Web analytics and AI-powered reporting platform built on Laravel. Vyzor aggregates data from Microsoft Clarity and generates intelligent reports using multiple LLM providers, designed for agencies and consultants delivering data-driven insights to clients.

## Features

- **Microsoft Clarity Integration** — Fetch live insights, trends, and metrics with built-in rate limiting
- **AI-Powered Reports** — Generate analytical reports using OpenAI, Anthropic, Gemini, Groq, and more
- **Customizable AI Contexts** — Define reusable prompts, preset analysis templates, and instructions
- **Heatmap Analysis** — Upload CSV heatmap data and include it in AI report generation
- **Multi-Project Management** — Manage multiple projects with per-project Clarity API keys and settings
- **Role-Based Access** — Admin, web user, and customer roles with scoped dashboards
- **Async Report Generation** — Queue-driven AI report generation with status tracking

## Tech Stack

| Layer     | Technology                                      |
|-----------|------------------------------------------------|
| Backend   | Laravel 12, PHP 8.2+                           |
| Frontend  | Livewire, Tailwind CSS v4, Vite                |
| Database  | SQLite (dev), PostgreSQL 15 (production/Docker) |
| AI        | Prism PHP — OpenAI, Anthropic, Gemini, Groq, and more |
| Queue     | Database / Redis / SQS                         |

## Prerequisites

- PHP 8.2+
- Composer
- Node.js & npm
- A Microsoft Clarity API key
- At least one AI provider API key (OpenAI, Anthropic, etc.)

## Installation

```bash
git clone https://github.com/your-username/Vyzor.git
cd Vyzor
composer setup
```

The `composer setup` command will:
1. Install PHP dependencies
2. Copy `.env.example` to `.env`
3. Generate the application key
4. Run database migrations
5. Install Node dependencies
6. Build frontend assets

Then configure your `.env` file:

```env
CLARITY_KEY=your-clarity-api-key
OPENAI_API_KEY=your-openai-key
# and/or
ANTHROPIC_API_KEY=your-anthropic-key
```

## Development

```bash
composer dev
```

This starts all development services concurrently:
- Laravel dev server at `http://localhost:8000`
- Queue worker for async jobs
- Log viewer (Laravel Pail)
- Vite dev server with hot reload

## Production (Docker)

A `docker-compose.yaml` is included for PostgreSQL:

```bash
docker compose up -d
```

Update `.env` to use PostgreSQL:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5433
DB_DATABASE=vyzor
DB_USERNAME=vyzor
DB_PASSWORD=secret
```

## Testing

```bash
composer test
```

## Project Structure

```
app/
├── Ai/Agents/          # AI agent implementations (ReportAnalyst)
├── Models/              # Eloquent models
├── Services/            # Business logic (ReportGeneratorService)
├── Jobs/                # Queued jobs (GenerateAiReport)
├── Livewire/            # Livewire components
└── Http/                # Controllers & middleware
resources/
├── views/               # Blade templates
├── ai-prompts/          # AI prompt templates & presets
├── js/                  # JavaScript entry points
└── css/                 # Tailwind CSS
```

## AI Report Presets

Vyzor ships with built-in analysis templates:
- Traffic Overview
- Weekly Summary
- Content Performance
- UX Issues
- Device & Browser Analysis
- And more via custom contexts

## License

Proprietary software. All rights reserved.
