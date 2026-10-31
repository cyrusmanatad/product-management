# Sprint 1 customer management and statistics

Validated 2026-10-31 against the existing working tree based on `06bb005`.
PM-002 decisions are confirmed by the user; PM-003/PM-004 implementation is in review.
No commit, PR, deployment, or production migration was performed.

## Agreed behavior

- Customer Active/Blocked is a reversible vendor relationship status. It does not
  change global identity, staff membership, other stores, or existing orders.
- Changes require active vendor membership and both `view customers` and
  `edit customers`. The existing Owner provisioning grants them; other staff need
  explicit grants. Platform administration alone does not grant access.
- New storefront and staff orders for a blocked buyer are refused at that vendor.
  Status changes and order creation lock the same buyer first. Existing idempotent
  retries can return the already-created order, preserving history and retry safety.
- PM-006 customer onboarding will use recipient-accepted, one-use, seven-day
  invitations, separate from staff invitations. Staff cannot set global credentials
  or identity fields. This flow is specified, not implemented in this sprint.
- Units Sold is all-time quantity on paid, non-cancelled orders, including archived
  history; it is vendor-scoped. Inventory and customer labels state their actual
  calculation windows. Repeat Customers uses `retention` as a percentage.
- Initial/loading/error statistics show an em dash, successful empty results show
  zero, and failed statistics requests have retry feedback. Store changes clear
  previous metrics. Header totals, customer IDs, and phone/unavailable values no
  longer contain example data.

The API and tenancy documents record the contract and rationale. Existing Laravel
and Vue conventions were retained. The new customer status migration adds a
non-null boolean with a false default, preserving existing active relationships.
The legacy cutover rehearsal now limits its initial migration pass to pre-cutover
files so future migrations do not run before their prerequisite tables exist.

## Validation

| Check | Result |
| --- | --- |
| Full PostgreSQL suite | 65 passed, 665 assertions, no skips, 45.70s; [log](postgresql.txt) |
| Full SQLite suite | 64 passed, 653 assertions, one expected PostgreSQL concurrency skip, 16.91s; [log](sqlite.txt) |
| Focused customer status API tests | 3 passed, 37 assertions |
| Focused statistics API tests | Passed as part of both complete suites |
| Vue production build / TypeScript | `npm run build` passed |
| Vue unit tests | 16 passed across 6 files |
| Full Chromium browser suite | 12 passed, including mobile/tablet/desktop customer flows and existing homepage, product uploads, carts, and vendor switching |
| PHP formatting | `vendor/bin/pint --dirty --format agent` passed; full formatting also run for newly created files |
| Frontend lint | ESLint on changed files passed; `npx oxlint .` passed with no warnings/errors |
| Whitespace | `git diff --check` passed |

Tests used the isolated PHP 8.5 / PostgreSQL 16 Compose setup documented in
TENANCY.md, with test-owned storage and dependencies. The PostgreSQL suite includes
real concurrent checkout/idempotency checks. Browser tests use controlled API
responses; real API tests verify authorization, isolation, persistence, rejected
checkout, preserved identity/membership/history, and unblock behavior.

Commands from the repository root unless noted:

```bash
docker compose -f docker-compose.test.yml run --rm backend composer test -- --colors=never --fail-on-skipped
docker compose -f docker-compose.test.yml run --rm -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: backend composer test -- --colors=never
# In backend/
vendor/bin/pint --dirty --format agent
vendor/bin/pint --format agent
# In bentador/
npm run build
npm run test:unit -- --run
npx playwright test
npx playwright test customer-management.spec.ts
npx oxlint .
```

Chromium was initially missing; `npx playwright install chromium` installed the
project's required browser. Local server/browser processes needed sandbox escalation.
All browser tests then passed. Responsive screenshots are saved alongside this file:
`customers-mobile.png` (390px), `customers-tablet.png` (768px), and
`customers-desktop.png` (1440px). The focused run also checks API failure/retry,
block/unblock confirmation, persisted state after reload, permissions, and store changes.

The running development backend was checked with `docker compose exec -T backend
php artisan env`, which reported `local`. Only the new migration was then applied:

```bash
docker compose exec -T backend php artisan migrate --path=database/migrations/2026_10_31_133550_add_customer_relationship_status.php --no-interaction
```

It completed successfully in 23.72ms. No production database was accessed.
Apply `2026_10_31_133550_add_customer_relationship_status.php` before releasing the
API and frontend together. Shared/global account activation is unchanged. Remote CI,
final reviewer acceptance, and commit/PR remain pending; Sprint 1 is not closed.
