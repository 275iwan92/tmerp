{{-- REMARK
Tujuan       : Kerangka layar pembanding Special Forms: breadcrumb sederhana, salinan layar dengan data contoh statis, dan bilah QC (Approved / Need improvement).
Sumber hulu  : App\Support\V2\Compare (daftar slug sf-*); isi layar di resources/views/v2/compare/<slug>.blade.php.
Input        : slug dan judul dari route v2.compare (hanya admin).
Output       : Halaman HTML; tidak menulis data kecuali keputusan QC yang ditekan admin (kunci = slug sf-*).
Dipakai oleh : routes/v2_compare.php; menu Developer > Comparison · Special Forms (AppMenu).
Status       : Aktif (bahan pembanding; dapat dibuang setelah keputusan).
--}}
@extends('v2.layout', ['title' => t($title), 'bodyClass' => 'dl-page-body'])
@section('body')
    <x-dl::box class="dl-cat">
        <x-dl::box class="dl-cat__nav">
            <x-dl::breadcrumb :items="[[t('Developer'), null], [t('Comparison · Special Forms'), null]]" :current="t($title)" />
        </x-dl::box>
        <x-dl::box as="p" class="dl-note">{{ t('Comparison screen: sample data is static and nothing is saved.') }}</x-dl::box>
        @include('v2.compare.'.$slug)
    </x-dl::box>
    @if (\App\Support\V2\Qc::enabled())
        @php($qc = \App\Support\V2\Qc::bar($slug, t($title)))
        <x-dl::qc-bar :key="$qc['key']" :name="raw($qc['name'])" :stage="$qc['stage']" :status="$qc['status']" :note="$qc['note']" :by="$qc['by']" :at="$qc['at']" :action="$qc['action']" :csrf="$qc['csrf']" :can-review="$qc['canReview']" :chain="$qc['chain']" :release="$qc['release']" />
    @endif
@endsection
