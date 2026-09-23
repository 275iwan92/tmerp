<div class="dl-demo">
    <x-dl::form.row>
        <x-dl::field.display label="No. PO" value="PO-2609-0001" format="mono" />
        <x-dl::field.display label="Tanggal" value="2026-09-23" format="date" />
    </x-dl::form.row>
    <x-dl::form.row>
        <x-dl::field.display label="Supplier" value="PT Contoh Kertas Jaya" />
        <x-dl::field.display label="Total Tagihan" :value="12500000" format="money" />
    </x-dl::form.row>
</div>
