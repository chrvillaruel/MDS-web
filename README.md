# MDS — Million Dollar Software

BIR-accreditable electronic invoicing for Filipino online sellers on Shopee,
Lazada, and TikTok Shop.

**Phase 1 (MVP) build window:** May–November 2026. Launch gate: BIR
accreditation before the December 2026 mandate (RR 11-2025, extended by RR
26-2025).

## Stack

- Laravel 13 + PHP 8.3 (modular monolith — see [ADR-002](docs/adr/ADR-002-modular-monolith.md))
- Inertia + Vue 3 + TypeScript + Tailwind v4 + shadcn-vue ([ADR-001](docs/adr/ADR-001-laravel-inertia-vue.md))
- Postgres 16 (Laravel Cloud, ap-southeast-1) with Row-Level Security for tenancy
- Horizon, Cashier, Pulse, Pennant, Scout, Boost, Sanctum, Fortify
- Pint, PHPStan level 8 (Larastan), Pest 3, Deptrac for module boundaries

## Project layout

```
app/
  Modules/
    Identity/         Sellers, branches, users, roles, 2FA
    Invoicing/        Invoice issuance, numbering, PDF, events
    BulkImport/       Shopee / Lazada / TikTok parsers
    BuyerLink/        Buyer self-service (one-time link, optional account)
    Compliance/       X/Z reading, e-Journal, Audit Journal, AGT
    Billing/          Stripe + PayMongo subscriptions
docs/adr/             Architecture Decision Records
deptrac.yaml          Module boundary rules — CI fails on cross-module reach
```

Each module has the same internal layout: `Domain/`, `Application/`,
`Infrastructure/`, `Http/`. Modules may not import from each other directly —
cross-module communication goes through events or `App\Contracts\*`. Deptrac
enforces this.

## Local setup

Requirements: PHP 8.3+ (with `bcmath`, `intl`, `pdo_pgsql`, `redis`),
Composer 2.8+, Node 22+, npm 10+.

```bash
git clone git@github.com:chrvillaruel/mds-web.git
cd mds-web

cp .env.example .env
composer install
npm install

php artisan key:generate
php artisan migrate
npm run dev          # Vite dev server
php artisan serve    # http://localhost:8000
```

Single command for the full local dev loop (Vite + queue + log tail + serve):

```bash
composer dev
```

## Tests + checks

```bash
composer lint        # Pint --test
composer format      # Pint (write)
composer stan        # PHPStan level 8
composer deptrac     # Module boundary check
composer test        # Pest unit + feature
npm run type-check   # vue-tsc
npm run build        # Vite production build
```

CI runs all of the above plus `composer audit` and `npm audit` on every PR.
See [`.github/workflows/ci.yml`](.github/workflows/ci.yml).

## Decision log

ADRs live in [`docs/adr/`](docs/adr/). When making a non-trivial architectural
or product-shape decision, copy `docs/adr/ADR-template.md` and add a new
numbered ADR. Keep them short — context, decision, consequences.

| #     | Title                                                                            |
| ----- | -------------------------------------------------------------------------------- |
| `001` | [Laravel 13 + Inertia + Vue 3](docs/adr/ADR-001-laravel-inertia-vue.md)          |
| `002` | [Modular monolith over microservices](docs/adr/ADR-002-modular-monolith.md)      |

## Linear

Backlog and milestones live in Linear under project **MDS**. Engineering work
maps to milestones M1–M6 (see project description).

## License

Proprietary — © Million Dollar Software. All rights reserved.
