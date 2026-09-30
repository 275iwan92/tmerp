@php
    // Siklus dokumen di DevLib: form dikirim (GET) ke halaman ini dgn _action; status baru ditentukan "server" (blok ini).
    // Awal selalu Draft -> Confirm = Confirmed (isi terkunci) -> Cancel = Cancelled; Back to Draft (Mode Developer) = Draft lagi.
    $act = request('_action');
    $status = match ($act) {
        'confirm' => 'confirmed', 'draft', 'save' => 'draft', 'cancel' => 'cancelled', 'delete' => 'new',
        default => request()->boolean('new') ? 'new' : request('status', 'draft'),
    };
    $isNew = $status === 'new';
    $sample = [
        'employee_code' => 'TM-00012', 'full_name' => 'Budi Santoso', 'nickname' => 'Budi', 'company_id' => '1', 'position_id' => '1',
        'join_date' => '2018-01-02', 'work_status' => 'WORKING', 'work_status_since' => '2018-01-02', 'payment_frequency' => 'WEEKLY',
        'default_shift_id' => '1', 'default_machine_id' => '1', 'device_pin' => '1012', 'personal_file_complete' => true, 'deposit_status' => 'ADA',
        'gender' => 'L', 'birth_place' => 'Sidoarjo', 'birth_date' => '1990-03-14', 'religion' => 'ISL', 'marital_status' => 'KAWIN',
        'npwp_number' => '09.254.294.3-407.000', 'nik_ktp' => '3515071403900002', 'no_kk' => '3515070101150011',
        'phone' => '+6281234567890', 'email_personal' => 'budi.santoso@gmail.com', 'village_id_ktp' => '1', 'address_detail_ktp' => 'Jl. Mawar 12 RT 03/04', 'domisili_same' => true,
        'emergency_contact_name' => 'Siti Aminah', 'emergency_contact_relation' => 'Istri', 'emergency_contact_phone' => '+6281322221111',
    ];
    $e = $isNew ? ['company_id' => '1', 'work_status' => 'WORKING'] : $sample;
    // Isian yang baru dikirim menang atas data contoh (field terkunci tidak ikut terkirim -> pakai data contoh).
    $v = fn ($k) => ! $isNew && request()->has($k) ? request($k) : ($e[$k] ?? null);
    $name = $v('full_name') ?: 'Budi Santoso';
    // Simulasi login: Back to Draft = hak khusus (super-admin, admin-app, atau group yang diberi hak itu), bukan Mode Developer.
    $roles = ['super-admin' => 'super-admin', 'admin-app' => 'admin-app', 'entry-reopen' => 'data-entry + hak Back to Draft', 'entry' => 'data-entry biasa'];
    $as = array_key_exists(request('as'), $roles) ? request('as') : 'super-admin';
    $canReopen = $as !== 'entry';
@endphp
<div class="dl-row" style="margin-bottom:8px">
    <span class="dl-note" style="margin:0">Simulasi login sebagai:</span>
    @foreach ($roles as $rk => $rl)
        <x-dl::button size="sm" :variant="$rk === $as ? 'primary' : 'secondary'" :label="$rl" :href="url()->current().'?'.http_build_query(['as' => $rk, 'status' => $isNew ? 'draft' : $status])" />
    @endforeach
</div>
<x-dl::form :title="$isNew ? 'Pegawai · Baru' : 'Pegawai · TM-00012 '.$name" panel="top" kind="document" :status="$status"
    :action="url()->current()" method="GET" :new-url="url()->current().'?new=1&as='.$as" :can-reopen="$canReopen" :hidden="['as' => $as]"
    :tabs="['utama' => 'Utama', 'pribadi' => 'Data Pribadi', 'alamat' => 'Alamat & Kontak', 'darurat' => 'Kontak Darurat']"
    :created-by="$isNew ? null : 'johiwan'" :created-at="$isNew ? null : '2018-01-02 08:10:00'"
    :updated-by="$isNew ? null : ($act ? 'johiwan' : 'annisa')" :updated-at="$isNew ? null : ($act ? now()->format('Y-m-d H:i:s') : '2026-09-28 14:22:00')">
    <x-slot:utama>
        <x-dl::form.row :cols="3">
            <x-dl::field.text name="employee_code" label="Kode Pegawai" :value="$v('employee_code')" placeholder="(otomatis)" code readonly :case-toggle="false" hint="Otomatis saat disimpan pertama kali." />
            <x-dl::field.text name="full_name" label="Nama Lengkap" :value="$v('full_name')" required />
            <x-dl::field.text name="nickname" label="Nama Panggilan" :value="$v('nickname')" />
        </x-dl::form.row>
        <x-dl::form.row :cols="3">
            <x-dl::field.select name="company_id" label="Perusahaan" :options="['1' => 'CV. Telasih Makmur']" :value="$v('company_id')" required />
            <x-dl::field.select name="position_id" label="Jabatan" :options="['1' => 'Operator Cetak', '2' => 'Operator Potong', '3' => 'Admin Gudang']" :value="$v('position_id')" />
            <x-dl::field.date name="join_date" label="Tanggal Masuk" :value="$v('join_date')" placeholder="—" />
        </x-dl::form.row>
        <x-dl::form.row :cols="3">
            <x-dl::field.select name="work_status" label="Status Kerja" :options="['WORKING' => 'Bekerja', 'DIRUMAHKAN' => 'Dirumahkan', 'CUTI' => 'Cuti']" :value="$v('work_status')" required />
            <x-dl::field.date name="work_status_since" label="Status Sejak" :value="$v('work_status_since')" placeholder="—" />
            <x-dl::field.select name="payment_frequency" label="Frekuensi Gaji" :options="['WEEKLY' => 'Mingguan', 'MONTHLY' => 'Bulanan']" :value="$v('payment_frequency')" required />
        </x-dl::form.row>
        <x-dl::form.row :cols="3">
            <x-dl::field.select name="default_shift_id" label="Shift Default" :options="['1' => 'S1 · Shift Pagi', '2' => 'S2 · Shift Siang', '3' => 'S3 · Shift Malam']" :value="$v('default_shift_id')" />
            <x-dl::field.select name="default_machine_id" label="Mesin Default" :options="['1' => 'PRS-01 · Heidelberg SM74', '2' => 'CUT-01 · Polar 115']" :value="$v('default_machine_id')" />
            <x-dl::field.text name="device_pin" label="PIN Fingerprint" :value="$v('device_pin')" code :case-toggle="false" />
        </x-dl::form.row>
        <x-dl::form.row :cols="3">
            <x-dl::field.checkbox name="personal_file_complete" label="Berkas Data Diri" text="Lengkap" :value="(bool) $v('personal_file_complete')" />
            <x-dl::field.select name="deposit_status" label="Deposit" :options="['ADA' => 'Ada', 'TIDAK' => 'Tidak', 'PROSES' => 'Proses', 'BELUM' => 'Belum']" :value="$v('deposit_status')" />
            <x-dl::field.date name="resign_date" label="Tanggal Resign" placeholder="—" readonly hint="Terisi lewat proses resign." />
        </x-dl::form.row>
    </x-slot:utama>
    <x-slot:pribadi>
        <x-dl::form.row :cols="3">
            <x-dl::field.radio name="gender" label="Jenis Kelamin" :options="['L' => 'Laki-laki', 'P' => 'Perempuan']" :value="$v('gender')" />
            <x-dl::field.text name="birth_place" label="Tempat Lahir" :value="$v('birth_place')" />
            <x-dl::field.date name="birth_date" label="Tanggal Lahir" :value="$v('birth_date')" placeholder="—" />
        </x-dl::form.row>
        <x-dl::form.row :cols="3">
            <x-dl::field.select name="religion" label="Agama" :options="['ISL' => 'Islam', 'KRS' => 'Kristen Protestan', 'KAT' => 'Katolik', 'HIN' => 'Hindu', 'BUD' => 'Buddha', 'KHC' => 'Khonghucu']" :value="$v('religion')" />
            <x-dl::field.select name="marital_status" label="Status Perkawinan" :options="['BELUM' => 'Belum Kawin', 'KAWIN' => 'Kawin', 'CERAI' => 'Cerai']" :value="$v('marital_status')" />
            <x-dl::field.masked name="npwp_number" label="NPWP" mask="99.999.999.9-999.999" :value="$v('npwp_number')" />
        </x-dl::form.row>
        <x-dl::form.row :cols="3">
            <x-dl::field.text name="nik_ktp" label="NIK (KTP)" :value="$v('nik_ktp')" :case-toggle="false" maxlength="16" />
            <x-dl::field.text name="no_kk" label="No. KK" :value="$v('no_kk')" :case-toggle="false" maxlength="16" />
        </x-dl::form.row>
    </x-slot:pribadi>
    <x-slot:alamat>
        <x-dl::form.row>
            <x-dl::field.phone name="phone" label="No. HP" :value="$v('phone')" />
            <x-dl::field.text name="email_personal" label="Email Pribadi" type="email" :value="$v('email_personal')" />
        </x-dl::form.row>
        <x-dl::form.row>
            <x-dl::field.select name="village_id_ktp" label="Alamat KTP (Desa/Kel, Kec, Kota)" :options="['1' => 'Pepelegi, Waru, Kab. Sidoarjo', '2' => 'Kepuh Kiriman, Waru, Kab. Sidoarjo']" :value="$v('village_id_ktp')" />
            <x-dl::field.text name="address_detail_ktp" label="Detail (Jalan, RT/RW)" :value="$v('address_detail_ktp')" />
        </x-dl::form.row>
        <x-dl::field.checkbox name="domisili_same" label="Domisili" text="Sama dengan alamat KTP" :value="(bool) $v('domisili_same')" />
    </x-slot:alamat>
    <x-slot:darurat>
        <x-dl::form.row :cols="3">
            <x-dl::field.text name="emergency_contact_name" label="Nama" :value="$v('emergency_contact_name')" />
            <x-dl::field.select name="emergency_contact_relation" label="Hubungan" :options="['Istri', 'Suami', 'Orang Tua', 'Saudara']" :value="$v('emergency_contact_relation')" />
            <x-dl::field.phone name="emergency_contact_phone" label="No. HP" :value="$v('emergency_contact_phone')" />
        </x-dl::form.row>
    </x-slot:darurat>
</x-dl::form>
<p class="dl-note">Siklus: status awal selalu <b>Draft</b> (Delete · + New Record · Confirm · Save). <b>Confirm</b> -> Confirmed: isi terkunci, tombol jadi Cancel · + New Record · Email · Print. <b>Back to Draft</b> muncul di sebelah Cancel hanya untuk login yang punya haknya — coba ganti "Simulasi login sebagai" di atas. Tab Riwayat (read-only) selalu ada.</p>
