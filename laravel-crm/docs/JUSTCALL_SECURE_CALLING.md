# Secure JustCall calling review

The Sales Agent HTTP 422 is intentional: JustCallClickToCallService rejects restricted users before returning a destination to the browser. It is not an authentication or phone-validation failure.

Admin and authorized Manager flow: controller permission/record visibility checks, enabled/account/mapping/phone validation, masked audit entry, then JSON containing the normalized destination and dialer URL (or a redirect for non-JSON submissions). The browser loads the CTI SDK and passes the destination to dialNumber(). This flow is preserved.

Sales Agent flow: the same authorization and validation checks, then a safe HTTP 422 with code `secure_calling_not_supported`, a fixed explanation, masked number, masked record name, record type/id, outbound direction and failed status. The custom panel renders this failure without loading the SDK. No call is placed and no provider call/session ID is fabricated. Existing successful-request audit logging is unchanged.

## Missing capability

The Laravel client implements connectivity/user lookup and browser dialer URLs, but no human-agent outbound POST. Its config has no outbound endpoint or calling contract. The legacy includes/justcall.php call methods explicitly fail; its Sales Dialer campaign-contact POST only adds a contact and does not initiate a mapped human-agent call. Webhook normalization, mapping resolution and call-log upserts receive calls; they do not originate them.

Reviewed official documentation on 2026-09-07:

- https://developer.justcall.io/docs/cti-dialer-sdk documents browser dialing and mentions a Make a Call API, but does not supply a server-side human-agent request contract on that page.
- https://developer.justcall.io/docs/justcall-dialer-setup-guide passes the destination through browser URLs.
- https://developer.justcall.io/reference/initiate_outbound_call_v21 documents POST /v2.1/voice-agents/calls for AI Voice Agents, not the mapped human user.

No supported human-agent call endpoint was verified in this project or the reviewed documentation. This is not a claim that JustCall cannot offer a private/account-specific capability. Obtain from JustCall the supported endpoint, authentication, mapped human-agent and destination fields, response/error schema, call correlation and idempotency behavior, and confirmation that the agent's browser/provider UI does not receive the destination. API credentials alone do not establish that capability. Do not substitute the AI endpoint or a browser URL.

Server-side success testing is not applicable until a real supported contract is available. Tests must not simulate success for an invented API.

## Changes and validation

- Service: explicit safe explanation and stable error code; privacy guard retained.
- Customer detail heading: mask phone-like text in names for restricted users.
- PHP regression coverage: Admin payload, all three restricted record types, response allowlist/phone absence, custom panel HTML, non-JSON safe redirect, inactive user; existing tests cover invalid phone, disabled integration, missing/inactive mapping and masked audit logging.
- JS regression coverage: submit/fetch failure renders masked values, restores the button and never loads the SDK; existing tests cover inbound state, duration, minimize and polling.

This targeted review does not establish that arbitrary free-text fields throughout the CRM can never contain a phone number. Do not grant Sales Agents `customers.view_full_phone`; this existing permission authorizes full-number access.

Validation result: `php artisan test` passed all 285 tests (44.13 seconds); `node tests/dialer-ui.cjs` passed both the existing state/render checks and the new restricted-submit checks. No real provider call was attempted. An initial `--no-ansi` test invocation was unsupported by this PHPUnit version; the required command was then run without that option.

## Deploy this patch

No environment, dependency, migration, seeding or frontend bundle change is required. Deploy the changed PHP service and Blade view using the existing release mechanism. Test files and this document can accompany the release.

The repository does not identify the production hostname, release directory or PHP process manager. From the existing production Laravel application directory (the directory containing artisan), run:

```sh
php artisan view:clear
php artisan view:cache
php artisan ops:preflight
```

If the production PHP process uses an OPcache configuration that does not detect changed files, reload it using that server's existing release procedure. No exact service name can be inferred here. Do not overwrite the production .env or regenerate APP_KEY. Verify Admin/Manager dialing and the masked Sales Agent failure after release. This patch does not enable Sales Agent outbound calls.
