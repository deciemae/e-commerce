# Bloom & Basket Design Adoption

**Status:** APPROVED foundation and COMPLETED storefront pilot; visual rollout remains IN PROGRESS.

## Purpose

This document translates the generated Nike-inspired `DESIGN.md` into an implementation plan
for Bloom & Basket. The generated reference remains intact. This adoption layer prevents brand,
font, component, and architecture assumptions from being copied into the application literally.

## Controlling decisions

- The product identity is Bloom & Basket.
- Nike names, logos, campaign copy, proprietary photography, and proprietary fonts are not
  project assets and must not be introduced.
- Plus Jakarta Sans is the display family for headings, product names, prices, dashboard
  metrics, and major labels.
- Inter is the interface family for body copy, navigation, forms, tables, and buttons.
- The approved visual direction is photography-first retail: neutral chrome, soft-gray product
  stages, flat product cards, restrained borders, pill controls, and limited semantic color.
- The generated reference guides visual decisions; it does not change PHP behavior, database
  rules, authentication, authorization, inventory, pricing, or checkout logic.
- Existing screens are migrated incrementally. A generated reference is not permission for a
  repository-wide rewrite.

## Token source

`assets/design-system.css` is the implementation authority for shared design tokens.

The first approved token set includes:

- Core colors: ink `#111111`, canvas `#ffffff`, surface `#f5f5f5`.
- Text colors: primary ink, soft charcoal, and muted gray.
- Borders: neutral hairline and soft hairline.
- Semantic colors: sale, danger, success, and information.
- Spacing: an 8px-based rhythm with compact 4px exceptions.
- Shapes: flat containers, moderate field radii, and fully pill-shaped actions.
- Motion: a short standard transition and a reduced-motion override.

Do not add a raw color, font stack, radius, or spacing value to a shared component when an
existing token expresses the same role. Add new tokens only when the current vocabulary cannot
represent an approved requirement.

## Stylesheet ownership

Stylesheets must load in this order:

1. Bootstrap 5.3.3
2. `assets/design-system.css`
3. `assets/style.css`
4. `assets/admin.css`, when the route belongs to the administrator experience
5. `assets/customer.css`, when the route belongs to the storefront or customer account
6. `assets/cart.css`, only for catalog, cart, or checkout components that require it
7. A page-local style block only while it is awaiting migration

Responsibilities:

- `assets/design-system.css`: shared tokens, typography roles, focus treatment, and motion
  preferences.
- `assets/style.css`: administrator shell and shared structural components.
- `assets/admin.css`: Phase 5 administrator shell, dashboard, tables, forms, status treatments,
  responsive navigation, and account/settings presentation.
- `assets/customer.css`: storefront, customer navigation, account, authentication, and
  customer-page layouts.
- `assets/cart.css`: product catalog, color swatches, cart controls, cart summary, and cart
  notifications.

Do not duplicate token declarations or global typography rules in page stylesheets.

## Component adoption order

### Phase 1 - Foundation

- `COMPLETED`: Centralize tokens and typography.
- `COMPLETED`: Repair the mixed-encoding stylesheet suffix and invalid authentication-layout
  declarations.
- `COMPLETED`: Establish stylesheet ownership and load order across rendered PHP entry points.
- `COMPLETED`: Preserve current PHP behavior and desktop page structure.
- `DEFERRED`: Consolidate overlapping catalog and color-picker selectors during the storefront
  pilot, when the affected components can be visually reviewed together.

### Phase 2 - Storefront pilot

- `COMPLETED`: Customer navigation, search, accessible mobile menu, and fixed-header spacing.
- `COMPLETED`: The supplied Bloom & Basket logo is paired with the brand name in the shared
  customer header without altering the original image asset.
- `COMPLETED`: Full-bleed photography-first homepage hero, contained benefits strip,
  category rail, and section rhythm.
- `COMPLETED`: Flat square product-card image stages with contained, consistently scaled
  full-bleed imagery and image-first product navigation, plus clear metadata, price, swatches, and a
  single purchase action; price and stock share one compact metadata row. Homepage featured cards
  now use the same full-bleed square image and price-stock arrangement as the catalog.
- `COMPLETED`: Compact catalog heading and search toolbar with reduced display typography and
  tighter filter-to-grid spacing.
- `COMPLETED`: Storefront category filters and 3-column desktop, 2-column tablet, and
  1-column narrow-mobile catalog grid.
- `COMPLETED`: Photography-led About page with an editorial story layout, existing quick-fact
  rail, mission and product-offer panels, and accessible contact links.
- `COMPLETED`: A shared dark customer footer uses the supplied Bloom & Basket logo, real
  storefront routes, and the support details already published on the About page without
  placeholder social accounts.
- `COMPLETED`: Exact 390px browser measurement confirmed that the document, navigation,
  hero, catalog search, and first product card remain within the viewport.

### Phase 3 - Product journey

- `COMPLETED`: Responsive product details with a photography-first image stage, clear product
  hierarchy, accessible color and rating controls, stock-aware purchasing controls, and flat
  review panels.
- `COMPLETED`: Responsive cart controls with flat product rows, accessible selection and
  quantity controls, an empty state, and a focused order summary that keeps checkout as the
  single primary action.
- `COMPLETED`: Responsive checkout form and order confirmation with server-authoritative
  totals, inventory checks, address ownership enforcement, transactional order writes, CSRF
  protection, and truthful pending-payment language.

### Phase 4 - Customer account

- `COMPLETED`: Responsive login and registration with a shared photography-first layout,
  accessible forms, CSRF protection, session-ID regeneration, and generic authentication
  failure messaging.
- `COMPLETED`: Registration creates the account without authenticating it, then redirects to
  Sign in with an accessible, dismissible success toast and email-only prefill; credentials
  are verified before the customer session and guest cart are attached to the account.
- `COMPLETED`: The shared customer success toast is reused for profile and address updates;
  validation and security errors remain persistent inline alerts.
- `COMPLETED`: Responsive dashboard, profile, address book, order history, and order tracking
  with a shared account navigation and flat operational layout.
- `COMPLETED`: Profile and address writes use CSRF protection, server-side validation,
  ownership-scoped queries, generic failure messaging, and transactional address changes.
- `COMPLETED`: Desktop and narrow-mobile browser checks cover the five protected account
  routes, including typography, touch-target sizing, navigation collapse, and horizontal
  overflow.

### Phase 5 - Administrator experience

- `COMPLETED`: Shared administrator sidebar, topbar, account menu, logo treatment, compact
  page headings, and responsive drawer navigation use the approved neutral design tokens.
- `COMPLETED`: Desktop sidebar collapse preserves a labeled-by-tooltip icon rail instead of
  removing navigation, while mobile keeps the off-canvas drawer; page titles and supporting
  copy sit in the content area rather than the utility header.
- `COMPLETED`: Dashboard metrics, charts, inventory notices, and recent-activity panels use
  dense flat cards without campaign typography or decorative elevation.
- `COMPLETED`: Product and category management use compact tables, square product thumbnails,
  pill actions, consistent forms, and restrained modals while preserving existing operations.
- `COMPLETED`: Order, customer, activity-log, account, and security-settings screens share
  compact tables, semantic status treatments, operational form controls, and mobile overflow
  handling.
- `COMPLETED`: Administrator login uses the supplied Bloom & Basket logo and the shared
  typography, form, focus, and action styles.
- `COMPLETED`: Administrator write actions use shared CSRF tokens, POST-only logout,
  redirect-after-POST feedback, accessible toast notifications, validated image uploads,
  positive identifier checks, and explicit order-status allowlists.
- `COMPLETED`: Legacy administrator page-level `<style>` blocks were consolidated into
  `assets/admin.css`; tables now include accessible captions and status controls have
  route-specific labels.

### Phase 6 - Regression, security, and release readiness

- `COMPLETED`: Public and protected-route HTTP regression checks, recursive PHP lint, shared
  session hardening, customer cart/review/logout CSRF protection, setup-script isolation, and
  generic database-failure handling.
- `COMPLETED`: Read-only database integrity checks cover required tables, foreign-key orphans,
  negative inventory, order-status allowlists, and order-total reconciliation.
- `COMPLETED`: Backup, restore-verification, proposed schema-versioning, security-audit, and
  deployment-readiness procedures are documented.
- `BLOCKED`: Two historical orders lack detail rows, tracked database exports contain sensitive
  data, and authenticated real-browser validation requires an approved disposable environment.
- `COMPLETED`: Product-management forms now honor the shared 12-column grid for three- and
  four-column fields; the product modal uses a wider scroll-safe layout, the category editor
  uses a dedicated responsive grid with compact actions, Bootstrap gutters no longer pull
  controls outside card padding, dashboard metrics use centered icons and tighter card gaps,
  and dense product metadata is consolidated into a seven-column operational table.

## Validation gates

Every visual phase requires:

- PHP syntax checks and `git diff --check`.
- Desktop, tablet, and mobile browser review.
- Keyboard navigation and visible-focus review.
- Contrast, overflow, empty-state, error-state, and reduced-motion review.
- Confirmation that Plus Jakarta Sans and Inter resolve correctly with graceful fallbacks.
- Focused functional smoke tests for every route whose markup changes.

Automated checks do not replace browser evidence. If browser verification is unavailable,
report the phase as not visually verified.

## Known baseline issues

- `RESOLVED`: The mobile homepage navigation and hero no longer overflow a 390px viewport.
  The navigation now uses an accessible toggle without increasing the fixed header height.
- `RESOLVED`: Administrator page-level `<style>` blocks were migrated into the shared
  administrator stylesheet after the Phase 5 redesign.
- `PROVISIONAL`: Catalog, cart, and checkout presentation is scoped in `assets/cart.css`, while
  the redesigned product-details route is scoped to `.product-detail-page` in
  `assets/customer.css`. Older unscoped declarations remain and can be consolidated only in a
  separately reviewed cleanup.
- `PROPOSED`: The flat checkout and payment-selection pattern is the initial Bloom & Basket
  interpretation because `DESIGN.md` does not define checkout-specific form styling.
- `PROPOSED`: The homepage customer-proof row uses the live registered-customer count and
  privacy-preserving initials from recent accounts because `DESIGN.md` does not define a
  social-proof pattern and the current customer schema has no profile-photo field.
- `PROPOSED`: The split editorial About-page hero and numbered offer list extend the existing
  flat, photography-first storefront vocabulary because `DESIGN.md` does not define an About
  page pattern.
- `PROPOSED`: The compact administrator workspace is the Bloom & Basket interpretation of the
  neutral retail system because `DESIGN.md` does not define operational dashboards, dense data
  tables, or administrator security forms.
- The generated `DESIGN.md` contains encoding artifacts and synthesized mobile guidance. Treat
  those passages as reference material, not verified implementation evidence.
