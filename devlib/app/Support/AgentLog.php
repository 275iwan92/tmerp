<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

// Agent Log devlib.test (user 2026-10-07): log pekerjaan (commit) agen Iwan dan Jo, bisa disaring. Sumber = git log repo tmerp;
// agen = trailer `Agent:` di pesan commit. Commit tanpa tanda = "Before tracking" (tidak ditebak dari hari).
final class AgentLog
{
    public const AGENTS = ['Iwan' => 'Agent Iwan', 'Jo' => 'Agent Jo', '' => 'Before tracking'];

    public const TONES = ['Agent Iwan' => 'info', 'Agent Jo' => 'success', 'Before tracking' => 'neutral'];

    public static function rows(): array
    {
        return Cache::remember('agentlog.rows', 60, function () {
            $out = shell_exec('git -C '.escapeshellarg(base_path('..')).' log -n 3000 --date=format:%Y-%m-%d --format=%x1e%h%x1f%ad%x1f%an%x1f%s%x1f%b 2>&1');
            $rows = [];
            foreach (array_filter(explode("\x1e", (string) $out)) as $r) {
                [$hash, $date, , $subject, $agent] = array_pad(explode("\x1f", $r), 5, '');
                // Tanda agen = baris `Agent: X` di badan pesan (blok trailer sering terpisah dari baris Co-Authored-By).
                $agent = preg_match('/^Agent:\s*(\w+)/mi', $agent, $m) ? ucfirst(strtolower($m[1])) : '';
                $rows[] = ['id' => $hash, 'job' => mb_substr($subject, 0, 220), 'agent' => self::AGENTS[$agent] ?? $agent, 'commit' => $hash,
                    'date' => $date, 'month' => substr($date, 0, 7)];
            }

            return $rows;
        });
    }
}
