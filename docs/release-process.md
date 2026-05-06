# Release Process

This document is referenced by SOF-295 (VatCalculator) acceptance: VAT math
is regulator-critical, so each release that touches the calculator or the
EIS payload must carry an explicit Ed sign-off.

## Pre-release checklist

For every cut to staging or production:

1. **Run the full test suite locally**
   - `composer test` (Pest, ~2 s as of M2 — flag any slowdown beyond 5 s p95
     on the issuance test).
   - `composer stan` (PHPStan level 8, must report zero errors).
   - `composer deptrac` (must report zero violations).
   - `composer lint` (Pint, must be a no-op).
2. **Run the type checker on the front-end**
   - `npm run type-check`.
3. **Build the front-end assets**
   - `npm run build` — produces `public/build/manifest.json`. CI requires
     this for the Inertia app shell; tests need it too once the Welcome
     route is exercised end-to-end.

## Compliance-critical sign-offs

Some changes cannot ship without a named sign-off in the PR description.

### VAT math (`Modules\Invoicing\Domain\VatCalculator` and friends)

Any change that touches:

- `VatCalculator`
- `Money`
- `LineInput` / `LineCalculation` / `InvoiceCalculation`
- `VatClassification`
- `VatMode`

requires:

1. The `tests/Unit/Invoicing/VatCalculatorTest.php` ReferenceVatCases
   suite to pass at 100% (this is enforced by CI; this requirement is here
   because we want it written down).
2. **Ed's signature** in the PR description: `VAT-signoff: @ed YYYY-MM-DD`.
3. A line in the changelog under "Compliance-critical" pointing at the
   commit and the ReferenceVatCases coverage delta.

### EIS payload (`EisPayloadBuilder`)

Any change to the canonical payload shape requires:

1. The golden file at `tests/Unit/Invoicing/golden/eis-payload-v1-sample.json`
   to be either unchanged OR explicitly regenerated AND reviewed.
2. A bump of the `version()` string if and only if the change is
   non-backward-compatible. v1 is frozen for already-issued invoices forever.
3. **Ed's signature** in the PR description: `EIS-signoff: @ed YYYY-MM-DD`.

### Database migrations that touch immutable tables

`invoice_events` is append-only at the database trigger level. Any
migration that adds, alters, or drops columns on `invoice_events` requires
both co-founders to sign off:

1. `Migration-signoff: @christian YYYY-MM-DD`
2. `Migration-signoff: @ed YYYY-MM-DD`

The trigger that blocks UPDATE/DELETE on `invoice_events` (see
`add_postgres_constraints_and_rls` migration) is a load-bearing regulatory
guarantee — do not weaken it without an ADR documenting why.

## Production deploy

1. Tag the release: `git tag -s vYYYY.MM.DD-N -m "<one-line summary>"`.
2. Push: `git push origin main --tags`.
3. Laravel Cloud auto-deploys to the Singapore environment on `main`.
4. Smoke-test the issuance path against staging:
   - Create a manual invoice → confirm a serial is allocated and the PDF
     renders.
   - Try to issue a duplicate (same Idempotency-Key, same body) → confirm
     it returns the cached invoice.
   - Try to issue with a stale Idempotency-Key + new body → confirm 409.
5. Watch the AGT monotonic-CHECK trigger and the invoice-events
   append-only trigger for any RAISE EXCEPTION in the logs (these would
   indicate a bug in our code, not a test-data issue, and require a
   rollback).

## Rollback

The issuance path is forward-only on the data side: we do not delete or
update issued invoices, we never reuse serials, and the canonical_payload
is frozen. A rollback of the *code* therefore does not require a rollback
of the *data*. To roll back:

1. Re-deploy the previous tag.
2. Migrations are forward-compatible; the `pdf_path` and `void_reason`
   columns added in M2 will simply be ignored by older code.
3. If a rollback exposes a regulatory bug (a serial got duplicated, an
   AGT regressed), file a P0 ticket and notify Ed immediately. Both of
   these conditions are caught by Postgres triggers; if they ever fire in
   production, treat it as evidence of a bug in our code.
