# Laravel Cloud — staging environment

Per PRD §4.2 we host MDS on Laravel Cloud in the **ap-southeast-1
(Singapore)** region — closest to Filipino users.

This document covers the one-time setup. After this, deploys happen on every
push to `main` (production) and `staging` (staging).

## Prerequisites

- A Laravel Cloud organization (create at https://cloud.laravel.com).
- The GitHub repo `chrvillaruel/mds-web` connected to the org.
- Domains: `staging.mds.ph` (staging) and `app.mds.ph` (production), DNS
  managed wherever you registered the domain.

## Provisioning steps

1. **Create the project** in Laravel Cloud, region `ap-southeast-1`.
2. **Provision a Postgres 16 cluster** (managed). Note the connection
   details — they're injected as `DB_*` env vars automatically.
3. **Provision a Redis 7 instance**. `REDIS_*` env vars are injected.
4. **Object Storage** — create a bucket for invoice PDFs and bulk-import
   uploads.
5. **Environments** — create two: `staging` and `production`. Each gets its
   own URL, DB, Redis, and Object Storage.
6. **Custom domains** — add `staging.mds.ph` to the staging env and
   `app.mds.ph` to production. SSL is auto-provisioned.
7. **Environment variables** — set the secrets that aren't auto-injected:

   ```
   APP_KEY=<run `php artisan key:generate --show` locally and paste>
   APP_URL=https://staging.mds.ph        # or app.mds.ph for production
   APP_ENV=staging                       # or production
   APP_DEBUG=false
   APP_TIMEZONE=Asia/Manila

   STRIPE_KEY=...
   STRIPE_SECRET=...
   STRIPE_WEBHOOK_SECRET=...
   PAYMONGO_PUBLIC_KEY=...
   PAYMONGO_SECRET_KEY=...
   PAYMONGO_WEBHOOK_SECRET=...

   OBSERVABILITY_ADMINS=christian@..., ed@...
   PULSE_ENABLED=true
   TELESCOPE_ENABLED=false               # Telescope is local-only
   ```

   `.env.example` documents every key — anything required there must be set
   in Cloud before the first deploy.
8. **Build hook** — Laravel Cloud auto-detects `package.json` and `composer.json`
   and runs `composer install` + `npm ci && npm run build` on every deploy.
   Migrations are run automatically post-deploy.
9. **Branch deploys** — wire GitHub:
   - `main` branch -> `production` env
   - `staging` branch -> `staging` env

## Health checks

Laravel Cloud probes `/up` (the route Laravel ships by default in
`bootstrap/app.php`). Don't remove it.

## Observability

- **Pulse** lives at `/pulse`. Gated to `OBSERVABILITY_ADMINS` — see
  `App\Providers\ObservabilityServiceProvider`.
- **Horizon** lives at `/horizon`. Same gate.
- **Telescope** is registered in dev only via `TELESCOPE_ENABLED`.
- **Nightwatch** (production traces) wires up via `NIGHTWATCH_TOKEN`.

## Rollbacks

Laravel Cloud retains the last 10 deployments. Roll back from the dashboard
in under 30 seconds. Database migrations are forward-only — if a migration
needs to be reverted, ship a new migration that does the inverse rather
than relying on `migrate:rollback` against production.
