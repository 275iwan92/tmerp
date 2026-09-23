<?php

namespace Devlib\Components;

// Kutipan acak dari daftar.
class Quote extends DevlibComponent
{
    public function __construct(
        public array $quotes = [
            ['t' => 'Cara memulai adalah berhenti bicara dan mulai melakukan.', 'a' => 'Walt Disney'],
            ['t' => 'Kesuksesan adalah hasil dari persiapan, kerja keras, dan belajar dari kegagalan.', 'a' => 'Colin Powell'],
            ['t' => 'Disiplin adalah jembatan antara tujuan dan pencapaian.', 'a' => 'Jim Rohn'],
            ['t' => 'Fokus pada progres, bukan kesempurnaan.', 'a' => 'Anonim'],
        ],
    ) {}

    public function tag(): string
    {
        return 'x-dl::quote';
    }

    public function source(): string
    {
        return 'sys_quotes (rencana mockup)';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-quote') }} {!! $dl() !!} data-dl-quote data-quotes="{{ json_encode($quotes) }}"><div class="qi">&#8220;</div><div class="q" data-dl-quote-text>—</div><div class="a" data-dl-quote-author>—</div></div>
            BLADE;
    }
}
