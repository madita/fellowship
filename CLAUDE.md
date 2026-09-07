# Fellowship Project

Community platform built with Laravel 12 + Vue 3 SPA. Features wiki, events, tickets, chat, conversations, and a full admin panel.

## Tech Stack

- **Backend:** Laravel 12, PHP 8.1+, MySQL, Redis
- **Auth:** Laravel Sanctum (SPA cookies), Spatie Laravel Permission (roles/permissions)
- **Frontend:** Vue 3, Vuetify 3, Pinia (Options API style), Vue Router, vue-i18n
- **Build:** Vite 6, Node 18+
- **Real-time:** Laravel Echo + Ably/Pusher
- **Translations:** Astrotomic Laravel Translatable (backend models), vue-i18n (frontend)
- **Rich Text:** TipTap editor suite
- **Testing:** PHPUnit (backend), Vitest (frontend)

## Project Structure

```
app/
  Http/Controllers/           # Controllers (flat namespace, no deep nesting)
    Admin/                    # Admin panel controllers
    Api/                      # External API (v1, key-authenticated)
    Auth/                     # Auth + social login
    Chat/                     # Chat controllers
    Conversation/             # Conversation controllers
    DataTable/                # Server-side datatable controllers
  Models/
    Ticket/                   # Ticket, TicketComment, TicketType
    Event/                    # Event, EventDetail, EventGuest, EventProfile, EventType
    Conversation/             # Conversation, ConversationMessage
    Chat/                     # Message
    Tag/                      # Taxonomy, Term, Taxable
    Translations/             # Astrotomic translation models
    Concerns/                 # Traits (Approvable, HasTickets)
  Services/                   # CacheService, ImageOptimization, Newsletter, etc.
routes/
  api.php                     # All API routes (main file)
  web.php                     # Minimal: auth, OAuth, sitemap, SPA catch-all
resources/js/
  app.js                      # Vue app bootstrap
  router/                     # Vue Router config
    index.js                  # Main router, middleware pipeline
    admin.routes.js           # Admin routes
    landing.routes.js         # Public routes
    wiki.routes.js            # Wiki routes
    users.routes.js           # User/account routes
    middleware/               # auth, verified, maintenance
  store/                      # Pinia stores (Options API style)
    userStore.js              # User state + roles/permissions
    settingStore.js           # App settings (theme, locale, etc.)
    authStore.js              # Auth state
    chatStore.js, conversationStore.js, notificationStore.js, etc.
  pages/                      # Page-level components
    admin/                    # Admin pages (Settings, etc.)
      settings/               # Settings tabs (General, Branding, Theme, etc.)
    auth/                     # Signin, Signup
    dashboard/                # User dashboard
    landing/                  # Public pages (HomePage, Wiki, Pages, Posts)
    users/                    # User profiles, account
  components/                 # Reusable components
    admin/, chat/, common/, conversation/, dashboard/
    event/, footer/, gallery/, landing/, navigation/
    settings/, ticket/, toolbar/, wiki/
  composables/                # useSettings, useLocale, usePWA, useTicketHelpers, etc.
  translations/               # en.js, de.js (large JS objects)
  plugins/                    # Vuetify, i18n, formatDate, Google Maps, etc.
  configs/                    # navigation.js, locales.js, theme.js, widgetTypes.js
  layouts/                    # DefaultLayout, AuthLayout, LandingLayout, SimpleLayout, ErrorLayout
```

## Key Conventions

### Backend
- Controllers are **flat** in `app/Http/Controllers/` (not namespaced in subfolders per feature)
- Route references use **string controller syntax**: `"\App\Http\Controllers\WikiController@show"`
- API routes are all in `routes/api.php`, grouped by middleware (`auth:sanctum`, `cache.control`, admin)
- Models use **Astrotomic Translatable** for multi-language fields (title, content, etc.)
- Polymorphic relationships used for tickets (`ticketable`), approvals, relateables
- DataTable controllers handle server-side pagination/filtering for admin tables
- Custom middleware: `AuthenticateApiKey`, `CacheControl`, `DynamicRateLimit`, `EnsureUserIsAdmin`, `SetLocale`

### Frontend
- Vue components use **Options API** (not Composition API) — newer components may use `<script setup>` but the codebase standard is Options API
- Pinia stores use **Options API style** (state/getters/actions, not setup function)
- Translations are in `resources/js/translations/{en,de}.js` as large nested JS objects
- Layouts determined by route `meta.layout` (defaults to 'default')
- Auth middleware pipeline: routes declare `meta: { middleware: [auth, verified] }`
- Permission checks: `userStore.hasRole('admin')`, `userStore.hasPermission('edit-wiki')`
- User state persisted to localStorage (`USER_INFO` key)
- Global event bus via `mitt` (accessible as `this.emitter` or injected)

### Taxonomy System
- Tags/categories use a generic **Taxonomy/Term** system (not dedicated models per feature)
- Forum categories use `Taxonomy` with `type: 'forum_cat'`
- Properties stored in `taxonomy.properties` JSON column
- Names/slugs via `taxonomy.term.title` / `taxonomy.term.slug`

### Ticket System
- Models in `app/Models/Ticket/` — Ticket, TicketComment, TicketType
- Polymorphic `ticketable` relation (nullable) links to Wiki, Page, or other models
- Frontend: `TicketList.vue` (split-panel with list + content viewer) + `TicketSidebar.vue` (drawer for details)
- Helpers in `resources/js/composables/useTicketHelpers.js`
- Available at `/admin/tickets` (admin) and `/account/tickets` (user)

## Commands

```bash
# Dev server (backend + frontend)
php artisan serve
npm run dev

# Build
npm run build

# Database
php artisan migrate
php artisan migrate:fresh --seed

# Tests
php artisan test                 # PHPUnit
npm run test                     # Vitest
npm run test:watch               # Vitest watch mode

# Cache
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Other
php artisan storage:link         # Symlink storage/app/public
php artisan queue:work           # Process jobs
```

## Database

- **Driver:** MySQL (utf8mb4, strict mode)
- **Secondary:** `stadtwache` connection for migration imports
- **Redis:** Used for cache and broadcasting
- Migrations in `database/migrations/`, seeders in `database/seeders/`
- Key seeders: Users, Roles, Permissions, Settings, Homepage, Footer, EventTypes, EventProfiles

## Authentication & Authorization

- **SPA Auth:** Sanctum cookie-based (no tokens for web, tokens for external API)
- **Social Login:** Google, Discord, GitHub, Facebook via Socialite
- **Roles/Permissions:** Spatie package — roles and permissions stored in DB
- **Admin check:** `$user->isAdmin()` method / `EnsureUserIsAdmin` middleware
- **API Keys:** Custom `ApiKey` model with `AuthenticateApiKey` middleware for external `/api/v1/` routes

## Localization

- **Supported languages:** en, de (primary), plus ar, es, fr, ja, ko, pl, pt, ru, zh
- **Backend:** Laravel translation files in `resources/lang/` + Astrotomic model translations
- **Frontend:** `resources/js/translations/{en,de}.js` — large JS objects with nested keys
- **Locale detection:** localStorage → cookie → browser default
- **Adding translations:** Add keys to both `en.js` and `de.js`, maintain same nesting structure

## Real-time Features

- **Broadcasting:** Laravel Echo with Ably (or Pusher fallback)
- **User channel:** `users.{id}` (private)
- **Notifications:** Real-time via broadcasting, stored in DB
- **Chat & Conversations:** Real-time messaging with presence channels

## Environment Setup

1. `composer install`
2. `npm install`
3. `cp .env.example .env && php artisan key:generate`
4. Configure MySQL in `.env`
5. `php artisan migrate --seed`
6. `php artisan storage:link`
7. Run `php artisan serve` + `npm run dev`

Laravel Sail available as alternative: `./vendor/bin/sail up -d`
