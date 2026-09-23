<div class="dl-demo">
    <x-dl::field.number-words name="jumlah" label="Angka biasa" value="1200" />
    <x-dl::field.number-words name="berat" label="Angka desimal (koma)" :decimals="3" value="12.5" />
    <x-dl::field.number-words name="nilai_idr" label="Rupiah" currency="IDR" value="1250000" />
    <x-dl::field.number-words name="nilai" label="Mata uang bisa dipilih" picker currency="USD" value="1234.56" />
</div>
