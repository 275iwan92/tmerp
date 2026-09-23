<x-dl::wizard title="Registrasi Customer Baru" finish-label="Daftarkan">
    <x-dl::wizard.step title="Identitas" icon="fa-building" hint="Data badan usaha sesuai NPWP.">
        <x-dl::form.row>
            <x-dl::field.text name="company" label="Nama Perusahaan" required />
            <x-dl::field.masked name="npwp" label="NPWP" mask="99.999.999.9-999.999" />
        </x-dl::form.row>
        <x-dl::form.row>
            <x-dl::field.select name="category" label="Kategori" :options="['FMCG', 'Farmasi', 'Kosmetik', 'Makanan & Minuman']" required />
            <x-dl::field.radio name="type" label="Bentuk Usaha" :options="['PT' => 'PT', 'CV' => 'CV', 'UD' => 'UD / Perorangan']" value="PT" />
        </x-dl::form.row>
    </x-dl::wizard.step>
    <x-dl::wizard.step title="Kontak" icon="fa-address-book">
        <x-dl::form.row>
            <x-dl::field.text name="pic" label="Nama PIC" required />
            <x-dl::field.phone name="pic_phone" label="No. HP PIC" required />
        </x-dl::form.row>
        <x-dl::form.row>
            <x-dl::field.text name="email" label="Email" type="email" />
            <x-dl::field.phone name="office_phone" label="Telepon Kantor" kind="landline" />
        </x-dl::form.row>
        <x-dl::field.text name="address" label="Alamat" multiline required />
    </x-dl::wizard.step>
    <x-dl::wizard.step title="Keuangan" icon="money">
        <x-dl::form.row>
            <x-dl::field.money name="credit_limit" label="Limit Kredit" />
            <x-dl::field.select name="term" label="Syarat Bayar" :options="['COD', 'Net 14', 'Net 30', 'Net 45']" required />
        </x-dl::form.row>
        <x-dl::field.toggle name="ppn" label="Faktur Pajak (PPN)" on-label="PPN" off-label="Non PPN" :value="true" />
    </x-dl::wizard.step>
</x-dl::wizard>
