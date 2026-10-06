# Deployment readiness

Status: `PROVISIONAL`; do not deploy until the Phase 6 blockers are resolved.

## Runtime requirements

- PHP 8.3 with `mysqli`, `mbstring`, `fileinfo`, `openssl`, and `session` extensions.
- MySQL 8.x with an application-specific user that has only the required CRUD permissions.
- Apache or another PHP-capable web server with HTTPS.
- Persistent, non-executable storage for `uploads/` and `assets/uploads/admins/`.
- Outbound access to the approved Bootstrap and Google Fonts CDNs, or locally hosted approved
  replacements.

## Required environment variables

Configure these in the server environment, not in Git:

```text
SHOPPINGCART_DB_HOST
SHOPPINGCART_DB_PORT
SHOPPINGCART_DB_USER
SHOPPINGCART_DB_PASS
SHOPPINGCART_DB_NAME
SHOPPINGCART_SECURE_COOKIES=1
```

Do not deploy with the local `root`/blank-password fallback. Disable `display_errors`, enable
server-side error logging, protect logs and backups from HTTP access, and set conservative
upload/body size limits.

## Web-server controls

- Serve the application only over HTTPS and redirect HTTP to HTTPS.
- Set HSTS at the HTTPS reverse proxy or web server after HTTPS is verified.
- Deny HTTP access to `.git/`, `.env*`, `backups/`, SQL files, logs, and internal documentation.
- Keep `setup_db.php` and `setup_security.php` out of the deployed document root where possible;
  their application guard is defense in depth, not the primary deployment boundary.
- Deny script execution inside upload directories and allow only the expected image types.
- Ensure the application database user cannot create, alter, or drop tables during normal web
  requests.

## Release procedure

1. Resolve every `BLOCKED` finding in `docs/security-audit.md`.
2. Create and checksum a full logical backup outside the web root.
3. Verify restoration in a disposable database with explicit operator approval.
4. Deploy application files without local dumps, browser profiles, logs, or development caches.
5. Configure production environment variables and writable upload directories.
6. Run recursive PHP lint and `php tests/release_readiness_test.php` against the intended
   database using read-only verification credentials where possible.
7. Run `php tests/http_smoke_test.php` with `SHOPPINGCART_BASE_URL` set to the deployed URL.
8. Perform real-browser desktop, tablet, and mobile checks for storefront, customer, checkout,
   and administrator routes.
9. Verify HTTPS cookies, redirects, login/logout, CSRF failures, ownership failures, upload
   rejection, transactional checkout rollback, and server logs.
10. Record the deployed commit, database schema version, backup checksum, operator, and date.

## Rollback

Application rollback and database rollback are separate decisions. Revert application files to
the recorded release only after confirming schema compatibility. Database restoration is a
destructive operation and requires explicit authorization, the verified backup checksum, and a
maintenance window.
