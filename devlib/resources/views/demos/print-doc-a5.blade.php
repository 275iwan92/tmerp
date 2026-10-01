{{-- x-dl::print-doc: cetakan dokumen transaksi. 1-5 baris = A5 landscape. Dokumen tanpa nilai uang: tanpa total.
     Objek dokumen = turunan Devlib\PrintDoc\Document (lihat App\Support\DemoPrintDocs). Print / PDF / JPEG lewat x-dl::doc-share. --}}
<x-dl::action-bar>
    <x-dl::doc-share target="#demo-print-doc-a5" type="demo-print-a5" number="10262874" title="Delivery Note" orientation="landscape" />
</x-dl::action-bar>
<x-dl::print-doc :doc="\App\Support\DemoPrintDocs::deliveryNote()" id="demo-print-doc-a5" />
