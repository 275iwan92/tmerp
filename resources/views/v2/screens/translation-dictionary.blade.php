{{--
 REMARK
 Tujuan       : Translation Dictionary dua panel: pohon kelompok pesan di kiri, grid satu kolom per bahasa aktif di kanan untuk mengisi terjemahan.
 Sumber hulu  : App\Support\V2\Grid\Setup\TranslationDictionaryLookup (tm2sys.sys_dictionary, sys_dictionary_lang, sys_dictionary_group).
 Input        : Variabel screen[grid] dari layar generik; node kelompok terpilih lewat grid (query g).
 Output       : x-dl::split + x-dl::side-menu (pohon) + x-dl::lookup-grid; simpan ke saveUrl grid.
 Dipakai oleh : Layar generik route v2.screen lewat Screens::grid (menu Setup > Translation Dictionary).
 Status       : Aktif.
--}}
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
