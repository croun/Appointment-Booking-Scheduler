<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('waitlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained()->nullOnDelete();
            $table->date('requested_date')->index();
            $table->string('preferred_time', 32)->default('anytime'); // morning, afternoon, anytime
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone');
            $table->text('notes')->nullable();
            $table->string('status', 32)->default('waiting'); // waiting, notified, converted, cancelled
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waitlists');
    }
};
