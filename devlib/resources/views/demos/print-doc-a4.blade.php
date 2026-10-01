{{-- x-dl::print-doc: lebih dari 5 baris = A4 portrait (tata letak sama, lebar 210 mm). Dokumen berharga: Harga + Subtotal di
     posisi tetap, kotak total, terbilang; PPN > 0 = kop badan hukum + NPWP. Tanda tangan: pihak luar, Menyetujui, Mengetahui, Pembuat. --}}
<x-dl::action-bar>
    <x-dl::doc-share target="#demo-print-doc-a4" type="demo-print-a4" number="PO26000001976" title="Purchase Order" />
</x-dl::action-bar>
<x-dl::print-doc :doc="\App\Support\DemoPrintDocs::purchaseOrder()" id="demo-print-doc-a4" />
