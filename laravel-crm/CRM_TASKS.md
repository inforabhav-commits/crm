# CRM Tasks

## Final Master Roadmap

- [x] T01 - Project audit & architecture baseline
- [x] T02 - Environment & configuration
- [x] T03 - Database foundation
- [x] T04 - Authentication & sessions
- [x] T05 - RBAC engine
- [x] T06 - Admin user/team management
- [x] T07 - CRM master data
- [x] T08 - Lead module
- [x] T09 - Lead assignment
- [x] T10 - Contacts & accounts
- [x] T11 - Lead qualification & conversion
- [x] T12 - Activities & follow-ups
- [x] T13 - Opportunity pipeline
- [x] T14 - Agent dashboard / work queue
- [x] T15 - Notifications
- [x] T16 - Audit & governance
- [x] T17 - JustCall integration config
- [x] T18 - JustCall user mapping
- [x] T19 - Webhook receiver
- [x] T20 - Call event normalizer
- [x] T21 - Call logs & CRM timeline
- [x] T22 - Click-to-call
- [x] T23 - Call disposition / notes sync
- [x] T24 - Recording access
- [x] T25 - Incoming call screen-pop
- [x] T26 - Missed-call automation
- [x] T27 - Integration monitoring & reconciliation
- [x] T28 - Reports & dashboards
- [x] T29 - Import / export
- [x] T30 - Workflow rules
- [x] T31 - Performance & security hardening
- [x] T32 - Backup / monitoring / recovery
- [x] T33 - UAT automation
- [x] T34 - Deployment & CI/CD
- [x] T35 - Phase 2 backlog

## Current

- Current Task: None
- Next Task: None
- Roadmap Status: COMPLETE

## Previous L-task Mapping

- L01 -> T01/T02/T03/T04 foundation portions
- L02 -> T05
- L03 -> T06
- L04 -> T07
- L05 -> T08
- L06 -> T09
- L07 -> T12
- L08 -> T10
- L09 -> T10 customer 360 foundation

## Architecture / Database Notes

- Laravel foundation exists in `laravel-crm`.
- L01 created Blade/session auth, the CRM layout, `users`, `roles`, and `role_user`.
- L02 added user management, active/inactive users, scalable permission tables, Gates, role assignment, and RBAC tests.
- L03 added `teams`, `team_user`, and `users.reports_to_id` for team membership and reporting hierarchy.
- Reporting hierarchy is user-based, not role-name based, with self-reporting and circular hierarchy protection.
- L04 added `crm_master_values` for reusable CRM master data: lead statuses, lead sources, opportunity stages, activity types, and loss reasons.
- CRM master settings are RBAC-protected with `crm_settings.view` and `crm_settings.manage`.
- L05 added `leads` with status/source master values, owner assignment, hierarchy-aware visibility, search/filtering, and lead CRUD screens.
- Lead permissions are `leads.view`, `leads.create`, `leads.edit`, and `leads.assign`.
- L06 added manual, team, and round-robin lead assignment with `lead_assignment_histories`.
- Round-robin uses active teams and active users with the Agent role, preserving assignment history for reassignment and reporting.
- L07 added reusable polymorphic `activities` tied to leads now and ready for customers/opportunities later.
- Activities use CRM master `activity_type` values, RBAC permissions, hierarchy-aware visibility, completion outcomes, and next follow-up creation.
- L08 added customer/account and contact CRUD with ownership visibility, active status, lead conversion foundation, and related activities.
- Contacts belong to customers; activities can attach to leads, customers, or contacts through the existing polymorphic activity relation.
- L09 enhanced the customer detail page into a Customer 360 view with profile, primary contact, related contacts, source lead, summaries, follow-ups, and a combined customer/contact activity timeline.
- Customer 360 reuses polymorphic activities and existing ownership/hierarchy visibility; no duplicate history system was added.
- T11 added lead qualification fields, qualified/converted tracking, conversion links, `contacts.source_lead_id`, and a minimal `opportunities` foundation table.
- Lead conversion creates or reuses clear matching customers/contacts, creates an opportunity foundation record, updates lead status to Converted, and runs inside a database transaction.
- T13 extended the existing `opportunities` foundation with CRUD, pipeline fields, won/lost state, loss reasons, stage history, Kanban pipeline view, and polymorphic opportunity activities.
- Opportunity visibility follows existing ownership/hierarchy rules, and lead-converted opportunities work in the full pipeline.
- T14 replaced the placeholder dashboard with role-aware KPIs, filters, daily work queue, recently assigned leads, recent CRM activity, and scoped summaries using existing visibility rules.
- T15 added Laravel database notifications for lead assignment/reassignment, activity due/overdue checks, and opportunity stage changes with unread/read state and a CRM inbox.
- T16 added append-only audit logs for authentication, user/role/team changes, CRM master values, leads, assignments, qualification/conversion, customers, contacts, activities, and opportunities with RBAC-protected audit viewing.
- T17 added secure Laravel JustCall config, env-only secrets, server-side config/status service, RBAC-protected Admin JustCall settings, safe connection testing, and audited test actions.
- T18 added active JustCall user mapping for CRM users with stable external IDs, safe API user fetch/verification, suggested email/name matching, uniqueness safeguards, and audited mapping actions.
- T19 added a public JustCall webhook receiver with official dynamic signature validation, replay protection, URL validation response handling, idempotent safe webhook inbox persistence, and a read-only admin inbox.
- T20 added JustCall call event normalization on webhook inbox entries, including stable event names, safe timestamp/duration parsing, normalized phone comparison values, active JustCall user mapping resolution, pending inbox processing, and unsupported-event handling.
- T21 added durable `call_logs` records applied from normalized JustCall inbox events, idempotent provider/call upserts, conservative exact phone matching to contacts/customers/leads, CRM user mapping, RBAC-scoped Calls UI, and Lead/Customer/Contact call history timelines.
- Call history is surfaced directly from `call_logs` rather than creating duplicate CRM activities; Activities remain for intentional tasks/follow-ups.
- T22 added RBAC-protected JustCall click-to-call actions on Lead, Customer, and Contact detail pages using the official JustCall dialer URL flow, active CRM user mapping checks, server-side record/phone validation, safe launch auditing, and no API secret exposure.
- T23 added local CRM disposition/notes editing for call logs with `calls.update`, provider-vs-CRM disposition/notes separation, audited manual updates, and one linked follow-up activity per call when scheduled.
- Call disposition precedence: provider webhook values are preserved in provider fields, CRM-entered values are stored separately and become the displayed call outcome/notes; later provider updates do not overwrite CRM-entered notes/disposition. Outbound provider disposition/notes sync is not implemented because account-specific JustCall disposition code mapping is not yet configured.
- T24 added recording metadata/status on `call_logs`, JustCall event extraction for documented recording links, RBAC-protected `/calls/{callLog}/recording` access with visibility checks, safe failure handling, and audited recording access without exposing recording URLs/tokens in Blade or audit logs.
- Recording playback currently redirects to the stored provider recording URL only after CRM authorization; no audio binaries are stored in CRM and no unverified JustCall recording API endpoint was invented.
- T25 added lightweight authenticated polling for incoming JustCall screen-pop state using existing normalized inbound events and `call_logs`; popups are isolated to the mapped CRM agent, expire on completed/missed calls, and can be dismissed.
- Screen-pop matching reuses conservative phone matching with Contact > Customer > Lead priority; ambiguous callers show a safe possible-match state, unknown callers show phone/search context, and restricted record details are not exposed.
- T26 added idempotent missed-call automation on confirmed normalized inbound missed events using existing `call_logs`, conservative phone matching, Activities, Notifications, and Audit logs.
- Missed-call follow-ups are high-priority Activities due one hour after processing; unknown, ambiguous, restricted, unmapped, and later answered/completed calls are preserved with safe automation status instead of guessed assignment.
- T28 added RBAC-protected real-data reports for lead funnel, source performance, lead aging, opportunity pipeline, won/lost opportunities, agent performance, activities/follow-ups, and call activity with date, owner, team, status/stage, source, direction, and call-status filters. Reports reuse existing visibility scopes and dashboard hierarchy/team filtering patterns.
- T29 added permission-controlled CSV imports for leads, customers, and contacts with row-level summaries, master-value and relationship validation, duplicate prevention, permitted owner assignment, and audited imports. It added filtered CSV exports for leads, customers, contacts, opportunities, activities, and call logs using existing RBAC/hierarchy/team visibility scopes, explicit safe field allowlists, formula-injection sanitization, provider-secret exclusion, and audited exports. XLSX was not added because no suitable Laravel spreadsheet package exists in the current application.
- T30 added a simple RBAC-protected workflow-rule foundation with structured JSON conditions/actions, priority and active status, durable execution history, idempotent event keys, safe failure recording, recursion protection, and admin CRUD/status history. Supported events include record creation/update, lead assignment/status changes, opportunity stage changes, and activity overdue scans. Actions reuse existing Activity and in-app Notification primitives plus narrowly permitted Lead/Opportunity updates and assignment.
- T31 hardened sensitive boundaries with login and webhook rate limits, production-secure session cookies, stricter reconciliation visibility, HTTPS-only recording redirects, broader webhook secret redaction, generic provider failure summaries, workflow assignment reuse of existing authorization/history services, and justified indexes for report/list/inbox query patterns. Focused regression coverage protects authentication, webhook, recording, import/export, workflow, dashboard, report, and monitoring security boundaries.
- T32 added a protected operational health page with safe database, storage, webhook backlog/failure, JustCall configuration, and failed workflow checks. It added a non-destructive timestamped PowerShell backup script for MySQL and `storage/app`, retention guidance, and `docs/OPERATIONS_RECOVERY.md` covering environment secrets, database/file restore, Laravel cache rebuild, migration verification, smoke checks, and rollback cautions. No automatic destructive restore or deployment automation was added.
- T33 added compact end-to-end UAT coverage in `tests/Feature/UATCriticalFlowsTest.php` for authentication/RBAC, lead lifecycle and conversion, opportunity outcomes, agent workflow, mocked JustCall processing and reconciliation, import/export, workflow idempotency, and record isolation. `docs/UAT.md` documents scenarios, prerequisites, test roles, expected results, and manual production checks; `composer test:uat` runs the focused UAT suite.
- T34 added production deployment documentation in `docs/DEPLOYMENT.md`, a production-safe `.env.example`, a PHP 8.1 GitHub Actions test workflow at `.github/workflows/tests.yml`, and the read-only `ops:preflight` Artisan command with a Composer shortcut. CI installs dependencies, prepares isolated SQLite testing, generates a test key, migrates, and runs PHPUnit; no production deployment or destructive operation is automated.
- T35 created `docs/PHASE2_BACKLOG.md` as a categorized, prioritized roadmap only. No Phase 2 production implementation was started.
- UI polish completed: the shared Blade CRM shell, dashboard, JustCall settings page, cards, forms, tables, badges, spacing, and responsive behavior now use a consistent professional SaaS visual system in `public/assets/css/crm.css`. No business logic or backend behavior changed.
