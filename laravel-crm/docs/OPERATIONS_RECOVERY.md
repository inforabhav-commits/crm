# CRM Operations and Recovery

This document describes the conservative backup and recovery procedure for the Laravel CRM. Backups must be stored outside the public web root and access-controlled separately from the application.

## Backup

From `laravel-crm`, set the database environment variables in the process environment or load them from the approved secret store. Do not commit `.env` or paste credentials into scripts.

```powershell
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '3306'
$env:DB_DATABASE = 'crm'
$env:DB_USERNAME = 'crm_backup'
$env:DB_PASSWORD = '<retrieve securely; do not commit>'
$env:CRM_BACKUP_ROOT = 'D:\secure-backups\crm'
.\scripts\backup\backup.ps1
```

The script creates a timestamped directory containing `database.sql` and `storage-app.zip`. It excludes `.env`, credentials, logs, caches, and public configuration. The default retention is 30 days and can be changed with `-RetentionDays`; keep at least one backup in a separate host or storage account.

Recommended operational schedule:

- Database and uploaded storage: daily, with a shorter interval if business volume requires it.
- Retention: at least 30 daily copies plus periodic monthly copies.
- Verify backup file size and creation output after each run.
- Periodically perform a restore rehearsal in an isolated environment.

## Recovery

Do not restore over a live database or storage directory without an approved maintenance window and a fresh pre-restore backup.

1. Put the application in maintenance mode or remove it from the load balancer.
2. Provision a clean application directory from the known-good release. Restore `.env` from the approved secret store; never reconstruct secrets from logs or backups.
3. Create or select the target MySQL database and verify the target host, database name, and account.
4. Inspect the SQL file before restoring. A restore is intentionally manual and destructive only when an operator explicitly runs it:

```powershell
mysql --host=$env:DB_HOST --port=$env:DB_PORT --user=$env:DB_USERNAME --password $env:DB_DATABASE < .\database.sql
```

5. Extract `storage-app.zip` into `storage\app` after confirming the destination and permissions.
6. Install the release dependencies and rebuild Laravel caches:

```powershell
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

7. Verify migration state without applying changes:

```powershell
php artisan migrate:status
```

8. Run the application smoke checks: login, dashboard, one visible CRM list, reports authorization, `/admin/health`, and a signed JustCall webhook test in the approved environment.
9. Review logs and the health page. Confirm database, storage, webhook backlog, JustCall configuration, and workflow execution status.
10. Exit maintenance mode only after the smoke checks pass.

## Environment and Secrets

Required runtime values include `APP_KEY`, `APP_ENV`, `APP_URL`, database connection values, session settings, and any enabled JustCall values such as `JUSTCALL_API_KEY`, `JUSTCALL_API_SECRET`, and `JUSTCALL_WEBHOOK_SECRET`. Keep these in the deployment secret store. Do not place them in Git, public storage, CSV exports, logs, or audit descriptions.

## Common Checks

```powershell
php artisan migrate:status
php artisan route:list
php artisan optimize:clear
php artisan test
```

The protected operational page is available at `/admin/health` to users with the `ops.view` permission. It reports only safe statuses and counts, not raw provider payloads or secrets.

## Rollback Notes

Prefer rolling back application code first while keeping the database schema compatible. Do not automatically run `migrate:rollback`, drop tables, overwrite storage, or restore a database. Take a fresh backup, confirm the target release and migration state, and obtain an explicit operator decision before any destructive action.
