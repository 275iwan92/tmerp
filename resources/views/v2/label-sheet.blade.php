{{-- Lembar cetak label gudang (route v2.wh.labels.sheet): deretan x-dl::label di dalam x-dl::label-sheet. Tombol Print hanya
     mencetak labelnya. Isi label = App\Support\V2\Data\Labels::sheet (bin atau posisi stok, dari database). --}}
@extends('v2.layout', ['title' => t('Labels'), 'bodyClass' => 'dl-page-body'])
@section('body')
    <div class="dl-cat">
        @if (! $labels)
            <x-dl::empty-state title="Nothing to print" message="The chosen bins or stock were not found." icon="fa-qrcode">
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$back" />
            </x-dl::empty-state>
        @else
            <x-dl::label-sheet :title="$type === 'item' ? 'Item Labels' : 'Location Labels'" :back="$back" :count="count($labels)">
                @foreach ($labels as $l)
                    <x-dl::label :code="$l['code']" :title="$l['title']" :name="$l['name']" :lines="$l['lines']" :qr="$l['qr']" :barcode="$l['barcode']" :size="$size" :tone="$l['tone']" :footer="$l['footer']" />
                @endforeach
            </x-dl::label-sheet>
        @endif
    </div>
@endsection
