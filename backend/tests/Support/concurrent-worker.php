<?php

use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
[$script, $payloadPath, $worker] = $argv;
$payload = json_decode(file_get_contents($payloadPath), true, 512, JSON_THROW_ON_ERROR);
config(['database.connections.sqlite.database' => $payload['database']]);
DB::purge('sqlite');
if (isset($payload['now'])) {
    CarbonImmutable::setTestNow(CarbonImmutable::parse($payload['now']));
}
file_put_contents($payloadPath.'.ready'.$worker, 'ready');
$deadline = microtime(true) + 15;
while (! file_exists($payloadPath.'.go')) {
    if (microtime(true) > $deadline) {
        exit(2);
    }
    usleep(10000);
}
$start = microtime(true);
try {
    // Hold the first acquired transaction briefly to exercise overlapping writers.
    $result = DB::transaction(function () use ($payload) {
        usleep(250000);
        $service = app(BookingService::class);

        return match ($payload['mode']) {
            'booking' => $service->create($payload['input']),
            'start' => $service->start($payload['booking_id'], $payload['actor']),
            default => $service->pay($payload['booking_id'], $payload['input'], $payload['actor']),
        };
    }, 3);
    $output = ['status' => 200, 'replayed' => $result['replayed'] ?? null];
} catch (ValidationException $e) {
    $output = ['status' => 422];
} catch (HttpException $e) {
    $output = ['status' => $e->getStatusCode()];
} catch (Throwable $e) {
    $output = ['status' => 500, 'error_class' => get_class($e)];
}
echo json_encode([...$output, 'started' => $start, 'finished' => microtime(true)]);
