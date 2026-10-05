<?php

namespace Devlib\Components;

// Daftar hasil kerja yang dinilai: satu baris per item (form / grid / report / engine / objek / fitur) dengan pembuat,
// tanggal, status QC, bintang 1-5 dan catatan. Klik bintang = langsung tersimpan (POST action); penilaian baru tidak
// menimpa yang lama (riwayat disimpan aplikasi). Saringan: jenis, agen, belum dinilai, bintang <= 3, kotak cari.
// items: [['id_item','item_type','name','module','qc_status','built_by','built_at','changed_by','changed_at','rating','note','rated_by','rated_at','ratings']]
// types: kode => label · qc: kode => [label, tone] · agents: kunci => nama. Nama item adalah DATA: tidak diterjemahkan.
class RateList extends DevlibComponent
{
    public const TYPE_TONE = ['FORM' => 'info', 'GRID' => 'violet', 'REPORT' => 'success', 'DASHBOARD' => 'success', 'ENGINE' => 'warn', 'OBJECT' => 'neutral', 'FEATURE' => 'accent'];

    public function __construct(
        public array $items = [],
        public array $types = [],
        public array $qc = [],
        public array $agents = [],
        public ?string $action = null,
        public ?string $csrf = null,
        public bool $canRate = true,
        public ?string $only = null,      // kunci jenis: daftar hanya jenis itu, tanpa saringan jenis
        public ?string $source = null,
    ) {
        if ($only) {
            $this->items = array_values(array_filter($items, fn ($i) => $i['item_type'] === $only));
        }
    }

    public function tag(): string
    {
        return 'x-dl::rate-list';
    }

    public function source(): string
    {
        return $this->source ?? 'daftar hasil kerja + rating';
    }

    public function count(?string $type = null): int
    {
        return $type === null ? count($this->items) : count(array_filter($this->items, fn ($i) => $i['item_type'] === $type));
    }

    public function agent(array $i): string
    {
        return (string) ($i['changed_by'] ?? $i['built_by'] ?? '');
    }

    public function render()
    {
        return <<<'BLADE'
            @php($T = \Devlib\Lang::class)
            <div {{ $attributes->class('dl-rl') }} {!! $dl() !!} data-dl-rate-list data-action="{{ $action }}" data-csrf="{{ $csrf }}" data-t-saved="{{ $T::text('Rating saved') }}" data-t-failed="{{ $T::text('Rating was not saved') }}">
                <div class="dl-rl__bar">
                    <input type="search" class="dl-input dl-rl__q" data-rl-q placeholder="{{ $T::text('Search name or module...') }}" aria-label="{{ $T::text('Search name or module...') }}">
                    <span class="dl-rl__chips" role="group">
                        @if (! $only)
                            <button type="button" class="dl-eng__chip is-active" data-rl-type="">{{ $T::text('All') }} <em>{{ $count() }}</em></button>
                            @foreach ($types as $k => $label)
                                @if ($count($k))<button type="button" class="dl-eng__chip" data-rl-type="{{ $k }}">{{ $label }} <em>{{ $count($k) }}</em></button>@endif
                            @endforeach
                            <i class="dl-rl__sep"></i>
                        @endif
                        @foreach ($agents as $k => $name)<button type="button" class="dl-eng__chip" data-rl-flag="agent:{{ $k }}">{{ $name }}</button>@endforeach
                        <button type="button" class="dl-eng__chip" data-rl-flag="unrated">{{ $T::text('Not rated') }}</button>
                        <button type="button" class="dl-eng__chip" data-rl-flag="low">{{ $T::text('3 stars or less') }}</button>
                    </span>
                    <span class="dl-rl__count"><b data-rl-count>{{ count($items) }}</b> {{ $T::text('shown') }}</span>
                </div>
                <div class="dl-rl__wrap">
                    <table class="dl-rl__table">
                        <thead><tr><th>{{ $T::text('Module') }}</th><th>{{ $T::text('Type') }}</th><th>{{ $T::text('Name') }}</th><th>{{ $T::text('Built by') }}</th><th>{{ $T::text('Built') }}</th><th>{{ $T::text('Last change') }}</th><th>{{ $T::text('QC status') }}</th><th>{{ $T::text('Rating') }}</th><th>{{ $T::text('Note') }}</th></tr></thead>
                        <tbody>
                            @foreach ($items as $i)
                                <tr data-rl-row data-id="{{ $i['id_item'] }}" data-type="{{ $i['item_type'] }}" data-agent="{{ $agent($i) }}" data-rating="{{ (int) ($i['rating'] ?? 0) }}" data-text="{{ mb_strtolower($i['name'].' '.$i['module']) }}">
                                    <td>{{ $i['module'] }}</td>
                                    <td><x-dl::badge :label="$types[$i['item_type']] ?? $i['item_type']" :tone="\Devlib\Components\RateList::TYPE_TONE[$i['item_type']] ?? 'neutral'" /></td>
                                    <td><b>{{ $i['name'] }}</b></td>
                                    <td>@if ($i['built_by'])<span class="dl-rl__agent is-{{ $i['built_by'] }}">{{ $agents[$i['built_by']] ?? $i['built_by'] }}</span>@else<span class="dl-rl__agent">{{ $T::text('Before tracking') }}</span>@endif</td>
                                    <td>{{ $i['built_at'] ? \Devlib\Locale::date($i['built_at']) : '-' }}</td>
                                    <td>{{ $i['changed_at'] ? \Devlib\Locale::date($i['changed_at']) : '-' }}@if ($i['changed_by'] && $i['changed_by'] !== $i['built_by']) <small>· {{ $agents[$i['changed_by']] ?? $i['changed_by'] }}</small>@endif</td>
                                    <td><x-dl::badge :label="$qc[$i['qc_status']][0] ?? $i['qc_status']" :tone="$qc[$i['qc_status']][1] ?? 'neutral'" /></td>
                                    <td class="dl-rl__stars">@include('devlib::components.field.rating', ['size' => 'sm', 'readonly' => ! $canRate, 'disabled' => false, 'max' => 5, 'half' => false, 'name' => 'rating', 'value' => (int) ($i['rating'] ?? 0)])</td>
                                    <td class="dl-rl__note">
                                        @if ($canRate)<input type="text" class="dl-input" data-rl-note maxlength="500" value="{{ $i['note'] }}" placeholder="{{ $T::text('note (optional)') }}">@else{{ $i['note'] }}@endif
                                        <small data-rl-meta>@if ($i['rated_by']){{ $i['rated_by'] }} · {{ \Devlib\Locale::date($i['rated_at']) }}@if ($i['ratings'] > 1) · {{ $i['ratings'] }}x @endif @endif</small>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="dl-rl__none" data-rl-none hidden>{{ $T::text('Nothing matches.') }}</p>
                </div>
            </div>
            BLADE;
    }
}
