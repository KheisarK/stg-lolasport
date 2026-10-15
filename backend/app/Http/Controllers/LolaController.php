<?php

namespace App\Http\Controllers;

use App\Services\BookingService;
use App\Support\BusinessClock;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LolaController extends Controller
{
    public function __construct(private BookingService $bookings, private BusinessClock $clock) {}

    public function courts()
    {
        return response()->json(['courts' => DB::table('courts')->get(), 'rules' => config('lola'), 'server_now' => $this->clock->now()->toIso8601String(), 'demo' => true]);
    }

    public function availability(Request $request)
    {
        $data = $request->validate(['date' => 'required|date_format:Y-m-d', 'court_id' => 'required|integer|exists:courts,id']);
        $start = CarbonImmutable::parse($data['date'], config('lola.timezone'))->startOfDay()->utc();
        $busy = $this->bookings->active()->where('court_id', $data['court_id'])->where('starts_at', '<', $start->addDay()->format('Y-m-d H:i:s'))->where('ends_at', '>', $start->format('Y-m-d H:i:s'))->get(['starts_at', 'ends_at']);

        return response()->json(['busy' => $busy->map(fn ($slot) => [
            'starts_at' => CarbonImmutable::parse($slot->starts_at, 'UTC')->toIso8601String(),
            'ends_at' => CarbonImmutable::parse($slot->ends_at, 'UTC')->toIso8601String(),
        ])]);
    }

    public function create(Request $request)
    {
        return response()->json($this->bookings->create($request->all()), 201);
    }

    public function customer(Request $request)
    {
        $token = $request->bearerToken() ?? '';
        abort_unless(preg_match('/^[a-f0-9]{64}$/', $token), 404, 'Tautan booking tidak valid.');
        $id = DB::table('bookings')->where('token_hash', hash('sha256', $token))->value('id');
        abort_unless($id, 404, 'Tautan booking tidak valid.');
        $booking = $this->bookings->detail($id);
        // Internal actor identifiers are not needed by customers.
        unset($booking['created_by'], $booking['cancelled_by'], $booking['started_by']);
        $booking['payments'] = array_map(function ($payment) {
            unset($payment->recorded_by);

            return $payment;
        }, $booking['payments']);

        return response()->json(['booking' => $booking]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => 'required|email', 'password' => 'required|string|max:200']);
        if (! Auth::attempt([...$credentials, 'is_admin' => true])) {
            throw ValidationException::withMessages(['email' => 'Email atau kata sandi tidak sesuai.']);
        }
        $request->session()->regenerate();

        return response()->json(['user' => $request->user()->only('name', 'email')]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Berhasil keluar.']);
    }

    public function schedule(Request $request)
    {
        $data = $request->validate(['date' => 'required|date_format:Y-m-d', 'court_id' => 'nullable|integer|exists:courts,id']);
        $start = CarbonImmutable::parse($data['date'], config('lola.timezone'))->startOfDay()->utc();
        $query = DB::table('bookings')->where('starts_at', '>=', $start->format('Y-m-d H:i:s'))->where('starts_at', '<', $start->addDay()->format('Y-m-d H:i:s'));
        if (! empty($data['court_id'])) {
            $query->where('court_id', $data['court_id']);
        }

        return response()->json(['bookings' => $query->orderBy('starts_at')->pluck('id')->map(fn ($id) => $this->bookings->detail($id))]);
    }

    public function adminCreate(Request $request)
    {
        return response()->json($this->bookings->create($request->all(), $request->user()->id), 201);
    }

    public function payment(Request $request, int $id)
    {
        return response()->json($this->bookings->pay($id, $request->all(), $request->user()->id));
    }

    public function cancel(Request $request, int $id)
    {
        return response()->json(['booking' => $this->bookings->cancel($id, $request->user()->id)]);
    }

    public function start(Request $request, int $id)
    {
        return response()->json($this->bookings->start($id, $request->user()->id));
    }

    public function report(Request $request)
    {
        $data = $request->validate(['period' => 'required|in:day,month', 'date' => 'required|date_format:Y-m-d']);
        $start = CarbonImmutable::parse($data['date'], config('lola.timezone'))->startOfDay();
        if ($data['period'] === 'month') {
            $start = $start->startOfMonth();
        }
        $end = $data['period'] === 'month' ? $start->addMonth() : $start->addDay();
        $payments = DB::table('payments')->join('bookings', 'bookings.id', '=', 'payments.booking_id')
            ->where('paid_at', '>=', $start->utc()->format('Y-m-d H:i:s'))
            ->where('paid_at', '<', $end->utc()->format('Y-m-d H:i:s'))
            ->orderBy('paid_at')->get(['payments.id', 'bookings.reference', 'payments.amount', 'payments.method', 'payments.paid_at', 'payments.recorded_by']);
        $payments->each(fn ($payment) => $payment->paid_at = CarbonImmutable::parse($payment->paid_at, 'UTC')->toIso8601String());

        return response()->json(['period' => $data['period'], 'from' => $start->toIso8601String(), 'until_exclusive' => $end->toIso8601String(), 'timezone' => config('lola.timezone'), 'total' => (int) $payments->sum('amount'), 'count' => $payments->count(), 'payments' => $payments]);
    }
}
