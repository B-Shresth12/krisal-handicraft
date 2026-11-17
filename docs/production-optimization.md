# Production optimization

These commands were verified against Laravel 13.42 and Filament 5.11.

## Deploy

```sh
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan optimize
php artisan filament:optimize
php artisan icons:cache
```

What they do:

- `php artisan optimize` caches the config, routes, events and views.
- `php artisan filament:optimize` caches Filament components and Blade icons.
- `php artisan icons:cache` generates a Blade icon manifest. It is already
  covered by `filament:optimize` for Filament icons, but running it explicitly
  is harmless.

Run the cache commands **after** `migrate` and after the assets have been
built. They must be re-run whenever resources, pages, widgets, config or
routes change.

## Clearing caches

```sh
php artisan optimize:clear
php artisan filament:optimize-clear
```

`php artisan optimize:clear` clears config, route, event, view and application
caches. `filament:optimize-clear` clears the Filament component and icon
caches.

## Notes

- The USD to NPR rate is cached by `CurrencyConverter`. Saving the rate from
  the admin page invalidates it, so no deploy-time cache clear is needed for
  rate changes.
- The low-stock navigation badge is cached for five minutes; it does not need
  a production cache action.
- Do not run `optimize` or `filament:optimize` in local development unless you
  remember to clear them; stale component caches make Filament changes appear
  to have no effect.
