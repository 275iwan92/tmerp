<?php

namespace App\Support;

// Demo of the production scheduling board (x-dl::gantt): fictitious rows / tasks plus a tiny "impact" judge.
// Hours are offsets from the origin date (Thu 8 Oct 2026, 00:00): 30 = Fri 06:00. In the application (tmerp) the same two
// endpoints are answered by the PPIC scheduling service; here the rules are hard-coded so the board can be tried alone.
class GanttDemo
{
    public const ORIGIN = '2026-10-08';

    public const LOCK_UNTIL = 16;

    public const NOW = 10.5;

    public static function categories(): array
    {
        return ['print' => ['label' => 'Printing', 'tone' => 'accent'], 'cut' => ['label' => 'Die-cut', 'tone' => 'warn'], 'fold' => ['label' => 'Folding', 'tone' => 'success']];
    }

    public static function rows(): array
    {
        return [
            ['key' => 'sm74', 'label' => 'Heidelberg SM74', 'sub' => 'Budi + Joko', 'section' => 'PRINTING', 'load' => 86],
            ['key' => 'cd102', 'label' => 'Heidelberg CD102', 'sub' => 'Andi', 'section' => 'PRINTING', 'load' => 94, 'bottleneck' => true,
                'down' => [['from' => 34, 'to' => 38, 'label' => 'Maintenance']]],
            ['key' => 'dc', 'label' => 'Bobst Die-cutter', 'sub' => 'Rudi', 'section' => 'FINISHING', 'load' => 62],
            ['key' => 'fg', 'label' => 'Folder Gluer', 'sub' => 'Siti', 'section' => 'FINISHING', 'load' => 48],
        ];
    }

    public static function tasks(): array
    {
        return [
            ['id' => 301, 'row' => 'sm74', 'label' => 'WO-0301', 'process' => 'print', 'start' => '06:00', 'end' => '11:30', 'progress' => 100, 'setup' => 45, 'gates' => ['ok', 'ok', 'ok']],
            ['id' => 302, 'row' => 'sm74', 'label' => 'WO-0302', 'process' => 'print', 'start' => '11:30', 'end' => '18:00', 'progress' => 40, 'status' => 'Running'],
            ['id' => 305, 'row' => 'sm74', 'label' => 'WO-0305', 'process' => 'print', 'start' => '18:30', 'end' => '26:00', 'progress' => 0, 'setup' => 30, 'gates' => ['ok', 'ok', 'wait'],
                'forecastEnd' => 27.3, 'eligible' => ['sm74', 'cd102']],
            ['id' => 309, 'row' => 'sm74', 'label' => 'WO-0309', 'process' => 'print', 'start' => '30:00', 'end' => '38:00', 'progress' => 0, 'setup' => 60, 'gates' => ['ok', 'ok', 'ok'], 'eligible' => ['sm74', 'cd102']],
            ['id' => 303, 'row' => 'cd102', 'label' => 'WO-0303', 'process' => 'print', 'start' => '06:00', 'end' => '13:00', 'progress' => 90, 'gates' => ['ok', 'ok', 'ok']],
            ['id' => 304, 'row' => 'cd102', 'label' => 'WO-0304', 'process' => 'print', 'start' => '14:00', 'end' => '22:00', 'progress' => 5, 'state' => 'hot', 'risk' => true, 'setup' => 40, 'gates' => ['ok', 'ok', 'ok'], 'eligible' => ['sm74', 'cd102']],
            ['id' => 306, 'row' => 'cd102', 'label' => 'WO-0306', 'process' => 'print', 'start' => '23:00', 'end' => '30:00', 'progress' => 0, 'setup' => 45, 'gates' => ['ok', 'block', 'ok'], 'eligible' => ['cd102']],
            ['id' => 310, 'row' => 'cd102', 'label' => 'WO-0310', 'process' => 'print', 'start' => '40:00', 'end' => '48:00', 'progress' => 0, 'setup' => 30, 'gates' => ['ok', 'ok', 'ok'], 'eligible' => ['sm74', 'cd102']],
            ['id' => 307, 'row' => 'dc', 'label' => 'WO-0307', 'process' => 'cut', 'start' => '16:00', 'end' => '20:00', 'progress' => 0, 'setup' => 20, 'gates' => ['ok', 'ok', 'ok'], 'eligible' => ['dc', 'fg']],
            ['id' => 308, 'row' => 'dc', 'label' => 'WO-0308', 'process' => 'cut', 'start' => '32:00', 'end' => '40:00', 'progress' => 0, 'forecastEnd' => 40.5833, 'gates' => ['ok', 'wait', 'ok'], 'eligible' => ['dc', 'fg']],
            ['id' => 311, 'row' => 'fg', 'label' => 'WO-0311', 'process' => 'fold', 'start' => '08:00', 'end' => '12:00', 'progress' => 70, 'gates' => ['ok', 'ok', 'ok']],
            ['id' => 312, 'row' => 'fg', 'label' => 'WO-0312', 'process' => 'fold', 'start' => '26:00', 'end' => '33:00', 'progress' => 0, 'setup' => 25, 'gates' => ['ok', 'ok', 'ok'], 'eligible' => ['dc', 'fg']],
        ];
    }

    private static function hours(string $hm): float
    {
        [$h, $m] = array_pad(explode(':', $hm), 2, 0);

        return (int) $h + (int) $m / 60;
    }

    // Judge a proposed move: {task,row,start,end} -> {ok, reason?, lines?}.
    public static function impact(array $in): array
    {
        $start = self::hours((string) ($in['start'] ?? '0'));
        $end = self::hours((string) ($in['end'] ?? '0'));
        $row = (string) ($in['row'] ?? '');

        if ($row === 'cd102' && $start < 38 && $end > 34) {
            return ['ok' => false, 'reason' => 'CD102 is down for maintenance in that window.'];
        }

        $late = $end > 44;

        return ['ok' => true, 'lines' => [
            ['label' => 'Changeover', 'value' => '+20 min', 'tone' => 'warn'],
            ['label' => 'Material ready', 'value' => 'Yes', 'tone' => 'ok'],
            ['label' => 'Due date', 'value' => $late ? 'At risk (+1 day)' : 'Unchanged', 'tone' => $late ? 'bad' : 'ok'],
        ]];
    }
}
