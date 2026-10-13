{{--
 REMARK
 Tujuan       : Menampilkan lembar cetak label gudang (item, palet, lokasi) pada gulungan atau A4, tombol Print hanya mencetak labelnya.
 Sumber hulu  : App\Support\V2\Data\Labels::sheet (bin, posisi stok, palet dari database), Labels::colorLegend.
 Input        : labels, size, type, paper, back dari route; query keys, copies, size, paper, rid.
 Output       : x-dl::label-sheet berisi x-dl::label lewat partial v2.partials.label, x-dl::label-guide, atau x-dl::empty-state bila kosong.
 Dipakai oleh : route v2.wh.labels.sheet (routes/v2_warehouse.php).
 Status       : Aktif.
--}}
@extends('v2.layout', ['title' => t('Labels'), 'bodyClass' => 'dl-page-body'])
@section('body')
    <x-dl::box class="dl-cat">
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
    </x-dl::box>
@endsection
