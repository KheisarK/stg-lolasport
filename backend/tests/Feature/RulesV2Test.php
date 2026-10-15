<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\BusinessClock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RulesV2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-16 09:00:00', 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function input(array $changes = []): array
    {
        return array_replace(['court_id' => 1, 'date' => '2026-10-16', 'start_time' => '10:00', 'duration' => 60, 'customer_name' => 'Pemesan Demo', 'contact' => '080000000001', 'payment_option' => 'dp'], $changes);
    }

    private function pay(int $id, string $kind, int $expected)
    {
        return $this->postJson("/api/admin/bookings/$id/payments", ['kind' => $kind, 'expected_amount' => $expected, 'method' => 'cash', 'idempotency_key' => (string) Str::uuid()]);
    }

    public function test_new_tariffs_and_flat_dp_for_one_two_and_three_hours(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        foreach ([1 => 130000, 2 => 160000, 3 => 100000, 4 => 100000] as $court => $rate) {
            foreach ([1 => '10:00', 2 => '11:00', 3 => '13:00'] as $hours => $time) {
                $this->postJson('/api/bookings', $this->input(['court_id' => $court, 'start_time' => $time, 'duration' => 60 * $hours]))
                    ->assertCreated()->assertJsonPath('booking.hourly_rate', $rate)->assertJsonPath('booking.total', $rate * $hours)
                    ->assertJsonPath('booking.initial_due', 50000)->assertJsonPath('booking.paid', 0)->assertJsonPath('booking.payment_status', 'unpaid');
            }
        }
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_midnight_is_next_date_and_does_not_allow_spilling_past_close(): void
    {
        $this->postJson('/api/bookings', $this->input(['start_time' => '23:00']))->assertCreated()
            ->assertJsonPath('booking.starts_at', '2026-10-16T16:00:00+00:00')->assertJsonPath('booking.ends_at', '2026-10-16T17:00:00+00:00');
        $this->postJson('/api/bookings', $this->input(['start_time' => '23:00', 'duration' => 120]))->assertUnprocessable();
        $this->postJson('/api/bookings', $this->input(['start_time' => '22:00', 'duration' => 120]))->assertConflict();
        $this->postJson('/api/bookings', $this->input(['start_time' => '22:00']))->assertCreated();
        $this->postJson('/api/bookings', $this->input(['date' => '2026-10-17', 'start_time' => '00:00']))->assertUnprocessable();
        $this->getJson('/api/availability?date=2026-10-16&court_id=1')->assertJsonCount(2, 'busy');
        $this->getJson('/api/availability?date=2026-10-17&court_id=1')->assertJsonCount(0, 'busy');
    }

    public function test_exact_sixty_minutes_allows_dp_but_one_second_less_requires_full(): void
    {
        $this->postJson('/api/bookings', $this->input())->assertCreated()->assertJsonPath('booking.initial_due', 50000);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-16 09:00:01', 'Asia/Jakarta'));
        $this->postJson('/api/bookings', $this->input(['court_id' => 2]))->assertUnprocessable()->assertJsonValidationErrors('payment_option');
        $this->postJson('/api/bookings', $this->input(['court_id' => 2, 'payment_option' => 'full']))->assertCreated()->assertJsonPath('booking.initial_due', 160000);
    }

    public function test_onsite_is_not_automatically_full_for_a_future_booking(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->postJson('/api/admin/bookings', $this->input(['channel' => 'onsite']))->assertCreated()->assertJsonPath('booking.initial_due', 50000);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-16 09:55', 'Asia/Jakarta'));
        foreach (['onsite', 'whatsapp'] as $index => $channel) {
            $this->postJson('/api/admin/bookings', $this->input(['court_id' => 2 + $index, 'channel' => $channel]))->assertUnprocessable();
            $this->postJson('/api/admin/bookings', $this->input(['court_id' => 2 + $index, 'channel' => $channel, 'payment_option' => 'full']))->assertCreated()->assertJsonPath('booking.expires_at', '2026-10-16T03:00:00+00:00');
        }
    }

    public function test_hold_begins_on_save_and_is_capped_at_start_and_confirmed_never_expires(): void
    {
        $this->getJson('/api/courts')->assertOk();
        $this->assertDatabaseCount('bookings', 0);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-16 09:10', 'Asia/Jakarta'));
        $id = $this->postJson('/api/bookings', $this->input(['date' => '2026-10-17']))->assertCreated()
            ->assertJsonPath('booking.expires_at', '2026-10-16T02:30:00+00:00')->json('booking.id');
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->pay($id, 'dp', 50000)->assertOk();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-16 09:55', 'Asia/Jakarta'));
        $this->getJson('/api/admin/bookings?date=2026-10-17')->assertJsonPath('bookings.0.status', 'confirmed');
        $last = $this->postJson('/api/bookings', $this->input(['court_id' => 2, 'payment_option' => 'full']))->assertCreated()
            ->assertJsonPath('booking.expires_at', '2026-10-16T03:00:00+00:00')->json('booking.id');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-16 10:00', 'Asia/Jakarta'));
        $this->pay($last, 'full', 160000)->assertConflict();
    }

    public function test_server_prices_standard_payments_and_rejects_tampering_or_new_duplicate_dp(): void
    {
        $id = $this->postJson('/api/bookings', $this->input())->assertCreated()->json('booking.id');
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->pay($id, 'dp', 1)->assertConflict();
        $this->postJson("/api/admin/bookings/$id/payments", ['kind' => 'dp', 'expected_amount' => 50000, 'amount' => 999999, 'method' => 'cash', 'idempotency_key' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertDatabaseCount('payments', 0);
        $this->pay($id, 'dp', 50000)->assertOk()->assertJsonPath('booking.remaining', 80000)->assertJsonPath('booking.payment_choices.0.kind', 'settlement');
        $this->pay($id, 'dp', 50000)->assertConflict();
        $this->pay($id, 'full', 130000)->assertConflict();
        $this->pay($id, 'settlement', 130000)->assertConflict();
        $this->pay($id, 'settlement', 80000)->assertOk()->assertJsonPath('booking.remaining', 0)->assertJsonCount(0, 'booking.payment_choices');
        $this->assertSame(130000, (int) DB::table('payments')->sum('amount'));
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_full_payment_can_be_chosen_instead_of_initial_dp(): void
    {
        $id = $this->postJson('/api/bookings', $this->input())->assertCreated()->json('booking.id');
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->pay($id, 'full', 130000)->assertOk()->assertJsonPath('booking.remaining', 0);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_start_requires_full_payment_and_records_actor_once_without_inferring_attendance(): void
    {
        $result = $this->postJson('/api/bookings', $this->input())->assertCreated()->json();
        $id = $result['booking']['id'];
        $actor = User::factory()->create(['is_admin' => true]);
        $this->actingAs($actor);
        $this->pay($id, 'dp', 50000)->assertOk();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-16 10:00', 'Asia/Jakarta'));
        $this->getJson('/api/admin/bookings?date=2026-10-16')->assertJsonPath('bookings.0.started_at', null);
        $this->postJson("/api/admin/bookings/$id/start")->assertConflict()->assertJsonFragment(['message' => 'Tagihan harus lunas sebelum mulai bermain. Catat pelunasan sisa tagihan terlebih dahulu.']);
        $this->pay($id, 'settlement', 80000)->assertOk();
        $started = $this->postJson("/api/admin/bookings/$id/start")->assertOk()->assertJsonPath('replayed', false)->assertJsonPath('booking.started_by', $actor->id)->json('booking.started_at');
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        CarbonImmutable::setTestNow(CarbonImmutable::now()->addDay());
        $this->postJson("/api/admin/bookings/$id/start")->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('booking.started_at', $started)->assertJsonPath('booking.started_by', $actor->id);
        $this->withToken($result['access_token'])->getJson('/api/bookings/status')->assertOk()->assertJsonPath('booking.started_at', $started)->assertJsonMissingPath('booking.started_by');
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_start_refuses_future_ended_cancelled_expired_and_pending_reservations(): void
    {
        $id = $this->postJson('/api/bookings', $this->input())->assertCreated()->json('booking.id');
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->postJson("/api/admin/bookings/$id/start")->assertConflict();
        $this->pay($id, 'full', 130000)->assertOk();
        $this->postJson("/api/admin/bookings/$id/start")->assertConflict();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-16 11:00', 'Asia/Jakarta'));
        $this->postJson("/api/admin/bookings/$id/start")->assertConflict();
        $other = $this->postJson('/api/bookings', $this->input(['start_time' => '13:00']))->assertCreated()->json('booking.id');
        $this->postJson("/api/admin/bookings/$other/cancel")->assertOk();
        $this->postJson("/api/admin/bookings/$other/start")->assertConflict();
        $expired = $this->postJson('/api/bookings', $this->input(['court_id' => 2, 'start_time' => '13:00']))->assertCreated()->json('booking.id');
        CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes(20));
        $this->postJson("/api/admin/bookings/$expired/start")->assertConflict();
        $this->assertDatabaseMissing('bookings', ['id' => $id, 'started_by' => 1]);
    }

    public function test_custom_rate_below_dp_requires_full_instead_of_overcharging(): void
    {
        DB::table('courts')->where('id', 1)->update(['hourly_rate' => 40000]);
        $this->postJson('/api/bookings', $this->input())->assertUnprocessable();
        $this->postJson('/api/bookings', $this->input(['payment_option' => 'full']))->assertCreated()->assertJsonPath('booking.initial_due', 40000);
    }

    public function test_horizon_remains_ninety_days_including_dates_beyond_thirty(): void
    {
        foreach ([31, 90] as $days) {
            $date = CarbonImmutable::now('Asia/Jakarta')->addDays($days)->toDateString();
            $this->postJson('/api/bookings', $this->input(['date' => $date, 'start_time' => '23:00']))->assertCreated();
        }
        $date = CarbonImmutable::now('Asia/Jakarta')->addDays(91)->toDateString();
        $this->postJson('/api/bookings', $this->input(['date' => $date]))->assertUnprocessable();
        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_payment_cannot_cross_the_deadline_between_read_and_recording(): void
    {
        $id = $this->postJson('/api/bookings', $this->input())->assertCreated()->json('booking.id');
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->app->instance(BusinessClock::class, new class extends BusinessClock
        {
            private bool $sampled = false;

            public function now(): CarbonImmutable
            {
                $time = $this->sampled ? '2026-10-16 09:20:00' : '2026-10-16 09:19:59';
                $this->sampled = true;

                return CarbonImmutable::parse($time, 'Asia/Jakarta')->utc();
            }
        });
        $this->pay($id, 'dp', 50000)->assertConflict();
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('bookings', ['id' => $id, 'status' => 'pending']);
    }
}
