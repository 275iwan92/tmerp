@extends('v2.layout', ['title' => $screen['label'], 'bodyClass' => 'dl-page-body', 'framed' => true])
@section('body')
    <div class="dl-cat">
        <div class="dl-cat__nav">
            <x-dl::breadcrumb :items="[$screen['module'] => null, $screen['group'] => null, $screen['label'] => null]" />
        </div>
        @if ($built)
            @include('v2.screens.'.$screen['slug'])
        @else
            <x-dl::card :title="$screen['label']" :icon="$screen['icon']" description="Belum dibangun di mockup — acuannya sudah dipetakan.">
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
        <x-dl::code-button :code="$code" :file="'resources/views/screens/'.$screen['slug'].'.blade.php'" :mockup-url="$screen['mockup'] ? route('v2.mockup.file', $screen['mockup']) : null" />
    @endif
@endsection
