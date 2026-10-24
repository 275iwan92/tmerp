<?php

/*
 * REMARK
 * Tujuan       : SEMENTARA UAT. Menyalin password lama pegawai (tm2old.tm3sec_users.pswd, teks biasa) menjadi password bawaan di ddtsys.sec_user, dicocokkan lewat NAMA (keputusan Jo 2026-10-24). Hanya kecocokan persis dan tunggal diterapkan; yang meragukan hanya didaftar.
 * Sumber hulu  : tm2old.tm3sec_users (HANYA SELECT), ddtsys.sec_user (via koneksi tm2sys, wajib berawalan ddt).
 * Input        : --apply (tanpa itu = dry-run: hanya hitung dan daftar). Selalu dengan --env=mockup.
 * Output       : Angka, daftar NAMA (tanpa password) yang ragu / tanpa pasangan / sumber kosong / default_password NULL; saat --apply: sec_user.default_password := password lama (polos), pswd := Hash::make(itu), must_change_password := 0, pswd_last_updated := now. Aman dijalankan ulang (baris yang sudah sesuai dilewati). Jejak (siapa, kapan, berapa baris) ke saluran log password_audit.
 * Dipakai oleh : php artisan security:import-old-passwords [--apply] --env=mockup
 * Status       : Aktif, SEMENTARA UAT. Password lama pegawai kini tersimpan POLOS sebagai default: pasca-UAT enkripsi ulang, hapus layar Default Passwords, dan minta semua pegawai membuat password baru (docs/backlog09_platform_facility.md). Perintah ini dibuang bersama layar itu.
 */

namespace App\Console\Commands;

use App\Services\Security\LegacyNameMatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class ImportOldPasswords extends Command
{
    protected $signature = 'security:import-old-passwords {--apply : tulis ke ddtsys.sec_user (tanpa ini: dry-run)}';

    protected $description = 'UAT: salin password lama (tm2old.tm3sec_users) ke password bawaan ddtsys.sec_user, cocok lewat nama';

    public function handle(): int
    {
        $db = DB::connection('tm2sys');
        $dbName = $db->getDatabaseName();
        if (! str_starts_with((string) $dbName, 'ddt')) {
            $this->error("Ditolak: koneksi tm2sys memakai database '$dbName', bukan ddt*. Jalankan dengan --env=mockup.");

            return self::FAILURE;
        }

        $src = DB::connection('tm2old')->table('tm3sec_users')->get(['login', 'name', 'pswd'])->values();
        $tgt = $db->table('sec_user')->get(['id_user', 'login', 'name', 'default_password', 'pswd', 'must_change_password'])->keyBy('id_user');

        $r = LegacyNameMatcher::match($tgt->pluck('name', 'id_user')->all(), $src->pluck('name')->all());
        $nameOf = fn ($tid) => $tgt[$tid]->name;
        $srcName = fn ($sid) => $src[$sid]->name;

        $emptySrc = $todo = [];
        $already = 0;
        $trimmed = 0;
        foreach ($r['exact'] as $tid => $sid) {
            $pw = $src[$sid]->pswd;
            if ($pw === null || trim($pw) === '') {
                $emptySrc[] = $tid;

                continue;
            }
            $trimmed += $pw !== trim($pw);
            $t = $tgt[$tid];
            if ($t->default_password === $pw && ! $t->must_change_password && $t->pswd && Hash::check($pw, $t->pswd)) {
                $already++;

                continue;
            }
            $todo[$tid] = $pw;
        }

        $this->line(sprintf('database tujuan: %s | mode: %s', $dbName, $this->option('apply') ? 'APPLY' : 'dry-run'));
        $this->line(sprintf('target(sec_user)=%d sumber(tm3sec_users)=%d cocok persis=%d (akan diubah=%d, sudah sesuai=%d, sumber kosong=%d) ragu=%d tanpa pasangan=%d; password sumber berspasi tepi=%d', $tgt->count(), $src->count(), count($r['exact']), count($todo), $already, count($emptySrc), count($r['doubtful']), count($r['none']), $trimmed));

        $this->section('Cocok persis tetapi password sumber NULL/kosong (tidak diubah)', array_map($nameOf, $emptySrc));
        $this->section('MERAGUKAN (tidak diterapkan): target <- kandidat sumber [alasan]', array_map(
            fn ($tid, $d) => $nameOf($tid).' <- '.implode(' | ', array_map($srcName, $d[0])).' ['.$d[1].']',
            array_keys($r['doubtful']), $r['doubtful']));
        $this->section('Tanpa pasangan di sumber (dibiarkan)', array_map($nameOf, $r['none']));

        $nullAfter = $tgt->filter(fn ($t, $tid) => ! isset($todo[$tid]) && ($t->default_password === null || $t->default_password === ''))->map(fn ($t) => $t->name.' ('.$t->login.')')->values()->all();
        $this->section('Pengguna target yang default_password-nya NULL setelah proses', $nullAfter);

        if (! $this->option('apply')) {
            $this->info('Dry-run: tidak ada yang ditulis. Tambahkan --apply untuk menulis.');

            return self::SUCCESS;
        }

        $db->transaction(function () use ($db, $todo) {
            foreach ($todo as $tid => $pw) {
                $db->table('sec_user')->where('id_user', $tid)->update([
                    'default_password' => $pw,
                    'pswd' => Hash::make($pw),
                    'must_change_password' => 0,
                    'pswd_last_updated' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $rows = $db->table('sec_user')->whereIn('id_user', array_keys($todo))->get(['id_user', 'default_password', 'pswd', 'must_change_password']);
        $ok = $rows->filter(fn ($t) => $t->default_password === $todo[$t->id_user] && Hash::check($todo[$t->id_user], $t->pswd) && ! $t->must_change_password)->count();
        $this->info(sprintf('Diubah=%d, terverifikasi (default_password sama, Hash::check benar, must_change=0)=%d', count($todo), $ok));
        Log::channel('password_audit')->info('default-passwords.import-old', ['actor' => 'artisan', 'database' => $dbName, 'changed' => count($todo), 'verified' => $ok, 'doubtful' => count($r['doubtful']), 'unmatched' => count($r['none'])]);

        return $ok === count($todo) ? self::SUCCESS : self::FAILURE;
    }

    private function section(string $title, array $lines): void
    {
        $this->newLine();
        $this->line($title.': '.count($lines));
        foreach ($lines as $l) {
            $this->line('  - '.$l);
        }
    }
}
