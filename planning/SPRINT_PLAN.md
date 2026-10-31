# Benta Door Sprint Plan

Living backlog and progress tracker for the Laravel API and active `bentador/` SPA.
Created from the repository scan on **2026-10-31**. Update this file as work proceeds;
new discoveries belong here alongside the original findings.

## Working agreement

- Use **two-week sprints** initially; set dates, owners, and capacity at sprint planning.
- The sequence below is proposed. Only commit work that fits available capacity.
- **Current sprint:** None. Sprint 1 closed 2026-10-31; Sprint 2 is next for planning.
- **Next priority after Sprint 1 closes:** Sprint 2 repository cleanup and Boost alignment
  (PM-014–PM-017), before customer/filter enhancements. Retain the separate Laravel
  API in `backend/` and active Vue SPA in `bentador/`.
- States: `Backlog`, `Ready`, `In progress`, `Blocked`, `In review`, `Done`, `Deferred`.
- Keep one implementation item in progress per contributor. Complete its validation
  before starting another item where practical.
- Check acceptance criteria only when verified. Mark a ticket `Done` only when it
  also meets the shared Definition of Done.
- Record evidence and the commit/PR on the ticket. Retain completed tickets and IDs.
- Scope decisions marked below are unresolved; this plan does not settle them.

## Sprint lifecycle

| Stage | Checklist |
| --- | --- |
| Planning | Select a goal and Ready tickets; assign owners, estimates, dates, and dependencies. Split oversized work. |
| Development | Implement in small changes; update ticket state, checklist, and blockers as work happens. |
| Daily check-in | Record completed work, next step, and impediments on active tickets. |
| Review | Demonstrate acceptance criteria and record validation, limitations, and feedback. |
| Retrospective | Record one useful process improvement and give actionable follow-ups backlog IDs. |
| Close | Count Done tickets; explicitly return unfinished work to the backlog or select it for the next sprint. |

## Backlog index

Priority: **P1** correctness or validation blocker; **P2** existing workflow or
maintainability improvement; **P3** enhancement or discovery. Owners and estimates
are recorded on selected tickets; newly added work remains unassigned.
Sprint assignments are provisional until planning.

| ID | Task | Priority | Proposed sprint | State | Depends on |
| --- | --- | --- | --- | --- | --- |
| PM-001 | Establish a reliable backend validation baseline | P1 | 1 | Done | — |
| PM-002 | Define vendor customer management rules | P1 | 1 | Done | — |
| PM-003 | Repair customer status and blocking | P1 | 1 | Done | PM-002 |
| PM-004 | Correct statistics and remove misleading sample data | P2 | 1 | Done | — |
| PM-005 | Complete filters and preserve them through pagination | P2 | 3 | Backlog | PM-002 for customer status |
| PM-006 | Complete customer creation or relationship onboarding | P2 | 3 | Backlog | PM-002 |
| PM-007 | Implement product CSV export | P2 | 4 | Backlog | — |
| PM-008 | Implement validated bulk product import | P2 | 4 | Backlog | PM-007 format decision |
| PM-009 | Implement safe bulk product archiving | P2 | 4 | Backlog | — |
| PM-010 | Define notification behavior and preference scope | P3 | 5 | Backlog | — |
| PM-011 | Decide scope for the dormant customer inbox | P3 | 5 | Backlog | — |
| PM-012 | Decide scope for product reviews | P3 | 5 | Backlog | — |
| PM-013 | Build release images in GitHub and deploy from GHCR | P3 | 6 | Backlog | — |
| PM-014 | Retire the unused legacy frontend | P2 | 2 | Backlog | Sprint 1 close |
| PM-015 | Consolidate Docker entry points | P2 | 2 | Backlog | Sprint 1 close |
| PM-016 | Align project documentation with the actual runtime | P2 | 2 | Backlog | PM-014, PM-015 |
| PM-017 | Persist Boost conventions for the separate API and SPA | P2 | 2 | Backlog | PM-016 |

## Sprint 1 — Correctness and trustworthy data

**Goal:** Establish dependable validation and correct customer-management behavior
and visible statistics. **Dates:** 2026-10-31. **State:** Closed.

### PM-001 — Establish a reliable backend validation baseline

Scan baseline: frontend unit tests **14 passed**, TypeScript check passed, deployment
workflow tests **6 passed**. Local SQLite backend run: **41 passed, 11 failed,
1 skipped**. Ten failures involved filesystem permissions; one was a numeric/string
price mismatch. These results do not establish PostgreSQL production failures.

- [x] Reproduce using the isolated PostgreSQL setup in [TENANCY.md](../TENANCY.md#verification).
- [x] Resolve test storage/log/cache ownership issues without broad writable permissions.
- [x] Investigate the homepage `123.45` versus `'123.45'` mismatch; define a consistent
  API money representation and cover it across supported test databases.
- [x] Run PostgreSQL isolation and checkout concurrency tests without concurrency skips.
- [x] Record commands, runtime/database versions, outcomes, and any remaining failures.

Owner: Codex | Estimate: Not assigned | State: Done | Blockers: None
Implementation and validation completed 2026-10-31. PostgreSQL: **60 passed,
0 skipped**; SQLite: **59 passed, 1 expected concurrency skip**; Pint: **164 files
passed**. Test runtime files and Docker dependencies are isolated from application
storage and host dependencies; CI now covers both databases and rejects PostgreSQL
skips. Price regressions cover exact two-decimal strings, `0.01`, the supported
maximum `9999999999.99`, and nullable prices. The Boost launcher resolves its
checkout root dynamically and was checked from root, backend, and relocated paths.
Evidence: [PM-001 validation record](evidence/pm-001/README.md).
Commit: Recorded in Sprint 1 closeout below. PR: None created.
Review: User supplied final review found no actionable regressions. Validation
evidence and review are complete.

References: [HomepageTest.php](../backend/tests/Feature/HomepageTest.php),
[ProductImagesTest.php](../backend/tests/Feature/ProductImagesTest.php),
[HomepageProductResource.php](../backend/app/Http/Resources/HomepageProductResource.php).

### PM-002 — Define vendor customer management rules

**Decision recorded for PM-003 and PM-006.** Global identities, staff memberships,
and vendor customer relationships have different ownership rules.

- [x] Define what customer Active/Inactive and blocking mean for a single store,
  including whether blocking prevents checkout and how unblocking works.
- [x] Define who may manage customers and how permissions are seeded and enforced.
- [x] Define how staff add an existing or new customer: identity matching,
  invitation/registration, and permitted profile edits.
- [x] Preserve the global account, other stores, staff memberships, and order history.
- [x] Record the chosen behavior in [API.md](../API.md) and [TENANCY.md](../TENANCY.md),
  with the decision rationale below.

Owner: Codex | State: Done (discovery) | Decision confirmed by user: 2026-10-31.
Store-specific reversible blocking and recipient-accepted customer invitations were
explicitly approved. Rules and rationale are recorded in API.md and TENANCY.md.
PM-003 implements blocking; PM-006 implements customer invitations in Sprint 3.

### PM-003 — Repair customer status and blocking

Current customer edits call the staff-membership status endpoint; the Block Customer
confirmation only closes its modal.

- [x] Replace the staff-endpoint call with the customer workflow agreed in PM-002.
- [x] Implement tenant-scoped validation, authorization, persistence, and enforcement.
- [x] Wire edit/block/unblock controls with confirmation, loading, and error feedback;
  display persisted state after reload.
- [x] Verify another store, a shared customer's staff membership, global login,
  and historical orders remain unaffected.
- [x] Add API regression tests and browser coverage for the customer workflow.

References: [customer.ts](../bentador/src/stores/customer.ts),
[CustomersView.vue](../bentador/src/views/CustomersView.vue),
[CustomerController.php](../backend/app/Http/Controllers/CustomerController.php).

Owner: Codex | State: Done. Implementation and review complete; commit recorded
in Sprint 1 closeout below; no PR created.
`vendor_customers.is_blocked` is managed through a vendor-scoped PATCH endpoint with
both customer permissions. Checkout and staff orders enforce it using the existing
buyer lock. The UI has confirmation, retry, success feedback, and permission gates.
Evidence: [Sprint 1 validation](evidence/sprint1/README.md). A migration is required
before deploying the API and SPA together. It was applied to the verified local
development environment; no production migration was performed.

### PM-004 — Correct statistics and remove misleading sample data

- [x] Define and calculate Product Sales instead of always displaying zero.
- [x] Display the API's `retention` percentage rather than its `retained` count.
- [x] Replace the fixed customer header total and example phone numbers with real
  values or honest unavailable states; initialize statistics without demo values.
- [x] Align labels and time windows with actual calculations: recent ordering activity
  must not be presented as users currently online.
- [x] Verify empty stores, populated stores, loading/errors, and vendor switches.

References: [products.ts](../bentador/src/stores/products.ts),
[customer.ts](../bentador/src/stores/customer.ts),
[CustomersView.vue](../bentador/src/views/CustomersView.vue).

Owner: Codex | State: Done. Implementation and review complete; commit recorded
in Sprint 1 closeout below; no PR created.
Units Sold counts all-time paid, non-cancelled order-item quantity, including archived
history. Customer percentage/time-window labels now match the API. Sample counts,
IDs, and phone values are removed; unknown/loading/error states remain unavailable,
and successful empty results show zero. Evidence: [Sprint 1 validation](evidence/sprint1/README.md).

## Sprint 2 — Repository clarity and Boost alignment

**Goal:** Make the active application, supported Docker commands, and agent guidance
unambiguous while preserving the separate Laravel API and Vue SPA.
**Dates:** unset. **State:** Planned.
**Priority:** Next after Sprint 1, ahead of the former Sprint 2 customer/filter work.
Assign owners and estimates at planning; do not expand Sprint 1 with these tickets.

Implement PM-014 and PM-015 first, update documentation in PM-016, then validate
durable Boost guidance in PM-017. This scope keeps `backend/` and `bentador/` in
their current locations; an Inertia migration or frontend relocation is outside it.

### PM-014 — Retire the unused legacy frontend

Type: Technical debt | Discovered: 2026-10-31 | Source: Architecture review
Priority: P2 | Sprint: 2 | State: Backlog
Owner: Unassigned | Estimate: TBD | Depends on: Sprint 1 close

The active Vue 3/TypeScript SPA lives in `bentador/`; the separate `frontend/`
application makes ownership and build commands ambiguous.

- [ ] Audit imports, unique functionality/assets, Docker builds, CI/deployment,
  scripts, and documentation before retiring `frontend/`.
- [ ] Preserve anything still required by the active app; remove the legacy app
  only after confirming no supported workflow depends on it.
- [ ] Keep the active SPA at `bentador/` and update remaining references so its
  ownership and commands are clear.
- [ ] Verify active SPA type-check/build, unit/browser tests, and Docker build
  configuration; record evidence that development and production use `bentador/`.

References: `frontend/`, `bentador/`, `docker-compose.yml`, `docker-compose.prod.yml`.
Blockers: Sprint 1 must close before implementation. Evidence/commit/PR: Pending.

### PM-015 — Consolidate Docker entry points

Type: Technical debt | Discovered: 2026-10-31 | Source: Boost portability checks
Priority: P2 | Sprint: 2 | State: Backlog
Owner: Unassigned | Estimate: TBD | Depends on: Sprint 1 close

The legacy `backend/docker-compose.yml` resolves a different stack when commands
run from `backend/`. Keep intentional development, production, and isolated-test
configurations while removing obsolete or conflicting entry points.

- [ ] Inventory Compose files and their callers; identify the supported root
  development, production, and isolated-test commands.
- [ ] Retire obsolete nested configuration after updating any callers; preserve
  required environment-specific differences and persistent storage.
- [ ] Verify documented commands and Boost startup select the intended project
  from supported working directories without fixed checkout paths.
- [ ] Validate supported Compose configurations and deployment workflow regression
  tests; exercise development/test startup without touching production data.

References: `docker-compose*.yml`, `backend/docker-compose.yml`, `init.sh`,
`backend/.codex/config.toml`, `.github/workflows/deploy.yml`.
Blockers: Sprint 1 must close before implementation. Evidence/commit/PR: Pending.

### PM-016 — Align project documentation with the actual runtime

Type: Documentation | Discovered: 2026-10-31 | Source: Architecture and Boost review
Priority: P2 | Sprint: 2 | State: Backlog
Owner: Unassigned | Estimate: TBD | Depends on: PM-014, PM-015

Contributor documents still contain conflicting database, PHP, frontend ownership,
and command guidance. Align them with the cleaned-up repository and actual config.

- [ ] Reconcile README, contributor, architecture, frontend/backend, and verification
  guidance with supported Docker files and package manifests.
- [ ] Distinguish the PHP 8.5 container runtime from supported host/minimum versions;
  describe PostgreSQL application storage and SQLite's test-only role accurately.
- [ ] Correct legacy frontend/submodule references and list commands with their
  working directories, including builds, tests, formatting, and Boost startup.
- [ ] Verify commands against the applicable environment; remove contradictory
  guidance and record the verification evidence.

References: `README.md`, `AGENTS.md`, `AGENT.md`, `ARCHITECTURE.md`, `BACKEND.md`,
`FRONTEND.md`, `TENANCY.md`, generated backend agent guidance.
Blockers: PM-014 and PM-015. Evidence/commit/PR: Pending.

### PM-017 — Persist Boost conventions for the separate API and SPA

Type: Technical debt | Discovered: 2026-10-31 | Source: Architecture review
Priority: P2 | Sprint: 2 | State: Backlog
Owner: Unassigned | Estimate: TBD | Depends on: PM-016

Boost's generated defaults need project-specific context for a Laravel API with a
sibling Vue SPA. Persist those conventions through supported customization rather
than repeatedly correcting generated output.

- [ ] Add durable custom Boost guidelines for `backend/` and `bentador/`, covering
  Vue Router, Pinia, `@/utils/axios`, JWT/API boundaries, and vendor isolation.
- [ ] Specify correct command working directories and Docker runtime; make the
  separate SPA architecture explicit so agents do not assume Inertia or Sail.
- [ ] Establish which custom/generated guideline, skill, and MCP configuration
  files are shared versus local; preserve existing contributor instructions.
- [ ] Run `boost:update` and verify the intended guidance survives regeneration
  without overwriting unrelated rules or introducing machine-specific paths.
- [ ] Verify the supported agent workspace can reach both applications; check
  Boost connection and read-only application/schema inspection against development.

References: `backend/boost.json`, `backend/AGENTS.md`, `backend/.agents/`,
`backend/.codex/config.toml`, `AGENTS.md`, `API.md`, `TENANCY.md`.
Blockers: PM-016. Evidence/commit/PR: Pending.

## Sprint 3 — Complete everyday customer and filtering workflows

**Goal:** Make customer onboarding and visible filters work end to end.
**Dates:** unset. **State:** Planned.

### PM-005 — Complete filters and preserve them through pagination

- [ ] Define date fields, timezone, inclusive boundaries, and allowed ranges for
  products, orders, and customer joined dates.
- [ ] Send date/status/category filters to supported API queries; implement missing
  backend filtering and reject invalid input.
- [ ] Preserve active filters on page changes and refreshes after mutations; reset
  to page one when changing search or filters.
- [ ] Make Apply/Clear/Reset update both visible controls and results.
- [ ] Cover combined filters, page two, reset, empty results, and vendor isolation.

References: [ProductTable.vue](../bentador/src/components/product/ProductTable.vue),
[TransactionsTable.vue](../bentador/src/components/modules/sales/TransactionsTable.vue),
[CustomersView.vue](../bentador/src/views/CustomersView.vue).

### PM-006 — Complete customer creation or relationship onboarding

- [ ] Implement the PM-002 identity/relationship flow with permissions and validation.
- [ ] Wire Add Customer, form fields, submission, duplicate handling, and error feedback.
- [ ] Show success only after persistence; refresh directory totals and results.
- [ ] Test existing identities, new recipients, duplicate relationships, unauthorized
  requests, and attempts to access another vendor's customers.

Reference: [CustomersView.vue](../bentador/src/views/CustomersView.vue).

## Sprint 4 — Product data operations

**Goal:** Replace console-only actions with usable, vendor-scoped data operations.
**Dates:** unset. **State:** Planned. Split import into smaller tickets if needed.

### PM-007 — Implement product CSV export

- [ ] Define columns, variant representation, encoding, and whether export includes
  filtered results or all vendor products; document the format for import reuse.
- [ ] Export the agreed scope across pagination with correct authorization and
  spreadsheet-safe escaping; exclude private/internal fields.
- [ ] Test variants, quotes/newlines, formula-like text, empty exports, and isolation.

### PM-008 — Implement validated bulk product import

- [ ] Provide a downloadable template and explicit create/update and duplicate-SKU rules.
- [ ] Validate file size, columns, types, currencies, categories, variants, and ownership.
- [ ] Provide a preview, row-level errors, confirmation, and a documented transaction/
  partial-failure policy before writing records.
- [ ] Report actual created/updated/rejected counts and refresh products/statistics.
- [ ] Test invalid rows, duplicates/retries, permissions, and cross-vendor references.

### PM-009 — Implement safe bulk product archiving

- [ ] Define all-versus-selected scope and enforce the intended bulk permission.
- [ ] Require confirmation showing affected scope/count; preserve historical order
  references and follow existing product deletion/archive behavior.
- [ ] Implement tenant-scoped handling, consistent image handling, and accurate feedback.
- [ ] Test unauthorized access, other-store isolation, historical references, and retries.

References for PM-007–009: [products.ts](../bentador/src/stores/products.ts),
[ProductsView.vue](../bentador/src/views/ProductsView.vue),
[ProductService.php](../backend/app/Services/ProductService.php).

## Sprint 5 — Enhancement discovery

**Goal:** Turn dormant features into explicit product decisions and estimable work.
**Dates:** unset. **State:** Planned. These are discovery tickets; implementation
requires separately scoped backlog items after the decisions.

### PM-010 — Define notification behavior and preference scope

- [ ] Decide supported events/channels, account-versus-vendor preference ownership,
  defaults, and which security messages cannot be disabled.
- [ ] Document backend persistence, delivery/queue requirements, and how preferences
  will actually affect notifications.
- [ ] Create implementation tickets with acceptance criteria and dependencies, or
  record deferral and a task to clarify the current device-local controls.

Reference: [AccountSettingsView.vue](../bentador/src/views/AccountSettingsView.vue).

### PM-011 — Decide scope for the dormant customer inbox

- [ ] Decide whether to retain, defer, or remove the static unrouted prototype.
- [ ] If retained, define vendor boundaries, participants, permissions, delivery
  expectations, attachments, and message retention.
- [ ] Create separate API, UI, and validation tickets, or record the deferral/removal.

Reference: [InboxView.vue](../bentador/src/views/InboxView.vue).

### PM-012 — Decide scope for product reviews

- [ ] Decide whether to retain, defer, or remove the unregistered controller scaffold.
- [ ] If retained, define review eligibility, moderation, ratings, ownership, and
  permitted edit/delete behavior.
- [ ] Create separate API, UI, and validation tickets, or record the deferral/removal.

Reference: [ProductReviewController.php](../backend/app/Http/Controllers/ProductReviewController.php).

## Sprint 6 — Reduce deployment storage and build overhead

**Goal:** Build release images in GitHub Actions, publish them to GitHub Container
Registry (GHCR), and have the production server pull the published images.
**Dates:** unset. **State:** Planned. **Priority:** Low; schedule after higher-priority
application work. Scope and capacity remain provisional.

### PM-013 — Build release images in GitHub and deploy from GHCR

Type: Enhancement / Deployment
Discovered: 2026-10-31 | Source: User's earlier deployment proposal and workflow review
Priority: P3 | Sprint: 6 (proposed) | State: Backlog
Owner: Unassigned | Estimate: TBD | Depends on: None

The current deployment runs `docker compose build` on the production server, and
production Compose defines backend/frontend build contexts. Move those builds to
GitHub Actions and store the release images in GHCR. The server will retain images
needed to run the application and a bounded rollback set; a retention policy will
control unused images and legacy build caches.

- [ ] Build backend and frontend production images in GitHub Actions from the same
  validated release commit, targeting the production server's architecture.
- [ ] Publish images to GHCR with traceable release/commit tags; deploy immutable
  digests and record the paired backend/frontend image references for each release.
- [ ] Configure registry publishing permissions and server pull access; keep secrets
  out of images, build arguments, repository files, and logs.
- [ ] Replace production backend/frontend build contexts with published image
  references; deploy with pulling and `--no-build` so the server performs no builds.
- [ ] Pull and verify both release images before stopping ingress; a failed pull
  must leave the current application serving requests.
- [ ] Preserve backups, persistent storage, migrations, tenancy reconciliation,
  permission repairs, and internal/public HTTP health checks in the deployment flow.
- [ ] Document application rollback using retained image digests and the existing
  database recovery rules; test failure paths in staging.
- [ ] Define server and GHCR retention policies that preserve running images and
  the agreed rollback releases. Reclaim obsolete images and legacy build caches
  without deleting database, upload, or backup storage.
- [ ] Update deployment workflow regression tests to verify published image selection,
  pull failures, absence of server builds, and existing migration/health-check behavior.
- [ ] Rehearse on staging, record before/after disk usage and successful deployment
  evidence, and update operator setup/deployment documentation.

References: [deploy.yml](../.github/workflows/deploy.yml),
[docker-compose.prod.yml](../docker-compose.prod.yml),
[test_deploy_workflow.py](../scripts/test_deploy_workflow.py),
[TENANCY.md](../TENANCY.md).

## Definition of Ready

Before moving a ticket to `Ready`, confirm its behavior and acceptance criteria are
clear, scope decisions are resolved, dependencies are satisfied, and its estimate
fits the sprint. Assign an owner when selecting it for a sprint.

## Definition of Done

Apply this checklist to each implementation ticket; link the evidence on that ticket.
Discovery tickets instead need a recorded decision and scoped follow-up tickets.

- [ ] All acceptance criteria verified; no unresolved blocker hidden by completion.
- [ ] Appropriate automated checks pass; add regression tests for changed behavior
  and feature tests for endpoint changes.
- [ ] Commerce/authorization changes pass PostgreSQL isolation/concurrency checks.
- [ ] Changed frontend passes TypeScript/build and lint; changed PHP passes Pint.
- [ ] UI changes checked on mobile, tablet, and desktop, including loading, empty,
  failure, keyboard, and permission states.
- [ ] API/configuration/migration documentation updated where affected.
- [ ] Review completed and commit/PR, test results, and UI screenshots recorded.
- [ ] Backlog state updated; discovered follow-ups captured separately.

Use the validation commands in [TENANCY.md](../TENANCY.md#verification).
Also run `npm run type-check` in `bentador/` and
`python3 scripts/test_deploy_workflow.py` when deployment scripts change.

## New discoveries and enhancements

Add findings immediately using the next unused ID (**PM-018** next). Bugs, enhancements,
technical debt, documentation gaps, and retrospective actions share this backlog.
Capture first; prioritize during refinement. A new finding does not automatically
expand the active sprint. Urgent work must record its impact and any displaced ticket.

Copy this template below, add a row to the backlog index, and update the next ID:

```markdown
### PM-018 — Short outcome-focused title

Type: Bug / Enhancement / Technical debt / Documentation
Discovered: YYYY-MM-DD | Source: file, reproduction, review, or user feedback
Priority: P1 / P2 / P3 | Sprint: Unscheduled | State: Backlog
Owner: Unassigned | Estimate: TBD | Depends on: None
Problem/value: What happens today, and why it matters.
Scope/decision needed: Intended behavior and any unresolved question.

- [ ] Observable acceptance criterion
- [ ] Relevant regression/validation criterion

Blockers: None recorded
Evidence: Commands/results, screenshots, and commit/PR links
```

For existing tickets, append owner, estimate, blockers, and evidence beneath their
description when selected. Keep the index state consistent with their actual status.

## Sprint 1 closeout and retrospective

Closed 2026-10-31 with PM-001, PM-002, PM-003, and PM-004 Done. The goal of
reliable backend validation, vendor-safe customer blocking, and trustworthy
store statistics was met. No Sprint 1 work remains open. Validation: PostgreSQL
65 passed with no skips; SQLite 64 passed with one expected PostgreSQL concurrency
skip. Frontend build, unit tests, Chromium suite, Pint, lint, and whitespace checks
passed; responsive evidence is in [Sprint 1 validation](evidence/sprint1/README.md).
The final review found no actionable regressions. Changes committed as `COMMIT_SHA`
(`feat: complete sprint 1 customer and stats workflows`). No PR was created.

Retrospective: Separate database-driver suites and isolated test runtime exposed
SQLite serialization and filesystem permission issues before release. Responsive
end-to-end coverage caught gaps across API and SPA behavior. Improve next sprint by
planning owners, estimates, and dates before implementation, and validate documented
commands from each supported working directory.

## Decisions and sprint history

Sprint 1 closed on 2026-10-31. Scope and sequencing changes are recorded below.

| Date | Ticket/sprint | Decision or change | Rationale / impact / evidence |
| --- | --- | --- | --- |
| 2026-10-31 | Initial plan | Proposed four-sprint sequence | Based on repository scan; dates, capacity, and owners unset. |
| 2026-10-31 | PM-013 / Sprint 5 | Added GitHub Actions builds and GHCR deployment at low priority | User requested server pull-only deployment to reduce unused images and build storage; implementation remains Backlog. |
| 2026-10-31 | Sprint 1 / PM-001 | Started Sprint 1; PM-001 implemented and moved to In review | User selected backend validation first. PostgreSQL 58 passed without skips; SQLite 57 passed with the expected concurrency skip. Evidence under `planning/evidence/pm-001/`; other sprint items remain unselected. |
| 2026-10-31 | PM-001 review follow-up | Addressed portable Boost startup and added missing price boundaries | PostgreSQL 60 passed with no skips; SQLite 59 passed with one expected skip. Platform/formatting checks passed; remote CI and commit/PR remain pending. |
| 2026-10-31 | PM-002–PM-004 | Confirmed customer policy; implemented store blocking and trustworthy statistics | User approved vendor-only blocking and recipient-accepted invitations. PM-002–PM-004 Done; customer and statistics changes validated across API, SPA, and responsive browser flows. Final review found no actionable regressions; commit recorded in closeout. |
| 2026-10-31 | Architecture / PM-014–PM-017 | Keep the separate Laravel API and Vue SPA; prioritize repository cleanup immediately after Sprint 1 | User approved retiring unused frontend code, consolidating Docker entry points, correcting docs, and persisting Boost conventions. Added Sprint 2 for this work; former Sprints 2–5 move to 3–6, retaining ticket IDs. Sprint 1 scope is unchanged; dates, estimates, and owners remain for planning. |

At each sprint close, append: dates, goal outcome, completed IDs, unfinished IDs and
disposition, validation/PR links, review feedback, and one retrospective improvement.
