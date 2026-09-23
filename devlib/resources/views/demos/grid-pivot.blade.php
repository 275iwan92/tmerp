<x-dl::pivot title="Penjualan" :rows="\App\Support\DemoData::sales()"
    :fields="['customer' => 'Customer', 'kategori' => 'Kategori', 'bulan' => 'Bulan', 'sales' => 'Sales']"
    :measures="['nilai' => 'Nilai (Rp)', 'qty' => 'Qty (pcs)']" :formats="['nilai' => 'money']"
    :rows-by="['customer', 'kategori']" col-by="bulan" value="nilai" />
