{{--
 REMARK
 Tujuan       : Cetak label item (penerimaan barang): pilih gedung dan posisi stok, ukuran, kertas, jumlah lembar, lalu Print mencatat riwayat dan membuka lembar label (QR dan barcode).
 Sumber hulu  : App\Support\V2\Data\Labels::itemForm (posisi stok per bin dari kartu stok, lot dan kedaluwarsa dari palet).
 Input        : Query receipt (item penerimaan sudah tercentang); sesi v2_flash dan errors.
 Output       : Objek x-mp::inv.label-print (MockupParts) dengan form dari Labels::itemForm; posting dan Print ditangani objek itu.
 Dipakai oleh : Route v2.screen slug label-item (App\Support\V2\Screens, menu sys_app_menu target v2:label-item); dibuka juga dari form Penerimaan Barang
 Status       : Aktif
--}}
@php
    use App\Support\V2\Data\Labels;
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

{{-- Form = objek form khusus x-mp::inv.label-print (MockupParts), dipakai juga layar label lainnya; isi = Labels::itemForm(). --}}
<x-mp::inv.label-print :form="Labels::itemForm(route('v2.screen', 'label-item'))" />
