<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M2 additions: credit/debit linkage, void metadata, rendered PDF path,
 * the idempotency key the issuance API echoes back, and the payload schema
 * version the canonical_payload was built against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('supersedes_invoice_id')->nullable()->after('buyer_id')
                ->constrained('invoices');
            $table->string('vat_mode', 16)->default('inclusive')->after('document_type');
            $table->string('payload_schema_version', 16)->nullable()->after('canonical_payload');
            $table->string('idempotency_key', 64)->nullable()->after('source_reference');
            $table->string('pdf_path')->nullable()->after('idempotency_key');
            $table->timestamp('pdf_rendered_at')->nullable()->after('pdf_path');
            $table->text('void_reason')->nullable()->after('voided_at');
            $table->foreignId('voided_by_user_id')->nullable()->after('void_reason')
                ->constrained('users');

            $table->unique(['seller_id', 'idempotency_key'], 'invoices_idempotency_unique');
            $table->index('supersedes_invoice_id');
        });

        Schema::create('issuance_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->string('idempotency_key', 64);
            $table->string('payload_fingerprint', 64);
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('outcome', 32);
            $table->timestamps();

            $table->unique(['seller_id', 'idempotency_key']);
            $table->index(['seller_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issuance_attempts');

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropUnique('invoices_idempotency_unique');
            $table->dropIndex(['supersedes_invoice_id']);
            $table->dropConstrainedForeignId('voided_by_user_id');
            $table->dropColumn([
                'void_reason', 'pdf_rendered_at', 'pdf_path',
                'idempotency_key', 'payload_schema_version', 'vat_mode',
            ]);
            $table->dropConstrainedForeignId('supersedes_invoice_id');
        });
    }
};
