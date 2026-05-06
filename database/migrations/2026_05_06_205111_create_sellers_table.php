<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->string('registered_name');
            $table->string('business_style')->nullable();
            $table->string('tin', 9);
            $table->string('branch_code', 5)->default('00000');
            $table->text('address');
            $table->string('vat_status', 32);
            $table->string('bir_rdo_code', 3)->nullable();
            $table->string('accreditation_number')->nullable();
            $table->string('machine_identification_number')->nullable();
            $table->string('software_license_number')->nullable();
            $table->bigInteger('accumulated_grand_total')->default(0);
            $table->unsignedInteger('accumulated_grand_total_resets')->default(0);
            $table->timestamp('setup_completed_at')->nullable();
            $table->timestamps();

            $table->unique(['tin', 'branch_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sellers');
    }
};
