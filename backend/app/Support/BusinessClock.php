<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class BusinessClock
{
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC');
    }
}
