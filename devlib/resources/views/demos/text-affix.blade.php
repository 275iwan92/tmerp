<div class="dl-demo">
    <x-dl::field.text name="harga" label="Harga (prefix)" prefix="IDR" value="1.250.000" code />
    <x-dl::field.text name="diskon" label="Diskon (suffix)" suffix="%" value="10" code />
    <x-dl::field.text name="keterangan" label="Keterangan (maks. 120 karakter)" multiline :maxlength="120" counter />
</div>
