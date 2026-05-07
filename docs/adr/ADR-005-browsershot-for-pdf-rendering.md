# ADR-005: Browsershot (headless Chromium) for invoice PDF rendering

- **Status:** Accepted (deferred binding)
- **Date:** 2026-05-06
- **Deciders:** @christian, @ed
- **Linear:** SOF-300

## Context

Per PRD TR-6.5.4 and §11.3, every issued invoice must be rendered to a PDF
that:

1. Carries all 13 BIR-mandated header fields (PRD §3.1).
2. Renders a prominent "Zero-Rated Sale" stamp when applicable (§3.1 #13).
3. Renders Filipino characters (ñ, é, accents) faithfully.
4. Re-renders with a "VOIDED" watermark + reason on void.
5. Completes within the 2 s p95 render budget.
6. Survives a BIR examiner's spot-check during a tax-mapping visit.

The rendering layer needs to evolve quickly: BIR may publish format-tweaks
during accreditation review, and Ed may iterate on cosmetic details with
beta sellers. Rebuilding HTML/CSS is cheaper than rebuilding programmatic
PDF API code.

## Decision

Render PDFs via `spatie/browsershot` (headless Chromium) over a single Blade
template (`resources/views/pdfs/invoice.blade.php`) with print-targeted CSS.

Bound through an `InvoicePdfRenderer` interface so we can:

- Swap the production implementation (Browsershot) without touching the
  template, the issuance flow, or the storage layer.
- Run tests against an `HtmlInvoicePdfRenderer` that emits the same Blade
  output as plain HTML — no Chromium dependency in test/CI environments.

The MVP ships with the `HtmlInvoicePdfRenderer` binding. The Browsershot
binding lands when Chromium is provisioned on Laravel Cloud (tracked as a
follow-up). The artifact path in storage records the file extension so the
swap is invisible to consumers (download links keep working).

## Alternatives considered

- **TCPDF / FPDF / mPDF (programmatic PDF builders)** — pros: no headless
  browser dependency. Cons: every cosmetic tweak is PHP code; Filipino
  characters and rich CSS layouts need explicit font registration; iteration
  on the document is high-friction. Rejected.

- **wkhtmltopdf** — pros: HTML/CSS-driven, lighter than Chromium. Cons:
  upstream is unmaintained; CSS support trails Chromium by years; modern
  layout primitives (flex, grid) are flaky. Rejected.

- **Server-side React/Vue rendering with @react-pdf/renderer** — pros:
  modern, programmable, deterministic. Cons: introduces a Node runtime into
  the PHP stack purely for PDFs; brings a different layout engine than the
  one we'd use for previews. Rejected.

## Consequences

**Easier:**
- Cosmetic iteration is HTML/CSS only. Ed can request "shrink the QR by
  10pt and move it next to the serial number" and it's a one-line change.
- The same Blade template feeds both the on-screen review preview and the
  PDF. There is no second source of truth for what an invoice looks like.
- Filipino character rendering is a Chromium concern, which it has solved
  for two decades.

**Harder:**
- Production needs Chromium installed, monitored, and kept up to date. The
  Browsershot job has its own queue and timeout budget. Memory pressure on
  burst load needs benchmarking.
- Cold-start latency on the first render of a worker is ~1.5 s. Pre-warm
  the queue worker (single-instance HTTP keepalive ping or a warmup job).

**Reversibility:**
- Bound via DI. Switching to a programmatic PDF library later is a single
  service-provider line plus a parallel implementation.

## Verification

- `tests/Feature/Invoicing/RenderInvoicePdfTest.php` — asserts all 13 BIR
  fields, Filipino diacritics, the zero-rated stamp, and the void watermark
  via the HTML renderer (which uses the same template Browsershot
  consumes).
- Pre-launch: Ed will visually inspect a sample of rendered PDFs against a
  mock BIR examiner checklist and sign off (PRD acceptance criterion).
