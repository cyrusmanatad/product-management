# AGENT.md — Benta Door

Instructions for AI agents and contributors working on this repository. These mandates take precedence over generic workflows when they conflict.

---

## Project overview

**Benta Door** is a full-stack commerce admin platform:

| Layer | Path | Stack |
|-------|------|--------|
| API | `backend/` | Laravel 12, PHP 8.2+, JWT, Spatie Permissions, PostgreSQL |
| SPA | `bentador/` | Vue 3, TypeScript, Pinia, Vue Router, Tailwind 4, Vite |
| Proxy | `backend/nginx/` | NGINX → Vue dev server + PHP-FPM |

`bentador/` is checked into this repository. Current vendor ownership and rollout requirements are documented in [TENANCY.md](./TENANCY.md).

**App URL (Docker):** `http://localhost:8000`

---

## Repository map

```
fyb-tech-exam/
├── backend/          # Laravel API (/api/v1/*)
├── bentador/         # Vue 3 SPA (submodule)
├── docker-compose.yml
├── init.sh           # Docker setup script
├── AGENT.md          # This file
├── DESIGN.md         # UI/UX standards
├── ARCHITECTURE.md   # System design
├── API.md            # REST API reference
└── FRONTEND.md       # SPA routes, stores, features
```

---

## Commands

### Root (Docker)

```bash
./init.sh                    # Build containers, migrate, JWT secret
docker-compose up -d
docker-compose exec backend php artisan db:seed
```

### Backend (`backend/`)

```bash
composer install
php artisan migrate
php artisan db:seed
php artisan jwt:secret
php artisan test
composer run dev             # serve + queue + vite (if using backend vite)
```

### Frontend (`bentador/`)

```bash
npm install                  # or pnpm install
npm run dev                  # Vite on port 3002 (see vite.config.ts)
npm run build
npm run type-check
npm run test:unit
npm run test:e2e
npm run lint
```

---

## Engineering standards

- **Surgical changes:** Modify only what the task requires. Do not refactor unrelated files.
- **Match existing patterns:** Naming, folder layout, Pinia store shape, Laravel Controller → Service → Resource flow.
- **Validation:** Verify mobile, tablet, and desktop layouts for UI work.
- **Linting:** Run ESLint/Prettier (frontend) and Pint/tests (backend) when touching those areas.
- **Auth:** Frontend API calls must use `@/utils/axios` (JWT in `localStorage` as `auth_token`). Avoid raw `import axios from 'axios'` in stores unless headers are intentionally omitted.
- **API prefix:** Vendor business routes live under `/api/v1/vendors/{vendor}/`; storefronts use `/api/v1/stores/{slug}/`. Missing vendor context must fail closed.

---

## Backend conventions

- **Controllers** — HTTP only; delegate to Services.
- **Services** — Business logic (`OrderService`, `ProductService`, etc.).
- **DTOs** — Structured input (`OrderData`, `ProductData`).
- **Form Requests** — Validation (`StoreProductRequest`, `UpdateProfileRequest`, etc.).
- **API Resources** — JSON shaping (`UserResource`, `ProductResource`).
- **Policies** — Authorization on models where enforced.
- **Permissions** — Spatie, guard `api`. Seeded in `PermissionsSeeder`.

When adding endpoints: register in `backend/routes/api.php`, add Feature tests under `backend/tests/Feature/`.

---

## Frontend conventions

- **Composition API** — `<script setup lang="ts">` only.
- **State** — Pinia stores in `bentador/src/stores/`.
- **API** — `@/utils/axios`, paths like `/api/v1/...`.
- **Routes** — `bentador/src/router/index.ts`; permission gates via `meta.permissions`.
- **Layouts** — Dashboard pages use `AppLayout` + `Sidebar`; Order Entry is standalone.
- **Modals** — `BaseModal` with `<Teleport to="body">`; logout always confirms first.

See [FRONTEND.md](./FRONTEND.md) for routes and stores. See [DESIGN.md](./DESIGN.md) for UI rules.

---

## Profile & account settings (latest)

Authenticated users can manage their own profile without extra permissions.

| Concern | Route | Backend |
|---------|-------|---------|
| Profile (name, email) | `/profile` | `GET/PUT /api/v1/profile` |
| Account (password, prefs) | `/account` | `PUT /api/v1/profile/password` |

Frontend: `ProfileSettingsView.vue`, `AccountSettingsView.vue`, `auth` store (`updateProfile`, `updatePassword`).

Entry points: Sidebar user menu, Order Entry header dropdown.

---

## AI reasoning mandate

*(From original GEMINI.md — retained as project policy.)*

- **Do not blindly agree with the user.** Evaluate requests critically.
- **Act as a senior engineer.** Production-level thinking, not code-only execution.
- **Challenge assumptions** when requests are ambiguous, risky, or incomplete.
- **Provide trade-offs:** pros, cons, alternatives, maintainability and scale impact.
- **Prioritize correctness over compliance.** Recommend better approaches when needed.
- **Think in systems:** architecture, state, UX, edge cases—not isolated diffs.
- **Default stance:** The first solution can usually be improved.

---

## Vendor development requirements

- A vendor is a business; users join through memberships and one-use invitations.
- Never bypass ownership with a platform role or use unscoped queries for business records.
- Raw SQL must constrain vendor IDs. Jobs must use `WithinVendor` and caches must include vendor identity.
- Run PostgreSQL isolation/concurrency tests for commerce or authorization changes.
- Preserve historical prices, attribution, and currencies during migrations; do not seed demo data in production.
- See [TENANCY.md](./TENANCY.md) for verification and the forward-only migration recovery procedure.

---

## Related docs

| File | Purpose |
|------|---------|
| [DESIGN.md](./DESIGN.md) | UI/UX and component standards |
| [ARCHITECTURE.md](./ARCHITECTURE.md) | Data model, Docker, layers |
| [API.md](./API.md) | REST endpoint reference |
| [FRONTEND.md](./FRONTEND.md) | SPA navigation, stores, features |
| [BACKEND.md](./BACKEND.md) | Laravel API conventions |
| [README.md](./README.md) | Quick start |
