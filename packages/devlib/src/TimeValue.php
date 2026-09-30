<?php

namespace Devlib;

use DateTimeInterface;
use Stringable;

// JAM = satu objek untuk input & tampilan (user 2026-10-01). Kembaran JS: DlTime.
// Ketik ala Excel: 8 / 830 / 0830 / 8:30 / 08.30 -> 08:30 (24 jam). Simpan H:i:s, tampil HH:mm (sama di semua bahasa).
final class TimeValue implements Stringable
{
    private function __construct(public readonly int $h, public readonly int $m, public readonly int $s = 0) {}

    public static function make(int $h, int $m, int $s = 0): ?self
    {
        return $h >= 0 && $h <= 23 && $m >= 0 && $m <= 59 && $s >= 0 && $s <= 59 ? new self($h, $m, $s) : null;
    }

    public static function of(mixed $v): ?self
    {
        if ($v instanceof self || $v === null || $v === '') {
            return $v ?: null;
        }
        if ($v instanceof DateTimeInterface) {
            $c = \Carbon\CarbonImmutable::instance($v)->setTimezone(Locale::timezone());

            return new self($c->hour, $c->minute, $c->second);
        }
        $t = trim((string) $v);
        if (preg_match('/^(\d{1,2})[:.](\d{2})(?:[:.](\d{2}))?$/', $t, $x)) {
            return self::make((int) $x[1], (int) $x[2], (int) ($x[3] ?? 0));
        }
        if (preg_match('/^\d{1,4}$/', $t)) {
            return strlen($t) <= 2 ? self::make((int) $t, 0) : self::make((int) substr($t, 0, -2), (int) substr($t, -2));
        }

        return null;
    }

    public function iso(): string
    {
        return sprintf('%02d:%02d:%02d', $this->h, $this->m, $this->s);
    }

    public function display(bool $seconds = false): string
    {
        return $seconds ? $this->iso() : sprintf('%02d:%02d', $this->h, $this->m);
    }

    public function __toString(): string
    {
        return $this->display();
    }
}
