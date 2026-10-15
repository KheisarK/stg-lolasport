<?php

use App\Http\Controllers\LolaController;
use App\Http\Middleware\AdminOnly;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    Route::get('/health', function () {
        DB::table('courts')->count();

        return ['status' => 'ok', 'demo' => true];
    });
    Route::middleware('throttle:public-read')->group(function () {
        Route::get('/csrf', fn () => ['token' => csrf_token()]);
        Route::get('/courts', [LolaController::class, 'courts']);
        Route::get('/availability', [LolaController::class, 'availability']);
        Route::get('/bookings/status', [LolaController::class, 'customer']);
    });
    Route::post('/bookings', [LolaController::class, 'create'])->middleware('throttle:public-booking');
    Route::post('/login', [LolaController::class, 'login'])->middleware('throttle:login');
    Route::prefix('admin')->middleware([AdminOnly::class, 'throttle:admin'])->group(function () {
        Route::get('/me', fn (Request $request) => ['user' => $request->user()->only('name', 'email')]);
        Route::post('/logout', [LolaController::class, 'logout']);
        Route::get('/bookings', [LolaController::class, 'schedule']);
        Route::post('/bookings', [LolaController::class, 'adminCreate']);
        Route::post('/bookings/{id}/payments', [LolaController::class, 'payment'])->whereNumber('id');
        Route::post('/bookings/{id}/cancel', [LolaController::class, 'cancel'])->whereNumber('id');
        Route::post('/bookings/{id}/start', [LolaController::class, 'start'])->whereNumber('id');
        Route::get('/reports', [LolaController::class, 'report']);
    });
});
