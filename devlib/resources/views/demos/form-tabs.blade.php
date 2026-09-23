<x-dl::form title="Data Customer (form dengan tab)" :actions="['Simpan' => '💾']">
    <x-dl::tabs :tabs="['umum' => 'Informasi Umum', 'alamat' => 'Alamat & Kontak', 'pajak' => 'Pajak & Keuangan']">
        <x-slot:umum>
            <x-dl::form.row><x-dl::field.text name="nama" label="Nama Perusahaan" required /><x-dl::field.select name="kategori" label="Kategori" :options="['FMCG', 'Farmasi', 'Kosmetik']" required /></x-dl::form.row>
            <x-dl::field.text name="catatan" label="Catatan" multiline />
        </x-slot:umum>
        <x-slot:alamat>
            <x-dl::field.text name="alamat" label="Alamat" multiline required />
            <x-dl::form.row><x-dl::field.phone name="telp" label="Telepon Kantor" kind="landline" required /><x-dl::field.text name="email" label="Email" type="email" /></x-dl::form.row>
        </x-slot:alamat>
        <x-slot:pajak>
            <x-dl::form.row><x-dl::field.masked name="npwp" label="NPWP" mask="99.999.999.9-999.999" required /><x-dl::field.select name="termin" label="Syarat Bayar" :options="['COD', 'Net 14', 'Net 30']" /></x-dl::form.row>
            <x-dl::field.money name="limit" label="Limit Kredit" currency="IDR" />
        </x-slot:pajak>
    </x-dl::tabs>
</x-dl::form>
<p class="dl-note">Klik Simpan dalam keadaan kosong: tab yang berisi isian wajib kosong diberi tanda merah berangka dan dibuka otomatis.</p>
