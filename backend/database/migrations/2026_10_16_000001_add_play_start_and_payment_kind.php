<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dateTime('started_at')->nullable();
            $table->foreignId('started_by')->nullable()->constrained('users')->restrictOnDelete();
        });
        Schema::table('payments', fn (Blueprint $table) => $table->string('kind')->nullable());
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn('kind'));
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['started_by']);
            $table->dropColumn(['started_at', 'started_by']);
        });
    }
};
