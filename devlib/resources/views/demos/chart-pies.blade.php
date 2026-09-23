<div class="dl-charts">
    <x-dl::chart type="pie" title="Komposisi Biaya Produksi" format="money" :labels="['Bahan baku', 'Tenaga kerja', 'Overhead mesin', 'Tinta & plat', 'Lain-lain']" :series="[['name' => 'Biaya', 'data' => [5.4e9, 1.8e9, 1.2e9, 0.9e9, 0.3e9]]]" />
    <x-dl::chart type="donut" title="Status Order" :labels="['Selesai', 'Produksi', 'Menunggu bahan', 'Batal']" :series="[['name' => 'Order', 'data' => [184, 46, 12, 5]]]" />
    <x-dl::chart type="funnel" title="Funnel Penjualan" subtitle="Inquiry → Order" :labels="['Inquiry', 'Costing', 'Quotation', 'Nego', 'Customer Order']" :series="[['name' => 'Dokumen', 'data' => [420, 310, 220, 140, 96]]]" />
</div>
