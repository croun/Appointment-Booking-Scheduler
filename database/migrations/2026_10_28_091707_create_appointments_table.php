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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code', 32)->unique();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone');
            $table->text('customer_notes')->nullable();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->index();
            $table->string('timezone', 64)->default('UTC');
            $table->string('status', 32)->default('confirmed'); // pending, confirmed, completed, cancelled, no_show
            $table->text('cancellation_reason')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->string('recurring_group_id', 64)->nullable()->index();
            $table->boolean('reminder_sent')->default(false);
            $table->timestamps();

            $table->index(['provider_id', 'starts_at', 'ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
