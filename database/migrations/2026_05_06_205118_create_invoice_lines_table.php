<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('line_number');
            $table->string('description');
            $table->decimal('quantity', 14, 4);
            $table->string('unit', 16)->default('pc');
            $table->decimal('unit_price', 14, 4);
            $table->decimal('line_total', 14, 4);
            $table->string('vat_classification', 32)->default('vatable');
            $table->timestamps();

            $table->unique(['invoice_id', 'line_number']);
            $table->index('seller_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
    }
};
