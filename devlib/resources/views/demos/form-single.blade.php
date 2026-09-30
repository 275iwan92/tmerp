<x-dl::form title="Pegawai · TM-00012 Budi Santoso" panel="top" kind="master" status="active"
    :tabs="['utama' => 'Utama', 'pribadi' => 'Data Pribadi', 'alamat' => 'Alamat & Kontak', 'darurat' => 'Kontak Darurat']"
    created-by="johiwan" created-at="2018-01-02 08:10:00" updated-by="annisa" updated-at="2026-09-28 14:22:00">
    <x-slot:utama>
        <x-dl::form.row :cols="3">
            <x-dl::field.text name="employee_code" label="Kode Pegawai" value="TM-00012" code readonly hint="Otomatis saat disimpan pertama kali." />
            <x-dl::field.text name="full_name" label="Nama Lengkap" value="Budi Santoso" required />
            <x-dl::field.text name="nickname" label="Nama Panggilan" value="Budi" />
        </x-dl::form.row>
        <x-dl::form.row :cols="3">
            <x-dl::field.select name="company_id" label="Perusahaan" :options="['1' => 'CV. Telasih Makmur']" value="1" required />
            <x-dl::field.select name="position_id" label="Jabatan" :options="['1' => 'Operator Cetak', '2' => 'Operator Potong', '3' => 'Admin Gudang']" value="1" />
            <x-dl::field.date name="join_date" label="Tanggal Masuk" value="2018-01-02" />
        </x-dl::form.row>
        <x-dl::form.row :cols="3">
            <x-dl::field.select name="work_status" label="Status Kerja" :options="['WORKING' => 'Bekerja', 'DIRUMAHKAN' => 'Dirumahkan', 'CUTI' => 'Cuti']" value="WORKING" required />
            <x-dl::field.date name="work_status_since" label="Status Sejak" value="2018-01-02" />
            <x-dl::field.select name="payment_frequency" label="Frekuensi Gaji" :options="['WEEKLY' => 'Mingguan', 'MONTHLY' => 'Bulanan']" value="WEEKLY" required />
        </x-dl::form.row>
        <x-dl::form.row :cols="3">
            <x-dl::field.select name="default_shift_id" label="Shift Default" :options="['1' => 'S1 · Shift Pagi', '2' => 'S2 · Shift Siang', '3' => 'S3 · Shift Malam']" value="1" />
            <x-dl::field.select name="default_machine_id" label="Mesin Default" :options="['1' => 'PRS-01 · Heidelberg SM74', '2' => 'CUT-01 · Polar 115']" value="1" />
            <x-dl::field.text name="device_pin" label="PIN Fingerprint" value="1012" code :case-toggle="false" />
        </x-dl::form.row>
        <x-dl::form.row :cols="3">
            <x-dl::field.checkbox name="personal_file_complete" label="Berkas Data Diri" text="Lengkap" :value="true" />
            <x-dl::field.select name="deposit_status" label="Deposit" :options="['ADA' => 'Ada', 'TIDAK' => 'Tidak', 'PROSES' => 'Proses', 'BELUM' => 'Belum']" value="ADA" />
            <x-dl::field.date name="resign_date" label="Tanggal Resign" placeholder="—" readonly hint="Terisi lewat proses resign." />
        </x-dl::form.row>
    </x-slot:utama>
    <x-slot:pribadi>
        <x-dl::form.row :cols="3">
            <x-dl::field.radio name="gender" label="Jenis Kelamin" :options="['L' => 'Laki-laki', 'P' => 'Perempuan']" value="L" />
            <x-dl::field.text name="birth_place" label="Tempat Lahir" value="Sidoarjo" />
            <x-dl::field.date name="birth_date" label="Tanggal Lahir" value="1990-03-14" />
        </x-dl::form.row>
        <x-dl::form.row :cols="3">
            <x-dl::field.select name="religion" label="Agama" :options="['ISL' => 'Islam', 'KRS' => 'Kristen Protestan', 'KAT' => 'Katolik', 'HIN' => 'Hindu', 'BUD' => 'Buddha', 'KHC' => 'Khonghucu']" value="ISL" />
            <x-dl::field.select name="marital_status" label="Status Perkawinan" :options="['BELUM' => 'Belum Kawin', 'KAWIN' => 'Kawin', 'CERAI' => 'Cerai']" value="KAWIN" />
            <x-dl::field.masked name="npwp_number" label="NPWP" mask="99.999.999.9-999.999" value="09.254.294.3-407.000" />
        </x-dl::form.row>
        <x-dl::form.row>
            <x-dl::field.text name="nik_ktp" label="NIK (KTP)" value="3515071403900002" :case-toggle="false" maxlength="16" />
            <x-dl::field.text name="no_kk" label="No. KK" value="3515070101150011" :case-toggle="false" maxlength="16" />
        </x-dl::form.row>
    </x-slot:pribadi>
    <x-slot:alamat>
        <x-dl::form.row>
            <x-dl::field.phone name="phone" label="No. HP" value="+6281234567890" />
            <x-dl::field.text name="email_personal" label="Email Pribadi" type="email" value="budi.santoso@gmail.com" />
        </x-dl::form.row>
        <x-dl::form.row>
            <x-dl::field.select name="village_id_ktp" label="Alamat KTP (Desa/Kel, Kec, Kota)" :options="['1' => 'Pepelegi, Waru, Kab. Sidoarjo', '2' => 'Kepuh Kiriman, Waru, Kab. Sidoarjo']" value="1" />
            <x-dl::field.text name="address_detail_ktp" label="Detail (Jalan, RT/RW)" value="Jl. Mawar 12 RT 03/04" />
        </x-dl::form.row>
        <x-dl::field.checkbox name="domisili_same" label="Domisili" text="Sama dengan alamat KTP" :value="true" />
    </x-slot:alamat>
    <x-slot:darurat>
        <x-dl::form.row :cols="3">
            <x-dl::field.text name="emergency_contact_name" label="Nama" value="Siti Aminah" />
            <x-dl::field.select name="emergency_contact_relation" label="Hubungan" :options="['Istri', 'Suami', 'Orang Tua', 'Saudara']" value="Istri" />
            <x-dl::field.phone name="emergency_contact_phone" label="No. HP" value="+6281322221111" />
        </x-dl::form.row>
    </x-slot:darurat>
</x-dl::form>
<p class="dl-note">Single Form: tombol selalu di atas (Nonaktifkan · + New Record · Save, Back di ujung kanan), isi dibagi tab bila tidak muat satu layar, tab Riwayat (read-only) selalu ada. Field abu-abu = read-only (Kode Pegawai, Tanggal Resign).</p>
