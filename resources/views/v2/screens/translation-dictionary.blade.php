{{-- Translation Dictionary dua panel ala ScriptCase (user 2026-10-03): pohon kelompok pesan (x-dl::side-menu) di kiri, grid lookup
     satu kolom per bahasa aktif di kanan. Semua isi dari App\Support\V2\Grid\Setup\TranslationDictionaryLookup (sys_dictionary). --}}
@php
    $grid = $screen['grid'];
    $p = $grid->props();
@endphp
@if ($guide = $grid->guide())
    <x-dl::alert tone="info" :message="$guide" />
@endif
<x-dl::split side="left" aside="280px">
    <x-slot:side>
        <x-dl::side-menu id="dict-groups" title="Message groups" :items="$grid->tree()" :active="$grid->node()" :favorites="false" />
    </x-slot:side>
    <x-dl::card :title="raw($grid->nodeTitle())" icon="fa-language" :description="raw(tf(':n texts · Setup > Languages switches the columns on; Setup > Message Groups edits the tree.', ['n' => \Devlib\Locale::number(count($p['rows']), 0)]))">
        <x-dl::lookup-grid :rows="$p['rows']" :columns="$p['columns']" :save-url="$p['saveUrl'].'?g='.$grid->node()" :source="$p['source']" />
    </x-dl::card>
</x-dl::split>
