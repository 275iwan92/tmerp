{{--
 REMARK
 Tujuan       : Cetak laporan grid (A4 landscape) dengan kop, kriteria, ringkasan, kelompok, subtotal, dan tanda tangan.
 Sumber hulu  : Kelas grid turunan App\Support\V2\Grid\DbGrid (printData, printSummary, groups, printSignatures); isi sama dengan grid di layar.
 Input        : grid, company dari route; query grid diteruskan ke tombol Back.
 Output       : x-dl::action-bar dan x-dl::report.
 Dipakai oleh : route v2.report.print (routes/v2.php, /report/{slug}/print).
 Status       : Aktif (laporan internal, tanpa salinan online).
--}}
@php $data = $grid->printData(); @endphp
@extends('v2.layout', ['title' => $grid->title(), 'bodyClass' => 'dl-page-body'])
@section('body')
    <div class="dl-cat">
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="back" label="Back" :href="$grid->url().'?'.http_build_query(request()->query())" />
        </x-dl::action-bar>
        <x-dl::report :title="$grid->title()" :company="$company" :printed-by="auth()->user()->login" paper="a4l" :criteria="$data['criteria']"
                :summary="$grid->printSummary()" :groups="$grid->groups()" :group-by="$grid->groupBy()" :columns="$data['columns']" :rows="$data['rows']"
                :signatures="$grid->printSignatures()" :source="$grid->sourceLabel()" />
    </div>
@endsection
