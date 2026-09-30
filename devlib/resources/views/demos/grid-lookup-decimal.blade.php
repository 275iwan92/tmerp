<x-dl::lookup-grid :rows="[
        ['id' => 1, 'code' => 'AP80', 'name' => 'Art Paper 80 gsm', 'rate' => 18450, 'factor' => 1.025, 'active' => true],
        ['id' => 2, 'code' => 'IVR250', 'name' => 'Ivory 250 gsm', 'rate' => 1250000.5, 'factor' => 1.1, 'active' => true],
        ['id' => 3, 'code' => 'DPX310', 'name' => 'Duplex 310 gsm', 'rate' => 12300.75, 'factor' => 0.985, 'active' => true],
        ['id' => 4, 'code' => 'KRF125', 'name' => 'Kraft 125 gsm', 'rate' => 9875, 'factor' => 1, 'active' => true],
    ]"
    :columns="[
        ['key' => 'code', 'label' => 'Kode', 'required' => true, 'unique' => true, 'case' => 'uppercase', 'width' => '110px'],
        ['key' => 'name', 'label' => 'Nama', 'required' => true, 'unique' => true, 'case' => 'capitalize'],
        ['key' => 'rate', 'label' => 'Tarif / kg', 'type' => 'number', 'decimals' => 2, 'required' => true, 'width' => '140px'],
        ['key' => 'factor', 'label' => 'Faktor', 'type' => 'number', 'decimals' => 3, 'width' => '90px'],
        ['key' => 'active', 'label' => 'Aktif', 'type' => 'check', 'width' => '70px'],
    ]" />
