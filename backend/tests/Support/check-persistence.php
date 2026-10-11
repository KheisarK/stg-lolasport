<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$path = config('database.connections.sqlite.database');
if (! str_starts_with($path, sys_get_temp_dir().'/lola-e2e-')) {
    throw new RuntimeException('Persistence check only accepts isolated test data.');
}
if (DB::table('bookings')->count() !== 4 || DB::table('payments')->count() !== 4 || (int) DB::table('payments')->sum('amount') !== 300000 || DB::table('bookings')->where('status', 'cancelled')->count() !== 2) {
    throw new RuntimeException('Unexpected persisted booking/payment records.');
}
echo "Persisted data verified: 4 bookings, 4 payments, Rp300.000 demo receipts.\n";
