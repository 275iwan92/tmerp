<div class="dl-demo">
    <div class="dl-kpis">
        <x-dl::kpi label="Penjualan Bulan Ini" :value="2.9e9" :previous="2.67e9" format="money" :spark="[1.8, 2.05, 1.96, 2.31, 2.44, 2.12, 2.67, 2.9]" :target="3e9" icon="money" tone="accent" />
        <x-dl::kpi label="Reject Rate" :value="1.4" :previous="1.9" format="percent" good-when="down" :spark="[2.4, 2.8, 2.1, 1.9, 1.6, 2, 1.9, 1.4]" icon="fa-triangle-exclamation" tone="success" hint="Turun = baik" />
        <x-dl::kpi label="Piutang Jatuh Tempo" :value="845e6" :previous="620e6" format="money" good-when="down" :spark="[400, 450, 520, 480, 560, 600, 620, 845]" icon="fa-file-invoice-dollar" tone="danger" hint="Naik 36% vs bulan lalu" />
        <x-dl::kpi label="Order Tepat Waktu" :value="93" :previous="89" format="percent" :target="95" icon="fa-truck-fast" tone="violet" />
    </div>
    <div class="dl-gauges" style="margin-top:14px">
        <x-dl::gauge label="OEE Mesin Cetak" :value="78.5" :target="85" />
        <x-dl::gauge label="Kapasitas Terpakai" :value="92" :bands="[[70, 'success'], [90, 'warn'], [100, 'danger']]" :target="85" />
        <x-dl::gauge label="Kepuasan Customer" :value="4.3" :min="1" :max="5" unit="" :bands="[[3, 'danger'], [4, 'warn'], [5, 'success']]" />
        <x-dl::progress-ring label="Plan Delivery" caption="46 dari 50 terkirim" :value="92" />
        <x-dl::progress-ring label="Stock Opname" caption="Gudang Bahan Baku" :value="64" />
        <x-dl::progress-ring label="Anggaran Terpakai" caption="Q3 2026" :value="38" tone="accent" />
    </div>
    <div style="margin-top:14px">
        <x-dl::bullet label="Penjualan Q3" sub="vs target" format="money" :value="7.4e9" :target="8e9" :ranges="[5e9, 7e9, 9e9]" />
        <x-dl::bullet label="Produksi (ribu lbr)" sub="vs rencana" :value="620" :target="600" :ranges="[400, 550, 700]" />
        <x-dl::bullet label="Margin Kotor" sub="%" format="percent" :value="24" :target="27" :ranges="[15, 22, 32]" />
    </div>
    <div class="dl-row" style="margin-top:14px;gap:28px">
        <x-dl::traffic-light status="green" label="Arus Kas" note="Saldo aman 2,4 bulan" />
        <x-dl::traffic-light status="amber" label="Stok Kertas Ivory" note="Di bawah min. 3 hari lagi" />
        <x-dl::traffic-light status="red" label="Mesin Pond 2" note="Breakdown sejak 08:10" />
    </div>
</div>
