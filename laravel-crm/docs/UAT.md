# CRM UAT

The automated UAT scenarios are in `tests/Feature/UATCriticalFlowsTest.php`. They use the Laravel testing database and mocked/in-memory JustCall payloads; no live credentials or real passwords are required.

## Run

```powershell
php artisan test tests/Feature/UATCriticalFlowsTest.php
```

Or use the project shortcut:

```powershell
composer test:uat
```

## Automated Scenarios

1. Authentication and RBAC: admin and agent login, protected administration access, lead creation/assignment/qualification, activity visibility, conversion, and record isolation.
2. Lead conversion: Lead to Customer, Contact, and Opportunity links remain connected to the source lead.
3. Opportunity lifecycle: create, update, stage progression, won, and lost with a loss reason.
4. Agent workflow: dashboard follow-up and overdue visibility, assigned work, and permitted record access.
5. JustCall lifecycle: signed mocked webhook intake, normalization, agent mapping, phone matching, call log persistence, missed-call activity/notification behavior, recording authorization, monitoring, and reconciliation.
6. Import/export and workflows: representative CSV import, permission-safe export, workflow action idempotency, and agent isolation.

## Test Accounts

The tests create temporary users with roles and permissions inside the testing database. Credentials are generated only for the test process and are not real production credentials.

- `super-admin`: administrative access for UAT setup and protected monitoring/reconciliation checks.
- `agent`: permitted CRM work, reporting, calls, and export access.
- Separate agent users: record-level isolation checks.

## Prerequisites

- PHP and Composer dependencies installed.
- A valid Laravel `APP_KEY` for the application test environment.
- SQLite testing support available through the configured PHP extensions.
- No database, storage, JustCall, mail, or external messaging service is required for automated UAT.

## Expected Results

All UAT scenarios should pass with no leaked unauthorized records, no live provider calls, no duplicate workflow execution, and intact CRM relationship/history assertions.

## Manual Checks Before Production

- Run UAT and the complete PHPUnit suite in the release environment.
- Verify login, dashboard, lead conversion, opportunity stage changes, reports, imports, exports, and `/admin/health` through the browser.
- Verify a signed JustCall webhook using the provider’s staging/test configuration and confirm no secrets appear in logs or rendered pages.
- Confirm backup/recovery rehearsal, storage permissions, migration status, queue behavior, and production `APP_KEY`/database/session configuration.
- Confirm role permissions and hierarchy with representative admin, manager, and agent accounts.
- Confirm CSV export handling and retention comply with the organization’s data policy.
