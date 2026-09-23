<x-dl::master title="Pegawai" :pipeline="['KONTRAK' => 'Kontrak', 'TETAP' => 'Tetap']">
    <x-dl::grid id="master-employee" :rows="\App\Support\DemoData::employees()" search="Cari nama, kode, jabatan..."
        :columns="[
            ['key' => 'name', 'label' => 'Pegawai', 'type' => 'identity', 'code' => 'code'],
            ['key' => 'dept', 'label' => 'Departemen'],
            ['key' => 'position', 'label' => 'Jabatan'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['TETAP' => 'success', 'KONTRAK' => 'info', 'HARIAN' => 'warn']],
            ['key' => 'join_date', 'label' => 'Tgl Masuk', 'type' => 'date'],
            ['key' => 'city', 'label' => 'Kota'],
        ]"
        :stats="[['label' => 'Total'], ['label' => 'Aktif', 'where' => ['active' => true], 'tone' => 'success'], ['label' => 'Nonaktif', 'where' => ['active' => false], 'tone' => 'danger']]"
        :filters="['dept' => 'Departemen', 'status' => 'Status']" />

    <x-slot:form>
        <x-dl::form.row>
            <x-dl::field.text name="name" label="Nama Lengkap" required />
            <x-dl::field.text name="code" label="Kode Pegawai" code required />
        </x-dl::form.row>
        <x-dl::form.row>
            <x-dl::field.select name="dept" label="Departemen" :options="['Produksi', 'Gudang', 'Sales', 'Keuangan']" required />
            <x-dl::field.text name="position" label="Jabatan" />
        </x-dl::form.row>
        <x-dl::form.row>
            <x-dl::field.radio name="status" label="Status Kerja" :options="['TETAP' => 'Tetap', 'KONTRAK' => 'Kontrak', 'HARIAN' => 'Harian']" />
            <x-dl::field.date name="join_date" label="Tanggal Masuk" />
        </x-dl::form.row>
        <x-dl::form.row>
            <x-dl::field.phone name="phone" label="No. HP" />
            <x-dl::field.select name="city" label="Kota" :options="['Surabaya', 'Sidoarjo', 'Gresik', 'Mojokerto']" />
        </x-dl::form.row>
        <x-dl::field.toggle name="active" label="Status Data" />
    </x-slot:form>
</x-dl::master>
