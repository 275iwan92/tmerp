{{-- REMARK
Tujuan       : Kerangka halaman layar v2: breadcrumb, flash, isi layar, panel tinjau.
Sumber hulu  : Layar yang dipilih (resources/views/v2/screens/*), Crumb, AppMenu::crumbTrail (jejak Modul › Grup sama dengan label Layer Menu).
Input        : Slug layar dan data dari controller.
Output       : Halaman HTML lengkap; tombol aksi breadcrumb dari Crumb::actionsHtml.
Dipakai oleh : routes/v2.php (v2.screen).
Status       : Aktif.
--}}
@extends('v2.layout', ['title' => t($screen['label']), 'bodyClass' => 'dl-page-body'])
@section('body')
    @php
        // Jejak = Modul › Grup persis seperti Layer Menu; list pasangan [label, url] karena grup dan layar boleh bernama sama.
        $mi = request('mi');
        $trail = \App\Support\V2\AppMenu::crumbTrail($screen['slug'], $mi) ?? [[\App\Support\V2\AppMenu::moduleLabel($screen['module_code'] ?? '') ?? t($screen['module']), null], [t($screen['group']), null]];
        $gridUrl = $mi ? $screen['url'].(str_contains($screen['url'], '?') ? '&' : '?').'mi='.urlencode($mi) : $screen['url'];
        // Modul / Grup: [label, null, alamat halaman cabang]; klik = panel kiri pindah ke cabang itu dan panel kanan membuka halaman cabangnya.
        $crumbItems = [...array_map(fn ($t) => [$t[0], null, $t[1] ? route('v2.branch', $t[1]) : null], $trail), [t($screen['label']), $gridUrl]];
    @endphp
    @if ($screen['side'] ?? false)
        {{-- Side Screen (user 2026-10-04): layar mengisi jendela penuh, breadcrumb ada di judul bagian (x-dl::side-screen.layout). --}}
        @include($screen['view'], ['screen' => $screen, 'crumbs' => array_map(fn ($c) => [$c[0], $c[1]], $crumbItems)])
        <x-dl::code-button :code="$code" :file="'resources/views/'.str_replace('.', '/', $screen['view']).'.blade.php'" />
    @else
    @php
        // Isi layar dirender dulu supaya layar bisa menyebut identitas dokumennya lewat Crumb::set() untuk breadcrumb.
        \App\Support\V2\Crumb::set(null);
        \App\Support\V2\Crumb::actions(null);
        \App\Support\V2\Crumb::status(null);
        $screenBody = $built ? view($screen['view'] ?? 'v2.screens.'.$screen['slug'], ['screen' => $screen])->render() : null;
    @endphp
    <x-dl::box class="dl-cat">
        <x-dl::box @class(['dl-cat__nav', 'has-actions' => \App\Support\V2\Crumb::actionsHtml() !== null])>
            {{-- Modul/Grup = buka Layer Menu di cabang itu; nama layar = kembali ke grid (DevLib Breadcrumb menu + current). --}}
            <x-dl::breadcrumb menu :items="$crumbItems"
                :current="\App\Support\V2\Crumb::current()" />
            @if (\App\Support\V2\Crumb::statusHtml() !== null)<x-dl::box class="dl-cat__status">{!! \App\Support\V2\Crumb::statusHtml() !!}</x-dl::box>@endif
            @if (\App\Support\V2\Crumb::actionsHtml() !== null)<x-dl::box class="dl-cat__actions">{!! \App\Support\V2\Crumb::actionsHtml() !!}</x-dl::box>@endif
        </x-dl::box>
        @if ($built)
            {!! $screenBody !!}
        @else
            <x-dl::card :title="$screen['label']" :icon="$screen['icon']" description="Not built in the mockup yet — its reference is mapped.">
                <x-dl::table :columns="['k' => 'Topic', 'v' => 'Description']" :rows="[
                    ['k' => t('Module › Group'), 'v' => $screen['module'].' › '.$screen['group']],
                    ['k' => t('Flow & field reference (current tmerp)'), 'v' => 'resources/views/pages/'.$screen['ref']],
                    ['k' => t('Display reference (original mockup)'), 'v' => $screen['mockup'] ?? '—'],
                ]" />
                @if ($screen['mockup'])
                    <x-dl::action-bar><x-dl::button variant="secondary" icon="view" label="Open original mockup" :href="route('v2.mockup.file', $screen['mockup'])" target="_blank" /></x-dl::action-bar>
                @endif
            </x-dl::card>
        @endif
    </x-dl::box>
    @endif
    @if ($built && ! ($screen['side'] ?? false))
        <x-dl::code-button :code="$code" :file="isset($screen['grid']) ? str_replace(base_path().DIRECTORY_SEPARATOR, '', (new ReflectionClass($screen['grid']))->getFileName()) : 'resources/views/screens/'.$screen['slug'].'.blade.php'" :mockup-url="$screen['mockup'] ? route('v2.mockup.file', $screen['mockup']) : null" />
    @endif
    @if (\App\Support\V2\Qc::enabled())
        @php($qc = \App\Support\V2\Qc::bar($screen['slug'], t($screen['label'])))
        <x-dl::qc-bar :key="$qc['key']" :name="raw($qc['name'])" :stage="$qc['stage']" :status="$qc['status']" :note="$qc['note']" :by="$qc['by']" :at="$qc['at']" :action="$qc['action']" :csrf="$qc['csrf']" :can-review="$qc['canReview']" :chain="$qc['chain']" :release="$qc['release']" />
    @endif
@endsection
