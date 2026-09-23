<x-dl::form title="Supplier · PT Contoh Kertas Jaya" mode="view" :actions="['Simpan' => '💾']">
    <x-dl::form.row>
        <x-dl::field.text name="nama" label="Nama Supplier" value="PT Contoh Kertas Jaya" required />
        <x-dl::field.text name="kode" label="Kode" value="SUP-001" code />
    </x-dl::form.row>
    <x-dl::form.row>
        <x-dl::field.select name="kota" label="Kota" :options="['Surabaya', 'Sidoarjo', 'Gresik']" value="Surabaya" />
        <x-dl::field.select name="termin" label="Syarat Bayar" :options="['COD', 'Net 14', 'Net 30']" value="Net 30" />
    </x-dl::form.row>
    <x-dl::form.row>
        <x-dl::field.phone name="telp" label="Telepon" kind="landline" value="+62315551234" />
        <x-dl::field.toggle name="aktif" label="Status" :value="true" />
    </x-dl::form.row>
    <x-dl::field.text name="alamat" label="Alamat" value="Jl. Contoh Industri 1, Surabaya" multiline />
</x-dl::form>
<p class="dl-note">Mode lihat: isian tampil sebagai teks. Klik Edit untuk mengubah, Batal mengembalikan nilai semula, Simpan kembali ke mode lihat.</p>
