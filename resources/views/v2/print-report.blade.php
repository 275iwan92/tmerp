{{-- Cetakan laporan grid (user 2026-10-02: laporan stok). Objek DevLib x-dl::report (kop, kriteria, ringkasan, kelompok +
     subtotal, halaman otomatis, A4 landscape, tombol Print sendiri). Kolom, baris, dan periode datang dari kelas laporannya
     (App\Support\V2\Grid\DbGrid::printData) -- isi yang sama dengan grid di layar. Laporan internal: tanpa salinan online. --}}
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
