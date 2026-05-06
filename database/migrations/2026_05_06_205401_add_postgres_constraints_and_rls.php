<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Postgres-only hardening: AGT monotonic CHECK, invoice ISSUED-state guards,
 * append-only trigger on invoice_events, and Row-Level Security policies.
 *
 * Skipped on SQLite/MySQL — those backends are dev/test only. Production
 * runs on Postgres 16 (Laravel Cloud, ap-southeast-1).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        // AGT monotonicity (PRD §9 FR-9.1.5): new value >= old value.
        DB::statement(<<<'SQL'
        CREATE OR REPLACE FUNCTION mds_assert_agt_monotonic()
        RETURNS trigger AS $$
        BEGIN
            IF NEW.accumulated_grand_total < OLD.accumulated_grand_total THEN
                RAISE EXCEPTION
                    'accumulated_grand_total cannot decrease (was %, attempted %)',
                    OLD.accumulated_grand_total, NEW.accumulated_grand_total;
            END IF;
            RETURN NEW;
        END;
        $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(<<<'SQL'
        CREATE TRIGGER sellers_agt_monotonic
        BEFORE UPDATE ON sellers
        FOR EACH ROW EXECUTE FUNCTION mds_assert_agt_monotonic();
        SQL);

        // Invoice ISSUED-state guard.
        DB::statement(<<<'SQL'
        ALTER TABLE invoices
        ADD CONSTRAINT invoices_issued_state_complete
        CHECK (
            status <> 'issued'
            OR (
                serial_number IS NOT NULL
                AND eis_unique_id IS NOT NULL
                AND canonical_payload IS NOT NULL
            )
        );
        SQL);

        // invoice_events is append-only at the database layer.
        DB::statement(<<<'SQL'
        CREATE OR REPLACE FUNCTION mds_block_invoice_event_mutation()
        RETURNS trigger AS $$
        BEGIN
            RAISE EXCEPTION 'invoice_events is append-only';
        END;
        $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(<<<'SQL'
        CREATE TRIGGER invoice_events_no_update
        BEFORE UPDATE ON invoice_events
        FOR EACH ROW EXECUTE FUNCTION mds_block_invoice_event_mutation();
        SQL);

        DB::statement(<<<'SQL'
        CREATE TRIGGER invoice_events_no_delete
        BEFORE DELETE ON invoice_events
        FOR EACH ROW EXECUTE FUNCTION mds_block_invoice_event_mutation();
        SQL);

        // Row-Level Security: app sets `mds.current_seller_id` per request.
        $tenantTables = [
            'sellers', 'branches', 'buyers', 'items', 'invoices', 'invoice_lines',
            'invoice_events', 'buyer_link_tokens', 'bulk_import_batches',
            'bulk_import_rows', 'usage_events',
        ];

        foreach ($tenantTables as $table) {
            $column = $table === 'sellers' ? 'id' : 'seller_id';

            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement(<<<SQL
            CREATE POLICY {$table}_seller_isolation ON {$table}
            USING (
                current_setting('mds.current_seller_id', true) = ''
                OR {$column} = current_setting('mds.current_seller_id', true)::bigint
            )
            WITH CHECK (
                current_setting('mds.current_seller_id', true) = ''
                OR {$column} = current_setting('mds.current_seller_id', true)::bigint
            );
            SQL);
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        $tenantTables = [
            'sellers', 'branches', 'buyers', 'items', 'invoices', 'invoice_lines',
            'invoice_events', 'buyer_link_tokens', 'bulk_import_batches',
            'bulk_import_rows', 'usage_events',
        ];

        foreach ($tenantTables as $table) {
            DB::statement("DROP POLICY IF EXISTS {$table}_seller_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }

        DB::statement('DROP TRIGGER IF EXISTS invoice_events_no_update ON invoice_events');
        DB::statement('DROP TRIGGER IF EXISTS invoice_events_no_delete ON invoice_events');
        DB::statement('DROP FUNCTION IF EXISTS mds_block_invoice_event_mutation()');
        DB::statement('ALTER TABLE invoices DROP CONSTRAINT IF EXISTS invoices_issued_state_complete');
        DB::statement('DROP TRIGGER IF EXISTS sellers_agt_monotonic ON sellers');
        DB::statement('DROP FUNCTION IF EXISTS mds_assert_agt_monotonic()');
    }
};
