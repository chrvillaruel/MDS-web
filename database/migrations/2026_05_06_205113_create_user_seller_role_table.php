<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_seller_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->string('role', 32);
            $table->json('permissions')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'seller_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_seller_role');
    }
};
