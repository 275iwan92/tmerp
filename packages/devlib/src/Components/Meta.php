<?php

namespace Devlib\Components;

// Info rekaman: dibuat/diubah oleh siapa & kapan (WIB), asal data. Mode Developer menampilkan tabel & ID.
class Meta extends DevlibComponent
{
    public function __construct(
        public ?string $createdBy = null,
        public mixed $createdAt = null,
        public ?string $updatedBy = null,
        public mixed $updatedAt = null,
        public ?string $origin = null,
        public ?string $table = null,
        public mixed $recordId = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::meta';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-meta') }} {!! $dl() !!}>
                @if ($createdAt)<span><i class="fa-solid fa-circle-plus"></i> {{ \Devlib\Lang::t('created_by') }} {{ \Devlib\Locale::dateTime($createdAt) }}@if ($createdBy) {{ \Devlib\Lang::t('by') }} <b>{{ $createdBy }}</b>@endif</span>@endif
                @if ($updatedAt)<span><i class="fa-solid fa-pen"></i> {{ \Devlib\Lang::t('updated_by') }} {{ \Devlib\Locale::dateTime($updatedAt) }}@if ($updatedBy) {{ \Devlib\Lang::t('by') }} <b>{{ $updatedBy }}</b>@endif</span>@endif
                @if ($origin)<x-dl::badge :label="$origin" :tone="$origin === 'REAL' ? 'success' : 'warn'" />@endif
                @if ($table)<span class="dl-meta__dev">{{ $table }}@if ($recordId !== null) #{{ $recordId }}@endif</span>@endif
            </div>
            BLADE;
    }
}
