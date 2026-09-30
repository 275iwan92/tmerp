<?php

namespace Devlib;

use Stringable;

// TEKS = satu objek untuk input & tampilan (user 2026-10-01). Kembaran JS: DlText (badge huruf aa / Aa / AA di isian teks).
// Input: dirapikan (trim, spasi ganda jadi satu) + mode huruf. Tampil: apa adanya; kosong -> pengganti (mis. '—').
final class TextValue implements Stringable
{
    public const CASES = ['none', 'capitalize', 'uppercase'];

    private function __construct(public readonly string $value) {}

    public static function of(mixed $v, string $case = 'none'): self
    {
        $s = preg_replace('/\s+/u', ' ', trim((string) $v));

        return new self(match ($case) {
            'uppercase' => mb_strtoupper($s),
            'capitalize' => mb_convert_case($s, MB_CASE_TITLE),
            default => $s,
        });
    }

    public function isEmpty(): bool
    {
        return $this->value === '';
    }

    public function display(string $empty = '—'): string
    {
        return $this->value === '' ? $empty : $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
