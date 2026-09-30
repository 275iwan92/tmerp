<x-dl::form title="Pengajuan Cuti" :actions="['Save' => '💾']">
    <x-dl::form.row>
        <x-dl::field.text name="pegawai" label="Pegawai" value="Budi Santoso (TM-000123)" readonly />
        <x-dl::field.select name="jenis" label="Jenis Cuti" :options="['Tahunan', 'Sakit', 'Melahirkan', 'Penting']" value="Tahunan" required />
    </x-dl::form.row>
    <x-dl::form.row>
        <x-dl::field.date-range name="mulai" name-end="selesai" label="Tanggal Cuti" />
        <x-dl::field.text name="keperluan" label="Keperluan" multiline required />
    </x-dl::form.row>
    <x-dl::approval-bar status="DRAFT" me="Budi Santoso" approver="Joko Santoso (Kepala Produksi)"
        :history="[['status' => 'DRAFT', 'label' => 'DIBUAT', 'by' => 'Budi Santoso', 'at' => '23 Sep 2026 08:12']]" />
</x-dl::form>
