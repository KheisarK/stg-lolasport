<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false);
        });
        Schema::create('courts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('sport');
            $table->unsignedInteger('hourly_rate');
        });
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('token_hash', 64)->unique();
            $table->foreignId('court_id')->constrained()->restrictOnDelete();
            $table->string('customer_name', 100);
            $table->string('contact', 30);
            $table->string('channel');
            $table->string('payment_option');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedInteger('hourly_rate');
            $table->unsignedInteger('total');
            $table->unsignedInteger('initial_due');
            $table->string('status')->default('pending');
            $table->dateTime('expires_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['court_id', 'starts_at', 'ends_at']);
        });
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->uuid('idempotency_key')->unique();
            $table->string('request_hash', 64);
            $table->unsignedInteger('amount');
            $table->string('method');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('paid_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('courts');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_admin'));
    }
};
