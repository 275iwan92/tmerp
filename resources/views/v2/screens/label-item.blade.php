{{-- Print Item Label (user 2026-10-02: "Facility for QR Code, Labelling", "cetak label item saat penerimaan barang") lewat
     App\Support\V2\Data\Labels: pilih gedung -> pilih posisi stok (item di satu bin, saldo sekarang) -> ukuran, kertas,
     jumlah lembar -> Print: riwayat dicatat lalu lembar label terbuka. Dibuka dari form Penerimaan Barang (?receipt=):
     item penerimaan itu sudah tercentang. Label item = pita warna kelas stok, kode + nama item, qty, bin, tanggal datang,
     lot + kedaluwarsa (dari palet), QR + barcode; garis merah = dead stock / kedaluwarsa. Beda dari label palet. --}}
@php
    use App\Support\V2\Data\Labels;
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

{{-- Form = objek form khusus x-mp::inv.label-print (MockupParts), dipakai juga layar label lainnya; isi = Labels::itemForm(). --}}
<x-mp::inv.label-print :form="Labels::itemForm(route('v2.screen', 'label-item'))" />
