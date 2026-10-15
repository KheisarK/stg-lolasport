<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LegacyUpgradeTest extends TestCase
{
    public function test_additive_migration_upgrades_a_populated_v1_file_without_repricing_history(): void
    {
        $directory = sys_get_temp_dir().'/lola-upgrade-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        touch($directory.'/legacy.sqlite');
        config(['database.connections.sqlite.database' => $directory.'/legacy.sqlite']);
        DB::purge('sqlite');
        try {
            foreach (['0001_01_01_000000_create_users_table.php', '2026_10_11_000001_create_lola_tables.php'] as $migration) {
                Artisan::call('migrate', ['--path' => 'database/migrations/'.$migration, '--force' => true]);
            }
            $actor = DB::table('users')->insertGetId(['name' => 'Uji Lama', 'email' => 'legacy@example.test', 'password' => password_hash(bin2hex(random_bytes(20)), PASSWORD_BCRYPT), 'is_admin' => true]);
            $court = DB::table('courts')->insertGetId(['name' => 'Futsal Sintetis', 'sport' => 'futsal', 'hourly_rate' => 150000]);
            $id = DB::table('bookings')->insertGetId(['reference' => 'LS-LEGACY-TEST', 'token_hash' => hash('sha256', random_bytes(32)), 'court_id' => $court, 'customer_name' => 'Riwayat Uji', 'contact' => '080000000001', 'channel' => 'web', 'payment_option' => 'dp', 'starts_at' => '2026-10-17 03:00:00', 'ends_at' => '2026-10-17 04:00:00', 'hourly_rate' => 150000, 'total' => 150000, 'initial_due' => 45000, 'status' => 'confirmed', 'expires_at' => '2026-10-16 02:20:00', 'created_at' => '2026-10-16 02:00:00', 'updated_at' => '2026-10-16 02:00:00']);
            DB::table('payments')->insert(['booking_id' => $id, 'idempotency_key' => (string) Str::uuid(), 'request_hash' => hash('sha256', 'legacy-test'), 'amount' => 45000, 'method' => 'cash', 'recorded_by' => $actor, 'paid_at' => '2026-10-16 02:01:00']);
            $before = (array) DB::table('bookings')->find($id);
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('db:seed', ['--force' => true]);
            $after = (array) DB::table('bookings')->find($id);
            $this->assertSame($before, array_intersect_key($after, $before));
            $this->assertNull($after['started_at']);
            $this->assertNull($after['started_by']);
            $this->assertDatabaseHas('payments', ['booking_id' => $id, 'amount' => 45000, 'kind' => null]);
            $this->assertDatabaseHas('courts', ['id' => $court, 'hourly_rate' => 150000]);
            $this->assertDatabaseCount('bookings', 1);
            $this->assertDatabaseCount('payments', 1);
        } finally {
            DB::disconnect('sqlite');
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }
}
