# MDS — Phase 1 Entity Relationship Diagram

```mermaid
erDiagram
    USERS ||--o{ USER_SELLER_ROLE : "memberships"
    USERS }o--|| SELLERS : "current seller"
    SELLERS ||--o{ USER_SELLER_ROLE : "members"
    SELLERS ||--o{ BRANCHES : "has"
    SELLERS ||--o{ BUYERS : "has"
    SELLERS ||--o{ ITEMS : "has"
    SELLERS ||--o{ INVOICES : "issues"
    SELLERS ||--o{ BULK_IMPORT_BATCHES : "uploads"
    SELLERS ||--o{ USAGE_EVENTS : "billing"
    BRANCHES ||--o{ INVOICES : "issues"
    BUYERS ||--o{ INVOICES : "receives"
    INVOICES ||--o{ INVOICE_LINES : "has"
    INVOICES ||--o{ INVOICE_EVENTS : "audit trail (append-only)"
    INVOICES ||--o{ BUYER_LINK_TOKENS : "self-service links"
    BULK_IMPORT_BATCHES ||--o{ BULK_IMPORT_ROWS : "rows"
    BULK_IMPORT_ROWS }o--|| INVOICES : "produces"

    SELLERS {
        bigint id PK
        string registered_name
        string business_style
        string tin
        string branch_code
        text address
        string vat_status
        bigint accumulated_grand_total "monotonic CHECK on pgsql"
        uint accumulated_grand_total_resets
        timestamp setup_completed_at
    }

    BRANCHES {
        bigint id PK
        bigint seller_id FK
        string code
        string name
        text address
        ubigint current_serial
        uint reset_counter
        ubigint max_serial
    }

    INVOICES {
        bigint id PK
        bigint seller_id FK
        bigint branch_id FK
        bigint buyer_id FK
        string status "draft|issued|voided|replaced"
        ubigint serial_number
        uint reset_counter
        string eis_unique_id
        json canonical_payload
        decimal subtotal
        decimal vat_amount
        decimal total_amount
        timestamp issued_at
        string source "manual|bulk|api"
        string source_reference
    }

    INVOICE_EVENTS {
        bigint id PK
        bigint invoice_id FK
        string event_type
        json payload
        bigint actor_user_id FK
        string actor_ip
        timestamp occurred_at
    }
```

## Invariants enforced at the database layer (Postgres only)

| Rule                                                                          | Mechanism                          |
| ----------------------------------------------------------------------------- | ---------------------------------- |
| `(seller_id, branch_id, serial_number, reset_counter)` UNIQUE on `invoices`   | Composite UNIQUE index             |
| `accumulated_grand_total` cannot decrease                                      | `BEFORE UPDATE` trigger            |
| ISSUED invoices must have `serial_number`, `eis_unique_id`, `canonical_payload` | CHECK constraint                   |
| `invoice_events` is append-only                                               | `BEFORE UPDATE/DELETE` triggers    |
| Rows are visible only for the active seller                                   | RLS policies + `mds.current_seller_id` |
