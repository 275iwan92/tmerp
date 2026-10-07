{{-- Contoh x-dl::label: tiga jenis yang sengaja berbeda (lokasi, item, palet), pita warna + tanda, tag Expired / Expires soon,
     garis merah = dead stock / kedaluwarsa, tiga ukuran nyata; x-dl::label-guide (ukuran + cara cetak + arti warna);
     x-dl::label-sheet (lembar cetak: gulungan label atau A4) dan x-dl::scan (kotak pindai). --}}
<div class="dl-demo">
    <x-dl::label-guide size="md" :open="true" :colors="[['color' => 'accent', 'label' => 'JAN'], ['color' => 'green', 'label' => 'FEB'], ['color' => 'tangerine', 'label' => 'MAR'],
        ['color' => 'violet', 'label' => 'APR'], ['color' => 'lime', 'label' => 'MAY'], ['color' => 'pink', 'label' => 'JUN'], ['color' => 'navy', 'label' => 'JUL'], ['color' => 'yellow', 'label' => 'AUG'],
        ['color' => 'teal', 'label' => 'SEP'], ['color' => 'orange', 'label' => 'OCT'], ['color' => 'amber', 'label' => 'NOV'], ['color' => 'neutral', 'label' => 'DEC']]" />
    <x-dl::label-sheet title="Labels" :count="5" size="md" paper="a4">
        <x-dl::label kind="location" color="accent" mark="Storage" code="A10-PPR-L03-05" name="Plano Paper · Floor 1 · Warehouse A10" qr="LOC:A10-PPR-L03-05" barcode="A10-PPR-L03-05"
            :lines="[['label' => 'Max weight', 'value' => '3,000 kg'], ['label' => 'Volume', 'value' => '7.5 m³'], ['label' => 'Floor area', 'value' => '4.69 m² × 1.6 m'], ['label' => 'Pallets', 'value' => '4']]" />
        <x-dl::label kind="item" color="accent" mark="Raw Material" code="KR-140" name="White Kraft 140 gsm 79 x 97" highlight="15,000 LBR" qr="ITM:RM:KR-140" barcode="KR-140" tone="danger"
            :lines="[['label' => 'Bin', 'value' => 'A10-PPR-L03-01'], ['label' => 'Arrived', 'value' => '31-Dec-2025'], ['label' => 'Expiry', 'value' => '27-Oct-2026']]"
            :tags="[['label' => 'Dead stock', 'tone' => 'danger'], ['label' => 'Expires soon', 'tone' => 'warn']]" />
        <x-dl::label size="sm" kind="item" color="green" mark="Finished Goods" code="FB-DUPLEX-001" name="Folding Box Duplex 350" highlight="12,000 PCS" qr="ITM:FG:FB-DUPLEX-001" barcode="FB-DUPLEX-001"
            :lines="[['label' => 'Bin', 'value' => 'A10-FG-L01-01'], ['label' => 'Arrived', 'value' => '20-Sep-2026']]" />
        <x-dl::label size="lg" kind="pallet" color="orange" mark="OCT 2026" code="PL2600032" name="PYJ31 · Ivory 300 79 x 109" highlight="6,000 LBR" qr="PLT:PL2600032" barcode="PL2600032"
            footer="After every use: print a new label with the remaining quantity."
            :lines="[['label' => 'Arrived', 'value' => '02-Oct-2026'], ['label' => 'Supplier pallet', 'value' => 'BOHUI-PLT-00921'], ['label' => 'Lot', 'value' => 'BH-2610-A'],
                ['label' => 'Receipt', 'value' => 'SPB2600005'], ['label' => 'Bin', 'value' => 'A10-RCV'], ['label' => 'Expiry', 'value' => '02-Oct-2027'], ['label' => 'Label no.', 'value' => '1']]" />
        <x-dl::label size="lg" kind="pallet" color="teal" mark="SEP 2025" code="PL2600015" name="PZ05649 · Sirio Ultra Black 280 gr 72 x 102" highlight="3,000 LBR" qr="PLT:PL2600015" barcode="PL2600015" tone="danger"
            :lines="[['label' => 'Arrived', 'value' => '02-Sep-2025'], ['label' => 'Supplier pallet', 'value' => 'FSW-250902-022'], ['label' => 'Expiry', 'value' => '02-Sep-2026']]"
            :tags="[['label' => 'Expired', 'tone' => 'danger']]" />
    </x-dl::label-sheet>
</div>
