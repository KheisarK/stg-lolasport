<?php

use App\Http\Middleware\ApiHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(ApiHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());
        $exceptions->render(function (PDOException $error, Request $request) {
            if ($request->is('api/*') && (str_contains($error->getMessage(), 'database is locked') || str_contains($error->getMessage(), 'database is busy'))) {
                return response()->json(['message' => 'Database sedang sibuk. Tunggu sebentar lalu coba lagi; gunakan kunci pembayaran yang sama.'], 503)->header('Retry-After', '2');
            }
        });
    })->create();
