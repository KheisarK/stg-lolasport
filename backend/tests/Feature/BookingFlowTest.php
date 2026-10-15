<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-11 08:00', 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function input(array $overrides = []): array
    {
        return array_replace(['court_id' => 1, 'date' => '2026-10-12', 'start_time' => '10:00', 'duration' => 120, 'customer_name' => 'Pelanggan Fiktif', 'contact' => '080000000001', 'payment_option' => 'dp'], $overrides);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function create(array $overrides = []): array
    {
        return $this->postJson('/api/bookings', $this->input($overrides))->assertCreated()->json();
    }

    private function payment(int $amount, ?string $key = null, string $kind = 'dp'): array
    {
        return ['kind' => $kind, 'expected_amount' => $amount, 'method' => 'cash', 'idempotency_key' => $key ?? (string) Str::uuid()];
    }

    public function test_price_snapshot_status_and_private_token(): void
    {
        $result = $this->create(['total' => 1]);
        $this->assertSame(260000, $result['booking']['total']);
        $this->assertSame(50000, $result['booking']['initial_due']);
        $this->assertSame('pending', $result['booking']['status']);
        $this->assertSame('unpaid', $result['booking']['payment_status']);
        $this->assertSame('2026-10-12T03:00:00+00:00', $result['booking']['starts_at']);
        $this->assertSame(64, strlen($result['access_token']));
        $this->assertDatabaseMissing('bookings', ['token_hash' => $result['access_token']]);
        DB::table('courts')->where('id', 1)->update(['hourly_rate' => 1]);
        $this->withToken($result['access_token'])->getJson('/api/bookings/status')->assertOk()->assertJsonPath('booking.total', 260000)->assertJsonMissingPath('booking.token_hash');
        $this->withToken(str_repeat('0', 64))->getJson('/api/bookings/status')->assertNotFound();
        $this->getJson('/api/availability?date=2026-10-12&court_id=1')->assertOk()->assertJsonCount(1, 'busy')->assertDontSee('Pelanggan Fiktif')->assertDontSee('080000000001');
    }

    public function test_partial_contained_enclosing_overlaps_and_adjacency(): void
    {
        $this->create();
        foreach ([['09:00', 120], ['11:00', 120], ['10:00', 60], ['09:00', 180], ['10:00', 120]] as [$time, $duration]) {
            $this->postJson('/api/bookings', $this->input(['start_time' => $time, 'duration' => $duration]))->assertConflict();
        }
        $this->create(['start_time' => '12:00']);
        $this->create(['start_time' => '09:00', 'duration' => 60]);
        $this->create(['court_id' => 2]);
    }

    public function test_admin_and_public_share_conflict_rules(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->postJson('/api/admin/bookings', $this->input(['channel' => 'whatsapp']))->assertCreated()->assertJsonPath('booking.created_by', $admin->id);
        $this->postJson('/api/bookings', $this->input())->assertConflict();
        $this->create(['court_id' => 2]);
        $this->postJson('/api/admin/bookings', $this->input(['court_id' => 2, 'channel' => 'onsite']))->assertConflict();
        $this->postJson('/api/admin/bookings', $this->input(['court_id' => 3, 'channel' => 'onsite']))->assertCreated();
    }

    public function test_invalid_input_past_times_and_operating_hours(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        foreach ([['court_id' => 999], ['date' => '2026-02-30'], ['date' => '2026-10-10'], ['start_time' => '06:00'], ['start_time' => '23:00'], ['start_time' => '10:30'], ['duration' => 45], ['date' => '2027-10-11'], ['customer_name' => ''], ['contact' => 'abc'], ['payment_option' => 'free'], ['channel' => 'onsite']] as $override) {
            $this->postJson('/api/bookings', $this->input($override))->assertUnprocessable();
        }
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_admin_authentication_and_authorization(): void
    {
        foreach (['/api/admin/me', '/api/admin/bookings?date=2026-10-12', '/api/admin/reports?period=day&date=2026-10-11'] as $url) {
            $this->getJson($url)->assertUnauthorized();
        }
        foreach (['/api/admin/bookings', '/api/admin/bookings/1/payments', '/api/admin/bookings/1/cancel', '/api/admin/bookings/1/start', '/api/admin/logout'] as $url) {
            $this->postJson($url, [])->assertUnauthorized();
        }
        $this->actingAs(User::factory()->create(['is_admin' => false]));
        $this->getJson('/api/admin/me')->assertForbidden();
        $this->postJson('/api/admin/bookings', $this->input(['channel' => 'onsite']))->assertForbidden();
        $this->postJson('/api/admin/bookings/1/payments', $this->payment(10))->assertForbidden();
        $this->postJson('/api/admin/bookings/1/cancel')->assertForbidden();
        $this->postJson('/api/admin/bookings/1/start')->assertForbidden();
        $this->getJson('/api/admin/reports?period=day&date=2026-10-11')->assertForbidden();
    }

    public function test_dp_settlement_and_idempotency(): void
    {
        $id = $this->create()['booking']['id'];
        $admin = $this->admin();
        $this->actingAs($admin);
        $request = $this->payment(50000);
        $this->postJson("/api/admin/bookings/$id/payments", $request)->assertOk()->assertJsonPath('booking.remaining', 210000)->assertJsonPath('booking.payment_status', 'partial')->assertJsonPath('booking.status', 'confirmed');
        $this->postJson("/api/admin/bookings/$id/payments", $request)->assertOk()->assertJsonPath('replayed', true);
        $this->postJson("/api/admin/bookings/$id/payments", [...$request, 'expected_amount' => 50001])->assertConflict();
        $this->postJson("/api/admin/bookings/$id/payments", [...$request, 'method' => 'qris'])->assertConflict();
        $this->postJson("/api/admin/bookings/$id/payments", $this->payment(210000, null, 'settlement'))->assertOk()->assertJsonPath('booking.remaining', 0)->assertJsonPath('booking.payment_status', 'paid');
        $this->assertDatabaseCount('payments', 2);
        $this->assertSame(260000, (int) DB::table('payments')->sum('amount'));
        $this->assertDatabaseHas('payments', ['recorded_by' => $admin->id, 'amount' => 50000]);
    }

    public function test_full_option_is_not_payment_and_requires_full_initial_amount(): void
    {
        $id = $this->create(['payment_option' => 'full'])['booking']['id'];
        $this->actingAs($this->admin());
        $this->postJson("/api/admin/bookings/$id/payments", $this->payment(50000))->assertConflict();
        $this->postJson("/api/admin/bookings/$id/payments", $this->payment(260000, null, 'full'))->assertOk()->assertJsonPath('booking.payment_status', 'paid');
    }

    public function test_invalid_amounts_and_key_reuse_across_bookings(): void
    {
        $id = $this->create()['booking']['id'];
        $id2 = $this->create(['court_id' => 2])['booking']['id'];
        $this->actingAs($this->admin());
        foreach ([0, -1, 1.5] as $amount) {
            $this->postJson("/api/admin/bookings/$id/payments", ['kind' => 'dp', 'expected_amount' => $amount, 'method' => 'cash', 'idempotency_key' => (string) Str::uuid()])->assertUnprocessable();
        }
        $payment = $this->payment(50000);
        $this->postJson("/api/admin/bookings/$id/payments", $payment)->assertOk();
        $this->postJson("/api/admin/bookings/$id2/payments", $payment)->assertConflict();
        $this->postJson("/api/admin/bookings/$id/payments", $this->payment(210001, null, 'settlement'))->assertConflict();
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_expiry_without_scheduler_and_late_payment(): void
    {
        $first = $this->create();
        $id = $first['booking']['id'];
        CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes(20));
        $this->withToken($first['access_token'])->getJson('/api/bookings/status')->assertJsonPath('booking.status', 'expired');
        $this->create();
        $this->actingAs($this->admin())->postJson("/api/admin/bookings/$id/payments", $this->payment(50000))->assertConflict();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_cancellation_releases_unpaid_only_and_keeps_history(): void
    {
        $id = $this->create()['booking']['id'];
        $this->actingAs($this->admin());
        $this->postJson("/api/admin/bookings/$id/cancel")->assertOk()->assertJsonPath('booking.status', 'cancelled');
        $this->postJson("/api/admin/bookings/$id/payments", $this->payment(50000))->assertConflict();
        $new = $this->create()['booking']['id'];
        $this->postJson("/api/admin/bookings/$new/payments", $this->payment(50000))->assertOk();
        $this->postJson("/api/admin/bookings/$new/cancel")->assertConflict();
        $this->assertDatabaseCount('bookings', 2);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_report_payment_date_jakarta_day_and_month_boundaries(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-31 23:50', 'Asia/Jakarta'));
        $id = $this->create(['date' => '2026-11-01'])['booking']['id'];
        $this->actingAs($this->admin());
        foreach ([['2026-10-31 16:59:59', 50000, 'dp'], ['2026-10-31 17:00:00', 210000, 'settlement']] as [$at, $amount, $kind]) {
            CarbonImmutable::setTestNow(CarbonImmutable::parse($at, 'UTC'));
            $this->postJson("/api/admin/bookings/$id/payments", $this->payment($amount, null, $kind))->assertOk();
        }
        $this->getJson('/api/admin/reports?period=day&date=2026-10-31')->assertOk()->assertJsonPath('total', 50000)->assertJsonPath('count', 1);
        $this->getJson('/api/admin/reports?period=month&date=2026-11-01')->assertOk()->assertJsonPath('total', 210000)->assertJsonPath('count', 1);
        $this->getJson('/api/admin/reports?period=month&date=2026-10-01')->assertJsonPath('total', 50000);
        $this->getJson('/api/admin/reports?period=day&date=2026-11-02')->assertJsonPath('total', 0);
    }

    public function test_login_logout_and_throttling(): void
    {
        $password = bin2hex(random_bytes(12));
        User::factory()->create(['email' => 'staff@example.test', 'password' => $password, 'is_admin' => true]);
        $this->postJson('/api/login', ['email' => 'staff@example.test', 'password' => $password])->assertOk();
        $this->getJson('/api/admin/me')->assertOk();
        $this->postJson('/api/admin/logout')->assertOk();
        $this->getJson('/api/admin/me')->assertUnauthorized();
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/login', ['email' => 'staff@example.test', 'password' => 'incorrect'])->assertUnprocessable();
        }
        $this->postJson('/api/login', ['email' => 'staff@example.test', 'password' => 'incorrect'])->assertStatus(429);
    }

    public function test_repeatable_seed_preserves_tariffs_and_bookings(): void
    {
        $this->create();
        DB::table('courts')->where('id', 1)->update(['hourly_rate' => 160000]);
        $this->seed();
        $this->assertDatabaseCount('courts', 4);
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseHas('courts', ['id' => 1, 'hourly_rate' => 160000]);
    }
}
