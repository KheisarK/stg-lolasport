<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Insert only: rerunning a seed never resets an existing tariff or user.
        foreach (config('lola_tariffs') as $name => $tariff) {
            if (! DB::table('courts')->where('name', $name)->exists()) {
                DB::table('courts')->insert(['name' => $name, 'sport' => $tariff['sport'], 'hourly_rate' => $tariff['hourly_rate']]);
            }
        }
    }
}
