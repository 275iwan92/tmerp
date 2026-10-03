{{-- x-dl::print-doc: dokumen berstatus DRAFT boleh dicetak, lembarnya diberi tanda air besar miring "DRAFT" (otomatis dari
     Document::watermark(); teks lain lewat data['watermark']). --}}
<x-dl::action-bar>
    <x-dl::doc-share target="#demo-print-doc-draft" type="demo-print-draft" number="10262874" title="Delivery Note" orientation="landscape" />
</x-dl::action-bar>
<x-dl::print-doc :doc="\App\Support\DemoPrintDocs::deliveryNote('DRAFT')" id="demo-print-doc-draft" />
