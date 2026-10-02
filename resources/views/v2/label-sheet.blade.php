{{-- Lembar cetak label gudang (route v2.wh.labels.sheet): deretan x-dl::label di dalam x-dl::label-sheet. Tombol Print hanya
     mencetak labelnya. Isi label = App\Support\V2\Data\Labels::sheet (bin, posisi stok, atau palet -- dari database).
     Kertas: gulungan label (satu label per halaman) atau A4 (label rapat, dipotong); tombol di bilah menggantinya. --}}
@extends('v2.layout', ['title' => t('Labels'), 'bodyClass' => 'dl-page-body'])
@section('body')
    <div class="dl-cat">
        @if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
        @if (session('v2_error'))<x-dl::alert tone="danger" :message="raw(session('v2_error'))" />@endif
        @if (! $labels)
            <x-dl::empty-state title="Nothing to print" message="The chosen bins, stock or pallets were not found." icon="fa-qrcode">
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$back" />
            </x-dl::empty-state>
        @else
            <x-dl::label-sheet :title="['item' => 'Item Labels', 'pallet' => 'Pallet Labels', 'location' => 'Location Labels'][$type]" :back="$back" :count="count($labels)" :size="$size" :paper="$paper">
                <x-slot:tools>
                    <x-dl::button size="md" :variant="$paper === 'roll' ? 'primary' : 'secondary'" icon="fa-scroll" label="Label Roll" :href="request()->fullUrlWithQuery(['paper' => 'roll'])" />
                    <x-dl::button size="md" :variant="$paper === 'a4' ? 'primary' : 'secondary'" icon="fa-file" label="A4 Sheet" :href="request()->fullUrlWithQuery(['paper' => 'a4'])" />
                </x-slot:tools>
                <x-slot:guide>
                    <x-dl::label-guide :size="$size" :colors="\App\Support\V2\Data\Labels::colorLegend($type)" />
                </x-slot:guide>
                @foreach ($labels as $l)
                    @include('v2.partials.label', ['l' => $l, 'size' => $size])
                @endforeach
            </x-dl::label-sheet>
        @endif
    </div>
@endsection
