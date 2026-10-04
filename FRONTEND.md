# FRONTEND.md — Benta Door SPA (bentador)

Current tenancy routes and security rules are in [API.md](./API.md), [ARCHITECTURE.md](./ARCHITECTURE.md), and [TENANCY.md](./TENANCY.md). Business requests now require an explicit vendor/store URL; examples below describe the original modules.


Documentation for the Vue 3 application in `bentador/`. Supplements [DESIGN.md](./DESIGN.md) and [API.md](./API.md).

---

## Tech stack

| Tool | Version / notes |
|------|-----------------|
| Vue | 3.5+ Composition API |
| TypeScript | 5.9 |
| Vite | 7, port `3002` in dev |
| Pinia | 3 |
| Vue Router | 5 |
| Tailwind | 4 (`@tailwindcss/vite`) |
| Axios | JWT via `@/utils/axios` |
| Vitest / Playwright | Unit + E2E |

Package manager: npm or pnpm (README mentions pnpm).

---

## Project structure

```
bentador/src/
├── views/              # Page components
├── components/
│   ├── layouts/        # AppLayout, Sidebar, AppHeader
│   ├── product/        # ProductTable, modals
│   ├── order-entry/    # POS flow
│   ├── common/         # BaseModal, Pagination
│   └── ui/             # TableSpinner, AppDropdown
├── stores/             # Pinia stores
├── composables/
├── types/
├── utils/axios.ts      # Primary API client
└── router/index.ts
```

---

## Routes

### Public

| Path | Name | Component |
|------|------|-----------|
| `/login` | `login` | `LoginView` |

### Authenticated (no AppLayout)

| Path | Name | Component | Notes |
|------|------|-----------|-------|
| `/order-entry` | `order-entry` | `OrderEntryView` | POS; no permission meta |

### Dashboard (`AppLayout` + sidebar)

| Path | Name | Permission required |
|------|------|-------------------|
| `/analytics` | `analytics` | `view analytics` |
| `/products` | `products` | `view products` |
| `/orders` | `orders` | `view orders` |
| `/customers` | `customers` | `view customers` |
| `/inbox` | `inbox` | `view inbox` ⚠️ not in seeder |
| `/users` | `users` | `view users` |
| `/roles-permission` | `roles-permission` | `view roles-permission` |
| `/profile` | `profile-settings` | **None** (any logged-in user) |
| `/account` | `account-settings` | **None** (any logged-in user) |

Default child redirect: `/` → `/analytics`.

**Guard behavior:** Missing permission → redirect to `order-entry` (not login).

---

## Navigation (sidebar)

- **Main Menu:** Home (`/analytics`)
- **My Shop:** Products, Order Entry, Sales Transactions, Customers
- **Shop Management:** Analytics, Inbox
- **Access Management:** Users, Roles & Permission
- **User menu (footer):** Profile Settings, Account Settings, Dark mode, Logout

Order Entry header dropdown also links to Profile and Account.

---

## Pinia stores

| Store | File | API areas |
|-------|------|-----------|
| `auth` | `stores/auth.ts` | login, me, logout, refresh, **profile**, **password** |
| `products` | `stores/products.ts` | products, categories, inventory |
| `order` | `stores/transactions.ts` | orders |
| `cart` | `stores/cart.ts` | client-only (order entry) |
| `customer` | `stores/customer.ts` | customers |
| `user` | `stores/user.ts` | staff users |
| `roleStore` | `stores/roleStore.ts` | roles, permissions |
| `analyticsStore` | `stores/analyticsStore.ts` | analytics |
| `category` | `stores/category.ts` | categories |
| `ui` | `stores/ui.ts` | sidebar, dark mode |
| `toast` | `stores/toast.ts` | global toasts |

### Auth store (profile-related)

```ts
fetchUser(force?: boolean)     // GET /api/v1/users/me
updateProfile({ name, email }) // PUT /api/v1/profile
updatePassword({ ... })        // PUT /api/v1/profile/password
```

After `updateProfile`, `user` ref is updated from response `data`.

---

## Profile settings (`/profile`)

**View:** `views/ProfileSettingsView.vue`

**Features:**

- Avatar (UI Avatars, updates with name)
- Read-only: role, member since, account status
- Editable: full name, email
- Save → `auth.updateProfile()` + toast
- Link to Account Settings and Order Entry

---

## Account settings (`/account`)

**View:** `views/AccountSettingsView.vue`

**Features:**

- **Security overview:** last login, IP, account status, role (from `UserResource`)
- **Change password:** current, new, confirm → `auth.updatePassword()`
- **Preferences (local):**
  - Dark mode → `ui` store / `localStorage.darkMode`
  - Email notifications → `localStorage.emailNotifications`
  - Order alerts → `localStorage.orderAlerts`
- Link back to Profile Settings

Password and profile changes hit the backend; notification toggles are device-local until a backend preferences API exists.

---

## API client

`utils/axios.ts`:

- Dev `baseURL`: `http://localhost:8000`
- Prod `baseURL`: `''` (same origin via NGINX)
- Token: `localStorage.auth_token` → `Authorization: Bearer …`

Use this module in all stores. Do not use bare `axios` for authenticated routes.

---

## Types

`types/auth.ts`:

- `User`, `Credentials`, `ProfileForm`, `PasswordForm`
- Form error interfaces for 422 mapping

---

## Global UI

- `App.vue`: `LogoutModal`, `ToastNotifications`, `RouterView`
- Logout: `productStore.toggleModal('logout')` → confirmed logout via `auth.logout()`

---

## Scripts

```bash
npm run dev          # Development server
npm run build        # Production build
npm run type-check   # vue-tsc
npm run test:unit
npm run test:e2e
npm run lint
```

---

## Related docs

- [DESIGN.md](./DESIGN.md) — Visual standards
- [AGENT.md](./AGENT.md) — Agent workflow
- [API.md](./API.md) — Profile endpoints

Legacy single-file mandates: [bentador/GEMINI.md](./bentador/GEMINI.md) (index only).
