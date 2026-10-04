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
