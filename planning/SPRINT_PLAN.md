# Benta Door Sprint Plan

Living backlog and progress tracker for the Laravel API and active `bentador/` SPA.
Created from the repository scan on **2026-10-31**. Update this file as work proceeds;
new discoveries belong here alongside the original findings.

## Working agreement

- Use **two-week sprints** initially; set dates, owners, and capacity at sprint planning.
- The sequence below is proposed. Only commit work that fits available capacity.
- **Current sprint:** none started. **Next sprint:** Sprint 1, proposed scope below.
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

Priority: **P1** correctness or validation blocker; **P2** incomplete existing workflow;
**P3** enhancement or discovery. All owners and estimates are currently unassigned.
Sprint assignments are provisional until planning.

| ID | Task | Priority | Proposed sprint | State | Depends on |
| --- | --- | --- | --- | --- | --- |
| PM-001 | Establish a reliable backend validation baseline | P1 | 1 | Backlog | — |
| PM-002 | Define vendor customer management rules | P1 | 1 | Backlog | — |
| PM-003 | Repair customer status and blocking | P1 | 1 | Backlog | PM-002 |
| PM-004 | Correct statistics and remove misleading sample data | P2 | 1 | Backlog | — |
| PM-005 | Complete filters and preserve them through pagination | P2 | 2 | Backlog | PM-002 for customer status |
| PM-006 | Complete customer creation or relationship onboarding | P2 | 2 | Backlog | PM-002 |
| PM-007 | Implement product CSV export | P2 | 3 | Backlog | — |
| PM-008 | Implement validated bulk product import | P2 | 3 | Backlog | PM-007 format decision |
| PM-009 | Implement safe bulk product archiving | P2 | 3 | Backlog | — |
| PM-010 | Define notification behavior and preference scope | P3 | 4 | Backlog | — |
| PM-011 | Decide scope for the dormant customer inbox | P3 | 4 | Backlog | — |
| PM-012 | Decide scope for product reviews | P3 | 4 | Backlog | — |
| PM-013 | Build release images in GitHub and deploy from GHCR | P3 | 5 | Backlog | — |

## Sprint 1 — Correctness and trustworthy data

**Goal:** Establish dependable validation and correct customer-management behavior
and visible statistics. **Dates:** unset. **State:** Planned.

### PM-001 — Establish a reliable backend validation baseline

Scan baseline: frontend unit tests **14 passed**, TypeScript check passed, deployment
workflow tests **6 passed**. Local SQLite backend run: **41 passed, 11 failed,
1 skipped**. Ten failures involved filesystem permissions; one was a numeric/string
price mismatch. These results do not establish PostgreSQL production failures.

- [ ] Reproduce using the isolated PostgreSQL setup in [TENANCY.md](../TENANCY.md#verification).
- [ ] Resolve test storage/log/cache ownership issues without broad writable permissions.
- [ ] Investigate the homepage `123.45` versus `'123.45'` mismatch; define a consistent
  API money representation and cover it across supported test databases.
- [ ] Run PostgreSQL isolation and checkout concurrency tests without concurrency skips.
- [ ] Record commands, runtime/database versions, outcomes, and any remaining failures.

References: [HomepageTest.php](../backend/tests/Feature/HomepageTest.php),
[ProductImagesTest.php](../backend/tests/Feature/ProductImagesTest.php),
[HomepageProductResource.php](../backend/app/Http/Resources/HomepageProductResource.php).

### PM-002 — Define vendor customer management rules

**Decision needed before PM-003 and PM-006.** Global identities, staff memberships,
and vendor customer relationships have different ownership rules.

- [ ] Define what customer Active/Inactive and blocking mean for a single store,
  including whether blocking prevents checkout and how unblocking works.
- [ ] Define who may manage customers and how permissions are seeded and enforced.
- [ ] Define how staff add an existing or new customer: identity matching,
  invitation/registration, and permitted profile edits.
- [ ] Preserve the global account, other stores, staff memberships, and order history.
- [ ] Record the chosen behavior in [API.md](../API.md) and [TENANCY.md](../TENANCY.md),
  with the decision rationale below.

### PM-003 — Repair customer status and blocking

Current customer edits call the staff-membership status endpoint; the Block Customer
confirmation only closes its modal.

- [ ] Replace the staff-endpoint call with the customer workflow agreed in PM-002.
- [ ] Implement tenant-scoped validation, authorization, persistence, and enforcement.
- [ ] Wire edit/block/unblock controls with confirmation, loading, and error feedback;
  display persisted state after reload.
- [ ] Verify another store, a shared customer's staff membership, global login,
  and historical orders remain unaffected.
- [ ] Add API regression tests and browser coverage for the customer workflow.

References: [customer.ts](../bentador/src/stores/customer.ts),
[CustomersView.vue](../bentador/src/views/CustomersView.vue),
[CustomerController.php](../backend/app/Http/Controllers/CustomerController.php).

### PM-004 — Correct statistics and remove misleading sample data

- [ ] Define and calculate Product Sales instead of always displaying zero.
- [ ] Display the API's `retention` percentage rather than its `retained` count.
- [ ] Replace the fixed customer header total and example phone numbers with real
  values or honest unavailable states; initialize statistics without demo values.
- [ ] Align labels and time windows with actual calculations: recent ordering activity
  must not be presented as users currently online.
- [ ] Verify empty stores, populated stores, loading/errors, and vendor switches.

References: [products.ts](../bentador/src/stores/products.ts),
[customer.ts](../bentador/src/stores/customer.ts),
[CustomersView.vue](../bentador/src/views/CustomersView.vue).

## Sprint 2 — Complete everyday customer and filtering workflows

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

## Sprint 3 — Product data operations

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

## Sprint 4 — Enhancement discovery

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

## Sprint 5 — Reduce deployment storage and build overhead

**Goal:** Build release images in GitHub Actions, publish them to GitHub Container
Registry (GHCR), and have the production server pull the published images.
**Dates:** unset. **State:** Planned. **Priority:** Low; schedule after higher-priority
application work. Scope and capacity remain provisional.

### PM-013 — Build release images in GitHub and deploy from GHCR

Type: Enhancement / Deployment
Discovered: 2026-10-31 | Source: User's earlier deployment proposal and workflow review
Priority: P3 | Sprint: 5 (proposed) | State: Backlog
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

Add findings immediately using the next unused ID (**PM-014** next). Bugs, enhancements,
technical debt, documentation gaps, and retrospective actions share this backlog.
Capture first; prioritize during refinement. A new finding does not automatically
expand the active sprint. Urgent work must record its impact and any displaced ticket.

Copy this template below, add a row to the backlog index, and update the next ID:

```markdown
### PM-014 — Short outcome-focused title

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

## Decisions and sprint history

No sprint has started. Scope and sequencing changes are recorded below.

| Date | Ticket/sprint | Decision or change | Rationale / impact / evidence |
| --- | --- | --- | --- |
| 2026-10-31 | Initial plan | Proposed four-sprint sequence | Based on repository scan; dates, capacity, and owners unset. |
| 2026-10-31 | PM-013 / Sprint 5 | Added GitHub Actions builds and GHCR deployment at low priority | User requested server pull-only deployment to reduce unused images and build storage; implementation remains Backlog. |

At each sprint close, append: dates, goal outcome, completed IDs, unfinished IDs and
disposition, validation/PR links, review feedback, and one retrospective improvement.
