{{--
 REMARK
 Tujuan       : Halaman cabang menu (klik Modul / Grup di breadcrumb): daftar grup dan layar di dalam cabang itu, tiap layar bisa dibuka sebagai tab.
 Sumber hulu  : App\Support\V2\AppMenu::branch (sys_app_menu, hanya yang boleh dilihat user).
 Input        : $node (code, label, icon, items) dan $trail ([label, kode] induknya) dari route v2.branch.
 Output       : Breadcrumb mode menu + satu x-dl::card per grup berisi tombol layar. Tombol memakai data-dl-open-item (core.js) -> Shell membuka tab.
 Dipakai oleh : routes/v2_account.php (v2.branch); dibuka dari breadcrumb layar (resources/views/v2/screen.blade.php).
 Status       : Aktif.
--}}
@extends('v2.layout', ['title' => $node['label'], 'bodyClass' => 'dl-page-body'])
@section('body')
    @php
        $crumbs = [...array_map(fn ($t) => [$t[0], null, $t[1] ? route('v2.branch', $t[1]) : null], $trail), [$node['label'], null]];
        $groups = array_values(array_filter($node['items'], fn ($i) => isset($i['items'])));
        $direct = array_values(array_filter($node['items'], fn ($i) => ! isset($i['items'])));
        $icon = fn (?string $i) => $i ? preg_replace('/^fa-solid\s+/', '', $i) : null;
        $flat = function (array $items) use (&$flat): array {
            return collect($items)->flatMap(fn ($i) => isset($i['items']) ? $flat($i['items']) : [$i])->all();
        };
    @endphp
    <x-dl::box class="dl-cat">
        <x-dl::box class="dl-cat__nav">
            <x-dl::breadcrumb menu :items="$crumbs" />
        </x-dl::box>
        @if ($direct)
            <x-dl::card :title="raw($node['label'])" :icon="$icon($node['icon'])">
                <x-dl::action-bar align="start">
                    @foreach ($direct as $leaf)
                        <x-dl::button variant="secondary" size="sm" :label="raw($leaf['label'])" :icon="$icon($leaf['icon'] ?? null)" :href="$leaf['url']"
                            data-dl-open-item="{{ $leaf['code'] }}" data-label="{{ $leaf['label'] }}" data-url="{{ $leaf['url'] }}" />
                    @endforeach
                </x-dl::action-bar>
            </x-dl::card>
        @endif
        @foreach ($groups as $g)
            <x-dl::card :title="raw($g['label'])" :icon="$icon($g['icon'] ?? null)">
                <x-dl::action-bar align="start">
                    @foreach ($flat($g['items']) as $leaf)
                        <x-dl::button variant="secondary" size="sm" :label="raw($leaf['label'])" :icon="$icon($leaf['icon'] ?? null)" :href="$leaf['url']"
                            data-dl-open-item="{{ $leaf['code'] }}" data-label="{{ $leaf['label'] }}" data-url="{{ $leaf['url'] }}" />
                    @endforeach
                </x-dl::action-bar>
            </x-dl::card>
        @endforeach
        @if (! $direct && ! $groups)
            <x-dl::empty-state icon="fa-folder-open" :title="raw($node['label'])" message="This menu branch has no screen you may open." />
        @endif
    </x-dl::box>
@endsection
