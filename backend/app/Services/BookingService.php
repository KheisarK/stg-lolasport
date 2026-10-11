<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BookingService
{
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
        if ($start->minute !== 0 || $start->hour < config('lola.open_hour') || $end->gt($start->startOfDay()->addHours(config('lola.close_hour'))) || $start->lte(CarbonImmutable::now()) || $start->gt(CarbonImmutable::now(config('lola.timezone'))->addDays(config('lola.advance_days'))->endOfDay())) {
            throw ValidationException::withMessages(['start_time' => 'Pilih jam bulat di masa depan, dalam jam operasional dan batas 90 hari.']);
        }
        $startUtc = $start->utc()->format('Y-m-d H:i:s');
        $endUtc = $end->utc()->format('Y-m-d H:i:s');

        return DB::transaction(function () use ($data, $actor, $startUtc, $endUtc) {
            $now = CarbonImmutable::now('UTC');
            if (CarbonImmutable::parse($startUtc, 'UTC')->lte($now)) {
                throw ValidationException::withMessages(['start_time' => 'Waktu mulai sudah lewat. Pilih jadwal berikutnya.']);
            }
            // SQLite IMMEDIATE locks the writer before this read, not after it.
            $conflict = $this->active()->where('court_id', $data['court_id'])
                ->where('starts_at', '<', $endUtc)->where('ends_at', '>', $startUtc)->exists();
            abort_if($conflict, 409, 'Slot baru saja terisi. Pilih jadwal lain.');
            $court = DB::table('courts')->find($data['court_id']);
            $total = (int) $court->hourly_rate * intdiv((int) $data['duration'], 60);
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
                'initial_due' => $data['payment_option'] === 'full' ? $total : intdiv($total * config('lola.dp_percent') + 99, 100),
                'status' => 'pending', 'expires_at' => $now->addMinutes(config('lola.hold_minutes')),
                'created_at' => $now, 'updated_at' => $now,
            ]);

            return ['booking' => $this->detail($id), 'access_token' => $token];
        }, 3);
    }

    public function active()
    {
        return DB::table('bookings')->where(function ($query) {
            $query->where('status', 'confirmed')->orWhere(function ($pending) {
                $pending->where('status', 'pending')->where('expires_at', '>', CarbonImmutable::now('UTC')->format('Y-m-d H:i:s'));
            });
        });
    }

    public function effectiveStatus(object $booking): string
    {
        return $booking->status === 'pending' && CarbonImmutable::parse($booking->expires_at, 'UTC')->lte(CarbonImmutable::now('UTC')) ? 'expired' : $booking->status;
    }

    public function detail(int $id): array
    {
        // A consistent snapshot prevents status and payment totals from mixing two commits.
        if (DB::connection()->transactionLevel() === 0) {
            return DB::transaction(fn () => $this->detail($id), 3);
        }
        $booking = DB::table('bookings')->find($id);
        abort_unless($booking, 404, 'Booking tidak ditemukan.');
        $payments = DB::table('payments')->where('booking_id', $id)->orderBy('id')->get(['id', 'amount', 'method', 'paid_at', 'recorded_by']);
        $paid = (int) $payments->sum('amount');
        $result = (array) $booking;
        unset($result['token_hash']);
        $result['court_name'] = DB::table('courts')->where('id', $booking->court_id)->value('name');
        $result['status'] = $this->effectiveStatus($booking);
        $result['paid'] = $paid;
        $result['remaining'] = (int) $booking->total - $paid;
        $result['payment_status'] = $paid === 0 ? 'unpaid' : ($paid === (int) $booking->total ? 'paid' : 'partial');
        foreach (['starts_at', 'ends_at', 'expires_at', 'created_at', 'updated_at', 'cancelled_at'] as $field) {
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
            'amount' => ['required', 'integer', 'min:1', 'max:100000000'],
            'method' => ['required', Rule::in(['cash', 'transfer', 'qris'])],
            'idempotency_key' => ['required', 'uuid'],
        ])->validate();
        $hash = hash('sha256', json_encode([$id, (int) $data['amount'], $data['method']]));

        return DB::transaction(function () use ($id, $data, $hash, $actor) {
            $existing = DB::table('payments')->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                abort_unless(hash_equals($existing->request_hash, $hash), 409, 'Kunci pembayaran sudah dipakai untuk isi yang berbeda.');

                return ['booking' => $this->detail($id), 'payment_id' => $existing->id, 'replayed' => true];
            }
            $booking = $this->detail($id);
            abort_unless(in_array($booking['status'], ['pending', 'confirmed']), 409, 'Booking kedaluwarsa atau dibatalkan tidak dapat menerima pembayaran.');
            if ((int) $data['amount'] > $booking['remaining'] || ($booking['paid'] === 0 && (int) $data['amount'] < $booking['initial_due'])) {
                throw ValidationException::withMessages(['amount' => 'Nominal tidak boleh melebihi sisa tagihan; pembayaran pertama harus memenuhi minimum yang ditampilkan.']);
            }
            $now = CarbonImmutable::now('UTC');
            $paymentId = DB::table('payments')->insertGetId([
                'booking_id' => $id, 'amount' => (int) $data['amount'], 'method' => $data['method'],
                'idempotency_key' => $data['idempotency_key'], 'request_hash' => $hash,
                'recorded_by' => $actor, 'paid_at' => $now,
            ]);
            DB::table('bookings')->where('id', $id)->update(['status' => 'confirmed', 'updated_at' => $now]);

            return ['booking' => $this->detail($id), 'payment_id' => $paymentId, 'replayed' => false];
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
            DB::table('bookings')->where('id', $id)->update(['status' => 'cancelled', 'cancelled_by' => $actor, 'cancelled_at' => CarbonImmutable::now('UTC'), 'updated_at' => CarbonImmutable::now('UTC')]);

            return $this->detail($id);
        }, 3);
    }
}
