# Utilynk Lists

Utilynk Lists is a free shopping list and checklist app with guest access, private sharing, Shopping Mode, and offline editing. The public website uses server-rendered Laravel Blade. The list interface uses Vue 3 with IndexedDB and a service worker.

```bash
composer install
npm ci
cp .env.example .env
touch database/database.sqlite
php artisan key:generate
php artisan migrate
npm run build
php artisan serve
```

Use a fresh local checkout for the commands above. Open http://127.0.0.1:8000. The production asset build enables the service worker. A Vite development server does not enable it.

```bash
php artisan test
npx playwright install chromium
npm run test:browser
```

See [DEPLOYMENT.md](DEPLOYMENT.md) for aaPanel and GitHub Actions setup, [SECURITY.md](SECURITY.md) for the access and data model, [OFFLINE.md](OFFLINE.md) for sync semantics, and [PRODUCTION.md](PRODUCTION.md) for release verification.
