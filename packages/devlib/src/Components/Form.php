<?php

namespace Devlib\Components;

// Induk form (mockup UIUX_components_part1.html): judul + isi (kolom/baris field) + kaki berisi tombol tambahan opsional dan tombol split aksi.
// Simpan: isian wajib dicek (tab berisi error dibuka & diberi tanda), x-dl::lines ikut divalidasi; di dalam modal/drawer ditutup setelah sukses.
// mode="view": tampil sebagai teks (fieldset disabled), tombol Edit -> bisa diubah, Batal mengembalikan nilai semula. Event dl:form-submit.
class Form extends DevlibComponent
{
    public function __construct(
        public string $title = '',
        public ?string $action = null,
        public string $method = 'POST',
        public array $actions = ['Save' => '💾', 'Download' => '📥', 'Export' => '📤'],
        public ?string $extraAction = null,
        public string $extraIcon = '📱',
        public bool $footer = true,
        public bool $guard = true,
        public string $mode = 'edit',
    ) {}

    public function tag(): string
    {
        return 'x-dl::form';
    }

    public function render()
    {
        return <<<'BLADE'
            <form {{ $attributes->class('dl-form') }} {!! $dl() !!} method="{{ $method === 'GET' ? 'GET' : 'POST' }}" @if ($action) action="{{ $action }}" @endif data-dl-form data-mode="{{ $mode }}" @if ($guard) data-dl-guard @endif novalidate>
                @if ($method !== 'GET')
                    @csrf
                @endif
                @if ($title !== '')
                    <div class="dl-form__head"><div class="dl-form__title">{{ $title }}</div>@if ($mode === 'view')<x-dl::button variant="secondary" size="sm" icon="edit" label="Edit" data-f-edit />@endif</div>
                @endif
                <fieldset class="dl-form__fs" data-f-fs @disabled($mode === 'view')><div class="dl-form__body">{{ $slot }}</div></fieldset>
                @if ($footer)
                    <div class="dl-form__foot {{ $extraAction ? '' : 'is-single' }}">
                        @if ($extraAction)
                            <x-dl::button variant="success" :icon="$extraIcon" :label="$extraAction" block data-dl-form-extra />
                        @endif
                        @if ($mode === 'view')<x-dl::button preset="cancel" data-f-cancel />@endif
                        <x-dl::split-button :actions="$actions" />
                    </div>
                @endif
            </form>
            BLADE;
    }
}
