# ADR-004: Pessimistic locking (`SELECT … FOR UPDATE`) for BIR serial allocation

- **Status:** Accepted
- **Date:** 2026-05-06
- **Deciders:** @christian, @ed
- **Linear:** SOF-296

## Context

PRD §3.4 C-3.4.1 establishes the regulatory invariant that drives every other
choice in the issuance path:

> No two ISSUED invoices on the same `(seller_id, branch_id)` ever share
> `(serial_number, reset_counter)`.

The BIR examiner's first move during a tax mapping visit is to ask for the
sequence of issued invoices and check that it is dense (no duplicates) and
strictly monotonic per branch. We can have gaps. We cannot have duplicates.

The hot path: at peak we expect ~100 simultaneous issuance requests on a
single high-volume branch (Shopee bulk import + manual issuance running
together, plus retries). Every request needs to read the current serial,
increment it, and write it back as part of the same transaction that
persists the invoice — and that transaction can fail anywhere along the way.

## Decision

Allocate serials inside a `DB::transaction` using a Postgres row lock
(`SELECT … FOR UPDATE` on the `branches` row) followed by an `UPDATE`. The
lock is held by the caller's larger transaction, so a rollback releases the
lock without committing the increment.

When `current_serial` reaches `max_serial` (default 999_999, per BIR
guideline length-10 numeric serial), we increment `reset_counter` and reset
`current_serial` to 1.

## Alternatives considered

- **Optimistic locking with a `version` column** — pros: cheap on the happy
  path. Cons: under contention each loser retries the entire issuance
  transaction (re-reads invoice, re-builds payload, re-allocates serial).
  Worse, the retry reuses the *same draft* — replay logic gets messier than
  the lock approach. Rejected.

- **Postgres sequence (`nextval`)** — pros: famously fast and lock-free.
  Cons: a sequence cannot be tied to a `(seller_id, branch_id)` tuple
  without per-branch sequences (operationally awful at multi-tenant scale),
  and we cannot atomically detect the wrap-to-`reset_counter` case. The
  regulatory invariant is per-branch, not per-table. Rejected.

- **Redis counter + Lua atomicity** — pros: low latency. Cons: Redis is a
  separate persistence boundary from Postgres. A Postgres rollback after a
  Redis increment leaks the serial into nothing — and the rule is
  "gaps allowed, reuse never," but only if the gap reflects a *successful*
  allocation. Adding two-phase commit between Redis and Postgres for a
  one-counter problem is not worth it. Rejected.

- **Application-level mutex (file lock, advisory lock outside the txn)** —
  pros: independent of database. Cons: subject to process death between
  acquire and DB commit, which is exactly the failure mode that breaks the
  invariant. Rejected.

## Consequences

**Easier:**
- The invariant is enforced at the same layer that persists the invoice.
- The lock window is bounded by the issuance transaction itself; no
  application code can sneak past it.
- The `branches.current_serial` column is the single source of truth; no
  separate cache, no drift.

**Harder:**
- All issuance traffic for a single branch serializes on that one row.
  Performance ceiling at MVP scale is ~50 ms p95 lock-hold, well within
  budget. Beyond ~10K active sellers we will likely need sharding by branch
  and/or a switch to per-branch advisory locks; documented in
  `docs/architecture/scale-paths.md` (TODO).
- Cross-driver portability: SQLite has no `FOR UPDATE`; the SerialAllocator
  silently degrades to a plain `SELECT`. Tests on SQLite are sequential, so
  this is fine. Production runs Postgres only.

**Reversibility:**
- Switching to advisory locks later is a swap inside `SerialAllocator`. The
  schema does not change; the regulatory contract does not change.

## Verification

- `tests/Feature/Invoicing/SerialAllocatorTest.php`:
  - 100 sequential allocations → 100 distinct serials, monotonic.
  - reset_counter advances at `max_serial`.
  - Rolled-back transactions do not advance the counter.
- `tests/Feature/Invoicing/IssueInvoiceActionTest.php`:
  - 100 sequential issuances on the same branch produce 100 distinct serials.
- The same path is exercised by the bulk import flow in M4.
