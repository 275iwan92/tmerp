{{-- Track via QR / Barcode (user 2026-10-02: "Facility for QR Code, Labelling") lewat App\Support\V2\Data\Labels::track.
     Pindai (scanner / kamera HP) atau ketik kode -> hasil: BIN = label + isi + tombol peta / transfer; ITEM = di bin mana
     saja stoknya + palet + 15 pergerakan terakhir; PALET = label + riwayat (datang, label ulang, pindah, ditutup);
     nomor Stock Transfer = buka dokumennya. Isi QR label = alamat halaman ini. --}}
@php
    use App\Support\V2\Data\Labels;
@endphp

{{-- Form = objek form khusus x-mp::inv.scan-track (MockupParts); isi = Labels::trackDesk(). --}}
<x-mp::inv.scan-track :desk="Labels::trackDesk(route('v2.screen', 'scan-track'))" />
