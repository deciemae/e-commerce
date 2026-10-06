# Administrator security baseline

Status: `COMPLETED` for the scoped Phase 5 hardening pass.

## Request protection

- Administrator state changes require an authenticated administrator session.
- Modified administrator write routes require POST and a session-backed CSRF token.
- Logout is POST-only and rotates the session identifier after clearing administrator state.
- Successful writes use redirect-after-POST and the shared accessible administrator toast.
- Validation and database failures use generic customer-safe text rather than raw SQL errors.
- Product and category identifiers must be positive integers. Order status values must match
  the server-owned status allowlist.

The shared implementation lives in `includes/admin_security.php`; toast rendering lives in
`includes/admin_toast.php` and is consumed by the shared administrator topbar.

## Upload handling

Product and administrator profile images are limited to two megabytes. The server verifies
the uploaded-file state and detects the content MIME type with `finfo`; only the explicitly
allowed image formats are accepted. Stored filenames are generated from cryptographically
random bytes and never reuse the browser-supplied filename.

## Verification

Run the focused helper and static-regression check with:

```powershell
php tests/admin_security_test.php
```

Run the repository syntax and whitespace baseline with:

```powershell
Get-ChildItem -Recurse -File -Filter '*.php' | ForEach-Object { php -l $_.FullName }
git diff --check
```

## Deferred risk

`DEFERRED`: The settings screen shows MFA as unavailable because this repository does not
contain an email-code delivery and verification flow. It cannot be enabled until a provider,
code lifecycle, recovery policy, and automated tests are approved and implemented.
