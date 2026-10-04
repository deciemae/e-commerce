# AGENTS.md

## Purpose

This file defines how coding agents must work in the Bloom & Basket e-commerce repository.
It applies to the entire repository unless a more specific `AGENTS.md` is added in a
subdirectory.

The application is a framework-free PHP and MySQL storefront with separate customer and
administrator experiences. Keep changes small, reviewable, reversible, and compatible with
the existing architecture.

## Required reading order

Before changing this repository:

1. Read this file completely.
2. Read `DESIGN.md` completely before any UI, CSS, layout, typography, icon, image, motion,
   responsive, or accessibility work.
3. Read `README.md` and any task-relevant documentation that is later added.
4. Inspect `git status` and the relevant implementation before proposing changes.
5. Trace the full request path, database access, shared includes, and client behavior before
   changing functional behavior.

Do not overwrite or reformat unrelated work. Existing uncommitted changes belong to the user
unless proven otherwise.

## Authority and status

Use these authorities in order:

1. The user's current explicit request.
2. This `AGENTS.md` for repository engineering and delivery rules.
3. Approved project documentation and recorded decisions, when those files exist.
4. `DESIGN.md` for visual direction, subject to the compatibility profile below.
5. Existing code for current behavior, but not automatically for security correctness.

Use these status labels when documenting work: `APPROVED`, `PROPOSED`, `PROVISIONAL`,
`BLOCKED`, `IN PROGRESS`, `COMPLETED`, `DEFERRED`, and `OUT OF SCOPE`.

Current documentation status:

- `DESIGN.md`: generated Nike-inspired visual reference; present.
- `README.md`: minimal project identifier only.
- Documentation index, progress tracker, decision register, roadmap, sprint board, and
  changelog: absent.
- Automated test suite: absent.
- Database migration framework: absent.

Do not claim that an absent tracker or test suite was updated. If a task requires a durable
business or architecture decision, mark the missing documentation baseline as `PROPOSED` or
ask the user before creating a new documentation system.

## Actual repository architecture

This is not Laravel, React, Vite, Tailwind, or a Node application. Do not introduce those
frameworks unless the user explicitly approves an architectural migration.

- Root `*.php`: page controllers and rendered views.
- `index.php`, `shop.php`, `browse.php`, and `product_details.php`: public storefront.
- `cart.php`, `cart_actions.php`, and `checkout.php`: cart and checkout flows.
- `customer_*.php`: customer authentication, account, address, order, and profile flows.
- `admin_*.php`, `products.php`, `categories.php`, and `customers.php`: administrator flows.
- `config/db.php`: MySQLi connection configuration.
- `includes/`: shared navigation, authorization-adjacent helpers, validation, customer state,
  and product-color helpers.
- `assets/style.css`: shared and administrator styling.
- `assets/customer.css`: storefront and customer styling.
- `assets/cart.css`: cart/catalog-specific styling.
- `uploads/` and `assets/uploads/`: runtime/user-uploaded files; treat them as user data.
- `ShoppingCart (1).sql`: current database dump/reference, not a migration mechanism.
- `setup_db.php` and `setup_security.php`: ad hoc setup scripts, not a versioned migration
  system.
- `backups/`: historical database backups; do not edit or delete them.

Reuse shared includes and styles instead of duplicating markup, database helpers, navigation,
or component CSS across pages. Keep SQL and server-side decisions in PHP; JavaScript must not
become the authority for prices, stock, identity, authorization, or order totals.

## Business and database rules

Do not invent product data, prices, discounts, stock, order states, payment results, customer
details, administrator policies, or business rules.

- The current database name is `ShoppingCart` and the connection uses MySQLi with `utf8mb4`.
- Preserve the existing table relationships among administrators, users, categories,
  products, colors, carts, cart items, orders, order details, addresses, and reviews.
- Use prepared statements for every value influenced by a request, session, or user input.
- Validate identifiers as positive integers and validate enum-like values against explicit
  server-side allowlists.
- Calculate price, inventory, cart totals, and order totals from server-authoritative database
  values. Never trust hidden inputs or browser-calculated totals.
- Keep checkout inventory and order writes transactional. Roll back the complete operation on
  failure.
- Enforce ownership in SQL for customer addresses, carts, orders, and other user-owned data.
- Do not run destructive schema commands or import a dump into a database containing valuable
  data without explicit approval and a verified backup.
- Schema changes are `BLOCKED` until the user approves both the change and a versioning
  approach, because this repository has no established migration system.
- Do not perform `CREATE TABLE` or `ALTER TABLE` during ordinary page requests in new code.

Keep implementation, SQL reference data, setup instructions, and affected documentation in
sync when an approved schema change is made.

## Authentication and security

Protected operations must be enforced on the server.

- Administrator pages must call `requireAdminLogin()` before protected reads or writes.
- Customer-only pages must call `requireCustomerLogin()` or perform the equivalent redirect
  before protected reads or writes.
- Preserve the separation between administrator and customer session identities.
- Regenerate the session identifier after successful authentication when modifying login code.
- Hash passwords with `password_hash()` and verify them with `password_verify()`.
- Never log, display, or commit passwords, session identifiers, database credentials, payment
  details, or other secrets.
- Escape untrusted HTML output with `htmlspecialchars()` using an appropriate encoding and
  quote mode.
- Every new or modified state-changing request must use POST and CSRF protection. The current
  repository has no shared CSRF helper; adding a compatible shared helper is a prerequisite
  when such a flow is changed.
- Redirect after successful POST operations where practical to prevent duplicate submissions.
- Validate uploaded files server-side by content type, extension allowlist, size, and generated
  filename. Never trust the original filename or browser MIME type.
- Uploaded executable content must never be permitted. Keep upload destinations scoped to the
  intended directory.
- Do not expose raw database errors, stack traces, filesystem paths, or sensitive account
  existence details to end users.
- Setup scripts must not be treated as safe public production endpoints.

Existing code may not satisfy every rule above. Record a discovered weakness as a current risk;
do not describe it as fixed unless the task implements and verifies the fix.

## UI and design compatibility profile

`DESIGN.md` is the visual authority for future UI work, but it is an inspiration document and
must be adapted to this product rather than copied literally.

The following project decisions override conflicting generated Nike references:

- Product identity is **Bloom & Basket**, not Nike.
- Never copy or introduce the Nike name, swoosh, campaign copy, proprietary photographs,
  proprietary fonts, or other trademarked/copyrighted brand assets.
- Use **Plus Jakarta Sans** for headings, product names, prices, dashboard metrics, and major
  labels.
- Use **Inter** for body text, navigation, forms, tables, and buttons.
- Use the generated document's neutral palette, flat product presentation, restrained depth,
  spacing rhythm, pill controls, and photography-first principles only where they fit the
  existing Bloom & Basket experience.
- Existing accepted screens are not authorization for a repository-wide redesign. Apply the
  system incrementally within the requested scope.
- Do not add proprietary font files. Keep robust system-font fallbacks.

UI implementation rules:

- Continue using the existing Bootstrap CDN and shared CSS architecture unless a migration is
  explicitly approved.
- Reuse shared navigation and existing component classes before creating new variants.
- Prefer semantic HTML and shared CSS over page-specific inline styles.
- Preserve responsive behavior across desktop, tablet, and mobile.
- Provide visible keyboard focus, logical tab order, labels for controls, useful alternative
  text, sufficient contrast, and touch targets of approximately 44 by 44 pixels.
- Do not use color alone to communicate status, error, selection, or availability.
- Respect `prefers-reduced-motion` for nonessential motion.
- Keep product imagery responsive and avoid layout shifts by preserving dimensions or aspect
  ratios.
- If `DESIGN.md` does not define a required pattern, label the new pattern `PROPOSED` rather
  than presenting it as established.

## Coding conventions

- Target the installed PHP 8.3 runtime while avoiding unnecessary version-specific complexity.
- Use `declare(strict_types=1)` only as part of an explicitly approved, consistently scoped
  adoption; do not add it to one interconnected file without checking call sites.
- Prefer small functions with one responsibility and early returns over deeply nested logic.
- Use `require_once` for required shared PHP modules and `__DIR__` for reliable filesystem
  paths.
- Follow the current naming style unless a scoped refactor explicitly standardizes it.
- Do not add Composer or npm dependencies for behavior that can be implemented safely with the
  existing stack.
- Keep JavaScript progressively enhanced where possible; core commerce and account operations
  must remain server validated.
- Do not edit generated or binary assets mechanically without inspecting their current use.
- Preserve line endings and file encodings. Some existing files contain encoding artifacts;
  do not perform broad encoding rewrites as part of an unrelated task.

## Required workflow

For every repository task:

1. Inspect `git status`, relevant files, and current behavior.
2. Identify the controlling business, security, database, and design authorities.
3. State assumptions and treat unresolved consequential decisions as blockers.
4. Implement the smallest coherent vertical change.
5. Add or update automated tests when functional behavior changes. If no suitable test harness
   exists, report that limitation and perform focused validation without claiming equivalent
   coverage.
6. Run relevant validation.
7. Review the final diff for unrelated changes, secrets, generated artifacts, and accidental
   formatting churn.
8. Synchronize affected documentation that actually exists; propose missing documentation
   rather than silently inventing a full governance structure.

Never use destructive Git commands, discard user work, force-push, delete uploads/backups, or
replace `DESIGN.md` without explicit authorization.

## Validation baseline

Run validation proportionate to the change. At minimum:

```powershell
Get-ChildItem -Recurse -File -Filter '*.php' | ForEach-Object { php -l $_.FullName }
git diff --check
```

For runtime work:

- Verify MySQL is running and the intended database is selected.
- Prefer the user's normal Laragon/Apache environment for session-dependent browser checks.
- A temporary local server can be started from the repository root with
  `php -S 127.0.0.1:8000`, but its session directory may differ from Laragon's Apache runtime.
- Smoke-test the directly affected public, customer, and administrator routes.
- Exercise success, validation-error, authorization-error, and ownership-error paths for
  changed protected operations.

For UI work:

- Check desktop, tablet, and mobile layouts in a real browser.
- Check keyboard navigation, focus visibility, form labels, contrast, overflow, and empty/error
  states.
- Confirm the computed font families and verify graceful fallback when Google Fonts is
  unavailable.

For database work:

- Use a disposable database or verified backup for schema/import testing.
- Verify constraints, rollback behavior, and compatibility with existing data.

Report only validations that actually passed. A PHP syntax check does not prove browser,
database, authentication, checkout, or accessibility behavior.

## Completion report

End every completed task with:

- **Status:** one of the defined status labels.
- **Files changed:** every file intentionally changed.
- **Tests performed:** exact commands/checks and outcomes.
- **Remaining blockers:** unresolved decisions or unavailable evidence.
- **Risks:** security, data, compatibility, accessibility, or operational concerns.
- **Recommended next action:** the safest concrete follow-up.

