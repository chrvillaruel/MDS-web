<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bulk_import_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained();
            $table->unsignedInteger('row_number');
            $table->string('source_reference')->nullable();
            $table->string('status', 32)->default('pending');
            $table->json('raw_payload');
            $table->json('normalized_payload')->nullable();
            $table->json('errors')->nullable();
            $table->timestamps();

            $table->index(['bulk_import_batch_id', 'status']);
            $table->index(['seller_id', 'source_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_import_rows');
    }
};
