<?php

namespace Chat\Http;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\NullSessionHandler;
use Symfony\Component\HttpFoundation\Response;

// Untuk permintaan latar yang berulang (cek pesan tiap beberapa detik, potongan upload): sesi dibaca untuk mengenali
// user, tetapi TIDAK ditulis balik. Tanpa ini (driver sesi yang tidak mengunci, mis. database) permintaan latar yang
// selesai belakangan menimpa isi sesi permintaan lain -- pesan flash / old input layar yang sedang disimpan bisa hilang --
// dan sesi tidak pernah kedaluwarsa selama tab terbuka. Berjalan SESUDAH auth (user sudah dibaca dari sesi).
class ReadOnlySession
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession()) {
            $request->session()->setHandler(new NullSessionHandler);
        }

        return $next($request);
    }
}
