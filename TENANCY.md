# Vendor tenancy and rollout

## Business model

A vendor is an independent business, not a user account. One global account can have different staff roles in multiple vendors and can also buy from any storefront. Vendors see only their own customer relationships and order history. The initial release supports PHP currency and cash/bank-transfer payment tracking. Payment recording does not transfer funds.

The public homepage at `/` shows platform-selected featured products and a searchable store directory; it does not choose a default store. Platform administrators curate selections at `/platform/featured-products`. Hidden/deleted products and suspended stores disappear from public listings automatically. Each featured product opens within its own storefront and keeps carts separate. Storefronts use `/stores/{slug}`; staff administration uses `/vendors/{slug}/products` and related pages. `/vendors` lists active memberships. Platform administrators use `/platform/vendors` to provision stores and generate owner invitation links. Invitations expire after seven days and are single-use. Existing accounts must authenticate as the invited recipient; invitations never change existing credentials. The issuer shares the link directly with the recipient.

## Access and data boundaries

`TenantContext` is request-scoped. Vendor middleware resolves URL ownership, verifies active staff membership when required, sets Spatie's permission team, and clears context in `finally`. Tenant models return no rows without context and reject writes without context. Database composite foreign keys protect business relationships and role assignments. Raw SQL queries must explicitly constrain vendor IDs.

Platform privileges use a separate, non-mass-assignable account flag; store Owner roles never bypass tenant authorization. Jobs accessing business data must use `WithinVendor($vendorId)` middleware. Cache keys and future storage paths must include vendor IDs. Store switches reset private Pinia stores; the API client rejects responses from an earlier context. Public carts are separate per storefront and hold actual variant IDs.

## Existing-data migration

The new migration is transactional on PostgreSQL and forward-only. Original migrations remain intact. It creates `benta-door`, assigns existing business records and staff permissions to it, retains creator/buyer IDs, stock, prices, order numbers, and historical currencies, and creates vendor customer relationships. Legacy `vendor_categories` retains the former user ID as `legacy_user_id`. Old Super Admin roles become vendor Owners. No account is automatically promoted to platform administrator.

Accounts using `Password@1234` are flagged for email password reset and cannot log in until reset. Configure a working mail transport before cutover. Historical non-PHP orders remain stored but are excluded from PHP revenue summaries; review product currencies before publishing them in the PHP storefront. Existing paid orders retain payment status; automatic settlement/refunds and partial-payment entry are outside this release.

## Maintenance-window cutover

1. Rehearse on a restored staging database. Compare row counts, `orders.total` sums, inventory quantities, attribution, role assignments, and sample customer histories. The automated legacy migration test also checks original column values.
2. Take a PostgreSQL custom-format backup with `pg_dump -Fc`; protect the backup and verify an actual restore on a separate database. Also back up application storage. The deployment workflow copies existing storage into the persistent volume before migration. Keep the previous application image/tag and environment configuration.
3. Stop ingress before deploying the new backend/frontend together. Do not expose the new API until the migration and verification finish. The deployment workflow stops NGINX during this interval.
4. Run `php artisan migrate --force`, `php artisan tenancy:verify`, and `php artisan permission:cache-reset`. Compare pre/post counts and totals. Do not run demo seeders against production.
5. Review the intended operator account and run `php artisan platform:grant operator@example.com`. Reset any flagged credentials through email. Granting platform access is audited and does not automatically grant store memberships.
6. Smoke-test two stores, separate roles, customer history, checkout, cancellation, manual payments, and reports; then restore ingress.

If migration or reconciliation fails, keep ingress stopped. Restore the pre-migration backup and previous application version together. Do not run `migrate:rollback` against this forward-only migration. New writes after cutover require a separate recovery decision; never restore a backup over unreviewed live writes.

Monitor vendor-access denials, checkout failures, failed jobs, negative inventory, manual payment reversals, and audit events. `tenancy:verify` exits unsuccessfully for missing ownership, negative stock, or broken vendor relationships.

## Verification

```bash
# Isolated PHP 8.5 + PostgreSQL 16; never uses production Compose.
docker compose -f docker-compose.test.yml build backend
docker compose -f docker-compose.test.yml run --rm backend composer install
docker compose -f docker-compose.test.yml run --rm backend php artisan test
docker compose -f docker-compose.test.yml run --rm backend vendor/bin/pint --test

cd bentador
npm ci
npm run test:unit -- --run
npm run build
npx oxlint .
npx eslint .
npx playwright install chromium
npm run test:e2e
```

Backend tests cover tenant isolation, real PostgreSQL concurrent stock deduction/idempotency, single-use invitations, revocation, manual payment auditing, and legacy migration preservation. Browser tests exercise independent carts, checkout payloads, vendor switching, and customer history using controlled API responses; backend integration tests verify the real authorization boundary. SQLite can run non-concurrency tests, but is not a substitute for the PostgreSQL checks.
