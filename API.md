# API reference

All routes use `/api/v1`. Authenticated requests use `Authorization: Bearer <token>`; business ownership comes from the URL, never a client-supplied `vendor_id` field.

## Global accounts

| Method | Path | Purpose |
| --- | --- | --- |
| POST | `/auth/login`, `/auth/register` | Customer/global account authentication |
| POST | `/auth/forgot-password`, `/auth/reset-password` | Email reset; reset requires token, email, password and confirmation |
| POST | `/auth/logout`, `/auth/refresh` | Invalidate or refresh token |
| GET | `/users/me`, `/profile` | Current global identity |
| PUT | `/profile`, `/profile/password` | Update own profile/password |
| GET | `/me/vendors` | Active staff memberships |
| GET | `/invitations/{token}` | Invitation recipient/store details |
| POST | `/invitations/{token}/accept` | Accept once; existing accounts must sign in as the recipient |

Disabled accounts cannot authenticate. Accounts flagged for default-password reset must use the email reset flow. Password changes revoke older JWT versions.

## Homepage

Public GET `/homepage/stores` lists active stores (12 per page; `search` matches store names). Public GET `/homepage/featured-products` lists platform-selected, published products from active stores with eligible variants (12 per page, newest selection first). Both return `data`, `links`, and pagination `meta` and work without choosing a vendor. Prices are starting prices in the store currency.

Platform-only GET `/platform/featured-products` includes hidden selections so administrators can remove them. GET `/platform/vendors/{slug}/feature-candidates` searches eligible products by title. POST `/platform/vendors/{slug}/featured-products` accepts `product_id`; DELETE the same path with `/{productId}` removes a selection. Selections are unique per store/product and audited. Store owners cannot manage them.

Stored product variant prices use JSON decimal strings with exactly two fractional digits, such as
`"123.00"` or `"0.10"`, matching the variant model's `decimal:2` cast. Homepage
starting prices follow this contract on both PostgreSQL and SQLite; a hidden
platform selection with no eligible variants has `price: null`. A variant without
a sale price has `sale_price: null`. Currency is supplied separately; display
formatting belongs to the client. Aggregate statistics retain their existing shapes.

## Independent storefronts

Prefix: `/stores/{slug}`. Unknown or suspended stores return 404.

| Method | Suffix | Purpose |
| --- | --- | --- |
| GET | `/` | Store information |
| GET | `/catalog/products/{productSlug}` | Public product details for homepage deep links |
| GET | `/catalog/products`, `/catalog/categories` | Published catalog for this vendor |
| POST | `/checkout` | Place an authenticated customer's order |
| GET | `/orders` | Current customer's history for this vendor |

Checkout accepts `payment_method` (`cash` or `bank_transfer`), optional notes/shipping method, `idempotency_key`, and `items` containing unique `variant_id`, positive integer `quantity`, and `price_type` (`original` or `sale`). The server supplies prices and rejects other-vendor variants. Discounts/tax/shipping totals from customer input are ignored. Reusing a key with a different validated payload returns 409. Currency must match the store's PHP currency.

## Vendor administration

Prefix: `/vendors/{slug}` (for example, `/vendors/benta-door/products`). Vendors are resolved by store slug, not database ID. Active membership and each module's permission are required; platform administration alone does not confer membership.

| Method | Suffix | Purpose |
| --- | --- | --- |
| GET | `/me` | Current membership roles/permissions |
| GET/POST | `/products` | List/create products |
| GET/PUT/PATCH/DELETE | `/products/{product}` | Read/update/archive catalog record |
| GET/POST | `/categories` | List/create vendor categories |
| PUT/DELETE | `/categories/{category}` | Rename/archive unused categories |
| GET | `/inventory/{total,sales,stocks,unavailable}` | Scoped inventory statistics |
| GET/POST | `/orders` | List/create staff orders |
| PUT/PATCH | `/orders/{order}` | Apply an allowed fulfillment transition |
| DELETE | `/orders/{order}` | Archive a finished/cancelled order |
| GET | `/orders/total`, `/orders/export` | Scoped statistics/PDF |
| POST | `/orders/{order}/payments` | Record full manual payment with `reference` |
| POST | `/orders/{order}/payments/{payment}/reverse` | Reverse a record with a required `reason` |
| GET | `/customers`, `/customers/total`, `/customers/{user}` | Vendor customer relationships/history |
| PATCH | `/customers/{user}/status` | Block/unblock a customer at this store (`is_blocked` boolean) |
| GET/POST | `/users` | List staff/generate invitation (`email`, `role`) |
| GET | `/users/total` | Membership statistics |
| PATCH | `/users/{user}/role`, `/users/{user}/status` | Change scoped role/activation |
| DELETE | `/users/{user}` | Revoke membership, retaining identity/history |
| GET/POST | `/roles` | List/create scoped roles |
| PUT/DELETE | `/roles/{role}` | Edit/delete unprotected, unused roles |
| GET | `/roles/permissions` | Permissions the acting user may grant |
| GET | `/analytics/{revenue,categories,kpi}` | Scoped paid-order analytics in vendor currency |

Owners cannot be edited through staff/role endpoints. Invitations cannot grant Owner or platform privileges; platform provisioning creates initial owner invitations. API handlers never expose another vendor's orders through a shared user ID.

## Platform administration

`/platform/vendors` supports GET/POST; creation accepts `name`, unique lowercase `slug`, and `owner_email`, returning the store and one-use invitation link. PATCH `/platform/vendors/{slug}` activates/suspends the vendor with `is_active`. Forums remain under `/platform/forums`, available only to platform administrators.

The former unscoped business URLs (`/products`, `/orders`, `/catalog/*`, etc.) no longer exist. Deploy the updated frontend with this API. See [TENANCY.md](TENANCY.md) for rollout compatibility and verification.

### Product images

Staff image routes use `/api/v1/vendors/{store-slug}/products/{product}/images`:

- `GET /` lists images; `GET /{image}/file` previews uploaded files, including drafts (`view products`).
- `POST /` accepts multipart `images[]` and optional zero-based `primary_index` (`edit products`). Maximum 10 images per product; JPEG/PNG/WebP only, 5 MB and 4096 × 4096 pixels per image.
- `PATCH /{image}/primary` selects the storefront cover; `DELETE /{image}` removes an image (`edit products`). Removing the cover promotes the next image.

Product and catalog resources include `images` (`id`, `url`, `is_primary`, `sort_order`) and `primary_image_url`. Featured homepage products include `primary_image_url`. Public files are served through `/api/v1/stores/{store-slug}/images/{image}` only while the store and product are available. Private storage paths are never exposed. Uploaded files live in `backend/storage/app/product-images`; retain that directory in storage backups. Rebuild the backend and reload NGINX after changing upload size configuration, then run `php artisan migrate`.

## Customer relationship policy (PM-002, agreed 2026-10-31)

A customer is a global identity with a separate relationship to each vendor.
`Active` and `Blocked` describe `vendor_customers.is_blocked`, not account activation
or staff membership. Missing relationships return 404. Reads require `view customers`;
status changes require both `view customers` and `edit customers`, active vendor
membership, and a valid boolean `is_blocked`. Owners receive these permissions by
existing role provisioning; other roles must be granted them explicitly. Platform
administration alone grants no vendor access. Status requests cannot edit global
name, email, password, or `is_active`.

Blocking is reversible. It prevents new orders for that buyer at that store through
both storefront and staff order creation, while preserving login, staff membership,
other stores, historical orders, and existing-order fulfillment/payment operations.
A retry of an already-created order with the same idempotency key and payload can
still return that order. Blocking and order creation serialize on the buyer lock;
a block does not retrospectively cancel an order that completed first. Changes are
audited, and repeating the same status is a no-op.

For PM-006, Add Customer will create a separate customer-purpose invitation addressed
to a normalized email, with a hashed one-use token and seven-day expiry. The issuer
shares the link, matching the existing invitation delivery model. Existing accounts
must sign in as the addressed recipient; new recipients register their own identity
and password before acceptance. Staff cannot set credentials or edit global profiles.
Acceptance creates only the vendor customer relationship and grants no staff role.
Duplicate acceptance must not create duplicate relationships or unblock a blocked
customer. Normal checkout may continue creating relationships organically. Issuing
customer invitations requires `view customers` and `create customers`; acceptance
requires recipient identity verification, not staff permissions. This invitation
workflow is specified here for PM-006 and is not implemented by the status endpoint.

## Statistics definitions (PM-004)

`inventory/total.total_units_sold` is the all-time sum of order-item quantity on paid,
non-cancelled orders for the current vendor, including archived historical orders.
It is units, not revenue or a count of distinct products. `total_available` remains
on-hand stock minus reservations; `total_out_stock` counts products explicitly marked
with the out-of-stock status. Catalog counts describe the current catalog.

Customer totals count current vendor relationships. `new` means relationships
created in the last seven days; `active` means customers with an order in the last
30 days, not online presence; `retention` is the percentage with more than one
non-deleted order (all statuses), not `retained`, which is the repeat-customer count.
The UI labels this measure Repeat Customers, rather than claiming cohort retention.
Unavailable/loading metrics use an em dash; a successful empty response displays zero.
