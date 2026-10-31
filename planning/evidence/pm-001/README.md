# PM-001 backend validation baseline

Validated 2026-10-31, working tree based on `06bb005`. Implementation is ready for
contributor review; no commit or PR has been created.

## Reproduction and diagnosis

Initial local command: `cd backend && php vendor/bin/pest --compact`.
PHP 8.3.6 / SQLite 3.45.1 reproduced **41 passed, 11 failed, 1 skipped**.
Ten failures involved application-owned storage/log files; the remaining failure
was SQLite returning the homepage price as a number instead of a decimal string.
Pest also warned about its dependency-directory result cache permissions.

After isolating storage and Laravel caches, before the price fix, SQLite returned
**51 passed, 1 failed, 1 skipped**, isolating the price serialization failure.
After the price fix and five regression cases: **57 passed, 1 skipped**, 578
assertions. Local Pint also passed. These local results are not PostgreSQL failures.

The initial isolated Composer install exposed a host `vendor/composer` ownership
conflict. The test service now uses its own dependency volume, initialized for the
test UID/GID. A fresh install into that volume completed successfully. No chmod
or chown was applied to host application storage or dependencies.

## Implementation

- Test bootstrap creates private per-run storage, logs, views, and Laravel caches
  under a random temporary directory, exported to checkout workers and removed
  when the runner exits normally. Directories use mode 0700.
- Pest's result cache uses ignored `.phpunit.cache` instead of `vendor/`.
- `composer test` invokes Pest directly, avoiding the application's shared
  configuration cache and Artisan boot before test isolation is established.
- Test Compose runs as UID/GID 1000 here (configurable through `TEST_UID` and
  `TEST_GID`), uses private setup tmpfs mounts, and isolates Composer dependencies.
- Homepage raw-query prices are normalized through decimal arithmetic to
  two-decimal JSON strings, matching Eloquent variant casts. Null remains null.
  Cases cover `123.45`, `123.00`, `0.00`, `0.01`, `0.10`, `99999999.99`, and the
  supported maximum `9999999999.99` through public homepage, platform
  listings/candidates, and storefront variant prices.
- CI runs PostgreSQL and SQLite; PostgreSQL uses `--fail-on-skipped`.

## Isolated environment

| Component | Version |
| --- | --- |
| PHP | 8.5.12 |
| PostgreSQL | 16.15, x86_64, Alpine |
| SQLite | 3.46.1 |
| Laravel | 12.59.0 |
| Laravel Boost | 2.10.4 |
| Pest | 4.7.0 |
| PHPUnit | 12.5.24 |

Versions were read using PHP/PDO, Composer InstalledVersions, and
`psql -U test -d bentador_test -Atc 'select version()'` inside test containers.

## Commands and results

Commands below run from the repository root, with `TEST_UID=1000` and
`TEST_GID=1000` (the defaults on this host). Other hosts should export their own
`id -u` / `id -g` values before both building and running.

```bash
docker compose -f docker-compose.test.yml build backend
docker compose -f docker-compose.test.yml run --rm backend composer install --no-interaction --prefer-dist
docker compose -f docker-compose.test.yml run --rm backend composer test -- --colors=never --fail-on-skipped
docker compose -f docker-compose.test.yml run --rm -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: backend composer test -- --colors=never
docker compose -f docker-compose.test.yml run --rm --no-deps backend vendor/bin/pint --test
docker compose -f docker-compose.test.yml run --rm --no-deps backend composer check-platform-reqs
docker compose -f docker-compose.test.yml down
```

| Check | Outcome | Evidence |
| --- | --- | --- |
| Complete PostgreSQL suite | 60 passed, 616 assertions, 0 skipped; 16.89s; exit 0 | [postgresql.txt](postgresql.txt) |
| Complete SQLite suite | 59 passed, 604 assertions, 1 expected skip; 7.21s; exit 0 | [sqlite.txt](sqlite.txt) |
| Pint on PHP 8.5 | 164 files passed; exit 0 | [pint.txt](pint.txt) |
| Composer platform requirements, including Boost | All requirements satisfied on PHP 8.5.12; exit 0 | [platform.txt](platform.txt) |
| Local Composer validation | `composer validate --strict --no-check-publish`: valid; exit 0 | Console result |
| Deployment workflow tests | `python3 scripts/test_deploy_workflow.py`: 6 passed; exit 0 | Console result |
| Compose syntax | `docker compose -f docker-compose.test.yml config --quiet`: exit 0 | Console result |
| Whitespace | `git diff --check`: exit 0 | Console result |

The PostgreSQL suite includes tenant isolation, legacy migration preservation,
oversell prevention, and concurrent idempotency retries. Its concurrency case
passed in 1.24s. SQLite intentionally skips that row-lock case; it is verified on
PostgreSQL instead. No remaining backend failures were observed. Production
Compose and production data were not used. GitHub CI configuration is updated;
the remote jobs have not been run as part of this working-tree validation.

## Review follow-up — 2026-10-31

The user supplied a Boost-assisted review identifying a machine-specific MCP
working directory and missing price boundaries. Both items have been addressed:

- Extended the existing dataset with `0.01` and `9999999999.99`. The focused
  homepage file passed independently on both databases: 12 tests / 174 assertions
  each (PostgreSQL 4.44s, SQLite 1.97s). The complete suite results above were then
  refreshed against the current lockfile, including the user's Boost installation.
- Removed the hardcoded `cwd` from `backend/.codex/config.toml`. Its launcher now
  derives the checkout root with `git rev-parse --show-toplevel` and explicitly
  selects that root's `docker-compose.yml`. This also avoids the different legacy
  Compose file inside `backend/`, which makes merely removing `cwd` insufficient.
- Parsed the actual TOML launcher and probed its Compose selection with
  `config --format json` instead of starting MCP. From both root and `backend/`,
  the resolved backend build context was the root project's `backend` directory
  and the root stack's `db` service was present. Repeated both checks in a
  temporary relocated Git checkout whose path contained spaces and both Compose
  files: all four cases passed. The temporary fixture was removed.
- `cd backend && vendor/bin/pint --dirty --format agent` passed before full
  formatting validation. No PHP runtime or price serializer change was necessary.

The MCP configuration is backend-scoped: start Codex from `backend/` to load it,
and restart existing sessions to pick up the revised launcher. The development
backend must be running for `docker compose exec` to connect. The launcher probes
verified project selection; they did not start a new live MCP session.
Final reviewer acceptance and commit/PR remain pending; PM-001 stays `In review`.
