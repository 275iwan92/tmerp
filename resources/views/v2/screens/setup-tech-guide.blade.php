{{-- REMARK
     Tujuan       : Layar Setup > Help and Support > Technical Guide: pohon Modul > Sub-modul > Kartu di kiri, satu kartu dibaca penuh di kanan; pencarian isi lewat ?q= (daftar hasil).
     Sumber hulu  : tm2sys.sys_guide section tg lewat TechGuideLookup (tree(), feed(), article()).
     Input        : ?doc= (id kartu; bawaan kartu pertama = peta besar), ?q= (kata dicari).
     Output       : Hanya tampilan baca; tidak ada yang tersimpan. Bagian rumus rahasia mengikuti FormulaAccess di lookup.
     Dipakai oleh : Menu SET_TECH_GUIDE (TechGuideLookup). Pola sama dengan Translation Dictionary (x-dl::split + x-dl::side-menu).
     Status       : BARU 2026-10-16 (menunggu persetujuan Jo atas screenshot). --}}
@php
    $grid = $screen['grid'];
    $q = trim((string) request('q'));
    $doc = (int) request('doc') ?: ($q === '' ? $grid->firstId() : 0);
    $article = $doc ? $grid->article($doc) : null;
@endphp

<x-dl::split side="left" aside="320px">
    <x-slot:side>
        <x-dl::action-form method="get" action="{{ $grid->url() }}">
            <x-dl::field.text name="q" label="Search inside the cards" :value="$q" :case-toggle="false" />
            <x-dl::button size="sm" icon="fa-magnifying-glass" label="Search" type="submit" />
        </x-dl::action-form>
        <x-dl::side-menu id="tech-guide" title="Technical Guide" :items="$grid->tree()" :active="$article ? (string) $article['id'] : null" :favorites="false" />
    </x-slot:side>
    @if ($article)
        <x-dl::review-feed :posts="[$article]" :search="false" :source="'sys_guide'" />
    @else
        @php
            $feed = $grid->feed($q);
        @endphp
        <x-dl::review-feed :posts="$feed['posts']" :filters="$feed['filters']" :source="$feed['source']" :quick="false" empty="No cards found." />
    @endif
</x-dl::split>