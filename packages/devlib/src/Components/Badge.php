<?php

namespace Devlib\Components;

// Label status kecil. tone: neutral|accent|success|warn|danger|info|violet. Status dokumen -> Badge::forStatus("APPROVED").
class Badge extends DevlibComponent
{
    public function __construct(
        public string $label = '',
        public string $tone = 'neutral',
        public bool $pill = true,
        public ?string $icon = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::badge tone='.$this->tone;
    }

    // Warna baku per status dokumen: satu tempat untuk seluruh aplikasi.
    public const STATUS = [
        'DRAFT' => 'neutral', 'SUBMITTED' => 'warn', 'HOLD' => 'violet', 'APPROVED' => 'success', 'CONFIRMED' => 'success',
        'SENT' => 'info', 'PARTIALLY_RECEIVED' => 'info', 'RECEIVED' => 'success', 'CLOSED' => 'neutral', 'REJECTED' => 'danger', 'CANCELLED' => 'danger',
    ];

    public static function forStatus(string $status): string
    {
        return self::STATUS[strtoupper($status)] ?? 'neutral';
    }

    public function render()
    {
        return <<<'BLADE'
            <span {{ $attributes->class(['dl-badge', 'dl-badge--'.$tone, 'dl-badge--pill' => $pill]) }} {!! $dl() !!}>@if ($icon)<i class="fa-solid {{ \Devlib\Icon::fa($icon) }}"></i>@endif{{ $label }}{{ $slot }}</span>
            BLADE;
    }
}
