# CRM Deployment

This is a deployment preparation guide for the Laravel CRM. It does not deploy, alter DNS, or run destructive database operations.

## Runtime Requirements

- PHP 8.1 is the reference runtime for this Laravel 9 application. PHP 8.0.2 or newer is required by `composer.json`; use a supported PHP 8.1 patch release for production.
- Required PHP extensions include `Ctype`, `cURL`, `DOM`, `Fileinfo`, `Filter`, `Hash`, `Mbstring`, `OpenSSL`, `PDO`, `PDO_MySQL`, `Session`, `Tokenizer`, and `XML`. `Zip` is recommended for Composer and deployment packaging.
- MySQL 8.0+ or a compatible MySQL 5.7 installation with InnoDB, foreign keys, JSON columns, and UTF-8 support.
- A web server with HTTPS, URL rewriting, and PHP-FPM or an equivalent PHP SAPI. Apache or Nginx are suitable.
- The web server document root must be `laravel-crm/public/`, never the repository root.

## Environment Setup

1. Copy `.env.example` to `.env` outside source control.
2. Set a unique production `APP_KEY` with `php artisan key:generate --show`, then store it in the deployment secret manager.
3. Set `APP_ENV=production`, `APP_DEBUG=false`, and the public HTTPS `APP_URL`.
4. Configure MySQL values: `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`.
5. Configure session and mail values. Use HTTPS session cookies in production with `SESSION_SECURE_COOKIE=true`.
6. Configure JustCall only when enabled. Keep `JUSTCALL_API_KEY`, `JUSTCALL_API_SECRET`, and `JUSTCALL_WEBHOOK_SECRET` out of Git and logs.
7. Do not commit `.env`, database dumps, uploaded files, or generated storage files.

## Install and Migrate

From `laravel-crm/`:

```powershell
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan ops:preflight
php artisan migrate --force
php artisan db:seed --class=DatabaseSeeder --force
```

Review migration output and database backups before applying migrations. The preflight command is read-only; it does not apply migrations.

Roles and permissions (including `calls.view`/`calls.initiate` used by Click-to-Call) are defined in `DatabaseSeeder`, not in migrations. `DatabaseSeeder` only uses `firstOrCreate`/`syncWithoutDetaching`, so it is safe and non-destructive to run on every deployment (fresh or existing) — it will not duplicate permissions, remove existing role assignments, or reset the admin password of an already-existing user.

## Permissions and Document Root

The PHP/web-server account needs read access to the application and write access to:

- `storage/framework/`
- `storage/logs/`
- `storage/app/`
- `bootstrap/cache/`

Keep `.env`, `storage/`, `vendor/`, and database backups outside the public document root. Only `laravel-crm/public/` should be served by the web server.

## Production Optimization

After the environment and migrations are verified:

```powershell
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Route caching is compatible with the current route definitions. Do not cache configuration before `.env` and deployment secrets are present. `QUEUE_CONNECTION=sync` is the current default; if a supported queue backend is configured later, run its worker under the host’s process supervisor. The scheduler currently has no required recurring task; if that changes, run `php artisan schedule:run` once per minute through the host scheduler.

## Smoke Checks

- Open `/login` over HTTPS and verify admin and agent authentication.
- Verify `/dashboard`, one CRM list, reports, import/export permissions, and `/admin/health`.
- Verify an agent cannot open another owner’s records.
- Verify a signed JustCall webhook in the approved staging/provider environment if JustCall is enabled.
- Run `php artisan migrate:status` and confirm no unexpected pending migrations.
- Inspect `storage/logs/laravel.log` for failures without exposing it through the web server.

## Rollback Guidance

Prefer rolling back application code to a known-good release while keeping the database schema compatible. Do not automatically run `migrate:rollback`, drop tables, overwrite storage, restore a database, or change DNS. Take a fresh backup, review migration compatibility, and obtain an explicit operator decision before any destructive rollback. See [OPERATIONS_RECOVERY.md](OPERATIONS_RECOVERY.md) for backup and restore procedures.

## CI/CD Boundary

The repository CI workflow runs Composer installation, creates an isolated SQLite test database, generates a test `APP_KEY`, runs migrations, and executes PHPUnit. It does not deploy to production, access production secrets, modify DNS, or alter a live database.
