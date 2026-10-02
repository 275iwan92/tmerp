<?php

namespace MockupParts\Components\Sales;

use Devlib\Components\DevlibComponent;

// Bar judul dokumen Inquiry (Baru / Revisi / Rekalkulasi): ikon + judul + jenis + "no · status [· vN]" + Back.
// Nomor & status diperbarui JS InquiryDoc lewat data-inq-no / data-inq-sub.
class DocBar extends DevlibComponent
{
    public const TITLES = ['NEW' => ['✨', 'New Product Inquiry'], 'RECALC' => ['🔁', 'Recalculate Price'], 'REVISE' => ['✏️', 'Change Specification']];

    public const TONES = ['NEW' => 'info', 'RECALC' => 'accent', 'REVISE' => 'warn'];

    public function __construct(
        public string $mode = 'NEW',
        public ?string $no = null,
        public string $state = 'DRAFT',
        public ?string $version = null,
        public ?string $backUrl = null,
    ) {}

    public function tag(): string
    {
        return 'x-mp::sales.doc-bar mode='.$this->mode;
    }

    public function heading(): string
    {
        [$icon, $title] = self::TITLES[$this->mode] ?? self::TITLES['NEW'];

        return $icon.' '.\Devlib\Lang::text($title);
    }

    public function tone(): string
    {
        return self::TONES[$this->mode] ?? 'info';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('mp-docbar') }} {!! $dl() !!}>
                <b>{{ $heading() }}</b>
                <x-dl::badge :label="$mode" :tone="$tone()" />
                <span class="mp-docbar__sub"><span data-inq-no>{{ $no ?: \Devlib\Lang::text('not saved yet') }}</span> · <span data-inq-sub>{{ $state }}</span>@if ($version) · {{ $version }}@endif</span>
                <span class="mp-grow"></span>
                @if ($backUrl)<x-dl::button variant="secondary" size="sm" icon="back" label="Back" :href="$backUrl" data-inq-keep />@endif
            </div>
            BLADE;
    }
}
