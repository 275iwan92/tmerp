<div class="dl-demo" style="max-width:820px">
    <x-dl::pick-matrix name="pick" unit="PO"
        :columns="[11 => ['label' => 'Sinar Dunia', 'sub' => 'Net 30'], 12 => ['label' => 'Indah Kertas', 'sub' => 'Net 30'], 13 => ['label' => 'Mega Karton', 'sub' => 'Tunai']]"
        :rows="[
            ['key' => 1, 'label' => '#1 Ivory 250', 'sub' => '40 RIM', 'default' => 11, 'cells' => [11 => ['value' => 101, 'text' => '1.160.000', 'sub' => 'lead 10 hr'], 12 => ['value' => 201, 'text' => '1.185.000', 'sub' => 'lead 7 hr']]],
            ['key' => 2, 'label' => '#2 Duplex 310', 'sub' => '25 RIM', 'default' => 12, 'cells' => [11 => ['value' => 102, 'text' => '930.000'], 12 => ['value' => 202, 'text' => '905.000'], 13 => ['value' => 302, 'text' => '912.000']]],
            ['key' => 3, 'label' => '#3 Kraft 125', 'sub' => '1.200 KG', 'default' => 11, 'cells' => [11 => ['value' => 103, 'text' => '14.250'], 13 => ['value' => 303, 'text' => '14.400 / ROLL', 'disabled' => 'Satuan beda dengan PR']]],
            ['key' => 4, 'label' => '#4 Tinta Cyan', 'sub' => '12 KG', 'locked' => 'Sudah di-PO', 'cells' => [12 => ['value' => 204, 'text' => '185.000']]],
        ]" />
</div>
