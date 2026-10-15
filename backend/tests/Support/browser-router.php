<?php

use App\Support\BusinessClock;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

// Test-only HTTP entry point. Production/public/index.php never loads this file.
// A private clock file lets browser tests cross the booking start time without
// changing the laptop clock, waiting an hour, or adding a clock-setting API.
$directory = realpath(dirname(getenv('LOLA_E2E_FIXTURE') ?: '')) ?: '';
if (PHP_SAPI !== 'cli-server' || dirname($directory) !== realpath(sys_get_temp_dir()) || ! str_starts_with(basename($directory), 'lola-e2e-') || realpath(getenv('DB_DATABASE') ?: '') !== realpath($directory.'/test.sqlite')) {
    throw new RuntimeException('Browser router requires an isolated temporary test database.');
}
require_once __DIR__.'/../../vendor/autoload.php';
$clock = json_decode(file_get_contents($directory.'/clock.json'), true, 512, JSON_THROW_ON_ERROR);
$app = require __DIR__.'/../../bootstrap/app.php';
$app->instance(BusinessClock::class, new class(CarbonImmutable::parse($clock['now'])->utc()) extends BusinessClock
{
    public function __construct(private CarbonImmutable $time) {}

    public function now(): CarbonImmutable
    {
        return $this->time;
    }
});
$app->handleRequest(Request::capture());
