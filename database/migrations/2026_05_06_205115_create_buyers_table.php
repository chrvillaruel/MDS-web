<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->string('tin', 9)->nullable();
            $table->string('registered_name')->nullable();
            $table->string('business_style')->nullable();
            $table->text('address')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();

            $table->index(['seller_id', 'tin']);
            $table->index(['seller_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyers');
    }
};
