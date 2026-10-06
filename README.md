# Bloom & Basket E-commerce

A framework-free PHP 8.3 and MySQL storefront with customer and administrator experiences.

## Project documentation

- [`AGENTS.md`](AGENTS.md) - repository architecture, security, workflow, and validation rules.
- [`DESIGN.md`](DESIGN.md) - generated Nike-inspired visual reference.
- [`docs/design-adoption.md`](docs/design-adoption.md) - approved Bloom & Basket adaptation,
  stylesheet ownership, rollout phases, and known visual issues.
- [`docs/admin-security.md`](docs/admin-security.md) - administrator request-security,
  upload-validation, feedback, and verification baseline.
- [`docs/security-audit.md`](docs/security-audit.md) - Phase 6 endpoint and deployment security
  findings, resolutions, and remaining risks.
- [`docs/database-operations.md`](docs/database-operations.md) - safe database backup, restore,
  and proposed schema-versioning workflow.
- [`docs/deployment.md`](docs/deployment.md) - PHP/MySQL production configuration and release
  verification checklist.
- [`docs/phase-6-release-readiness.md`](docs/phase-6-release-readiness.md) - regression scope,
  evidence, blockers, and release decision.

Read `AGENTS.md` before repository work and `DESIGN.md` plus the adoption document before UI
changes. The generated reference is inspiration only; Bloom & Basket branding and the approved
compatibility rules remain authoritative.

## Local runtime

The application reads database configuration from these optional environment variables while
retaining the existing Laragon defaults for local development:

- `SHOPPINGCART_DB_HOST`
- `SHOPPINGCART_DB_PORT`
- `SHOPPINGCART_DB_USER`
- `SHOPPINGCART_DB_PASS`
- `SHOPPINGCART_DB_NAME`
- `SHOPPINGCART_SECURE_COOKIES=1` when HTTPS termination is not visible to PHP

Setup scripts are command-line only and require `ALLOW_DB_SETUP=1`. Do not enable them on a
production web request.
