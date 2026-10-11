<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_command_uses_hidden_input_hashes_password_and_preserves_existing_account(): void
    {
        $password = 'Demo-'.bin2hex(random_bytes(12)).'9';
        $this->artisan('lola:admin', ['email' => 'pegawai@example.test', '--name' => 'Pegawai Fiktif'])
            ->expectsQuestion('Kata sandi baru (minimal 12 karakter; tidak ditampilkan)', $password)
            ->expectsOutput('Akun pegawai dibuat. Gunakan halaman /admin/login.')
            ->assertExitCode(0);
        $user = User::firstOrFail();
        $this->assertTrue($user->is_admin);
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertNotEquals($password, $user->password);
        $this->artisan('lola:admin', ['email' => 'pegawai@example.test'])
            ->expectsOutput('Email sudah terdaftar. Tidak ada akun yang diubah.')
            ->assertExitCode(1);
        $this->assertDatabaseCount('users', 1);
    }
}
