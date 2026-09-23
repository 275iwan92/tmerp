<?php

namespace Devlib\Components\Field;

use DateTimeImmutable;

// Geser tanggal (satu tanggal atau rentang). Nilai dikirim ISO (YYYY-MM-DD), tampil dalam format lokal WIB;
// label skala = awal tiap bulan. from/until = batas geser.
class DateSlider extends Slider
{
    public function __construct(
        public string $from = '',
        public string $until = '',
        bool $range = false,
        mixed $valueTo = null,
        ?string $nameTo = null,
        string $orientation = 'horizontal',
        string $height = '220px',
        string $name = '',
        string $label = '',
        ?string $tooltip = null,
        ?string $hint = null,
        ?string $error = null,
        mixed $value = null,
        ?string $placeholder = null,
        bool $required = false,
        bool $disabled = false,
        bool $readonly = false,
        ?string $icon = null,
        string $size = 'sm',
        ?string $i18n = null,
        ?string $source = null,
    ) {
        $this->from = $from ?: date('Y-m-01');
        $this->until = $until ?: date('Y-m-t', strtotime('+2 months'));
        $days = $this->offset($this->until);
        parent::__construct(0, $days, 1, '{v}', $range, $valueTo === null ? null : $this->offset($valueTo), $nameTo, $orientation, $height, 0,
            $name, $label, $tooltip, $hint, $error, $value === null ? null : $this->offset($value), $placeholder, $required, $disabled, $readonly, $icon, $size, $i18n, $source);
    }

    private function offset(string $date): int
    {
        return (int) (new DateTimeImmutable($this->from))->diff(new DateTimeImmutable($date))->format('%r%a');
    }

    public function kind(): string
    {
        return 'date';
    }

    public function start(): string
    {
        return $this->from;
    }

    public function tickList(): array
    {
        $names = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $d = new DateTimeImmutable($this->from);
        $end = new DateTimeImmutable($this->until);
        $out = [[0, $names[(int) $d->format('n') - 1].' '.$d->format('Y')]];
        for ($m = $d->modify('first day of next month'); $m <= $end; $m = $m->modify('+1 month')) {
            $n = (int) $m->format('n');
            $out[] = [round($this->offset($m->format('Y-m-d')) / max(1, $this->max) * 100, 2), $names[$n - 1].($n === 1 ? ' '.$m->format('Y') : '')];
        }

        return count($out) > 14 ? array_values(array_filter($out, fn ($t, $i) => $i % 3 === 0, ARRAY_FILTER_USE_BOTH)) : $out;
    }
}
