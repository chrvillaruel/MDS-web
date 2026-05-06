<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->string('code', 16);
            $table->string('name');
            $table->text('address');
            $table->unsignedBigInteger('current_serial')->default(0);
            $table->unsignedInteger('reset_counter')->default(0);
            $table->unsignedBigInteger('max_serial')->default(999999);
            $table->timestamps();

            $table->unique(['seller_id', 'code']);
            $table->index(['seller_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
