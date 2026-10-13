<?php

/*
 * REMARK
 * Tujuan       : Mencatat satu baris log untuk setiap aksi di layar Production Output: kapan, siapa, dari host mana, aksi, nilai sebelum -> sesudah.
 * Sumber hulu  : Dipanggil OperatorTimerService di dalam transaksi aksi yang sama; host dari request (IP + reverse DNS).
 * Input        : Operasi WO, kode aksi, detail {before, after}, login pelaku.
 * Output       : Satu baris prd_output_action_log (tanpa ubah/hapus).
 * Dipakai oleh : OperatorTimerService::act / saveResult; dibaca ProductionOutput untuk status Stop.
 * Status       : Aktif di mockup (ddt*).
 */

declare(strict_types=1);

namespace App\Services\Scheduling\Operator;

use App\Models\Prd\PrdOutputActionLog;
use App\Models\Prd\PrdWoOperation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

final class OutputActionLog
{
    /** Actions that lock a timer (Stop): once logged, that timer accepts no more Play / Pause. */
    public const STOPS = ['SETUP_STOP', 'RUN_STOP'];

    /** @param array<string, mixed> $detail what changed: ['before' => ..., 'after' => ...] */
    public static function record(PrdWoOperation $op, string $action, array $detail, string $actor): void
    {
        $ip = request()->ip();
        PrdOutputActionLog::create([
            'operation_id' => $op->operation_id, 'machine_id' => $op->machine_id, 'action' => $action, 'detail' => $detail,
            'acted_by' => $actor, 'employee_id' => Auth::user()->employee_id ?? null, 'acted_at' => now(),
            'client_ip' => $ip, 'client_host' => self::host($ip), 'user_agent' => mb_substr((string) request()->userAgent(), 0, 255) ?: null,
        ]);
    }

    /** Host name of the client by reverse DNS, kept for a day per IP (PHP has no lookup timeout); the IP itself when it does not resolve. */
    private static function host(?string $ip): ?string
    {
        if (! $ip) {
            return null;
        }

        return Cache::remember('output-host:'.$ip, 86400, function () use ($ip) {
            $host = @gethostbyaddr($ip);

            return $host && $host !== $ip ? mb_substr($host, 0, 190) : $ip;
        });
    }
}
