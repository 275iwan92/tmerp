<x-dl::sheet title="Harga Bahan Baku (edit massal)" :rows="collect(config('devlib.lookups.item.rows'))->map(fn ($r, $i) => ['id' => $i + 1, 'code' => $r['code'], 'name' => $r['name'], 'uom' => $r['uom'], 'price' => $r['price'], 'min' => 100 * ($i + 1), 'active' => true])->all()"
    :columns="[
        ['key' => 'code', 'label' => 'Kode', 'readonly' => true, 'width' => '120px'],
        ['key' => 'name', 'label' => 'Nama Item', 'required' => true, 'width' => '220px'],
        ['key' => 'uom', 'label' => 'Satuan', 'type' => 'select', 'options' => ['PCS', 'LBR', 'RIM', 'KG', 'ROLL'], 'width' => '90px'],
        ['key' => 'price', 'label' => 'Harga', 'type' => 'money', 'required' => true, 'width' => '130px'],
        ['key' => 'min', 'label' => 'Stok Minimum', 'type' => 'number', 'width' => '120px'],
        ['key' => 'active', 'label' => 'Aktif', 'type' => 'check', 'width' => '70px'],
    ]" />
