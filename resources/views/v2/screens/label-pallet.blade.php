{{--
 REMARK
 Tujuan       : Cetak dan cetak ulang label palet: daftar pekerjaan label (kedatangan belum berlabel, relabel setelah pemakaian), isi palet (qty, kode palet supplier, lot, kedaluwarsa), Create dan Print atau Relabel; palet tidak mengubah stok.
 Sumber hulu  : App\Support\V2\Data\Pallets::labelDesk; App\Services\Inventory\PalletService (stok per bin, palet, Stock Transfer untuk sisa).
 Input        : Query receipt (datang dari form Penerimaan Barang); sesi v2_flash dan errors.
 Output       : Objek x-mp::inv.pallet-label (MockupParts) dengan meja dari Pallets::labelDesk.
 Dipakai oleh : Route v2.screen slug label-pallet (App\Support\V2\Screens, menu sys_app_menu target v2:label-pallet); dibuka juga dari form Penerimaan Barang
 Status       : Aktif
--}}
@php
    use App\Support\V2\Data\Pallets;
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

{{-- Form = objek form khusus x-mp::inv.pallet-label (MockupParts); isi = Pallets::labelDesk(). --}}
<x-mp::inv.pallet-label :desk="Pallets::labelDesk(route('v2.screen', 'label-pallet'))" />
