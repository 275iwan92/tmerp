{{--
 REMARK
 Tujuan       : Cetak label lokasi: pilih gedung, bin (atau seluruh bin satu area), ukuran dan jumlah lembar; Print mencatat riwayat lalu membuka lembar label (kode bin, area, kapasitas, QR dan barcode).
 Sumber hulu  : App\Support\V2\Data\Labels::locationForm (master gudang dan bin tm2mst.wh_*).
 Input        : Sesi v2_flash dan errors; pilihan dibuat di form objek.
 Output       : Objek x-mp::inv.label-print (MockupParts) dengan form dari Labels::locationForm.
 Dipakai oleh : Route v2.screen slug label-location (App\Support\V2\Screens, menu sys_app_menu target v2:label-location)
 Status       : Aktif
--}}
@php
    use App\Support\V2\Data\Labels;
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

{{-- Form = objek form khusus x-mp::inv.label-print (MockupParts), dipakai juga layar label lainnya; isi = Labels::locationForm(). --}}
<x-mp::inv.label-print :form="Labels::locationForm(route('v2.screen', 'label-location'))" />
