<?php

namespace Tests\Feature;

use App\Console\Commands\UpdatePrototypeTariffs;
use App\Models\User;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class TariffUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        foreach (config('lola_tariffs') as $name => $tariff) {
            DB::table('courts')->where('name', $name)->update(['hourly_rate' => $tariff['previous']]);
        }
    }

    public function test_preview_does_not_write_and_apply_requires_explicit_court_selection(): void
    {
        $before = DB::table('courts')->get()->toJson();
        $this->artisan('lola:rates-v2')->expectsOutputToContain('Preview saja')->assertSuccessful();
        $this->assertSame($before, DB::table('courts')->get()->toJson());
        $this->artisan('lola:rates-v2', ['--apply' => true])->assertFailed();
        $this->assertSame($before, DB::table('courts')->get()->toJson());
        $this->artisan('lola:rates-v2', ['--apply' => true, '--court' => ['1']])
            ->expectsConfirmation('Terapkan hanya tarif lama yang cocok pada ID terpilih?', 'no')->assertSuccessful();
        $this->assertSame($before, DB::table('courts')->get()->toJson());
    }

    public function test_apply_preserves_custom_rates_and_old_booking_and_payment_snapshots_and_can_repeat(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-16 09:00', 'Asia/Jakarta'));
        try {
            $actor = User::factory()->create(['is_admin' => true]);
            $id = app(BookingService::class)->create(['court_id' => 1, 'date' => '2026-10-17', 'start_time' => '10:00', 'duration' => 60, 'customer_name' => 'Riwayat Lama', 'contact' => '080000000001', 'payment_option' => 'dp'])['booking']['id'];
            // A legacy 30% snapshot and its actual recorded payment are immutable.
            DB::table('bookings')->where('id', $id)->update(['initial_due' => 45000]);
            app(BookingService::class)->pay($id, ['kind' => 'dp', 'expected_amount' => 45000, 'method' => 'cash', 'idempotency_key' => (string) Str::uuid()], $actor->id);
            $booking = (array) DB::table('bookings')->find($id);
            $payments = DB::table('payments')->get()->toJson();
            DB::table('courts')->where('id', 2)->update(['hourly_rate' => 175000]);
            $this->artisan('lola:rates-v2', ['--apply' => true, '--court' => ['1', '2', '3', '4']])
                ->expectsConfirmation('Terapkan hanya tarif lama yang cocok pada ID terpilih?', 'yes')->assertSuccessful();
            $this->assertDatabaseHas('courts', ['id' => 1, 'hourly_rate' => 130000]);
            $this->assertDatabaseHas('courts', ['id' => 2, 'hourly_rate' => 175000]);
            $this->assertDatabaseHas('courts', ['id' => 3, 'hourly_rate' => 100000]);
            $this->assertSame($booking, (array) DB::table('bookings')->find($id));
            $this->assertSame($payments, DB::table('payments')->get()->toJson());
            $courts = DB::table('courts')->get()->toJson();
            $this->artisan('lola:rates-v2', ['--apply' => true, '--court' => ['1', '2', '3', '4']])->assertSuccessful();
            $this->assertSame($courts, DB::table('courts')->get()->toJson());
            $this->assertSame(105000, app(BookingService::class)->detail($id)['remaining']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_tariff_change_after_preview_aborts_the_entire_plan(): void
    {
        $command = new class extends UpdatePrototypeTariffs
        {
            public function confirm($question, $default = false)
            {
                DB::table('courts')->where('id', 2)->update(['hourly_rate' => 177000]);

                return true;
            }
        };
        $command->setLaravel($this->app);
        $result = $command->run(new ArrayInput(['--apply' => true, '--court' => ['1', '2']]), new BufferedOutput);
        $this->assertSame(1, $result);
        $this->assertDatabaseHas('courts', ['id' => 1, 'hourly_rate' => 150000]);
        $this->assertDatabaseHas('courts', ['id' => 2, 'hourly_rate' => 177000]);
    }
}
