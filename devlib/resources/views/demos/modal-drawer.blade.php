<div class="dl-demo dl-demo--row">
    <x-dl::button label="Buka modal" icon="new" data-dl-open="dm-kontak" />
    <x-dl::button variant="secondary" label="Buka drawer" icon="info" data-dl-open="dd-detail" />
    <x-dl::button variant="danger" label="Modal tone danger" icon="fa-triangle-exclamation" data-dl-open="dm-tone" />
</div>
{{-- tone = kepala modal berwarna mencolok: peringatan yang wajib terlihat (mis. popup kekurangan bahan saat login). --}}
<x-dl::modal id="dm-tone" title="Material Shortage" icon="fa-triangle-exclamation" tone="danger">
    <p><b>3 materials are short.</b> Open Material Planning to create a Purchase Requisition.</p>
    <x-slot:footer><x-dl::button preset="close" data-dl-close-modal /></x-slot:footer>
</x-dl::modal>
<x-dl::modal id="dm-kontak" title="Kontak Baru" icon="user">
    <x-dl::field.text name="nama" label="Nama" required />
    <x-dl::field.phone name="hp" label="No. HP" />
    <x-slot:footer><x-dl::button preset="cancel" data-dl-close-modal /><x-dl::button preset="save" type="button" data-dl-close-modal onclick="Devlib.toast('Kontak tersimpan.', 'success')" /></x-slot:footer>
</x-dl::modal>
<x-dl::drawer maximize id="dd-detail" title="Detail Supplier" icon="info">
    <x-dl::field.display label="Nama" value="PT Contoh Kertas Jaya" />
    <x-dl::field.display label="Syarat Bayar" value="Net 30" />
    <x-dl::field.display label="Total PO tahun ini" :value="248500000" format="money" />
    <x-slot:footer><x-dl::button preset="close" data-dl-close-modal /></x-slot:footer>
</x-dl::drawer>
