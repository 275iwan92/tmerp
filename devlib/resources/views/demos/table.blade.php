<div class="dl-demo" style="max-width:820px">
    <x-dl::table
        :columns="['item' => 'Item', 'uom' => 'Satuan', 'qty' => ['Qty', 'num' => true], 'price' => ['Harga', 'num' => true, 'money' => true], 'total' => ['Total', 'num' => true, 'money' => true]]"
        :rows="[
            ['item' => 'Kertas Duplex 250 gsm', 'uom' => 'LBR', 'qty' => 5000, 'price' => 1850, 'total' => 9250000],
            ['item' => 'Tinta Cyan', 'uom' => 'KG', 'qty' => 12, 'price' => 185000, 'total' => 2220000],
            ['item' => 'Lem Laminasi', 'uom' => 'KG', 'qty' => 20, 'price' => 51500, 'total' => 1030000],
        ]"
        :totals="['item' => 'Total', 'total' => 12500000]" />
</div>
