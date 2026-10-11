<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$directory = $argv[1] ?? '';
if (! str_starts_with(realpath($directory) ?: '', sys_get_temp_dir().'/lola-e2e-') || ! is_file($directory.'/test.sqlite') || filesize($directory.'/test.sqlite') !== 0) {
    throw new RuntimeException('Requires a new isolated lola-e2e temporary database.');
}
Artisan::call('migrate', ['--force' => true]);
Artisan::call('db:seed', ['--force' => true]);
$password = bin2hex(random_bytes(20));
$user = new User;
$user->name = 'Pegawai Uji';
$user->email = 'pegawai@example.test';
$user->password = $password;
$user->is_admin = true;
$user->save();
$file = $directory.'/credentials.json';
file_put_contents($file, json_encode(['email' => $user->email, 'password' => $password]));
chmod($file, 0600);
echo "Isolated browser fixture created.\n";
