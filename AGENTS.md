# AGENTS.md

Early-stage Laravel 13 admin app (PHP 8.3, Filament 5.11, Vite 8/Tailwind 4). Only the default Filament panel, `User` auth, and welcome page exist — no domain models or Filament resources yet.

## Commands

- `composer dev` — starts `php artisan dev`: web server on http://localhost:8000, database queue worker, and Vite together.
- `composer test` — clears config, then runs `php artisan test`. Tests use in-memory SQLite (`phpunit.xml`), not the local DB.
- `php artisan test tests/Feature/ExampleTest.php` or `php artisan test --filter=test_name` — run a single test.
- `vendor/bin/pint` — format PHP; use `vendor/bin/pint --test` to check. There is no composer lint script.
- `npm install` then `npm run build` — assets. `node_modules` is untracked/absent; `.npmrc` sets `ignore-scripts=true`.
- Typical check before finishing: `vendor/bin/pint` → `composer test`; run `npm run build` if you changed CSS, JS, or Blade/Vite inputs.

## Environment and setup

- `.env` is untracked and points at MySQL database `krusal_handicraft`; `.env.example` defaults to SQLite. Do not replace the working `.env` with `.env.example`.
- The local MySQL DB already contains user data. Prefer `php artisan migrate`; avoid `migrate:fresh` or `db:wipe` unless explicitly asked.
- `composer setup` is for a fresh checkout (install, key, `migrate --force`, npm install/build).
- `composer install/update` runs `filament:upgrade` via `post-autoload-dump`; expect Filament file changes after dependency updates.
- `vendor/bin/pint --test` currently fails on `bootstrap/providers.php` because of mixed line endings; running Pint rewrites it.

## Structure

- Filament panel `admin` is served at `/admin` and configured in `app/Providers/Filament/AdminPanelProvider.php`. It auto-discovers `app/Filament/{Resources,Pages,Widgets}` under `App\Filament\*`; generate files with `php artisan make:filament-*` instead of registering them manually.
- No admin credentials are committed. Create a login with `php artisan make:filament-user`; `php artisan db:seed` would create `test@example.com` / `password`.
- Providers are registered in `bootstrap/providers.php`, not `config/app.php`.
- Models use PHP attributes (`#[Fillable]`, `#[Hidden]` on `App\Models\User`) rather than `$fillable`/`$hidden`; follow that pattern for new models.
- `routes/web.php` only defines `/`. Filament routes are vendor routes, so `php artisan route:list --except-vendor` will not show `/admin`.
- Vite inputs are `resources/css/app.css` and `resources/js/app.js`.

## Testing

- PHPUnit 12 with separate `tests/Unit` and `tests/Feature` suites; extend `Tests\TestCase`. Pest is not used.
- `php artisan test` emits `laravel/pao` JSON for agents, not normal PHPUnit text.
- Add `RefreshDatabase` to tests that touch the database; the example tests do not use it.
- No CI or JS lint/typecheck is configured.

## Instruction files

- `CLAUDE.md` is the old Laravel Boost bootstrap stub. Ignore its `boost:install` step: Boost is not a dependency here, and installing it would replace this file.
