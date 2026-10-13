{{--
 REMARK
 Tujuan       : Menampilkan satu dokumen pedoman (Markdown di docs/) sebagai artikel baca.
 Sumber hulu  : Berkas docs/flow_product_master.md atau docs/globaluserrules.md, dirender Str::markdown di route.
 Input        : title dan html dari route /pedoman/{slug}.
 Output       : Satu elemen article kelas dl-prose berisi HTML hasil render.
 Dipakai oleh : route v2.guide (routes/v2.php); tombol di layar Help & support dan menu G-PRODUCT.
 Status       : Aktif.
--}}
@extends('v2.layout', ['title' => $title, 'bodyClass' => 'dl-page-body'])
@section('body')
    <article class="dl-prose">{!! $html !!}</article>
@endsection
