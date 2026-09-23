<div class="dl-demo">
    <div class="dl-row" style="gap:28px;align-items:flex-end">
        <x-dl::qr text="https://tmerp.test/doc/CO2600011" caption="Tautan dokumen CO" />
        <x-dl::qr text="TM-000123" :size="90" caption="ID pegawai" />
        <x-dl::barcode value="RM-KRT-0012" caption="Kode item" />
        <x-dl::barcode value="8991234567895" format="EAN13" />
        <x-dl::barcode value="LOT-IVR-2608-A" :height="36" />
    </div>
</div>
