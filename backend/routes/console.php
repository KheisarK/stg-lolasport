<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

Artisan::command('lola:admin {email} {--name=Pegawai Demo}', function () {
    $email = strtolower($this->argument('email'));
    if (User::where('email', $email)->exists()) {
        $this->error('Email sudah terdaftar. Tidak ada akun yang diubah.');

        return 1;
    }
    $password = $this->secret('Kata sandi baru (minimal 12 karakter; tidak ditampilkan)');
    $validation = Validator::make(['email' => $email, 'password' => $password, 'name' => $this->option('name')], [
        'email' => 'required|email|max:255', 'name' => 'required|string|max:100',
        'password' => ['required', 'string', Password::min(12)->letters()->numbers()],
    ]);
    if ($validation->fails()) {
        $this->error($validation->errors()->first());

        return 1;
    }
    $user = new User;
    $user->name = $this->option('name');
    $user->email = $email;
    $user->password = $password;
    $user->is_admin = true;
    $user->save();
    $this->info('Akun pegawai dibuat. Gunakan halaman /admin/login.');
})->purpose('Membuat akun pegawai tanpa password bawaan');
