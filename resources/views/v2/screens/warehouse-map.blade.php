{{--
 REMARK
 Tujuan       : Warehouse Map: denah gedung per lantai (area, bin, tumpukan), klik bin untuk isi, kapasitas, label QR, Transfer, Label, Block; Edit Layout dan Generate Bins.
 Sumber hulu  : App\Support\V2\Data\Warehouse::desk() (tm2mst wh_*, tm2inv posisi stok).
 Input        : Url layar sendiri; pilihan gedung dan bin ditangani objek; session v2_flash dan errors.
 Output       : x-mp::inv.warehouse-desk (objek form khusus MockupParts) dan x-dl::alert.
 Dipakai oleh : route v2.screen (routes/v2.php), slug warehouse-map (menu Warehouse).
 Status       : Aktif.
--}}
@php
    use App\Support\V2\Data\Warehouse;
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

{{-- Form = objek form khusus x-mp::inv.warehouse-desk (MockupParts); isi = Warehouse::desk(). --}}
<x-mp::inv.warehouse-desk :desk="Warehouse::desk(route('v2.screen', 'warehouse-map'))" />
