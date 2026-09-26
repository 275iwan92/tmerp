<x-dl::grid id="grid-report" mode="report" :rows="\App\Support\DemoData::salesLines()" search="Cari faktur, customer, produk..."
    :filters="['category' => 'Kategori', 'sales' => 'Sales', 'status' => 'Status']"
    :columns="[
        ['key' => 'inv_date', 'label' => 'Tanggal', 'type' => 'date'],
        ['key' => 'inv_no', 'label' => 'No. Faktur'],
        ['key' => 'customer', 'label' => 'Customer', 'type' => 'identity', 'code' => 'customer_code'],
        ['key' => 'product', 'label' => 'Produk'],
        ['key' => 'category', 'label' => 'Kategori'],
        ['key' => 'sales', 'label' => 'Sales'],
        ['key' => 'qty', 'label' => 'Qty', 'type' => 'number', 'sum' => true],
        ['key' => 'price', 'label' => 'Harga', 'type' => 'money'],
        ['key' => 'value', 'label' => 'Nilai', 'type' => 'money', 'sum' => true],
        ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['LUNAS' => 'success', 'JATUH TEMPO' => 'warn', 'BELUM LUNAS' => 'danger']],
    ]" />
