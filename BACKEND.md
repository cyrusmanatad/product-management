# BACKEND.md — Laravel API

Current tenancy routes and security rules are in [API.md](./API.md), [ARCHITECTURE.md](./ARCHITECTURE.md), and [TENANCY.md](./TENANCY.md). Business requests now require an explicit vendor/store URL; examples below describe the original modules.


Backend documentation for `backend/`. See [ARCHITECTURE.md](./ARCHITECTURE.md) for system context and [API.md](./API.md) for endpoints.

---

## Stack

- Laravel 12, PHP 8.2+
- SQLite (default in Docker / `.env`)
- JWT: `php-open-source-saver/jwt-auth`
- RBAC: `spatie/laravel-permission` (guard `api`)
- PDF: `barryvdh/laravel-dompdf`
- Tests: Pest

---

## Directory layout

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Requests/
│   │   └── Resources/
│   ├── Services/
│   ├── Models/
│   ├── Policies/
│   ├── DTOs/
│   └── Enums/
├── routes/api.php          # All /api/v1 routes
├── database/
│   ├── migrations/
│   └── seeders/
└── tests/Feature/
```

---

## Adding a feature (checklist)

1. Migration / model changes if needed
2. `FormRequest` for validation
3. `Service` for business logic (if non-trivial)
4. `Controller` method
5. `Resource` for JSON shape
6. Route in `routes/api.php` inside `auth:api` group when protected
7. Feature test in `tests/Feature/`
8. Document endpoint in [API.md](./API.md)

---

## Profile module (reference implementation)

| File | Purpose |
|------|---------|
| `Http/Controllers/ProfileController.php` | show, update, updatePassword |
| `Http/Requests/UpdateProfileRequest.php` | name, email unique rule |
| `Http/Requests/UpdatePasswordRequest.php` | password rules |
| `Http/Resources/UserResource.php` | Extended user JSON |
| `tests/Feature/ProfileTest.php` | Profile API tests |

Routes (authenticated):

```php
Route::prefix('profile')->group(function () {
    Route::get('/', [ProfileController::class, 'show']);
    Route::put('/', [ProfileController::class, 'update']);
    Route::put('/password', [ProfileController::class, 'updatePassword']);
});
```

---

## Seeders

```bash
php artisan db:seed
```

- `PermissionsSeeder` — roles, permissions, demo staff users
- `DatabaseSeeder` — categories, products, variants, inventory, forums

Demo password: `Password@1234`

---

## Common artisan commands

```bash
php artisan migrate
php artisan migrate:fresh --seed
php artisan jwt:secret
php artisan test
php artisan route:list --path=api
```

---

## CORS

Configured in `config/cors.php` for `api/*` with credentials support (SPA JWT).

---

## Related docs

- [API.md](./API.md)
- [ARCHITECTURE.md](./ARCHITECTURE.md)
- [AGENT.md](./AGENT.md)
