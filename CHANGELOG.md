# Changelog

All notable changes to Fellowship are documented here. This project follows
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0-alpha.1] — 2026-11-15

First tagged release of the project, and the first alpha of the 2.0 line. It
collects 636 commits made on `develop` since the 1.5.3 state that `main` still
holds: 1139 files changed, with the forum, wiki, permissions and migration
subsystems rebuilt rather than extended. Treat it as breaking against 1.5.3.

### Platform

- Laravel 12 on PHP 8.1+ (CI builds and deploys on 8.4)
- Vue 3 + Vuetify 3 with Vite, replacing the Laravel Mix build
- Pinia for state, vue-i18n for translations
- Realtime through Laravel Echo with Ably or Pusher
- Meilisearch via Laravel Scout; Redis-backed queue and cache available

### Added

**Content and collaboration**
- Wiki: Vue 3 rewrite, categories, special pages, revision history
- Forum: threads, posts, solutions, likes, subscriptions, activity feed,
  filtering and sorting, spam detection, `@mentions`
- Events with calendar views, and a mailing system that composes event mail
- Ticket system
- Poll system
- Collaborative sandbox
- Media center, gallery and podcast support
- Status timeline and user pages
- IRC client, including comic-chat rendering
- Direct conversations and chat
- Page help tours written by editors, shown on the page they describe

**Site-wide systems**
- Permissions reworked onto one permission per feature (Spatie)
- Menu system with admin management
- Settings grouped into categories
- Dashboard with a quick-links widget
- Breadcrumbs
- Activity log and revisionable history (Spatie)
- Achievements
- Notifications: mentions, and toasts that can be switched off per user
- Generic `Commentable`, `Voteable` and `Watchable` traits
- Translations: translated columns in `*_translations` tables, German and
  English, consolidated locale configuration
- Migration tool for importing legacy content, mapping-driven
- Discord webhooks and Socialite login
- Session timeout modal
- Feedback system

### Changed

- Forum categories now use the `Taxonomy` model (type `forum_cat`) instead of a
  dedicated `Forum` model. `forum_threads.taxonomy_id` references
  `taxonomies.id`; `is_locked` and `is_private` live in `taxonomy.properties`.
  The `Forum` model is gone. **Breaking.**
- Controllers for namespaced models moved into matching feature subfolders;
  traits consolidated under `app/Traits`, helpers and presenters under
  `app/Support`.
- Every routed page moved under `resources/js/pages/<feature>/`; `components/`
  holds reusable pieces only, and `utils/` replaced `helpers/`.
- Schema rebuilt by a consolidated set of migrations, with seeders that are safe
  to run twice.
- Event controller moved to Eloquent; gallery moved onto the relateable
  relationship.
- Frontend feedback standardised on an app-wide dialog service
  (`DialogHost.vue`, `dialogStore.js`) instead of per-component alerts.
- Responsive layout and general UX pass across the frontend.

### Fixed

- Authorization on admin routes, the menu system's admin endpoints, the
  collection controller, the event controller and the poll system
- Injection through `relateable_type`
- Category updates from the page form
- Datatable, username and wiki quick-win defects
- Deployment no longer truncates the server's `.env` when the disk is full: the
  configuration is written beside the live file, checked, then moved into place

### Infrastructure

- StyleCI across the codebase, with a local `pint`/`php-cs-fixer` toolchain and
  a pre-commit hook
- GitHub Actions: tests and staging deploy on `develop`, build and production
  deploy on `main`
- PHPUnit for the backend, Vitest with Vue Test Utils for the frontend

### Known limitations

This is an alpha. The 2.0 schema is not migration-compatible with a live 1.5.3
database — the consolidated migrations build it from scratch, so an existing
production database needs a planned migration path before 2.0 ships.
