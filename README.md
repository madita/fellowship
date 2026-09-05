# Fellowship

A Laravel 12 + Vue 3 application. The backend is built with Laravel (PHP 8.1+) and the frontend uses Vite, Vue 3, and Vuetify with Pinia for state management. Real‑time features are wired via Laravel Echo with Ably or Pusher.

This README documents the stack, requirements, setup steps, common scripts, environment variables, how to run tests, and a quick tour of the project structure.


## Overview
- Backend: Laravel Framework 12 (PHP ^8.1)
- Frontend: Vue 3, Vuetify 3, Vite
- State: Pinia
- Realtime: Laravel Echo with Ably and/or Pusher
- Testing: PHPUnit (backend), Vitest + Vue Test Utils (frontend)

Primary entry points
- Laravel HTTP: public/index.php (served via Artisan, PHP-FPM, or web server)
- Frontend build: resources/js/app.js and resources/sass/app.scss (configured in vite.config.js)


## Requirements
- PHP 8.1 or newer
- Composer 2.x
- Node.js 18+ (recommended 18 LTS or 20 LTS)
- npm 9+ or yarn/pnpm (choose one)
- A database supported by Laravel (e.g., MySQL/MariaDB, PostgreSQL, SQLite)
- Optional: Redis for queue/cache (if enabled)
- Optional: Ably or Pusher account for broadcasting (if real‑time enabled)


## Getting started
1) Clone and install PHP dependencies
- composer install

2) Install JS dependencies
- npm install
  - or: yarn install

3) Environment
- cp .env.example .env  (Windows PowerShell: Copy-Item .env.example .env)
- php artisan key:generate
- Configure database and broadcasting in .env (see Environment variables below)

4) Database
- php artisan migrate
- Optional (if you have seeders): php artisan db:seed

5) Storage symlink (for public uploads, if used)
- php artisan storage:link

6) Run the app (two terminals)
- Terminal A (backend): php artisan serve
- Terminal B (frontend): npm run dev
- Open http://localhost:8000 (Laravel) and ensure Vite dev server injects assets

7) Production build
- npm run build
- Configure your web server to serve public/ as the document root and ensure PHP is configured for Laravel

Optional: Docker via Laravel Sail
- composer require laravel/sail --dev (already present as a dev dependency)
- php artisan sail:install
- ./vendor/bin/sail up -d
- Then run npm install and npm run dev/build in a Node container or on host


## Scripts (package.json)
- npm run dev: Start Vite dev server
- npm run build: Production build via Vite
- npm run prod / npm run production: Aliases for build
- npm run test: Run Vitest in CI (headless)
- npm run test:watch: Run Vitest in watch mode
- npm run test:coverage: Vitest with coverage
- npm run audit:repo: Audit dependencies with custom script (tools/dependency-audit.js)

Composer scripts
- post-root-package-install: Copies .env.example to .env if missing
- post-create-project-cmd: Generates APP_KEY
- post-autoload-dump: Laravel package discovery


## Environment variables
Common Laravel variables (configure in .env):
- APP_NAME, APP_ENV, APP_KEY, APP_DEBUG, APP_URL
- LOG_CHANNEL
- DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
- CACHE_DRIVER, SESSION_DRIVER, QUEUE_CONNECTION
- FILESYSTEM_DISK (and disks as needed)
- BROADCAST_DRIVER=ably or pusher

Broadcasting (realtime)
- For Ably: ABLY_KEY=your-ably-api-key
- For Pusher: PUSHER_APP_ID, PUSHER_APP_KEY, PUSHER_APP_SECRET, PUSHER_HOST (optional), PUSHER_PORT (optional), PUSHER_SCHEME (optional)

Frontend runtime configuration
- If the frontend needs environment variables at build time, define them via Vite conventions (VITE_*). Example: VITE_APP_NAME, VITE_ECHO_TRANSPORT, etc. TODO: Confirm actual VITE_* keys used in the app.

TODOs
- Confirm which broadcaster is used in each environment (Ably vs Pusher) and document the exact .env values.
- Document any additional custom env variables used by resources/js/echo.js or notification services.


## Running tests
Backend (PHPUnit)
- php artisan test
  - or: ./vendor/bin/phpunit

Frontend (Vitest)
- npm run test        # single run
- npm run test:watch  # watch mode
- npm run test:coverage

See TESTING.md for any additional notes.


## Project structure (partial)
- app/                  Laravel application (models, controllers, events, etc.)
- bootstrap/
- config/
- database/
- public/               Web server document root (public/index.php)
- resources/
  - js/                 Vue 3 app source
    - app.js            Frontend entry point (Vite)
    - components/       Vue components (chat, conversations, etc.)
    - store/            Pinia stores
    - echo.js           Laravel Echo client config
    - notificationService.js
  - sass/               SCSS entry (app.scss)
- routes/               Laravel routes (web.php, api.php, etc.)
- tests/                PHPUnit tests
- vite.config.js        Vite configuration (inputs defined here)
- vitest.config.js      Vitest configuration
- phpunit.xml           PHPUnit configuration
- package.json          Frontend scripts and dependencies
- composer.json         PHP dependencies and composer scripts


## Data migration tool
Imports content from legacy databases (old forums, wikis, galleries, …) into this app, driven by
stored mappings instead of code. Admin UI at `/admin/migrations` (tabs: Runs, Data Sources,
Mappings, Legacy Users); backend in `app/Services/Migration/` and
`app/Http/Controllers/Admin/MigrationController.php`.

### Concepts
- **Source** — a connection to a legacy database (MySQL/MariaDB, PostgreSQL, SQLite, SQL Server).
  Credentials are stored encrypted; tables/columns are introspected live.
- **Mapping** — connects one source table to a **target** (a feature of this app) via a field map.
  Mappings can be exported/imported as JSON (Mappings tab), so a whole migration is reproducible.
- **Target** — what a row becomes. Available targets: `users`, `legacy_users` (roster only, no
  accounts), `events`, `wiki_pages`, `wiki_terms`, `forum_threads`, `forum_posts`,
  `gallery_collections`, `gallery_images`. Targets skip already-imported rows, so re-running a
  mapping resumes instead of duplicating.

### Mapping features
- **Field map** per target field: source column, transform
  (`trim`, `html_decode`, `underscores_to_spaces`, `int`, `float`, `bool`, `json`,
  `date`/`time`/`datetime` with input format), default value, or a **template** combining columns
  (`{column}`, `{column|slug}`, `{column|fold}` — `fold` converts umlauts/specials for file paths).
- **Joins** — pull columns from related source tables (e.g. content tables split across
  page/revision/text).
- **Row filters** — only import matching rows; a filter can compare against a value or another
  column (`compare: "column"`).
- **Content conversion** — wiki targets convert MediaWiki wikitext (incl. tables) to HTML;
  forum targets convert phpBB BBCode (both storage formats) to HTML.
- **Locale** — `options.locale` writes translated fields (title/content/…) to that language.
- **Preview** — dry-runs the first rows with validation errors before anything is written.

### Legacy users & content claims
Imported records remember their original owner's username per legacy system
(`migration_attributions`). Import the old user tables with the `legacy_users` target to get the
full roster incl. e-mails (no accounts are created). Registered users can request their old
content under Account → Legacy Account, which files a `legacy-account-claim` ticket. Admins
assign identities on the Legacy Users tab — matching e-mails show a verified badge/suggestion —
which moves ownership of all attributed content and resolves the ticket. Until assigned, imported
forum content shows the original poster's name and an initials avatar (`meta.legacy_author`).

### Commands
```bash
# Run a mapping import from the CLI — REQUIRED for large imports (thousands of rows):
# on the sync queue driver the dashboard's Run button executes inside the HTTP request
# and gets killed by the web server timeout. Safe to re-run; it resumes.
php artisan migration:run-mapping "<mapping name or id>"
```

### Typical workflow
1. Add the legacy database on the **Data Sources** tab and test the connection.
2. Create mappings (or **Import JSON** of an existing mapping set) — pick the table, join related
   tables, filter rows, map the fields, **Preview**.
3. Import in dependency order: legacy users → independent content (events, collections, wiki
   terms/pages, forum threads) → dependent content (gallery images, forum posts — these link to
   records imported before them).
4. Run the source-independent **post-import steps** on the Runs tab (link galleries to events,
   rewrite wiki-internal links).
5. As users register and claim their legacy accounts, assign them on the **Legacy Users** tab.


## License
This project is open-sourced software licensed under the MIT license.
