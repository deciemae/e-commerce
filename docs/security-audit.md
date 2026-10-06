# Phase 6 security audit

Status: `IN PROGRESS` pending the blockers listed below.

## Completed remediations

- `COMPLETED`: Cart add, update, remove, and clear actions are POST-only and require the shared
  customer CSRF token.
- `COMPLETED`: Cart quantities are checked against current server-side stock and submitted
  color values must belong to the product's database-backed color options.
- `COMPLETED`: Customer logout is POST-only, CSRF-protected, clears customer state, and rotates
  the session identifier.
- `COMPLETED`: Product-review writes require CSRF validation, enforce the database/UI length
  limit, and no longer expose raw database errors.
- `COMPLETED`: Runtime customer requests no longer execute `CREATE TABLE` or `ALTER TABLE`.
- `COMPLETED`: `setup_db.php` and `setup_security.php` return 404 over HTTP and require explicit
  `ALLOW_DB_SETUP=1` approval from the command line.
- `COMPLETED`: Database credentials can be supplied through environment variables and
  connection failures return a generic 503 response while detailed errors go to the server log.
- `COMPLETED`: A shared session bootstrap enables strict-mode, cookie-only sessions, HttpOnly,
  SameSite=Lax, HTTPS-aware Secure cookies, clickjacking protection, MIME sniffing protection,
  a restricted permissions policy, and referrer protection.
- `COMPLETED`: Protected customer and administrator responses use private no-store caching.
- `COMPLETED`: The unused empty `assets/admin_profile.php` legacy endpoint was removed after
  confirming that no application route referenced it.

## Open findings

| Status | Severity | Finding | Required decision |
| --- | --- | --- | --- |
| `BLOCKED` | High | `ShoppingCart (1).sql` and the tracked historical backups contain customer names, emails, phone numbers, addresses, session identifiers, password hashes, and order history. Removing the current files alone would not erase Git history. | Confirm whether the repository is private, rotate affected credentials where appropriate, produce an approved sanitized/schema-only replacement, and approve a coordinated history rewrite if this repository has ever been shared. |
| `BLOCKED` | High | Orders `#4` and `#7` have totals but no order-detail rows, so their totals cannot be reconciled. | Supply authoritative line-item evidence or approve marking these records as legacy/unverifiable. Do not fabricate missing products, quantities, or prices. |
| `DEFERRED` | Medium | Product reviews have no customer foreign key, ownership, moderation state, or rate-limit data. | Approve a versioned schema change and review policy before adding reviewer identity or moderation behavior. |
| `DEFERRED` | Medium | A strict Content Security Policy would currently break CDN assets and page-level inline scripts. | Approve nonce/hash adoption and script extraction as a separate frontend-security change. |
| `DEFERRED` | Medium | Administrator MFA remains unavailable because no mail provider, verification-code lifecycle, or recovery flow exists. | Approve provider and recovery requirements before implementation. |

No production deployment should include public setup routes, committed browser profiles, live
database exports, writable backups under the web root, or development error display.
