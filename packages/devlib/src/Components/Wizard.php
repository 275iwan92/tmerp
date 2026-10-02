<?php

namespace Devlib\Components;

// Form bertahap. Isi = beberapa x-dl::wizard.step. Next mengecek isian wajib langkah itu;
// langkah yang sudah dilewati bisa diklik di stepper; review=true menambah langkah Review
// (ringkasan semua isian, klik "Ubah" untuk kembali). Selesai = submit form (dl:wizard-finish).
class Wizard extends DevlibComponent
{
    public function __construct(
        public string $title = '',
        public ?string $action = null,
        public bool $review = true,
        public string $finishLabel = 'Finish',
    ) {}

    public function tag(): string
    {
        return 'x-dl::wizard';
    }

    public function render()
    {
        return <<<'BLADE'
            <form {{ $attributes->class('dl-wizard') }} {!! $dl() !!} data-dl-wizard data-dl-guard novalidate method="POST" @if ($action) action="{{ $action }}" @endif data-review="{{ $review ? 1 : 0 }}">
                @if ($action) @csrf @endif
                @if ($title !== '')<div class="dl-wizard__title">{{ $title }}</div>@endif
                <ol class="dl-wizard__steps" data-w-steps></ol>
                <div class="dl-wizard__body">
                    {{ $slot }}
                    @if ($review)
                        <section class="dl-wizard__step" data-w-step data-title="Review" data-icon="fa-clipboard-check" data-w-review><div data-w-summary></div></section>
                    @endif
                </div>
                <div class="dl-wizard__foot">
                    <x-dl::button variant="secondary" icon="prev" label="Previous" data-w-prev />
                    <span class="dl-wizard__pos" data-w-pos></span>
                    <x-dl::button icon="next" label="Next" data-w-next />
                    <x-dl::button preset="submit" :label="$finishLabel" type="submit" data-w-finish />
                </div>
            </form>
            BLADE;
    }
}
