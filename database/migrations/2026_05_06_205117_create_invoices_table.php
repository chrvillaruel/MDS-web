<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->nullable()->constrained();
            $table->string('document_type', 32)->default('sales_invoice');
            $table->string('status', 32)->default('draft');
            $table->unsignedBigInteger('serial_number')->nullable();
            $table->unsignedInteger('reset_counter')->default(0);
            $table->string('eis_unique_id', 64)->nullable();
            $table->json('canonical_payload')->nullable();
            $table->decimal('subtotal', 14, 4)->default(0);
            $table->decimal('vat_amount', 14, 4)->default(0);
            $table->decimal('total_amount', 14, 4)->default(0);
            $table->string('currency', 3)->default('PHP');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('source', 32)->default('manual');
            $table->string('source_reference')->nullable();
            $table->timestamps();

            $table->unique(['seller_id', 'branch_id', 'serial_number', 'reset_counter'], 'invoices_serial_unique');
            $table->index(['seller_id', 'created_at']);
            $table->index(['seller_id', 'status']);
            $table->index(['seller_id', 'source_reference']);
            $table->index('eis_unique_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
