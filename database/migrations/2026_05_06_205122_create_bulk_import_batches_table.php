<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('platform', 32);
            $table->string('mode', 16);
            $table->string('status', 32)->default('uploaded');
            $table->string('original_filename');
            $table->string('storage_path');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->unsignedInteger('duplicate_rows')->default(0);
            $table->json('summary')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['seller_id', 'created_at']);
            $table->index(['seller_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_import_batches');
    }
};
