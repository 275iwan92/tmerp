{{-- Contoh x-dl::warehouse-desk: pemilih gedung + pintasan, peta (x-dl::wh-map: panel bin di kiri, tumpukan, QR, barang), kapasitas per lantai,
     ringkasan dan pop-up Generate Bins. Data contoh = salinan gedung A10 dari database dummy (resources/data/warehouse-desk.json), jadi
     tampilannya sama dengan layar Warehouse Map di mockup.test. Di aplikasi: <x-mp::inv.warehouse-desk :desk="Warehouse::desk($home)" /> (turunan objek ini). --}}
<div class="dl-demo">
    <x-dl::warehouse-desk :desk="json_decode(file_get_contents(resource_path('data/warehouse-desk.json')), true)" />
</div>
