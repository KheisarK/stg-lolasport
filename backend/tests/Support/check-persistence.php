<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$path = realpath(config('database.connections.sqlite.database')) ?: '';
$directory = dirname($path);
if (dirname($directory) !== realpath(sys_get_temp_dir()) || ! str_starts_with(basename($directory), 'lola-e2e-')) {
    throw new RuntimeException('Persistence check only accepts isolated test data.');
}
if (DB::table('bookings')->count() !== 8 || DB::table('payments')->count() !== 4 || (int) DB::table('payments')->sum('amount') !== 260000 || DB::table('bookings')->where('status', 'cancelled')->count() !== 2 || DB::table('bookings')->whereNotNull('started_at')->count() !== 2) {
    throw new RuntimeException('Unexpected persisted booking/payment records.');
}
echo "Persisted data verified: 8 bookings, 4 payments, 2 play starts, Rp260.000 demo receipts.\n";
