<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only event log for invoices. UPDATE/DELETE permissions are revoked
 * at the Postgres level in a follow-up migration (production only).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 64);
            $table->json('payload');
            $table->foreignId('actor_user_id')->nullable()->constrained('users');
            $table->string('actor_ip', 45)->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['invoice_id', 'occurred_at']);
            $table->index(['seller_id', 'occurred_at']);
            $table->index(['seller_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_events');
    }
};
