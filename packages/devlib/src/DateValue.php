<?php

namespace Devlib;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Stringable;

// TANGGAL = satu objek untuk input & tampilan (user 2026-10-01: "Object oriented adalah KEY"). Kembaran JS: Devlib.h.DlDate.
// Simpan/kirim: ISO Y-m-d. Tampil: SATU format dd-Mmm-yyyy, bulan 3 huruf ikut bahasa tampilan (10-Okt-2026 / 10-Oct-2026).
// Ketik (ala sel Excel): 10-10-2026, 10/10/26, 10.10, 10-Oct-2026, 10 okt 2026, 2026-10-10; tanpa tahun = tahun ini.
// Tanggal mustahil (31-02) = null. 'Y-m-d H:i' dibaca bagian tanggalnya apa adanya; nilai berzona (Z/+07:00) digeser ke zona aplikasi.
final class DateValue implements Stringable
{
    private const ALIAS = ['mei' => 5, 'agt' => 8, 'agu' => 8, 'okt' => 10, 'des' => 12, 'peb' => 2];

    private function __construct(public readonly int $y, public readonly int $m, public readonly int $d) {}

    public static function make(int $y, int $m, int $d): ?self
    {
        return checkdate($m, $d, $y) ? new self($y, $m, $d) : null;
    }

    public static function today(): self
    {
        return self::of(CarbonImmutable::now());
    }

    public static function of(mixed $v): ?self
    {
        if ($v instanceof self || $v === null || $v === '') {
            return $v ?: null;
        }
        if ($v instanceof DateTimeInterface) {
            $c = CarbonImmutable::instance($v)->setTimezone(Locale::timezone());

            return new self($c->year, $c->month, $c->day);
        }
        $s = trim((string) $v);
        if (preg_match('/[T ]\d{2}:\d{2}.*([+-]\d{2}:?\d{2}|Z)$/', $s)) {
            return self::of(CarbonImmutable::parse($s));
        }
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})(?:[T ].*)?$/', $s, $x)) {
            return self::make((int) $x[1], (int) $x[2], (int) $x[3]);
        }
        if (preg_match('/^(\d{1,2})[\s\/.-]+(\d{1,2}|[^\s\d\/.,-]{3,})\.?(?:[\s\/.,-]+(\d{2}|\d{4}))?$/u', $s, $x)) {
            $m = ctype_digit($x[2]) ? (int) $x[2] : self::month($x[2]);
            $y = isset($x[3]) ? (strlen($x[3]) === 2 ? 2000 + (int) $x[3] : (int) $x[3]) : (int) CarbonImmutable::now(Locale::timezone())->year;

            return self::make($y, $m, (int) $x[1]);
        }

        return null;
    }

    // Nama/singkatan bulan (bahasa tampilan, English, ejaan umum Indonesia) -> 1..12; 0 = tidak dikenal.
    public static function month(string $t, ?string $lang = null): int
    {
        $k = mb_strtolower(mb_substr($t, 0, 3));
        foreach ([Locale::spec($lang)['months'], Locale::MONTHS_EN] as $list) {
            foreach ($list as $i => $name) {
                if (mb_strtolower(mb_substr($name, 0, 3)) === $k) {
                    return $i + 1;
                }
            }
        }

        return self::ALIAS[$k] ?? 0;
    }

    public function iso(): string
    {
        return sprintf('%04d-%02d-%02d', $this->y, $this->m, $this->d);
    }

    public function display(?string $lang = null): string
    {
        return sprintf('%02d', $this->d).'-'.Locale::spec($lang)['months'][$this->m - 1].'-'.$this->y;
    }

    public function carbon(): CarbonImmutable
    {
        return CarbonImmutable::create($this->y, $this->m, $this->d, 0, 0, 0, Locale::timezone());
    }

    public function __toString(): string
    {
        return $this->display();
    }
}
