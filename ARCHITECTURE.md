# Benta Door architecture

## Runtime

Laravel 12 provides the API; `bentador/` is the Vue 3/TypeScript SPA. Docker uses PostgreSQL 16 and NGINX. `frontend/` remains the separate legacy application. This is a shared-database application with explicit vendor ownership, not a marketplace.

## Ownership

- `users`: global identities and platform-administrator status.
- `vendors`: independent stores, slug, activation, PHP currency.
- `vendor_memberships`: staff account/vendor activation; Spatie teams store vendor-specific roles and permissions.
- `vendor_customers`: customer relationships independent of staff roles.
- Catalog, variants, inventory, categories, orders, order items, and related records: vendor ownership with composite relationship constraints.
- `vendor_invitations`: hashed one-use tokens with expiry.
- `manual_payments` and `audit_events`: payment evidence and business/security actions.

Requests resolve the vendor before resource binding. Staff calls additionally require active membership and permission; public catalog reads are limited to the selected active storefront. Customers can check out and read their own orders without staff permissions. Missing context returns no business records. Platform APIs have separate authorization and never disable vendor scoping.

## Commerce

Checkout validates ownership, duplicate variants, publication, availability, currency, and stock. Variant/inventory rows are locked inside transactions. Monetary calculation uses integer minor units; persisted decimals and historical item snapshots remain compatible. Buyer/vendor idempotency keys prevent duplicate orders and stock deductions. Order numbers use ULIDs.

Fulfillment transitions are explicit. Cancellation before shipping restores stock exactly once. Completed/cancelled orders are archived, and historical references restrict destructive deletion. Manual full-payment confirmation/reversal is audited; it does not process bank or gateway transactions.

## Frontend

The shared API client resolves every legacy component request to a vendor/store URL and rejects late responses after context changes. Staff layout includes a vendor selector. Private Pinia state resets on switches/logout. Carts persist independently by store and selected variant. Global profile/password endpoints operate on the signed-in identity; changing a password invalidates earlier tokens.

See [TENANCY.md](TENANCY.md) for migration, operator access, configuration, and rollout checks.
