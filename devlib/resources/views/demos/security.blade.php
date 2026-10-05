@php
    $objects = [
        ['key' => 'inq', 'name' => 'Sales Inquiry', 'type' => 'Form', 'module' => 'Sales & Inquiry'], ['key' => 'quo', 'name' => 'Quotation', 'type' => 'Form', 'module' => 'Sales & Inquiry'],
        ['key' => 'co', 'name' => 'Customer Order', 'type' => 'Form', 'module' => 'Sales & Inquiry'], ['key' => 'inq-l', 'name' => 'Daftar Inquiry', 'type' => 'Grid', 'module' => 'Sales & Inquiry'],
        ['key' => 'quo-l', 'name' => 'Daftar Quotation', 'type' => 'Grid', 'module' => 'Sales & Inquiry'], ['key' => 'cust', 'name' => 'Master Customer', 'type' => 'Form', 'module' => 'Master Data'],
        ['key' => 'prod', 'name' => 'Master Produk', 'type' => 'Form', 'module' => 'Master Data'], ['key' => 'prod-l', 'name' => 'Daftar Produk', 'type' => 'Grid', 'module' => 'Master Data'],
        ['key' => 'po', 'name' => 'Purchase Order', 'type' => 'Form', 'module' => 'Procurement'], ['key' => 'po-l', 'name' => 'Daftar PO', 'type' => 'Grid', 'module' => 'Procurement'],
    ];
    $groups = [['name' => 'Administrator', 'desc' => 'Akses penuh sistem', 'users' => 1], ['name' => 'Sales', 'desc' => 'Tim penjualan', 'users' => 1], ['name' => 'Gudang', 'desc' => 'Tim inventory', 'users' => 0], ['name' => 'Finance', 'desc' => 'Keuangan & akunting', 'users' => 0]];
    $users = [['login' => 'demo.admin', 'name' => 'Demo Admin', 'group' => 'Administrator', 'active' => true], ['login' => 'rina.wijaya', 'name' => 'Rina Wijaya', 'group' => 'Sales', 'active' => true], ['login' => 'bayu.n', 'name' => 'Bayu Nugroho', 'group' => 'Gudang', 'active' => false]];
@endphp
<x-dl::tabs vertical :tabs="['users' => ['Pengguna', 'user'], 'groups' => ['Grup', 'users'], 'objects' => ['Objek Form & Grid', 'fa-folder-tree'], 'perms' => ['Hak Akses Grup', 'lock'], 'lang' => ['Bahasa', 'fa-globe'], 'theme' => ['Tema', 'fa-palette']]">
    <x-slot:users>
        <div class="dl-sec"><h2>Pengguna</h2><p class="dl-sec__desc">Kelola akun yang bisa masuk ke aplikasi. Tambah langsung di baris teratas.</p>
            <x-dl::grid id="sec-users" :rows="$users" row-key="login" active-key="active" :quick-add="['login', 'name', 'group']" search="Cari pengguna..."
                :columns="[['key' => 'login', 'label' => 'Username'], ['key' => 'name', 'label' => 'Nama'], ['key' => 'group', 'label' => 'Grup'], ['key' => 'active', 'label' => 'Status', 'type' => 'toggle']]" /></div>
    </x-slot:users>
    <x-slot:groups>
        <div class="dl-sec"><h2>Grup</h2><p class="dl-sec__desc">Grup menampung pengguna yang berbagi hak akses sama.</p>
            <x-dl::grid id="sec-groups" :rows="$groups" row-key="name" :quick-add="['name', 'desc']" search="Cari grup..."
                :columns="[['key' => 'name', 'label' => 'Nama grup'], ['key' => 'desc', 'label' => 'Deskripsi'], ['key' => 'users', 'label' => 'Jumlah user', 'type' => 'number']]" /></div>
    </x-slot:groups>
    <x-slot:objects>
        <div class="dl-sec"><h2>Objek Form & Grid</h2><p class="dl-sec__desc">Seluruh objek aplikasi yang bisa diberi hak akses (dasar pengaturan izin).</p>
            <x-dl::grid id="sec-objects" :rows="$objects" row-key="key" :new-record="false" search="Cari objek..." :filters="['module' => 'Modul', 'type' => 'Tipe']"
                :columns="[['key' => 'name', 'label' => 'Nama objek'], ['key' => 'type', 'label' => 'Tipe', 'type' => 'badge', 'tones' => ['Form' => 'info', 'Grid' => 'violet']], ['key' => 'module', 'label' => 'Modul']]" /></div>
    </x-slot:objects>
    <x-slot:perms>
        <div class="dl-sec"><h2>Hak Akses Grup</h2><p class="dl-sec__desc">Pilih grup, lalu tentukan tingkat akses per objek.</p>
            <x-dl::permission-matrix :objects="$objects" :groups="array_column($groups, 'name')" :perms="['Administrator' => array_fill_keys(array_column($objects, 'key'), 4)]" /></div>
    </x-slot:perms>
    <x-slot:lang>
        <div class="dl-sec"><h2>Bahasa</h2><p class="dl-sec__desc">Bahasa default saat login dan bahasa yang tersedia.</p>
            <x-dl::form title="Bahasa default" :actions="['Save' => 'fa-floppy-disk']"><x-dl::field.select name="default_lang" label="Bahasa saat login" :options="['id' => 'Bahasa Indonesia', 'en' => 'English']" value="id" /></x-dl::form>
            <x-dl::grid id="sec-langs" :rows="[['code' => 'id', 'name' => 'Bahasa Indonesia', 'active' => true], ['code' => 'en', 'name' => 'English', 'active' => true]]" row-key="code" :quick-add="['code', 'name']"
                :columns="[['key' => 'code', 'label' => 'Kode'], ['key' => 'name', 'label' => 'Nama'], ['key' => 'active', 'label' => 'Aktif', 'type' => 'toggle']]" /></div>
    </x-slot:lang>
    <x-slot:theme>
        <div class="dl-sec"><h2>Tema</h2><p class="dl-sec__desc">Pilih warna utama aplikasi. Perubahan langsung terlihat.</p><x-dl::theme-picker /></div>
    </x-slot:theme>
</x-dl::tabs>
