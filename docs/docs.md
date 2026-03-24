# Vyzor — Project Documentation

## Overview

**Vyzor** is a Laravel 12 web application. Currently in early/boilerplate stage with Livewire for reactive components and Tailwind CSS v4 for styling.

---

## Technologies

| Technology | Version | Purpose |
|---|---|---|
| PHP | ^8.2 | Backend language |
| Laravel | ^12.0 | Backend framework |
| Livewire | ^4.2 | Reactive frontend components |
| Sheaf UI | — | Blade UI component library (in-house) |
| Tailwind CSS | ^4.0.0 | Utility-first CSS framework |
| Vite | ^7.0.7 | Asset bundler |
| Axios | ^1.11.0 | HTTP client (frontend) |
| PostgreSQL | 15 | Production database (Docker) |
| SQLite | — | Local development database |
| PHPUnit | ^11.5.3 | Backend testing |
| Laravel Pint | ^1.24 | PHP code style fixer |
| Laravel Sail | ^1.41 | Docker development environment |
| Laravel Pail | ^1.2.2 | Real-time log viewer |
| Concurrently | ^9.0.1 | Run multiple dev processes |

---

## Project Structure

```
Vyzor/
├── app/
│   ├── Http/
│   │   └── Controllers/
│   │       └── Controller.php        # Abstract base controller
│   ├── Models/
│   │   └── User.php                  # Eloquent User model
│   └── Providers/
│       └── AppServiceProvider.php    # Application service provider
├── bootstrap/                        # Laravel bootstrap files
├── config/                           # Configuration files
├── database/
│   ├── factories/
│   │   └── UserFactory.php           # User model factory
│   ├── migrations/                   # Database migrations
│   └── seeders/
│       └── DatabaseSeeder.php        # Database seeder
├── docs/
│   └── docs.md                       # This file
├── public/                           # Web root
├── resources/
│   ├── css/
│   │   └── app.css                   # Tailwind CSS entry point
│   ├── js/
│   │   ├── app.js                    # JS entry point
│   │   └── bootstrap.js              # Axios configuration
│   └── views/
│       ├── components/
│       │   └── counter.blade.php     # Livewire counter component (empty stub)
│       └── welcome.blade.php         # Landing page
├── routes/
│   ├── web.php                       # Web routes
│   └── console.php                   # Artisan commands
├── storage/                          # Logs, cache, compiled views
├── tests/
│   ├── Feature/                      # Feature tests
│   └── Unit/                         # Unit tests
├── agents.md                         # Agent rules
├── artisan                           # Laravel CLI entry point
├── composer.json                     # PHP dependencies
├── docker-compose.yaml               # Docker services
├── package.json                      # Node.js dependencies
├── phpunit.xml                       # PHPUnit configuration
└── vite.config.js                    # Vite build configuration
```

---

## Configuration

### Application (`config/app.php`)
- **Name**: Laravel
- **Environment**: local (controlled via `APP_ENV`)
- **Debug**: true (controlled via `APP_DEBUG`)
- **URL**: http://localhost
- **Timezone**: UTC
- **Cipher**: AES-256-CBC

### Database (`config/database.php`)
- **Default connection**: sqlite (local), PostgreSQL (Docker)
- Supports MySQL, MariaDB, PostgreSQL, SQL Server

### Cache (`config/cache.php`)
- **Default store**: database
- Supports array, file, redis, memcached, dynamodb

### Session (`config/session.php`)
- **Driver**: database
- **Lifetime**: 120 minutes
- **Cookie name**: laravel-session

### Queue (`config/queue.php`)
- **Default connection**: database
- Supports sync, redis, SQS, Beanstalkd

### Mail (`config/mail.php`)
- **Default mailer**: log (emails are logged locally, not sent)
- Supports SMTP, SES, Postmark, Resend

### Logging (`config/logging.php`)
- **Default channel**: stack
- **Log level**: debug
- Supports single, daily, slack, syslog, errorlog

---

## Authentication

Session-based authentication using Laravel's built-in `Auth` facade and Livewire components.

### Flow
- **Login**: `GET /login` — Livewire component with email/password/remember-me. Validates credentials via `Auth::attempt()`, regenerates session, redirects to dashboard.
- **Logout**: `POST /logout` — Invalidates session, regenerates CSRF token, redirects to login.
- **Guest middleware**: `/login` is only accessible to unauthenticated users. Authenticated users are redirected to `/dashboard`.
- **Auth middleware**: `/dashboard` (and future authenticated routes) require login. Guests are redirected to `/login`.
- **Root redirect**: `GET /` redirects to dashboard (if authenticated) or login (if guest).

### Configuration (`bootstrap/app.php`)
- `redirectGuestsTo('/login')` — unauthenticated users are sent to login
- `redirectUsersTo('/dashboard')` — authenticated users hitting guest routes are sent to dashboard

---

## Routes

### Web Routes (`routes/web.php`)

| Method | URI | Middleware | Action |
|---|---|---|---|
| GET | `/` | — | Redirects to dashboard or login |
| GET | `/login` | guest | Livewire login component |
| GET | `/dashboard` | auth | Livewire dashboard component |
| POST | `/logout` | auth | Logs out and redirects to login |

### Artisan Commands (`routes/console.php`)

| Command | Description |
|---|---|
| `inspire` | Displays a random inspiring quote |

---

## Models

### User (`app/Models/User.php`)
- **Traits**: `HasFactory`, `Notifiable`
- **Fillable**: `name`, `email`, `password`
- **Hidden**: `password`, `remember_token`
- **Casts**: `password` → hashed

---

## Database

### Migrations

#### `create_users_table`
| Column | Type | Notes |
|---|---|---|
| id | bigint | Primary key |
| name | string | — |
| email | string | Unique |
| email_verified_at | timestamp | Nullable |
| password | string | — |
| remember_token | string | Nullable |
| timestamps | — | created_at, updated_at |

#### `create_password_reset_tokens_table`
| Column | Type | Notes |
|---|---|---|
| email | string | Primary key |
| token | string | — |
| created_at | timestamp | Nullable |

#### `create_sessions_table`
| Column | Type | Notes |
|---|---|---|
| id | string | Primary key |
| user_id | bigint | Nullable, indexed |
| ip_address | string | Nullable |
| user_agent | text | Nullable |
| payload | longtext | — |
| last_activity | int | Indexed |

#### `create_cache_table`
| Column | Type | Notes |
|---|---|---|
| key | string | Primary key |
| value | mediumtext | — |
| expiration | int | — |

Also creates `cache_locks` table with: `key`, `owner`, `expiration`.

#### `create_jobs_table`
| Column | Type | Notes |
|---|---|---|
| id | bigint | Primary key |
| queue | string | Indexed |
| payload | longtext | — |
| attempts | tinyint | — |
| reserved_at | int | Nullable |
| available_at | int | — |
| created_at | int | — |

Also creates `job_batches` and `failed_jobs` tables.

### Factories

- **UserFactory**: Generates fake users with `name`, `email`, `email_verified_at`, hashed password, and `remember_token`.

### Seeders

- **DatabaseSeeder**: Creates a single test user — `Test User` / `test@example.com`.

---

## Frontend

### Entry Points
- **CSS**: `resources/css/app.css` — imports Tailwind CSS v4, scans Blade and JS files
- **JS**: `resources/js/app.js` — imports bootstrap script
- **Bootstrap**: `resources/js/bootstrap.js` — configures Axios with `X-Requested-With` header

### Views
- **`welcome.blade.php`**: Landing page with conditional auth navigation (login/register/dashboard links), getting started guide, and Tailwind dark mode support.
- **`components/counter.blade.php`**: Livewire counter component stub (currently empty).

### Build Tool (Vite)
- Input: `resources/css/app.css`, `resources/js/app.js`
- Plugins: `laravel-vite-plugin`, `@tailwindcss/vite`
- Hot reload enabled; ignores `storage/framework/views/` during watch

---

## Docker

### Services (`docker-compose.yaml`)

| Service | Image | Container | Port |
|---|---|---|---|
| db | postgres:15 | vyzor-db | 5433→5432 |

**PostgreSQL credentials**:
- User: `vyzor`
- Password: `secret`
- Database: `vyzor`

Data is persisted via a named Docker volume `db_data`.

---

## Testing

### Configuration (`phpunit.xml`)

| Suite | Path |
|---|---|
| Unit | `tests/Unit` |
| Feature | `tests/Feature` |

**Test environment overrides**:
- `APP_ENV=testing`
- `DB_CONNECTION=sqlite` (in-memory `:memory:`)
- `CACHE_STORE=array`
- `MAIL_MAILER=array`
- `QUEUE_CONNECTION=sync`
- `SESSION_DRIVER=array`

### Running Tests
```bash
composer test
# or
php artisan test
```

---

## Development Scripts

### Composer Scripts
```bash
composer setup   # Install deps, generate app key, migrate, build assets
composer dev     # Start server, queue, logs, and Vite concurrently
composer test    # Clear config cache and run PHPUnit
```

### NPM Scripts
```bash
npm run dev      # Start Vite dev server
npm run build    # Build production assets
```

---

## Environment Variables

Key variables from `.env.example`:

| Variable | Default | Description |
|---|---|---|
| `APP_NAME` | Laravel | Application name |
| `APP_ENV` | local | Environment (local/production) |
| `APP_DEBUG` | true | Enable debug mode |
| `APP_URL` | http://localhost | Application URL |
| `DB_CONNECTION` | sqlite | Database driver |
| `SESSION_DRIVER` | database | Session storage driver |
| `CACHE_STORE` | database | Cache storage driver |
| `QUEUE_CONNECTION` | database | Queue driver |
| `MAIL_MAILER` | log | Mail driver |
| `LOG_CHANNEL` | stack | Logging channel |
| `LOG_LEVEL` | debug | Minimum log level |
| `BCRYPT_ROUNDS` | 12 | Password hashing cost |
