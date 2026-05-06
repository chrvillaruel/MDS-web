# ADR-002: Modular monolith over microservices and flat structure

- **Status:** Accepted
- **Date:** 2026-05-06
- **Deciders:** @christian, @ed
- **Linear:** SOF-286

## Context

Phase 1 has six functional areas (Identity, Invoicing, BulkImport, BuyerLink,
Compliance, Billing). Each area has its own domain rules — invoice numbering
rules, VAT computation, BIR report shapes, marketplace parsers. With two
engineers and a six-month window, every hour spent on infrastructure is an
hour not spent on compliance correctness.

We want:

1. Clear ownership boundaries so changes to (e.g.) Shopee parser don't bleed
   into invoice issuance.
2. The ability to test a module in isolation with its own factories and
   fixtures.
3. A boring deployment story — one app, one queue, one database, one CI
   pipeline — so we can spend our time on BIR rules, not on Helm charts.
4. A structural rule that's mechanically enforceable, not a code-review
   convention.

## Decision

Modular monolith. Source code lives under `app/Modules/<Module>/` with a
fixed internal layout (`Domain/`, `Application/`, `Infrastructure/`,
`Http/`). Modules may not import from each other directly. Cross-module
communication goes through:

- Domain events dispatched on the framework event bus, or
- Explicit service contracts under `App\Contracts\*` that any module may
  depend on.

The rule is enforced by Deptrac (`deptrac.yaml`) in CI — a violating import
fails the build.

## Alternatives considered

- **Microservices** — rejected outright. Two engineers, six months, a
  regulatory deadline. We do not have the operational headcount to run
  multiple services, multiple databases, and the network between them. The
  PRD's "single transactional database" assumption (e.g. invoice numbering
  monotonicity, audit journal append-only ordering) gets cheaper inside a
  monolith.
- **Flat `app/` structure** — what `laravel new` ships. Fine for small apps,
  but here it would let, say, `BulkImport` controllers reach directly into
  `Invoicing` Eloquent models, then 18 months later we'd discover the
  Shopee parser writes invoice rows in a way that bypasses VAT
  computation. Mechanical enforcement of boundaries from day 1 is cheap
  insurance.
- **Domain-Driven sub-packages with separate composer.json** (as in some
  large modular-monolith setups) — too much ceremony for this team size.
  PSR-4 namespacing under one composer package is enough.

## Consequences

- Easier: feature work has an obvious home; tests can target one module;
  refactoring a module's internals doesn't ripple; the boundary rule is a
  CI check, not a meeting.
- Harder: when we genuinely need cross-module behavior, we have to design
  the contract or event up front instead of casual reaching. This is the
  intended cost.
- Reversibility: high. If a module ever genuinely warrants extraction (we
  don't expect this in Phase 1), the boundary already maps cleanly to a
  service. If the modular structure proves too heavy for a small section
  (e.g. a tiny admin tool), we can collapse that part back into `app/`
  without disturbing other modules.
- Follow-up: as we add modules, update `deptrac.yaml` rulesets. As we
  introduce shared contracts, place them under `App\Contracts\*` and never
  inside a module's namespace.
