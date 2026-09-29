<div class="dl-demo">
    <x-dl::field.lookup name="supplier_id" lookup="supplier" label="Supplier" tooltip="Pilih dari master; syarat bayar & alamat terisi otomatis. Ketik nama baru untuk + Tambah." placeholder="Cari supplier..." create />
    <x-dl::field.text name="payment_term" label="Syarat Bayar" code />
    <x-dl::field.text name="ship_address" label="Alamat Kirim" multiline />
    <x-dl::field.lookup name="uom" lookup="uom" label="Satuan" value="RIM" />
    <x-dl::field.lookup name="area_id" lookup="area_remote" label="Wilayah kirim (remote)" tooltip="Data besar: ketik min. 2 huruf, dicari ke server (Lookup::define dengan search + find)." value="7" />
    <x-dl::field.text name="postal" label="Kode Pos" code />
</div>
