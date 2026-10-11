<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Insert only: rerunning a seed never resets an existing tariff or user.
        foreach ([['Futsal Sintetis', 'futsal', 150000], ['Futsal Vinil', 'futsal', 120000], ['Badminton 1', 'badminton', 50000], ['Badminton 2', 'badminton', 50000]] as [$name, $sport, $rate]) {
            if (! DB::table('courts')->where('name', $name)->exists()) {
                DB::table('courts')->insert(['name' => $name, 'sport' => $sport, 'hourly_rate' => $rate]);
            }
        }
    }
}
