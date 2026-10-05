{{-- Print Pallet Label (user 2026-10-02: "wajib buat label setiap kedatangan dan setiap habis pemakaian dalam satu palet",
     "bedakan label item dan label palet", "kode plate dari supplier harus disertakan", "kertas, tinta pasti ada tanggal
     kedaluarsa") lewat App\Support\V2\Data\Pallets + App\Services\Inventory\PalletService.
     Alur: pilih gedung (atau datang dari form Penerimaan Barang: ?receipt=) -> DAFTAR PEKERJAAN LABEL = posisi stok yang
     label paletnya belum cocok dengan saldo (Arrival label = barang datang belum berlabel; Relabel after use = isinya
     sudah dipakai) -> buka satu baris -> isi palet (qty, kode palet supplier, lot, kedaluwarsa) -> Create & Print;
     atau Relabel (qty sisa; 0 = palet ditutup); atau cetak ulang. Palet tidak mengubah stok.
     Sisa (user 2026-10-03): jenis satuan Pallet / Pack (otomatis Pack bila lembaran di bawah batas sisa); saat label ulang
     sisa boleh langsung dipindah ke tumpukan lain ("Now in bin") = Stock Transfer dibuat dan di-Confirm. --}}
@php
    use App\Support\V2\Data\Pallets;
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

{{-- Form = objek form khusus x-mp::inv.pallet-label (MockupParts); isi = Pallets::labelDesk(). --}}
<x-mp::inv.pallet-label :desk="Pallets::labelDesk(route('v2.screen', 'label-pallet'))" />
