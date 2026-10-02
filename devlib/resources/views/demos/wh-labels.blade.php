{{-- Contoh x-dl::label (tiga ukuran, pita merah = dead stock), x-dl::label-sheet (lembar cetak) dan x-dl::scan (kotak pindai). --}}
<div class="dl-demo">
    <x-dl::label-sheet title="Location Labels" :count="3">
        <x-dl::label title="Location" code="A10-PPR-L03-05" name="Plano Paper · Floor 1 · Warehouse A10" qr="LOC:A10-PPR-L03-05" barcode="A10-PPR-L03-05"
            :lines="[['label' => 'Capacity', 'value' => '3,000 kg'], ['label' => 'Pallets', 'value' => '4']]" />
        <x-dl::label title="Raw Material" code="KR-140" name="White Kraft 140 gsm 79 x 97" qr="ITM:RM:KR-140" barcode="KR-140" tone="danger" footer="DEAD STOCK"
            :lines="[['label' => 'Qty', 'value' => '15,000 sheets'], ['label' => 'Bin', 'value' => 'A10-PPR-L03-01'], ['label' => 'Last move', 'value' => '31-Dec-2025']]" />
        <x-dl::label size="sm" title="Location" code="A10-INK-R01-02-1" name="Ink & Chemicals" qr="LOC:A10-INK-R01-02-1" barcode="A10-INK-R01-02-1"
            :lines="[['label' => 'Capacity', 'value' => '300 kg']]" />
    </x-dl::label-sheet>
    <x-dl::divider label="Scan" />
    <x-dl::scan name="code" label="Scan or type" hint="A scanner types the code and presses Enter by itself. The camera button appears on a phone over https." :autofocus="false" />
</div>
