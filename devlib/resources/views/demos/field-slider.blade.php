<div class="dl-demo">
    <x-dl::field.slider name="biaya" label="Pengeluaran Bulanan (satu nilai)" :max="100000000" :step="1000000" :value="15000000" format="IDR {v}" :ticks="5" />
    <x-dl::field.slider name="harga" label="Rentang Harga (dua gagang)" range :max="5000000" :step="50000" :value="750000" :value-to="2500000" format="IDR {v}" :ticks="6" />
    <x-dl::field.slider name="gramatur" label="Gramatur Kertas" range :min="60" :max="400" :step="10" :value="150" :value-to="310" format="{v} gsm" :ticks="5" />
    <div class="dl-row" style="align-items:flex-start;gap:40px">
        <x-dl::field.slider name="tinta" label="Sisa Tinta (vertikal)" orientation="vertical" :value="60" format="{v}%" :ticks="5" />
        <x-dl::field.slider name="suhu" label="Suhu Ruang Cetak (vertikal, rentang)" orientation="vertical" range :min="15" :max="35" :value="20" :value-to="26" format="{v} °C" :ticks="5" />
    </div>
</div>
