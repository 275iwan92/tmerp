<div class="dl-charts">
    <x-dl::chart type="bar" title="Penjualan vs Target" subtitle="Per bulan 2026 (Rp)" format="money" :labels="['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu']"
        :series="[['name' => 'Penjualan', 'data' => [1.82e9, 2.05e9, 1.96e9, 2.31e9, 2.44e9, 2.12e9, 2.67e9, 2.9e9]], ['name' => 'Target', 'data' => [2e9, 2e9, 2.1e9, 2.2e9, 2.3e9, 2.3e9, 2.5e9, 2.6e9], 'tone' => 'neutral']]" />
    <x-dl::chart type="stacked" title="Produksi per Proses" subtitle="Lembar cetak (ribu)" :labels="['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu']"
        :series="[['name' => 'Offset', 'data' => [420, 460, 440, 510, 530, 480, 560, 600]], ['name' => 'Digital', 'data' => [80, 95, 90, 110, 120, 100, 130, 140], 'tone' => 'violet'], ['name' => 'Reject', 'data' => [12, 15, 9, 11, 8, 10, 7, 6], 'tone' => 'danger']]" />
    <x-dl::chart type="hbar" title="Top 5 Customer" subtitle="Nilai order YTD" format="money" :labels="['PT Contoh Snackindo', 'CV Farma Jaya', 'PT Kosmetik Indah', 'UD Teh Wangi', 'PT Label Prima']"
        :series="[['name' => 'Nilai order', 'data' => [4.2e9, 3.1e9, 2.6e9, 1.9e9, 1.2e9]]]" />
    <x-dl::chart type="combo" title="Order & Margin" subtitle="Batang = jumlah order, garis = margin %" :labels="['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu']"
        :series="[['name' => 'Jumlah order', 'type' => 'bar', 'data' => [48, 52, 50, 61, 64, 58, 70, 74]], ['name' => 'Margin %', 'type' => 'line', 'data' => [22, 24, 21, 26, 27, 25, 28, 29], 'tone' => 'warn']]" />
</div>
