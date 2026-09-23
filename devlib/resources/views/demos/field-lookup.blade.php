<div class="dl-demo">
    <x-dl::field.lookup name="supplier_id" lookup="supplier" label="Supplier" tooltip="Pilih dari master; syarat bayar & alamat terisi otomatis" placeholder="Cari supplier..." />
    <x-dl::field.text name="payment_term" label="Syarat Bayar" code />
    <x-dl::field.text name="ship_address" label="Alamat Kirim" multiline />
    <x-dl::field.lookup name="uom" lookup="uom" label="Satuan" value="RIM" />
</div>
