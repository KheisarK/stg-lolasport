<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminOnly
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user(), 401, 'Silakan login sebagai pegawai.');
        abort_unless($request->user()->is_admin, 403, 'Akses ditolak untuk akun ini.');

        return $next($request);
    }
}
