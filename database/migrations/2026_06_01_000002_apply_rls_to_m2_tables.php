<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE issuance_attempts ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE issuance_attempts FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
        CREATE POLICY issuance_attempts_seller_isolation ON issuance_attempts
        USING (
            current_setting('mds.current_seller_id', true) = ''
            OR seller_id = current_setting('mds.current_seller_id', true)::bigint
        )
        WITH CHECK (
            current_setting('mds.current_seller_id', true) = ''
            OR seller_id = current_setting('mds.current_seller_id', true)::bigint
        );
        SQL);
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS issuance_attempts_seller_isolation ON issuance_attempts');
        DB::statement('ALTER TABLE issuance_attempts DISABLE ROW LEVEL SECURITY');
    }
};
