<x-dl::grid id="grid-qa" :rows="\App\Support\DemoData::products()" search="Cari produk..." :quick-add="['name', 'code', 'category', 'price']" :new-record="false"
    :columns="[['key' => 'name', 'label' => 'Produk'], ['key' => 'code', 'label' => 'Kode'], ['key' => 'category', 'label' => 'Kategori'], ['key' => 'price', 'label' => 'Harga/pcs', 'type' => 'money']]" />
