<?php

namespace App\Services\Production\SpeedTarget;

use App\Models\Sys\SysConstant;

// Parameter engine = sys_constant grup SPEED_ENGINE (Settings > Constants, CLAUDE.md §7.12). Default di sini hanya
// jaring pengaman bila baris konstanta belum ada/dinonaktifkan.
final class SpeedSettings
{
    public const DEFAULTS = [
        'SPEED_PERCENTILE' => 75,
        'SPEED_MIN_SAMPLE' => 20,
        'SPEED_HIGH_SAMPLE' => 200,
        'SPEED_RUN_MIN_MINUTES' => 5,
        'SPEED_RUN_MAX_MINUTES' => 960,
        'SPEED_EFF_MANUAL' => 0.50,
        'SPEED_EFF_AUTO' => 0.60,
        'SPEED_EFF_CALIBRATE' => 1,
        'SPEED_EFF_CALIBRATE_MIN' => 2,
        'SPEED_CHANGE_MIN_PCT' => 5,
        'SPEED_V2_LOOKBACK_DAYS' => 365,
        'SPEED_LEGACY_EXCLUDE' => 'SETTING,GANTI,REPARASI,CLEANING,PERBAIKAN,PERSIAPAN,HEAT UP,HITUNG,QC',
    ];

    private function __construct(private readonly array $values) {}

    public static function make(array $overrides = []): self
    {
        return new self($overrides + self::DEFAULTS);
    }

    public static function load(): self
    {
        $rows = SysConstant::query()->where('constant_group', 'SPEED_ENGINE')->where('activate', true)->get();
        $values = [];
        foreach ($rows as $r) {
            $values[$r->constant_key] = $r->constant_number !== null ? (float) $r->constant_number : $r->constant_text;
        }

        return self::make(array_filter($values, fn ($v) => $v !== null && $v !== ''));
    }

    public function num(string $key): float
    {
        return (float) $this->values[$key];
    }

    public function percentile(): float
    {
        return min(100, max(1, $this->num('SPEED_PERCENTILE'))) / 100;
    }

    public function minSample(): int
    {
        return max(1, (int) $this->num('SPEED_MIN_SAMPLE'));
    }

    public function highSample(): int
    {
        return max($this->minSample(), (int) $this->num('SPEED_HIGH_SAMPLE'));
    }

    public function efficiency(?string $drive): float
    {
        return $this->num($drive === 'AUTO' ? 'SPEED_EFF_AUTO' : 'SPEED_EFF_MANUAL');
    }

    public function isExcludedProcess(?string $process): bool
    {
        $process = mb_strtoupper(trim((string) $process));
        foreach (explode(',', (string) $this->values['SPEED_LEGACY_EXCLUDE']) as $word) {
            $word = mb_strtoupper(trim($word));
            if ($word !== '' && str_contains($process, $word)) {
                return true;
            }
        }

        return false;
    }
}
