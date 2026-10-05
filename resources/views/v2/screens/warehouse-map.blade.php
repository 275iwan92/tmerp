{{-- Warehouse Map v2 (user 2026-10-02: "warehouse mapping") lewat App\Support\V2\Data\Warehouse: pilih gedung -> denah per
     lantai (garis luar gedung, area, bin) -> klik bin = isi, kapasitas, label QR, tombol Transfer / Label / Block.
     Tumpukan (user 2026-10-03: "selain posisi X dan Y juga ada stack"): angka di kotak bin = jumlah satuan ditumpuk; panel bin
     menggambar tumpukan dari atas ke bawah (1 = paling bawah) dengan tombol tukar urutan; saringan Remnant dan Buried.
     Merah = bin yang memuat dead stock. Edit Layout = geser / ubah ukuran area. Generate Bins = pembuat bin per area.
     Gedung, area, dan bin ditambah / diubah di layar Buildings, Storage Areas, Bins (tombol di kanan atas). --}}
@php
    use App\Support\V2\Data\Warehouse;
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

{{-- Form = objek form khusus x-mp::inv.warehouse-desk (MockupParts); isi = Warehouse::desk(). --}}
<x-mp::inv.warehouse-desk :desk="Warehouse::desk(route('v2.screen', 'warehouse-map'))" />
