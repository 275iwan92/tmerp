<?php

namespace Devlib\Components;

// Layar Master = grid + form edit dalam satu layar (mockup master customer/employee):
// klik 2x / Edit / + New Record -> form terisi dari baris; Back/Batal dijaga dirty guard;
// Simpan -> cek isian wajib -> baris grid diperbarui -> kembali ke grid.
// Slot bawaan = x-dl::grid; slot "form" = field-field (name = kunci kolom baris); slot "actions" = tombol tambahan.
// pipeline = [nilai => label] tahapan status yang disorot sesuai kolom pipelineKey.
class Master extends DevlibComponent
{
    public function __construct(
        public string $title = '',
        public string $nameKey = 'name',
        public ?string $codeKey = 'code',
        public array $pipeline = [],
        public string $pipelineKey = 'status',
    ) {}

    public function tag(): string
    {
        return 'x-dl::master';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-master') }} {!! $dl() !!} data-dl-master data-title="{{ $title }}" data-name-key="{{ $nameKey }}" data-code-key="{{ $codeKey }}" data-pipeline-key="{{ $pipelineKey }}">
                <div data-m-grid>{{ $slot }}</div>
                <form class="dl-master__form" data-m-form data-dl-guard novalidate hidden>
                    <div class="dl-master__bar">
                        <x-dl::button variant="secondary" icon="back" label="Back" data-dl-leave data-m-back />
                        <div class="dl-master__heading"><b data-m-heading></b><small data-m-sub></small></div>
                        <span class="dl-master__spacer"></span>
                        {{ $actions ?? '' }}
                        <x-dl::button preset="cancel" data-dl-leave data-m-back />
                        <x-dl::button preset="save" type="submit" />
                    </div>
                    @if ($pipeline)
                        <div class="dl-master__pipe" data-m-pipe>@foreach ($pipeline as $k => $l)<span class="dl-master__step" data-step="{{ $k }}">{{ $l }}</span>@endforeach</div>
                    @endif
                    <div class="dl-master__body">{{ $form ?? '' }}</div>
                </form>
            </div>
            BLADE;
    }
}
