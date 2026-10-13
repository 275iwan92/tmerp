<?php

namespace Devlib\Components;

use Devlib\Lang;

/*
 * REMARK
 * Tujuan       : Peta bantuan = pohon Modul > Grup > Layar di kiri, detail panduan layar terpilih di kanan, dengan filter
 *                (modul, jenis, belum ada panduan), kotak cari, panah atas/bawah, dan layar aktif ditandai "di sini".
 * Sumber hulu  : Pemanggil menyerahkan data jadi (pohon menu + topik); objek ini tidak membaca database.
 * Input        : tree [{id,label,icon,kids|s:{slug,url,code,topic}}], topics {key:{key,type,title,purpose,keywords,steps,fields}},
 *                current (slug layar aktif), copyUrl, requestTab.
 * Output       : Pemilihan layar, pesan 'open-item' ke Shell (buka layar, laci tetap terbuka), tab Tickets terisi.
 * Dipakai oleh : Help & support > Help Topics (resources/views/v2/screens/help.blade.php).
 * Status       : Aktif (DevLib).
 */
class HelpMap extends DevlibComponent
{
    public function __construct(
        public array $tree = [],
        public array $topics = [],
        public ?string $current = null,
        public string $copyUrl = '',
        public string $requestTab = 'tickets',
    ) {}

    public function tag(): string
    {
        return 'x-dl::help-map';
    }

    public function jsClass(): ?string
    {
        return 'HelpMap';
    }

    public function source(): string
    {
        return 'peta menu + topik help';
    }

    // Teks tampilan untuk JS (diterjemahkan di sini, JS tidak punya kamus sendiri).
    public function labels(): array
    {
        $en = ['Search screen, purpose, keyword or menu path…', 'All modules', 'General guides (no menu)', 'All', 'Input', 'Report', 'Guide',
            'No guide yet', 'Reset filter', 'No screen matches', 'Change or reset the filters above.', 'You are at', 'Guide for this screen',
            'Open screen', 'Copy guide link', 'Link copied', 'Previous screen', 'Next screen', 'Purpose', 'Steps', 'Fields', 'here',
            'There is no guide for this screen yet.', 'Request guide', 'Request guide: ', 'Pick a screen on the left to read its guide.',
            'screens match the filter', 'screen matches the filter', ':with of :total screens have a guide, :without without a guide', 'Module', 'Type'];

        return array_combine($en, array_map(fn ($s) => Lang::text($s), $en));
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-hm') }} {!! $dl() !!} data-dl-helpmap
                data-map="{{ json_encode(['tree' => $tree, 'topics' => (object) $topics, 'current' => $current, 'copyUrl' => $copyUrl, 'requestTab' => $requestTab, 'l' => $labels()]) }}">
                <div class="dl-hm__top">
                    <label class="dl-hm__search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" data-hm-q placeholder="{{ \Devlib\Lang::text('Search screen, purpose, keyword or menu path…') }}" aria-label="{{ \Devlib\Lang::text('Search screen, purpose, keyword or menu path…') }}"><kbd>/</kbd></label>
                    <div class="dl-hm__filters" data-hm-filters></div>
                    <div class="dl-hm__sum" data-hm-sum></div>
                </div>
                <div class="dl-hm__main">
                    <nav class="dl-hm__tree" data-hm-tree aria-label="{{ \Devlib\Lang::text('Operational menus') }}"></nav>
                    <article class="dl-hm__detail" data-hm-detail aria-live="polite"></article>
                </div>
                <footer class="dl-hm__keys"><span><kbd>F1</kbd> {{ \Devlib\Lang::text('open / close') }}</span><span><kbd>/</kbd> {{ \Devlib\Lang::text('search') }}</span><span><kbd>↑</kbd><kbd>↓</kbd> {{ \Devlib\Lang::text('move between screens') }}</span><span><kbd>Ctrl</kbd>+<kbd>Enter</kbd> {{ \Devlib\Lang::text('open the screen') }}</span><span><kbd>Esc</kbd> {{ \Devlib\Lang::text('clear search, then close') }}</span></footer>
            </div>
            BLADE;
    }
}
