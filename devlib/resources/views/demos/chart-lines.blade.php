<div class="dl-charts">
    <x-dl::chart type="line" title="Tren Kas Masuk vs Keluar" format="money" :labels="['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu']"
        :series="[['name' => 'Kas masuk', 'data' => [1.6e9, 1.9e9, 1.7e9, 2.2e9, 2.1e9, 2.4e9, 2.5e9, 2.8e9], 'tone' => 'success'], ['name' => 'Kas keluar', 'data' => [1.4e9, 1.5e9, 1.8e9, 1.7e9, 1.9e9, 2e9, 2.1e9, 2.2e9], 'tone' => 'danger']]" />
    <x-dl::chart type="area" title="Nilai Stok Bahan Baku" format="money" :labels="['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu']" :series="[['name' => 'Nilai stok', 'data' => [3.2e9, 3.0e9, 3.4e9, 3.6e9, 3.1e9, 2.9e9, 3.3e9, 3.5e9], 'tone' => 'violet']]" />
    <x-dl::chart type="radar" title="Kinerja Supplier" subtitle="Skor 0-100" :labels="['Kualitas', 'Tepat waktu', 'Harga', 'Respons', 'Dokumen', 'Fleksibel']"
        :series="[['name' => 'PT Contoh Kertas', 'data' => [90, 75, 70, 85, 80, 65]], ['name' => 'CV Tinta Nusantara', 'data' => [70, 90, 85, 60, 75, 80], 'tone' => 'warn']]" />
    <x-dl::chart type="heatmap" title="Jam Sibuk Mesin" subtitle="Rata-rata utilisasi % per hari & shift" format="percent" :labels="['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab']"
        :series="[['name' => 'Shift 1', 'data' => [92, 88, 95, 90, 85, 60]], ['name' => 'Shift 2', 'data' => [80, 84, 78, 86, 82, 45]], ['name' => 'Shift 3', 'data' => [40, 52, 48, 55, 50, 10]]]" />
</div>
