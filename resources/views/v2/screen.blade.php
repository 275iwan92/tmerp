@extends('v2.layout', ['title' => $screen['label'], 'bodyClass' => 'dl-page-body'])
@section('body')
    <div class="dl-cat">
        <div class="dl-cat__nav">
            {{-- Modul/Grup = buka Layer Menu di cabang itu; nama layar = kembali ke grid (DevLib Breadcrumb menu + current). --}}
            <x-dl::breadcrumb menu :items="[$screen['module'] => null, $screen['group'] => null, $screen['label'] => $screen['url']]"
                :current="request('id') ? 'Form' : (request('new') || (request()->query() && empty($screen['filters'])) ? '+ New Record' : null)" />
        </div>
        @if ($built)
            @include($screen['view'] ?? 'v2.screens.'.$screen['slug'], ['screen' => $screen])
        @else
            <x-dl::card :title="$screen['label']" :icon="$screen['icon']" description="Not built in the mockup yet — its reference is mapped.">
                <x-dl::table :columns="['k' => 'Hal', 'v' => 'Keterangan']" :rows="[
                    ['k' => 'Modul › Grup', 'v' => $screen['module'].' › '.$screen['group']],
                    ['k' => 'Acuan alur & field (tmerp sekarang)', 'v' => 'resources/views/pages/'.$screen['ref']],
                    ['k' => 'Acuan tampilan (mockup asli)', 'v' => $screen['mockup'] ?? '—'],
                ]" />
                @if ($screen['mockup'])
                    <x-dl::action-bar><x-dl::button variant="secondary" icon="view" label="Buka mockup asli" :href="route('v2.mockup.file', $screen['mockup'])" target="_blank" /></x-dl::action-bar>
                @endif
            </x-dl::card>
        @endif
    </div>
    @if ($built)
        <x-dl::code-button :code="$code" :file="isset($screen['grid']) ? str_replace(base_path().DIRECTORY_SEPARATOR, '', (new ReflectionClass($screen['grid']))->getFileName()) : 'resources/views/screens/'.$screen['slug'].'.blade.php'" :mockup-url="$screen['mockup'] ? route('v2.mockup.file', $screen['mockup']) : null" />
    @endif
@endsection
