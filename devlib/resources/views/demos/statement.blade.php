<div class="dl-demo">
    <x-dl::statement title="Statement of Profit or Loss" subtitle="Unaudited management accounts · IDR" :rows="[
        ['kind' => 'group', 'label' => 'REVENUE'],
        ['label' => 'Sales revenue', 'values' => [1250000000]],
        ['kind' => 'total', 'label' => 'Total revenue', 'values' => [1250000000]],
        ['kind' => 'group', 'label' => 'COST OF REVENUE'],
        ['label' => 'Cost of goods sold', 'values' => [-830000000]],
        ['kind' => 'total', 'label' => 'Gross profit', 'values' => [420000000]],
        ['kind' => 'note', 'label' => 'Income tax is estimated monthly.'],
        ['kind' => 'net', 'label' => 'NET PROFIT FOR THE PERIOD', 'values' => [338000000]],
    ]" />
    <x-dl::statement title="Comparative (two columns)" :columns="['2026', '2025']" style="margin-top:16px" :rows="[
        ['kind' => 'group', 'label' => 'ASSETS'],
        ['label' => 'Cash and cash equivalents', 'values' => [480000000, 410000000]],
        ['label' => 'Trade accounts receivable, net', 'values' => [920000000, 1010000000]],
        ['kind' => 'net', 'label' => 'TOTAL ASSETS', 'values' => [1400000000, 1420000000]],
    ]" />
</div>
