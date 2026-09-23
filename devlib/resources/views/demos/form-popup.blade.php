<div class="dl-forms-row">
    <x-dl::button icon="new" label="Tambah Satuan (pop-up)" data-dl-open="popup-uom" />
    <x-dl::button variant="secondary" icon="new" label="Tambah Kontak (drawer kanan)" data-dl-open="drawer-contact" />
</div>
<x-dl::modal id="popup-uom" title="Satuan Baru" icon="fa-ruler">
    <x-dl::form :actions="['Simpan' => '💾']">
        <x-dl::field.text name="kode" label="Kode Satuan" code required />
        <x-dl::field.text name="nama" label="Nama Satuan" required />
        <x-dl::field.decimal name="konversi" label="Konversi ke Satuan Dasar" :decimals="3" />
    </x-dl::form>
</x-dl::modal>
<x-dl::drawer id="drawer-contact" title="Kontak Baru" icon="users" width="420px">
    <x-dl::form :actions="['Simpan' => '💾']">
        <x-dl::field.text name="nama" label="Nama" required />
        <x-dl::field.text name="jabatan" label="Jabatan" />
        <x-dl::field.phone name="hp" label="No. HP" required />
        <x-dl::field.text name="email" label="Email" type="email" />
    </x-dl::form>
</x-dl::drawer>
<p class="dl-note">Form di dalam pop-up / drawer menutup sendiri setelah tersimpan; isian wajib tetap dicek.</p>
