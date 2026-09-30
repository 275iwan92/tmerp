<?php

namespace Devlib;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Stringable;

// TANGGAL + JAM = satu objek (DateValue + TimeValue) untuk input & tampilan (user 2026-10-01). Kembaran JS: DlDateTime.
// Simpan 'Y-m-d H:i:s' (zona aplikasi). Tampil SATU format 'dd-Mmm-yyyy HH:mm', bulan ikut bahasa tampilan.
// Ketik: '10-10-26 8:30', '10 okt 2026 0830', '2026-10-10 08:30:00', '2026-10-10T08:30'. Tanpa jam = 00:00.
final class DateTimeValue implements Stringable
{
    private function __construct(public readonly DateValue $date, public readonly TimeValue $time) {}

    public static function now(): self
    {
        return self::of(CarbonImmutable::now());
    }

    public static function of(mixed $v): ?self
    {
        if ($v instanceof self || $v === null || $v === '') {
            return $v ?: null;
        }
        if ($v instanceof DateTimeInterface || preg_match('/\d{2}:\d{2}(:\d{2})?(\.\d+)?([+-]\d{2}:?\d{2}|Z)$/', trim((string) $v))) {
            $c = CarbonImmutable::parse($v instanceof DateTimeInterface ? $v->format(DATE_ATOM) : (string) $v)->setTimezone(Locale::timezone());

            return new self(DateValue::make($c->year, $c->month, $c->day), TimeValue::make($c->hour, $c->minute, $c->second));
        }
        $s = trim((string) $v);
        if (preg_match('/^(\d{4}-\d{1,2}-\d{1,2})[T ](\d{1,2}:\d{2}(?::\d{2})?)/', $s, $x)) {
            $d = DateValue::of($x[1]);
            $t = TimeValue::of($x[2]);

            return $d && $t ? new self($d, $t) : null;
        }
        if ($d = DateValue::of($s)) {  // tanggal saja dulu: "10 okt 2026" bukan "10 okt" jam 20:26
            return new self($d, TimeValue::make(0, 0));
        }
        if (preg_match('/^(.*?\S)[\sT]+(\d{1,2}(?:[:.]\d{2}){0,2}|\d{3,4})$/', $s, $x) && ($d = DateValue::of($x[1])) && ($t = TimeValue::of($x[2]))) {
            return new self($d, $t);
        }

        return null;
    }

    public function iso(): string
    {
        return $this->date->iso().' '.$this->time->iso();
    }

    public function display(?string $lang = null): string
    {
        return $this->date->display($lang).' '.$this->time->display();
    }

    public function __toString(): string
    {
        return $this->display();
    }
}
