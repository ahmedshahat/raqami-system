# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

"رقمى سيستم" (Raqmy System): a multi-tenant cloud ERP/POS built on **UltimatePOS V6.12** (a commercial CodeCanyon Laravel product), customized for Arabic/Egyptian/Saudi businesses. Laravel 9, PHP 8, MySQL, Blade + jQuery + DataTables. The owner communicates in Arabic. The main area of active development is the custom `Modules/Construction` contracting module.

## Commands (Windows, XAMPP)

PHP isn't on PATH. Use the XAMPP binary:

```bash
C:\xampp\php\php.exe artisan serve --host=127.0.0.1 --port=8000   # start MySQL in XAMPP first
C:\xampp\php\php.exe artisan migrate                              # also runs module migrations
C:\xampp\php\php.exe vendor/bin/phpunit                           # full suite
C:\xampp\php\php.exe vendor/bin/phpunit tests/Feature/ConstructionCertificateWorkflowTest.php
C:\xampp\php\php.exe vendor/bin/phpunit --filter test_approved_measurement_creates_one_certificate
C:\xampp\php\php.exe artisan construction:seed-demo {business_id}  # idempotent demo construction project
```

The local DB is `betasolu_v612` (root, no password), seeded from `betasolu_v612.sql` (git-ignored dump). Front-end assets in `public/` are prebuilt. There's no npm build in the normal workflow.

**Tests run against the real local MySQL DB.** The SQLite lines in `phpunit.xml` are commented out. Feature tests use `DatabaseTransactions`, not `RefreshDatabase`, and depend on existing seed data: for example `Business::whereHas('locations')->...->firstOrFail()`, the business owner user, existing customer contacts, and a `SAR` currency. They fail on an empty database. Never add `RefreshDatabase`/`DatabaseMigrations`, because that would wipe the dev DB.

## Architecture

### Core (`app/`)
Classic UltimatePOS layout. Models sit directly in `app/` (`App\Transaction`, `App\Contact`, `App\Business`, …), not in `app/Models`. Business logic lives in `app/Utils/*Util.php` (`TransactionUtil`, `ProductUtil`, `ModuleUtil`, `Util`). `App\Transaction` is the polymorphic document table: sells, purchases, expenses, and so on, distinguished by `type`. `routes/web.php` includes `routes/install_r.php`.

**Multi-tenancy:** every operational row carries `business_id`. The current tenant comes from `session('user.business_id')`, populated by the `SetSessionData` middleware. Scope every query by it.

**Permissions:** `spatie/laravel-permission`, with permission strings like `construction.access`. Admin check: `(new Util())->is_admin($user, $businessId)`. In Superadmin/SaaS mode, module availability also passes through `ModuleUtil::hasThePermissionInSubscription($businessId, '<module>_module')`.

### Modules (`Modules/`, nwidart/laravel-modules)
Enabled modules are listed in `modules_statuses.json`. Several entries there have no folder in the repo, so don't assume a listed module exists. Each module follows the UltimatePOS module convention:

- `Providers/<Name>ServiceProvider.php` registers config, views, translations (`<alias>::lang.key`), migrations, commands, and event listeners.
- `Http/Controllers/DataController.php` holds the **hook methods the core calls on every module**, for example `modifyAdminMenu()` to inject sidebar items and `superadmin_package()`/`user_permissions()` to declare permissions. This is how modules extend core UI without editing it. `home_dashboard_widgets(['business_id', 'location_id'])` returns cards (title, icon, tone, url, items) that the new home (`/home/v2`) renders in the Raqmi design (see `BostaShipping` and `ZatcaIntegrationKsa`).
- `Http/Controllers/InstallController.php` + `Config/config.php` `module_version`: the installed version is stored via `System::addProperty('<Name>_version')`. Bumping `module_version` lets admins run the module's `/install/update`, which runs migrations and records the new version.
- Routes use the middleware stack `web, SetSessionData, auth, language, timezone, AdminSidebarMenu`.
- Modules attach relations to core models with `Model::resolveRelationUsing(...)` instead of editing core models (see `ConstructionServiceProvider`).

### Construction module (`Modules/Construction`)
- **Domain:** quotes → projects → BOQ versions/items → contracts (+ adjustments) → measurements → customer certificates (IPCs) → collections and retention releases. On the cost side there are material documents, labor sheets and payments, expenses, and subcontracts (certificates, payments, advances, retention releases). The roadmap and design principles are in `docs/construction-module-roadmap-ar.md` (Arabic).
- **`Support/`** holds the non-trivial logic: the report builders (`*Report.php`, used by both screens and print/PDF via `PrintPdfFactory`) and **`ConstructionAccountingPoster`**.
- **Accounting integration:** the module posts journal entries into `Modules/Accounting` (`AccountingAccountsTransaction`, `AccountingAccTransMapping`). Every posting is recorded in `ConstructionAccountingPosting` keyed by `(business_id, source_type, source_id, event)`. That key makes posting idempotent: re-posting returns the existing row, and rows are locked with `lockForUpdate()`. Cancellation creates a reversing `cancelled` posting rather than deleting. Account mapping per business is in `ConstructionAccountingSetting`. A reconciliation screen lives at `settings/accounting/reconciliation`.
- **Approved documents are never deleted.** Entities using the `ProtectsPostedConstructionDocument` trait block `deleting` once posted. Corrections go through cancellation or reversal with an audit trail (`Support/AuditTrail`).
- **Core event coupling:** `Listeners/MapCustomerCollection` listens to `TransactionPaymentAdded/Updated/Deleted`. That's how core payments get mapped to construction certificates.
- **Purchases stay inventory-based:** they're general and go into stock. Cost is charged to a project only when a site material issue document is approved.
- Translations live in both `Resources/lang/ar` and `Resources/lang/en`. New UI strings need both.
- When adding schema, add a new migration in `Database/Migrations`. Migrations must be safe to re-run. Then bump `module_version` in `Config/config.php`.

### Construction UI design system (required for new screens)
Every new screen in `Modules/Construction`, and any screen being redesigned, **must** use the shared design system in `public/css/raqmi.css` ("رقمي"). It is loaded on every page by `resources/views/layouts/app.blade.php` (all its rules are scoped to `.rr-page`/`.rr-scope`), so views must not link it again. The app bar (`layouts/partials/header.blade.php`, `.rr-appbar`) and the sidebar brand block use it too. Reference implementations: `dashboard/overview.blade.php` and `quotes/index.blade.php`.

- Wrap the screen in `<main class="content rr-page">`. All tokens are defined on `.rr-page` (plus a `[data-theme="dark"]` variant), so `rr-*` components don't render correctly outside it. Elements rendered outside `<main>`, such as Bootstrap modals, get `class="rr-scope"` instead. Modals use the `rr-modal` markup. The reference is `quotes/partials/form_modal.blade.php`, which keeps the `data-ct-quick-create` and `.ct-quick-errors` hooks used by `partials/quick_create`.
- Use the `--rr-*` tokens (colors, `--rr-fs-*`/`--rr-lh-*` type scale, `--rr-space-*`, `--rr-control-*`, `--rr-radius-*`) and the existing `rr-*` component classes: page header, breadcrumbs, buttons, badges, money, cards, stat groups, tables, fields/controls, filter bar, menu, pager, stepper, nav list, progress. The header comment of `raqmi.css` documents the markup for each. Don't hard-code colors or spacing in views, and don't add new per-screen stylesheets. Add a missing component to `raqmi.css` instead.
- Icons come from `@include('partials.rr.icon', ['name' => ...])` (inline stroke SVG, no emoji; `construction::partials.rr_icon` is a wrapper for it). Add new glyphs in `resources/views/partials/rr/icon.blade.php`. Money in core screens: `partials.rr.money`; chart tooltips: `partials.rr.chart_script`. Pagination uses `@include('construction::partials.rr_pager', ['paginator' => $p, 'itemLabel' => ...])`.
- The core theme (`public/css/app.css`, `rtl.css`) forces `font-family` and `font-size` with `!important` on every element. So every `font-size` in `raqmi.css` also carries `!important`. Follow that pattern in new rules.
- The app font is IBM Plex Sans Arabic (`public/fonts/ibm-plex-sans-arabic/`).
- Numbers and document codes go in `rr-num` / `rr-code` (LTR-isolated, tabular).
- `public/modules/`, `public/css/` and `public/fonts/` are git-ignored, so new or changed files there need `git add -f` and a manual upload on deploy.

### Other notable pieces
- `config/demo_reset.php` + `docs/demo-business-reset.md`: a nightly snapshot/restore of the demo businesses (ids 81, 92), guarded by `DEMO_RESET_ENABLED` and an id allow-list. The commands are `demo:snapshot` and a restore that requires `--force` and supports `--dry-run`.
- `inventory_app/` is a separate Flutter mobile app for stock counting. It's unrelated to the PHP build.
- `UltimatePOS-V6.12/` holds the original vendor codebase archive, for reference only.

## Repo hygiene notes
- `.env` and `betasolu_v612.sql` are git-ignored. `.env.txt` (a production-style env) and `.codex/` (FTP deploy scripts that touch production) are untracked but **not** ignored, so avoid `git add .` and `git add -A`.
- The root and `Modules/` contain many hotfix `.zip` bundles. These are deployment artifacts, not source.
