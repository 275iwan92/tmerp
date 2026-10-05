{{-- Form khusus Warehouse Map. Contoh ini memakai keadaan "gedung belum punya ukuran" ('map' => null); peta lengkapnya
     adalah objek x-dl::wh-map (lihat Grid > Warehouse Map). Di aplikasi: <x-mp::inv.warehouse-desk :desk="Warehouse::desk($home)" /> --}}
<div class="dl-demo">
    <x-mp::inv.warehouse-desk :desk="[
        'wh' => 1, 'home' => '#', 'title' => 'A10 · Main Warehouse', 'options' => [1 => 'A10 · Main Warehouse', 2 => 'B20 · Finished Goods'],
        'buildingsUrl' => '#', 'zonesUrl' => '#',
        'links' => [['fa-building', 'Buildings', '#'], ['fa-vector-square', 'Storage Areas', '#'], ['fa-table-cells', 'Bins', '#'], ['fa-layer-group', 'Stack Monitor', '#']],
        'generate' => ['show' => true, 'action' => '#', 'zones' => [1 => 'PPR · Paper rack · Floor 1 · RACK', 2 => 'INK · Ink room · Floor 1 · FLOOR']],
        'map' => null,
    ]" />
</div>
