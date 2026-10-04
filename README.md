# Product Management UI and API's

A full-stack web application built with Laravel (API backend) and Vue.js (SPA frontend).

The app uses Docker and PostgreSQL, with independent vendor storefronts and vendor-scoped staff access.

## Documentation

| Document | Description |
|----------|-------------|
| [TENANCY.md](./TENANCY.md) | Vendor ownership, migration, rollout, and verification |
| [AGENT.md](./AGENT.md) | Guidelines for AI agents and contributors |
| [DESIGN.md](./DESIGN.md) | UI/UX and component standards |
| [ARCHITECTURE.md](./ARCHITECTURE.md) | System design and monorepo layout |
| [API.md](./API.md) | REST API reference |
| [FRONTEND.md](./FRONTEND.md) | Vue SPA routes, stores, and features |
| [BACKEND.md](./BACKEND.md) | Laravel API conventions |

The original single-file frontend mandates live in [bentador/GEMINI.md](./bentador/GEMINI.md) (index to the docs above).

## Getting Started

### 1. Clone the Repository

```bash
git clone https://github.com/cyrusmanatad/fyb-tech-exam.git

cd fyb-tech-exam
```

### 2. Running the Application (Laravel + Vue.js)

Ensure Docker is installed and running on your machine.

Then initialize the project:

```bash
./init.sh
```

### This script will:

- Build and start the Docker containers
- Install backend and frontend dependencies
- Run migrations
- Compile frontend assets
- Once finished, the application will be available at: `http://localhost:8000/stores/benta-door`

### Starting containers directly

If containers were started with `docker compose up` directly on a fresh installation, initialize Laravel before signing in:

```bash
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan jwt:secret
docker compose exec backend php artisan config:clear
docker compose exec backend php artisan migrate
```

Generate keys only when missing; retain existing keys on subsequent starts. Backend container startup repairs Laravel storage/cache group permissions for PHP-FPM, including bind mounts and restored volumes.

### Tech Stack

- Backend: Laravel 12, PHP 8.2+, JWT, Spatie Permissions
- Frontend: Vue 3, TypeScript, Vite, Pinia, Tailwind CSS 4 (`bentador/`)
- Web Server: NGINX
- Database: PostgreSQL 16
- Containerization: Docker / Docker Compose

### Recent features

- **Profile Settings** (`/profile`) — Update name and email
- **Account Settings** (`/account`) — Change password, security info, preferences

See [FRONTEND.md](./FRONTEND.md) and [API.md](./API.md) for details.

## Vendor administration

Staff open `/vendors` to select a store. Platform operators open `/platform/vendors` to provision vendors and share owner invitations. Existing data migrates to `benta-door`; migration never grants platform access automatically. After reviewing the intended operator, run `php artisan platform:grant operator@example.com` inside the backend container.

Default-password accounts must use `/reset-password` before login. Configure SMTP for production; local development logs reset messages. Demo seeders are blocked in production. Read [TENANCY.md](./TENANCY.md) before migrating existing data or deploying this API change.
