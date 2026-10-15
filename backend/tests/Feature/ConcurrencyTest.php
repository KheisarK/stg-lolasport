<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrencyTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/lola-race-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
        touch($this->directory.'/shared.sqlite');
        config(['database.connections.sqlite.database' => $this->directory.'/shared.sqlite']);
        DB::purge('sqlite');
        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('db:seed', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        foreach (glob($this->directory.'/*') as $file) {
            unlink($file);
        }
        rmdir($this->directory);
        parent::tearDown();
    }

    private function input(): array
    {
        return ['court_id' => 1, 'date' => now('Asia/Jakarta')->addDay()->toDateString(), 'start_time' => '10:00', 'duration' => 120, 'customer_name' => 'Uji Bersamaan', 'contact' => '080000000001', 'payment_option' => 'dp'];
    }

    private function race(array $payloads): array
    {
        $processes = [];
        $files = [];
        foreach ($payloads as $index => $payload) {
            $file = $this->directory.'/request'.$index.'.json';
            file_put_contents($file, json_encode([...$payload, 'database' => $this->directory.'/shared.sqlite']));
            $files[] = $file;
            $process = new Process([PHP_BINARY, base_path('tests/Support/concurrent-worker.php'), $file, (string) $index], base_path());
            $process->setTimeout(20);
            $process->start();
            $processes[] = $process;
        }
        try {
            $deadline = microtime(true) + 10;
            while (! file_exists($files[0].'.ready0') || ! file_exists($files[1].'.ready1')) {
                if (microtime(true) > $deadline) {
                    $this->fail('Workers failed to reach start barrier: '.$processes[0]->getErrorOutput().$processes[1]->getErrorOutput());
                }
                usleep(10000);
            }
            foreach ($files as $file) {
                touch($file.'.go');
            }
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
                $results[] = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            }
            // Both workers entered the operation before either finished.
            $this->assertLessThan(min(array_column($results, 'finished')), max(array_column($results, 'started')));

            return $results;
        } finally {
            foreach ($processes as $process) {
                $process->stop();
            }
        }
    }

    public function test_write_lock_is_acquired_before_reads(): void
    {
        DB::beginTransaction();
        $other = new \PDO('sqlite:'.$this->directory.'/shared.sqlite');
        $other->exec('PRAGMA busy_timeout=1');
        try {
            try {
                $other->exec('BEGIN IMMEDIATE TRANSACTION');
                $other->exec('ROLLBACK');
                $this->fail('Another writer acquired a lock before the first transaction read anything.');
            } catch (\PDOException $error) {
                $this->assertStringContainsString('locked', $error->getMessage());
            }
        } finally {
            DB::rollBack();
        }
    }

    public function test_busy_database_returns_controlled_error_and_allows_later_retry(): void
    {
        config(['database.connections.sqlite.busy_timeout' => 1]);
        DB::purge('sqlite');
        $blocker = new \PDO('sqlite:'.$this->directory.'/shared.sqlite');
        $blocker->exec('BEGIN IMMEDIATE TRANSACTION');
        try {
            $this->postJson('/api/bookings', $this->input())->assertStatus(503)->assertHeader('Retry-After', '2')->assertJsonPath('message', 'Database sedang sibuk. Tunggu sebentar lalu coba lagi; gunakan kunci pembayaran yang sama.');
        } finally {
            $blocker->exec('ROLLBACK');
        }
        $this->assertDatabaseCount('bookings', 0);
        $this->postJson('/api/bookings', $this->input())->assertCreated();
    }

    public function test_two_processes_cannot_double_book_shared_sqlite(): void
    {
        $payload = ['mode' => 'booking', 'input' => $this->input()];
        $results = $this->race([$payload, $payload]);
        $statuses = array_column($results, 'status');
        sort($statuses);
        $this->assertSame([200, 409], $statuses);
        $this->assertDatabaseCount('bookings', 1);
        $this->assertSame('wal', DB::selectOne('PRAGMA journal_mode')->journal_mode);
    }

    public function test_two_processes_cannot_overpay_shared_sqlite(): void
    {
        $actor = User::factory()->create(['is_admin' => true])->id;
        $service = app(BookingService::class);
        $id = $service->create($this->input())['booking']['id'];
        $service->pay($id, ['kind' => 'dp', 'expected_amount' => 50000, 'method' => 'cash', 'idempotency_key' => (string) Str::uuid()], $actor);
        $base = ['mode' => 'payment', 'booking_id' => $id, 'actor' => $actor];
        $results = $this->race([
            [...$base, 'input' => ['kind' => 'settlement', 'expected_amount' => 210000, 'method' => 'cash', 'idempotency_key' => (string) Str::uuid()]],
            [...$base, 'input' => ['kind' => 'settlement', 'expected_amount' => 210000, 'method' => 'qris', 'idempotency_key' => (string) Str::uuid()]],
        ]);
        $statuses = array_column($results, 'status');
        sort($statuses);
        $this->assertSame([200, 409], $statuses);
        $this->assertSame(260000, (int) DB::table('payments')->sum('amount'));
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_simultaneous_identical_payment_is_recorded_once(): void
    {
        $actor = User::factory()->create(['is_admin' => true])->id;
        $id = app(BookingService::class)->create($this->input())['booking']['id'];
        $payload = ['mode' => 'payment', 'booking_id' => $id, 'actor' => $actor, 'input' => ['kind' => 'dp', 'expected_amount' => 50000, 'method' => 'cash', 'idempotency_key' => (string) Str::uuid()]];
        $results = $this->race([$payload, $payload]);
        $this->assertSame([200, 200], array_column($results, 'status'));
        $this->assertEqualsCanonicalizing([false, true], array_column($results, 'replayed'));
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_two_staff_start_requests_record_one_actor_and_timestamp(): void
    {
        $first = User::factory()->create(['is_admin' => true])->id;
        $second = User::factory()->create(['is_admin' => true])->id;
        $service = app(BookingService::class);
        $booking = $service->create($this->input())['booking'];
        $service->pay($booking['id'], ['kind' => 'full', 'expected_amount' => 260000, 'method' => 'cash', 'idempotency_key' => (string) Str::uuid()], $first);
        $base = ['mode' => 'start', 'booking_id' => $booking['id'], 'now' => $booking['starts_at']];
        $results = $this->race([[...$base, 'actor' => $first], [...$base, 'actor' => $second]]);
        $this->assertSame([200, 200], array_column($results, 'status'));
        $this->assertEqualsCanonicalizing([true, false], array_column($results, 'replayed'));
        $row = DB::table('bookings')->find($booking['id']);
        $this->assertSame($booking['starts_at'], CarbonImmutable::parse($row->started_at, 'UTC')->toIso8601String());
        $this->assertContains($row->started_by, [$first, $second]);
        $this->assertDatabaseCount('bookings', 1);
    }
}
