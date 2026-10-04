# Repository Guidelines

## Project Structure & Module Organization

- `backend/` contains the Laravel 12 API: `app/` holds controllers, services, DTOs, requests, resources, and policies; `routes/api.php` defines endpoints; `database/` holds migrations and seeders.
- `bentador/` is the active Vue 3/TypeScript SPA used by Docker. Components, views, Pinia stores, and routing live in `src/`; static assets live in `public/`.
- `frontend/` contains a separate Vue/JavaScript app; confirm the intended application before editing it.
- Backend tests live in `backend/tests/Feature/` and `backend/tests/Unit/`. Docker and NGINX configuration live at the root and under `backend/nginx/` and `nginx/`.
- Consult `AGENT.md`, `DESIGN.md`, `ARCHITECTURE.md`, and `API.md` for existing contributor, UI, architecture, and endpoint guidance.

## Build, Test, and Development Commands

- Root: `./init.sh` builds and starts Docker services, installs backend dependencies, generates keys, migrates, and seeds. It also stops existing Compose containers. The app opens at `http://localhost:8000`.
- Root: `docker compose up -d --build` starts or rebuilds services; `docker compose logs -f` follows logs.
- In `bentador/`: `npm install`, then `npm run dev` starts Vite; `npm run build` type-checks and builds production assets.
- In `bentador/`: `npm run lint` runs Oxlint and ESLint with automatic fixes; `npm run format` applies Prettier.
- In `backend/`: `composer install` installs dependencies; `vendor/bin/pint` formats PHP; `composer test` clears configuration and runs tests.

## Coding Style & Naming Conventions

Use two-space indentation for Vue/TypeScript, single quotes, no semicolons, and Prettier's 100-column width. Use `<script setup lang="ts">`, PascalCase component names such as `ProductTable.vue`, and Pinia stores under `src/stores/`. Use four spaces for PHP and descriptive names such as `StoreProductRequest` and `ProductService`. Keep controllers focused on HTTP handling and delegate business logic to services. Authenticated SPA requests should use `@/utils/axios`.

## Testing Guidelines

Backend tests use Pest with PHPUnit configuration and SQLite in memory; name files `*Test.php`. Add feature tests for endpoint changes. Frontend scripts provide Vitest (`npm run test:unit -- --run`) and Playwright (`npm run test:e2e`); follow `src/**/__tests__/` and `e2e/*.spec.ts` conventions. No numeric coverage threshold is configured. Check responsive layouts for UI changes.

## Commit & Pull Request Guidelines

History mixes short imperative subjects and `feat:` prefixes. Use concise, specific subjects. PRs should describe behavior changes, link relevant issues, report validation, and include screenshots for UI changes. Keep unrelated refactors separate.

## Security & Configuration

Keep secrets out of commits; use `.env.example` for configuration templates. Docker Compose uses PostgreSQL, although some documentation describes SQLite. Check actual environment configuration before database changes.
