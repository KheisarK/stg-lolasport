<?php

namespace App\Services;

use App\Support\BusinessClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(private BusinessClock $clock) {}

    public function create(array $input, ?int $actor = null): array
    {
        $data = Validator::make($input, [
            'court_id' => ['required', 'integer', 'exists:courts,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration' => ['required', 'integer', Rule::in(config('lola.durations'))],
            'customer_name' => ['required', 'string', 'max:100'],
            'contact' => ['required', 'string', 'regex:/^[+0-9 ()-]{8,30}$/'],
            'payment_option' => ['required', Rule::in(['dp', 'full'])],
            'channel' => $actor ? ['required', Rule::in(['whatsapp', 'onsite'])] : ['prohibited'],
        ], [
            'contact.regex' => 'Isi kontak telepon yang valid (8–30 karakter).',
        ])->validate();
        $start = CarbonImmutable::createFromFormat('!Y-m-d H:i', $data['date'].' '.$data['start_time'], config('lola.timezone'));
        $end = $start->addMinutes((int) $data['duration']);
        if ($start->minute !== 0 || $start->hour < config('lola.open_hour') || $end->gt($start->startOfDay()->addHours(config('lola.close_hour'))) || $start->lte($this->clock->now()) || $start->gt($this->clock->now()->setTimezone(config('lola.timezone'))->addDays(config('lola.advance_days'))->endOfDay())) {
            throw ValidationException::withMessages(['start_time' => 'Pilih jam bulat di masa depan, dalam jam operasional dan batas 90 hari.']);
        }
        $startUtc = $start->utc()->format('Y-m-d H:i:s');
        $endUtc = $end->utc()->format('Y-m-d H:i:s');

        return DB::transaction(function () use ($data, $actor, $startUtc, $endUtc) {
            $now = $this->clock->now();
            if (CarbonImmutable::parse($startUtc, 'UTC')->lte($now)) {
                throw ValidationException::withMessages(['start_time' => 'Waktu mulai sudah lewat. Pilih jadwal berikutnya.']);
            }
            $start = CarbonImmutable::parse($startUtc, 'UTC');
            if ($data['payment_option'] === 'dp' && $start->lt($now->addMinutes(config('lola.full_payment_within_minutes')))) {
                throw ValidationException::withMessages(['payment_option' => 'Booking kurang dari 60 menit sebelum mulai wajib dibayar penuh. Pilih pembayaran penuh.']);
            }
            // SQLite IMMEDIATE locks the writer before this read, not after it.
            $conflict = $this->active()->where('court_id', $data['court_id'])
                ->where('starts_at', '<', $endUtc)->where('ends_at', '>', $startUtc)->exists();
            abort_if($conflict, 409, 'Slot baru saja terisi. Pilih jadwal lain.');
            $court = DB::table('courts')->find($data['court_id']);
            $total = (int) $court->hourly_rate * intdiv((int) $data['duration'], 60);
            if ($data['payment_option'] === 'dp' && $total <= config('lola.dp_amount')) {
                throw ValidationException::withMessages(['payment_option' => 'Tagihan tidak melebihi DP Rp50.000. Pilih pembayaran penuh.']);
            }
            $expires = $now->addMinutes(config('lola.hold_minutes'));
            if ($expires->gt($start)) {
                $expires = $start;
            }
            $token = bin2hex(random_bytes(32));
            $id = DB::table('bookings')->insertGetId([
                'reference' => 'LS-'.strtoupper(bin2hex(random_bytes(6))),
                'token_hash' => hash('sha256', $token),
                'court_id' => $court->id,
                'customer_name' => $data['customer_name'], 'contact' => $data['contact'],
                'channel' => $actor ? $data['channel'] : 'web', 'created_by' => $actor,
                'payment_option' => $data['payment_option'],
                'starts_at' => $startUtc, 'ends_at' => $endUtc,
                'hourly_rate' => $court->hourly_rate, 'total' => $total,
                'initial_due' => $data['payment_option'] === 'full' ? $total : config('lola.dp_amount'),
                'status' => 'pending', 'expires_at' => $expires,
                'created_at' => $now, 'updated_at' => $now,
            ]);

            return ['booking' => $this->detail($id), 'access_token' => $token];
        }, 3);
    }

    public function active()
    {
        return DB::table('bookings')->where(function ($query) {
            $query->where('status', 'confirmed')->orWhere(function ($pending) {
                $pending->where('status', 'pending')->where('expires_at', '>', $this->clock->now()->format('Y-m-d H:i:s'));
            });
        });
    }

    public function effectiveStatus(object $booking): string
    {
        return $booking->status === 'pending' && CarbonImmutable::parse($booking->expires_at, 'UTC')->lte($this->clock->now()) ? 'expired' : $booking->status;
    }

    public function detail(int $id): array
    {
        // A consistent snapshot prevents status and payment totals from mixing two commits.
        if (DB::connection()->transactionLevel() === 0) {
            return DB::transaction(fn () => $this->detail($id), 3);
        }
        $booking = DB::table('bookings')->find($id);
        abort_unless($booking, 404, 'Booking tidak ditemukan.');
        $payments = DB::table('payments')->where('booking_id', $id)->orderBy('id')->get(['id', 'kind', 'amount', 'method', 'paid_at', 'recorded_by']);
        $paid = (int) $payments->sum('amount');
        $result = (array) $booking;
        unset($result['token_hash']);
        $result['court_name'] = DB::table('courts')->where('id', $booking->court_id)->value('name');
        $result['status'] = $this->effectiveStatus($booking);
        $result['paid'] = $paid;
        $result['remaining'] = (int) $booking->total - $paid;
        $result['server_now'] = $this->clock->now()->toIso8601String();
        $result['payment_status'] = $paid === 0 ? 'unpaid' : ($paid === (int) $booking->total ? 'paid' : 'partial');
        $result['payment_choices'] = $this->paymentChoices($result);
        foreach (['starts_at', 'ends_at', 'expires_at', 'created_at', 'updated_at', 'cancelled_at', 'started_at'] as $field) {
            $result[$field] = $booking->$field ? CarbonImmutable::parse($booking->$field, 'UTC')->toIso8601String() : null;
        }
        $result['payments'] = $payments->map(function ($payment) {
            $payment->paid_at = CarbonImmutable::parse($payment->paid_at, 'UTC')->toIso8601String();

            return $payment;
        })->all();

        return $result;
    }

    public function pay(int $id, array $input, int $actor): array
    {
        $data = Validator::make($input, [
            'kind' => ['required', Rule::in(['dp', 'full', 'settlement'])],
            'expected_amount' => ['required', 'integer', 'min:1', 'max:100000000'],
            'amount' => ['prohibited'],
            'method' => ['required', Rule::in(['cash', 'transfer', 'qris'])],
            'idempotency_key' => ['required', 'uuid'],
        ])->validate();
        $hash = hash('sha256', json_encode([$id, $data['kind'], (int) $data['expected_amount'], $data['method']]));

        return DB::transaction(function () use ($id, $data, $hash, $actor) {
            $existing = DB::table('payments')->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                abort_unless(hash_equals($existing->request_hash, $hash), 409, 'Kunci pembayaran sudah dipakai untuk isi yang berbeda.');

                return ['booking' => $this->detail($id), 'payment_id' => $existing->id, 'replayed' => true];
            }
            $booking = $this->detail($id);
            abort_unless(in_array($booking['status'], ['pending', 'confirmed']), 409, 'Booking kedaluwarsa atau dibatalkan tidak dapat menerima pembayaran.');
            $choice = collect($booking['payment_choices'])->firstWhere('kind', $data['kind']);
            abort_unless($choice, 409, 'Pilihan pembayaran sudah tidak tersedia. Muat ulang rincian booking. Nominal lain belum didukung; cocokkan catatan bersama pengelola.');
            $amount = $choice['amount'];
            abort_unless($amount === (int) $data['expected_amount'], 409, 'Nominal konfirmasi tidak sesuai tagihan server. Muat ulang sebelum mencatat.');
            $now = $this->clock->now();
            $paymentId = DB::table('payments')->insertGetId([
                'booking_id' => $id, 'kind' => $data['kind'], 'amount' => $amount, 'method' => $data['method'],
                'idempotency_key' => $data['idempotency_key'], 'request_hash' => $hash,
                'recorded_by' => $actor, 'paid_at' => $now,
            ]);
            DB::table('bookings')->where('id', $id)->update(['status' => 'confirmed', 'updated_at' => $now]);

            return ['booking' => $this->detail($id), 'payment_id' => $paymentId, 'replayed' => false];
        }, 3);
    }

    private function paymentChoices(array $booking): array
    {
        if (! in_array($booking['status'], ['pending', 'confirmed']) || $booking['remaining'] <= 0) {
            return [];
        }
        if ($booking['paid'] > 0) {
            return [['kind' => 'settlement', 'label' => 'Pelunasan sisa tagihan', 'amount' => $booking['remaining']]];
        }
        $choices = [];
        if ($booking['payment_option'] === 'dp' && (int) $booking['initial_due'] < (int) $booking['total']) {
            // Preserve the initial due on older bookings; never recalculate it from new rules.
            $choices[] = ['kind' => 'dp', 'label' => 'Pembayaran awal DP', 'amount' => (int) $booking['initial_due']];
        }
        $choices[] = ['kind' => 'full', 'label' => 'Pembayaran penuh', 'amount' => (int) $booking['total']];

        return $choices;
    }

    public function start(int $id, int $actor): array
    {
        return DB::transaction(function () use ($id, $actor) {
            $booking = $this->detail($id);
            if ($booking['started_at']) {
                return ['booking' => $booking, 'replayed' => true];
            }
            abort_unless($booking['status'] === 'confirmed', 409, 'Booking belum terkonfirmasi atau sudah tidak sah.');
            abort_unless($booking['remaining'] === 0, 409, 'Tagihan harus lunas sebelum mulai bermain. Catat pelunasan sisa tagihan terlebih dahulu.');
            $now = $this->clock->now();
            abort_unless($now->gte(CarbonImmutable::parse($booking['starts_at'])) && $now->lt(CarbonImmutable::parse($booking['ends_at'])), 409, 'Mulai bermain hanya dapat dicatat selama rentang jadwal booking.');
            DB::table('bookings')->where('id', $id)->update(['started_at' => $now, 'started_by' => $actor, 'updated_at' => $now]);

            return ['booking' => $this->detail($id), 'replayed' => false];
        }, 3);
    }

    public function cancel(int $id, int $actor): array
    {
        return DB::transaction(function () use ($id, $actor) {
            $booking = $this->detail($id);
            if ($booking['status'] === 'cancelled') {
                return $booking;
            }
            abort_unless($booking['status'] === 'pending' && $booking['paid'] === 0, 409, 'Demo hanya membatalkan booking menunggu yang belum dibayar. Refund belum tersedia.');
            DB::table('bookings')->where('id', $id)->update(['status' => 'cancelled', 'cancelled_by' => $actor, 'cancelled_at' => $this->clock->now(), 'updated_at' => $this->clock->now()]);

            return $this->detail($id);
        }, 3);
    }
}
