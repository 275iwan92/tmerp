<x-dl::mega-menu brand="TELASIH MAKMUR" active="home"
    :items="[
        ['key' => 'home', 'label' => 'Home', 'icon' => 'home'],
        ['key' => 'dash', 'label' => 'Dashboard', 'icon' => 'fa-gauge-high'],
        ['label' => 'Mega Menu', 'icon' => 'fa-table-cells-large', 'columns' => [
            ['label' => 'Sales', 'icon' => 'fa-cart-shopping', 'items' => [['key' => 'cust', 'label' => 'Customers', 'icon' => 'users'], ['key' => 'quo', 'label' => 'Quotations', 'icon' => 'fa-file-signature'], ['key' => 'co', 'label' => 'Customer Orders', 'icon' => 'fa-bag-shopping', 'badge' => '4'], ['key' => 'inv', 'label' => 'Invoices', 'icon' => 'fa-file-invoice-dollar']]],
            ['label' => 'Finance', 'icon' => 'money', 'items' => [['key' => 'coa', 'label' => 'Chart of Accounts', 'icon' => 'fa-sitemap'], ['key' => 'pay', 'label' => 'Payments', 'icon' => 'fa-money-check-dollar'], ['key' => 'cash', 'label' => 'Cash Flow', 'icon' => 'fa-chart-line'], ['label' => 'Journals', 'icon' => 'fa-book', 'items' => [['key' => 'bkm', 'label' => 'Kas Masuk'], ['key' => 'bkk', 'label' => 'Kas Keluar'], ['key' => 'bbm', 'label' => 'Bank Masuk']]]]],
            ['label' => 'Operations', 'icon' => 'fa-industry', 'items' => [['key' => 'prod', 'label' => 'Products', 'icon' => 'fa-box'], ['key' => 'stock', 'label' => 'Inventory', 'icon' => 'fa-warehouse'], ['key' => 'sup', 'label' => 'Suppliers', 'icon' => 'fa-truck'], ['key' => 'wo', 'label' => 'Work Orders', 'icon' => 'fa-screwdriver-wrench']]],
            ['label' => 'Settings and Users', 'icon' => 'settings', 'items' => [['key' => 'users', 'label' => 'Users', 'icon' => 'user'], ['key' => 'groups', 'label' => 'Groups', 'icon' => 'users'], ['key' => 'sec', 'label' => 'Security', 'icon' => 'lock'], ['key' => 'pref', 'label' => 'Preferences', 'icon' => 'fa-sliders']]],
        ]],
        ['label' => 'Reports', 'icon' => 'fa-chart-pie', 'items' => [['key' => 'r-sales', 'label' => 'Sales Report', 'icon' => 'fa-chart-column'], ['key' => 'r-stock', 'label' => 'Stock Card', 'icon' => 'fa-clipboard-list'], ['label' => 'Finance', 'icon' => 'money', 'items' => [['key' => 'r-tb', 'label' => 'Trial Balance'], ['key' => 'r-gl', 'label' => 'General Ledger'], ['key' => 'r-ap', 'label' => 'AP Aging']]]]],
        ['key' => 'help', 'label' => 'Help', 'icon' => 'help'],
    ]">
    <i class="fa-solid fa-circle-user"></i> Admin
</x-dl::mega-menu>
<div style="height: 300px"></div>
<p class="dl-note">Mega menu ala ScriptCase: bilah berwarna (tone green / navy / accent / violet), panel 4 kolom ber-judul ikon, sorot saat hover, dropdown biasa bertingkat (Reports ▸ Finance ▸ …). Hover / klik membuka, Esc menutup, panah kiri-kanan pindah item; lebar &lt; 760px → hamburger + akordeon.</p>
