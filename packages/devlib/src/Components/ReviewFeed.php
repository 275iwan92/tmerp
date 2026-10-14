<?php

/*
 * REMARK
 * Tujuan       : Umpan review gaya blog (user 2026-10-13): satu kartu per butir, huruf besar seperti WordPress, teks hitam, lalu tombol keputusan besar yang langsung tersimpan.
 * Sumber hulu  : Turunan Feed (umpan blog); data butir dari kelas data layar (mis. MdReviewLookup, StoryReviewLookup).
 * Input        : posts [id, title, meta, badge, tone, summary, body (markdown), text (polos), note, url (tautan Read article), decision, state, data{kolom=>nilai}], choices kode=>label, choiceTones kode=>tone, filters [label, where, tone], saveUrl, csrf, field.
 * Output       : POST saveUrl {changed:[{id, <field>:kode|''}]} lalu pembaruan kartu dan hitungan saringan; klik pilihan yang sama = batal pilih.
 * Dipakai oleh : resources/views/v2/screens/setup-review.blade.php (MD Review, Old Stories Review), setup-docs-library.blade.php (indeks artikel tanpa tombol keputusan: choices kosong menyembunyikan footer); demo devlib.test > Status > Review Feed.
 * Status       : Aktif (DevLib).
 */

namespace Devlib\Components;

// Review = membaca lalu memutuskan, bukan mengisi sel (user 2026-10-13: "ini REVIEW bukan ENTRY"). Karena itu bentuknya blog:
// satu kolom, huruf besar, teks HITAM (tanpa abu-abu; abu-abu hanya untuk disabled), pilihan berupa tombol besar.
// Warisan dari Feed: kartu = article, isi markdown memakai kelas dl-prose. Yang ditambahkan di sini: saringan + cari + keputusan.
class ReviewFeed extends Feed
{
    public function __construct(
        array $posts = [],
        string $empty = 'Nothing to review.',
        public array $choices = [],       // kode => label English (tombol keputusan)
        public array $choiceTones = [],   // kode => success | warn | danger | accent | neutral (warna tombol saat terpilih)
        public array $filters = [],       // [['label', 'where' => [kolom => nilai | [nilai, ...]], 'tone']] sama bentuknya dengan stats grid
        public ?string $saveUrl = null,
        public ?string $csrf = null,
        public string $field = 'decision',
        public ?string $source = null,
        public bool $search = true,      // false = tanpa bar saring + cari (mis. satu artikel penuh)
        public bool $quick = true,       // false = bar tanpa kotak cari cepat (layar sudah punya pencarian sendiri)
    ) {
        parent::__construct($posts, $empty);
    }

    public function tag(): string
    {
        return 'x-dl::review-feed';
    }

    public function source(): string
    {
        return $this->source ?? 'daftar butir review + keputusan';
    }

    // Jumlah butir yang cocok dengan satu saringan (kolom => nilai | [nilai, ...]); tanpa where = semua.
    public function count(array $filter): int
    {
        return count(array_filter($this->posts, fn ($p) => $this->matches((array) ($p['data'] ?? []), (array) ($filter['where'] ?? []))));
    }

    private function matches(array $data, array $where): bool
    {
        foreach ($where as $col => $want) {
            if (! in_array((string) ($data[$col] ?? ''), array_map('strval', (array) $want), true)) {
                return false;
            }
        }

        return true;
    }

    public function render()
    {
        return <<<'BLADE'
            @php($T = \Devlib\Lang::class)
            <div {{ $attributes->class('dl-rfeed') }} {!! $dl() !!} data-dl-review-feed data-action="{{ $saveUrl }}" data-csrf="{{ $csrf ?? csrf_token() }}" data-field="{{ $field }}"
                 data-t-saved="{{ $T::text('Decision saved') }}" data-t-failed="{{ $T::text('Decision was not saved') }}" data-t-none="{{ $T::text('Not decided yet') }}" data-t-done="{{ $T::text('Your decision') }}">
                @if ($search)
                <div class="dl-rfeed__bar">
                    @if ($quick)<input type="search" class="dl-input dl-rfeed__q" data-rf-q placeholder="{{ $T::text('Search...') }}" aria-label="{{ $T::text('Search') }}">@endif
                    <span class="dl-rfeed__chips" role="group">
                        @foreach ($filters as $i => $f)
                            <button type="button" class="dl-rfeed__chip dl-rfeed__chip--{{ $f['tone'] ?? 'neutral' }}{{ $i === 0 ? ' is-active' : '' }}" data-rf-filter data-where="{{ json_encode((object) ($f['where'] ?? [])) }}">{{ $T::text($f['label']) }} <b data-rf-n>{{ $count($f) }}</b></button>
                        @endforeach
                    </span>
                    <span class="dl-rfeed__count"><b data-rf-shown>{{ count($posts) }}</b> {{ $T::text('shown') }}</span>
                </div>
                @endif
                <div class="dl-rfeed__list">
                    @forelse ($posts as $p)
                        <article class="dl-rfeed__post{{ ! empty($p['decision']) ? ' is-decided' : '' }}" data-rf-post data-id="{{ $p['id'] }}" data-decision="{{ $p['decision'] ?? '' }}"
                                 data-f="{{ json_encode((object) ($p['data'] ?? [])) }}" data-text="{{ mb_strtolower(($p['title'] ?? '').' '.($p['meta'] ?? '').' '.($p['summary'] ?? '').' '.($p['note'] ?? '')) }}">
                            <header>
                                <h2>{{ $p['title'] ?? '' }}</h2>
                                @if (! empty($p['meta']))<p class="dl-rfeed__meta">{{ $p['meta'] }}</p>@endif
                            </header>
                            @if (! empty($p['badge']))
                                <p class="dl-rfeed__prop"><span>{{ $T::text('Claude proposes') }}</span> <x-dl::badge :label="$p['badge']" :tone="$p['tone'] ?? 'neutral'" /></p>
                            @endif
                            @if (! empty($p['summary']))<p class="dl-rfeed__summary">{{ $p['summary'] }}</p>@endif
                            @if (! empty($p['text']))<p class="dl-rfeed__text">{{ $p['text'] }}</p>@endif
                            @if (! empty($p['body']))<div class="dl-prose dl-rfeed__body">{!! $html($p['body']) !!}</div>@endif
                            @if (! empty($p['note']))<p class="dl-rfeed__note"><b>{{ $T::text('Why') }}:</b> {{ $p['note'] }}</p>@endif
                            @if (! empty($p['url']))<p class="dl-rfeed__more"><a class="dl-rfeed__readmore" href="{{ $p['url'] }}">{{ $T::text('Read article') }} &rarr;</a></p>@endif
                            @if ($choices)<footer>
                                <div class="dl-rfeed__choose" role="group" aria-label="{{ $T::text('Decision') }}">
                                    @foreach ($choices as $code => $label)
                                        <button type="button" class="dl-rfeed__btn dl-rfeed__btn--{{ $choiceTones[$code] ?? 'accent' }}{{ ($p['decision'] ?? '') === $code ? ' is-on' : '' }}" data-rf-choice="{{ $code }}" aria-pressed="{{ ($p['decision'] ?? '') === $code ? 'true' : 'false' }}">{{ $T::text($label) }}</button>
                                    @endforeach
                                </div>
                                <p class="dl-rfeed__state" data-rf-state>@if (! empty($p['decision'])){{ $T::text('Your decision') }}: <b>{{ $T::text($choices[$p['decision']] ?? $p['decision']) }}</b>@if (! empty($p['state'])) · {{ $p['state'] }}@endif @else{{ $T::text('Not decided yet') }}@endif</p>
                            </footer>@endif
                        </article>
                    @empty
                        <p class="dl-feed__empty">{{ $T::text($empty) }}</p>
                    @endforelse
                    <p class="dl-rfeed__none" data-rf-none hidden>{{ $T::text('Nothing matches.') }}</p>
                </div>
            </div>
            BLADE;
    }
}
