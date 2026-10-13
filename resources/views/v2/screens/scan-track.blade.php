{{--
 REMARK
 Tujuan       : Pelacakan lewat pindai QR / barcode: hasil untuk bin, item, palet, atau nomor Stock Transfer.
 Sumber hulu  : App\Support\V2\Data\Labels::trackDesk() (tm2inv: stok per bin, palet, kartu stok, transfer).
 Input        : Kode hasil pindai atau ketikan (dibaca objek form); url layar ini sebagai isi QR label.
 Output       : x-mp::inv.scan-track menampilkan label, isi bin, palet, riwayat, dan tombol peta / transfer.
 Dipakai oleh : route v2.screen (routes/v2.php), slug scan-track (menu Warehouse); alamat isi QR pada label.
 Status       : Aktif.
--}}
@php
    use App\Support\V2\Data\Labels;
@endphp

{{-- Form = objek form khusus x-mp::inv.scan-track (MockupParts); isi = Labels::trackDesk(). --}}
<x-mp::inv.scan-track :desk="Labels::trackDesk(route('v2.screen', 'scan-track'))" />
