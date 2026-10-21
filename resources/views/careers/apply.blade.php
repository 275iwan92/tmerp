{{--
 REMARK
 Tujuan       : Form lamaran "Data Calon Karyawan" (LJM/HRD/P01/F02): 11 seksi dalam satu halaman, semua isian wajib, seksi berulang berupa baris x-dl::lines, CV dan Ijazah x-dl::field.upload; satu POST multipart. Setelah terkirim tampil kartu "Lamaran Terkirim".
 Sumber hulu  : CareersController::apply / submit, CandidateApplicationService (formValues, columns, validator, submit), CandidateFormOptions (pilihan dan kesediaan), tm2hrr.vac_vacancy.
 Input        : vacancy, f (nilai awal: identitas akun + lamaran terakhir), verified (surel terverifikasi), submitted (uuid lamaran, flash); old() dan galat dari sesi.
 Output       : Satu x-dl::action-form (enctype multipart) berisi x-dl::card per seksi; semua galat dikumpulkan dalam satu x-dl::alert.
 Dipakai oleh : Route careers.apply (middleware portal.candidate).
 Status       : Aktif (T10 tahap 6b, 2026-10-21). Menggantikan pages::careers.apply dan careers._apply-form-steps (Livewire v1).
--}}
@php
    $o = \App\Support\Recruitment\CandidateFormOptions::class;
    $val = fn (string $k) => old($k, data_get($f, $k));
    $err = fn (string $k) => $errors->first($k);
    $svc = app(\App\Services\Portal\CandidateApplicationService::class);
    $none = 'Minimal 1 baris — isi "Tidak ada" kalau memang belum pernah';
@endphp
@extends('careers.layout', ['title' => 'Lamar Posisi'])
@section('content')
    <x-mp::careers.page size="apply">
        @if ($submitted)
            <x-dl::empty-state :title="raw('Lamaran Terkirim')" icon="check" :message="raw('Terima kasih, lamaran Anda untuk '.$vacancy->title.' sudah kami terima. Ref: '.$submitted)">
                <x-dl::button :label="raw('Lihat Lamaran Saya')" :href="route('careers.dashboard')" />
                <x-dl::button variant="secondary" :label="raw('Lowongan Lain')" :href="route('careers.landing')" />
            </x-dl::empty-state>
        @else
            <x-dl::button variant="link" icon="back" :label="raw('Batalkan, kembali ke detail lowongan')" :href="route('careers.vacancy-detail', $vacancy->vacancy_code)" />
            <x-dl::headline>{{ 'Lamar: '.$vacancy->title }}</x-dl::headline>
            <x-dl::hint>Semua field bertanda * wajib diisi sebelum lamaran bisa dikirim.</x-dl::hint>

            @unless ($verified)
                <x-dl::alert tone="warn" :message="raw('Email Anda belum diverifikasi. Anda masih bisa mengisi form ini, tapi tidak bisa mengirim sampai email diverifikasi (cek inbox saat mendaftar).')" />
            @endunless
            @if ($errors->any())
                <x-dl::alert tone="danger" :message="raw('Ada '.$errors->count().' isian yang belum lengkap:')" :items="$errors->all()" />
            @endif

            <x-dl::action-form :action="route('careers.apply.submit', $vacancy->vacancy_code)" enctype="multipart/form-data">
                <x-dl::card :title="raw('1. Data Pribadi')">
                    <x-dl::field.text name="national_id" :label="raw('No. KTP')" :value="$f['national_id']" readonly :case-toggle="false" />
                    <x-dl::form.row :cols="2">
                        <x-dl::field.text name="name" :label="raw('Nama Lengkap')" required :value="$val('name')" :error="$err('name')" />
                        <x-dl::field.text name="nickname" :label="raw('Nama Panggilan')" required :value="$val('nickname')" :error="$err('nickname')" />
                    </x-dl::form.row>
                    <x-dl::form.row :cols="2">
                        <x-dl::field.select name="gender" :label="raw('Jenis Kelamin')" required :options="$o::GENDER" :value="$val('gender')" :error="$err('gender')" />
                        <x-dl::field.text name="religion" :label="raw('Agama')" required :value="$val('religion')" :error="$err('religion')" />
                    </x-dl::form.row>
                    <x-dl::field.date name="birth_date" :label="raw('Tanggal Lahir')" required :value="$val('birth_date')" :error="$err('birth_date')" />
                    <x-dl::form.row :cols="2">
                        <x-dl::field.text name="email" type="email" :label="raw('Email')" :value="$f['email']" readonly :case-toggle="false" />
                        <x-dl::field.text name="phone" :label="raw('No. HP')" required :value="$val('phone')" :error="$err('phone')" :case-toggle="false" />
                    </x-dl::form.row>
                    <x-dl::field.text name="address" multiline :label="raw('Alamat Sesuai KTP')" required :value="$val('address')" :error="$err('address')" />
                    <x-dl::field.text name="address_domisili" multiline :label="raw('Alamat Tinggal (isi sama dgn KTP kalau tidak beda)')" required :value="$val('address_domisili')" :error="$err('address_domisili')" />
                    <x-dl::form.row :cols="2">
                        <x-dl::field.select name="vehicle_type" :label="raw('Kendaraan yang Dimiliki')" required :options="$o::VEHICLE_TYPE" :placeholder="raw('— pilih —')" :value="$val('vehicle_type')" :error="$err('vehicle_type')" />
                        <x-dl::field.checkboxes name="sim_types" :label="raw('SIM yang Dimiliki')" :options="['C' => 'SIM C', 'A' => 'SIM A', 'B' => 'SIM B', 'B1' => 'SIM B1']" :value="$val('sim_types')" />
                    </x-dl::form.row>
                </x-dl::card>

                <x-dl::card :title="raw('2. Data Keluarga')">
                    <x-dl::form.row :cols="2">
                        <x-dl::field.select name="marital_status" :label="raw('Status Pernikahan')" required :options="$o::MARITAL_STATUS" :placeholder="raw('— pilih —')" :value="$val('marital_status')" :error="$err('marital_status')" />
                        <x-dl::field.date name="marital_date" :label="raw('Tanggal Menikah/Bercerai (wajib kalau bukan Lajang)')" :value="$val('marital_date')" :error="$err('marital_date')" />
                    </x-dl::form.row>
                    <x-dl::hint>Susunan Keluarga — Ayah dan Ibu wajib diisi; anggota keluarga lain ditambah lewat tabel di bawahnya.</x-dl::hint>
                    @foreach (['father' => 'Ayah', 'mother' => 'Ibu'] as $who => $title)
                        <x-dl::form.row :cols="4">
                            <x-dl::field.text :name="$who.'[name]'" :label="raw('Nama '.$title)" required :value="$val($who.'.name')" :error="$err($who.'.name')" />
                            <x-dl::field.text :name="$who.'[age]'" :label="raw('Usia '.$title)" :value="$val($who.'.age')" :case-toggle="false" />
                            <x-dl::field.text :name="$who.'[last_education]'" :label="raw('Pendidikan Terakhir '.$title)" :value="$val($who.'.last_education')" />
                            <x-dl::field.text :name="$who.'[current_job]'" :label="raw('Pekerjaan '.$title)" :value="$val($who.'.current_job')" />
                        </x-dl::form.row>
                    @endforeach
                    <x-dl::lines name="family" :title="raw('Anggota Keluarga Lain')" :add-label="raw('＋ Tambah Anggota Keluarga Lain')" :min-rows="0" :columns="$svc->columns('family')" :rows="$val('family')" />
                </x-dl::card>

                <x-dl::card :title="raw('3. Riwayat Pendidikan')">
                    <x-dl::lines name="education" :title="raw('Pendidikan Formal * (minimal 1 baris)')" :add-label="raw('＋ Tambah Pendidikan Formal')" :min-rows="0" :columns="$svc->columns('education')" :rows="$val('education')" />
                    <x-dl::lines name="educationNonformal" :title="raw('Pendidikan Non-Formal (Kursus/Pelatihan) * '.$none)" :add-label="raw('＋ Tambah Kursus/Pelatihan')" :min-rows="0" :columns="$svc->columns('educationNonformal')" :rows="$val('educationNonformal')" />
                </x-dl::card>

                <x-dl::card :title="raw('4. Pengalaman Organisasi *')">
                    <x-dl::lines name="orgExperience" :title="raw($none)" :add-label="raw('＋ Tambah Pengalaman Organisasi')" :min-rows="0" :columns="$svc->columns('orgExperience')" :rows="$val('orgExperience')" />
                </x-dl::card>

                <x-dl::card :title="raw('5. Pengalaman Kerja *')">
                    <x-dl::hint>Minimal 1, urutan dari yang terakhir. Belum pernah bekerja? Tetap tambah 1 baris, isi &quot;Belum ada pengalaman&quot; di Nama Perusahaan.</x-dl::hint>
                    <x-dl::lines name="workExp" :title="raw('Pengalaman Kerja')" :add-label="raw('＋ Tambah Pengalaman Kerja')" :min-rows="0" :columns="$svc->columns('workExp')" :rows="$val('workExp')" />
                </x-dl::card>

                <x-dl::card :title="raw('6. Aktivitas Sosial, Kegiatan & Referensi')">
                    @foreach (['company_acquaintance' => 'Apakah Anda memiliki kenalan di perusahaan kami? (nama & jabatan)', 'hobby' => 'Hobi', 'leisure_activity' => 'Bagaimana Anda mengisi waktu luang?', 'reading_topics' => 'Topik-topik apa saja yang Anda baca?'] as $name => $label)
                        <x-dl::field.text :name="$name" :label="raw($label)" required :value="$val($name)" :error="$err($name)" />
                    @endforeach
                    <x-dl::field.text name="went_abroad" multiline :label="raw('Apakah Anda pernah ke luar negeri? Kapan & untuk keperluan apa?')" required :value="$val('went_abroad')" :error="$err('went_abroad')" />
                    <x-dl::lines name="professionalReferences" :title="raw('Referensi * (minimal 2 orang, bukan keluarga)')" :add-label="raw('＋ Tambah Referensi')" :min-rows="0" :columns="$svc->columns('professionalReferences')" :rows="$val('professionalReferences')" />
                    <x-dl::lines name="emergencyContacts" :title="raw('Kontak Darurat * (minimal 3 orang, dengan hubungan & alamat)')" :add-label="raw('＋ Tambah Kontak Darurat')" :min-rows="0" :columns="$svc->columns('emergencyContacts')" :rows="$val('emergencyContacts')" />
                </x-dl::card>

                <x-dl::card :title="raw('7. Minat & Konsep Pribadi')">
                    @foreach ([
                        'why_join_us' => 'Mengapa Anda ingin bekerja di Perusahaan kami?',
                        'what_you_know_about_us' => 'Apa yang Anda ketahui mengenai perusahaan kami?',
                        'contribution_plan' => 'Kontribusi apa yang dapat Anda berikan sehubungan dengan posisi yang dilamar?',
                        'role_plan_if_hired' => 'Uraian apa yang akan Anda lakukan jika menduduki posisi tersebut?',
                        'job_type_preference' => 'Jenis pekerjaan apa yang Anda senangi? (Perencana/Pelaksana) — sebutkan alasan',
                        'job_nature_preference' => 'Sifat pekerjaan apa yang Anda senangi? (Penelitian/Teknis/Non-Teknis) — sebutkan alasan',
                        'work_environment_preference' => 'Lingkungan/suasana kerja yang Anda senangi? (Pabrik/Kantor/Lapangan/Laboratorium) — sebutkan alasan',
                        'hindering_environment' => 'Pekerjaan/lingkungan seperti apa yang dapat menghambat pekerjaan & memicu human error?',
                        'preferred_colleague_type' => 'Tipe orang seperti apa yang Anda senangi bekerja sama?',
                        'difficult_decision_topic' => 'Terhadap hal-hal apa Anda paling sulit mengambil keputusan tegas berdampak besar?',
                        'strengths_weaknesses' => 'Apa kelebihan & kekurangan Anda? (minimal masing-masing 5)',
                        'who_you_ask_when_stuck' => 'Jika mengalami kesulitan menyelesaikan masalah, kepada siapa Anda bertanya? Mengapa?',
                    ] as $name => $label)
                        <x-dl::field.text :name="$name" multiline :label="raw($label)" required :value="$val($name)" :error="$err($name)" />
                    @endforeach
                </x-dl::card>

                <x-dl::card :title="raw('8. Riwayat Kesehatan')">
                    <x-dl::form.row :cols="3">
                        <x-dl::field.number name="height_cm" :label="raw('Tinggi Badan (cm)')" required :value="$val('height_cm')" :error="$err('height_cm')" />
                        <x-dl::field.number name="weight_kg" :label="raw('Berat Badan (kg)')" required :value="$val('weight_kg')" :error="$err('weight_kg')" />
                        <x-dl::field.text name="blood_type" :label="raw('Golongan Darah')" required :value="$val('blood_type')" :error="$err('blood_type')" />
                    </x-dl::form.row>
                    <x-dl::field.text name="illness_history" multiline :label="raw('Penyakit apa yang pernah Anda derita? Berapa lama? Bagaimana kondisi saat ini?')" required :value="$val('illness_history')" :error="$err('illness_history')" />
                    <x-dl::field.text name="hospitalized_history" multiline :label="raw('Apakah pernah dirawat di RS? Karena apa? Akibat apa yang diderita sekarang?')" required :value="$val('hospitalized_history')" :error="$err('hospitalized_history')" />
                    <x-dl::form.row :cols="2">
                        <x-dl::field.select name="is_smoker" :label="raw('Apakah Anda merokok?')" required :options="$o::YES_NO" :placeholder="raw('— pilih —')" :value="$val('is_smoker')" :error="$err('is_smoker')" />
                        <x-dl::field.select name="family_health_ok" :label="raw('Apakah kesehatan keluarga Anda baik?')" required :options="$o::FAMILY_HEALTH" :placeholder="raw('— pilih —')" :value="$val('family_health_ok')" :error="$err('family_health_ok')" />
                    </x-dl::form.row>
                    <x-dl::field.text name="physical_disability" multiline :label="raw('Apakah Anda mempunyai gangguan jasmani yang tetap/keras?')" required :value="$val('physical_disability')" :error="$err('physical_disability')" />
                </x-dl::card>

                <x-dl::card :title="raw('9. Bahasa, Komputer & Info Lowongan')">
                    <x-dl::lines name="languageSkills" :title="raw('Penguasaan Bahasa Asing * (minimal 1)')" :add-label="raw('＋ Tambah Bahasa')" :min-rows="0" :columns="$svc->columns('languageSkills')" :rows="$val('languageSkills')" />
                    <x-dl::lines name="computerSkills" :title="raw('Penguasaan Komputer * (minimal 1)')" :add-label="raw('＋ Tambah Skill Komputer')" :min-rows="0" :columns="$svc->columns('computerSkills')" :rows="$val('computerSkills')" />
                    <x-dl::field.text name="still_receiving_aid" multiline :label="raw('Apakah Anda masih mendapatkan bantuan? Dari siapa & berapa besar/lamanya?')" required :value="$val('still_receiving_aid')" :error="$err('still_receiving_aid')" />
                    <x-dl::field.text name="other_dependents" multiline :label="raw('Apakah Anda punya tanggungan lain selain anak & istri/suami? Sebutkan & berapa besar')" required :value="$val('other_dependents')" :error="$err('other_dependents')" />
                    <x-dl::field.text name="source_of_info" :label="raw('Dari mana Anda menerima info lowongan di perusahaan kami?')" required :value="$val('source_of_info')" :error="$err('source_of_info')" />
                    <x-dl::form.row :cols="2">
                        <x-dl::field.number name="expected_salary" :label="raw('Gaji yang diinginkan')" required :value="$val('expected_salary')" :error="$err('expected_salary')" />
                        <x-dl::field.date name="availability_date" :label="raw('Kesediaan mulai bekerja')" required :value="$val('availability_date')" :error="$err('availability_date')" />
                    </x-dl::form.row>
                    <x-dl::field.text name="expected_facility" :label="raw('Fasilitas yang diinginkan')" required :value="$val('expected_facility')" :error="$err('expected_facility')" />
                </x-dl::card>

                <x-dl::card :title="raw('10. Kesediaan & Pernyataan (semua wajib dicentang)')">
                    @foreach ($o::WILLING_ITEMS as $key => $label)
                        <x-dl::field.checkbox :name="'willingness['.$key.']'" :text="raw($label)" :value="data_get(old('willingness', $f['willingness']), $key)" />
                    @endforeach
                    <x-dl::hint note>&quot;Demikian informasi yang saya berikan adalah benar sesuai dengan kondisi saya, jika terdapat ketidaksesuaian dari informasi ini maka saya bersedia untuk berhenti/diberhentikan tanpa pesangon dan kompensasi berupa apapun serta seluruh konsekuensi yang diberikan oleh Telasih Makmur dan bersedia diproses sesuai hukum yang berlaku.&quot;</x-dl::hint>
                    <x-dl::field.checkbox name="agreement_signed" :text="raw('Saya menyetujui pernyataan di atas *')" :value="$val('agreement_signed')" :error="$err('agreement_signed')" />
                    <x-dl::field.text name="signature_name" :label="raw('Ketik nama lengkap Anda sebagai tanda tangan')" :placeholder="raw('Nama lengkap')" required :value="$val('signature_name')" :error="$err('signature_name')" />
                </x-dl::card>

                <x-dl::card :title="raw('11. Upload Dokumen')">
                    <x-dl::field.upload name="cv_file" :label="raw('CV')" required :error="$err('cv_file')" />
                    <x-dl::field.upload name="ijazah_file" :label="raw('Ijazah')" required :error="$err('ijazah_file')" />
                </x-dl::card>

                <x-dl::card :title="raw('Kirim Lamaran')">
                    <x-dl::button type="submit" size="lg" variant="success" block :label="raw('Kirim Lamaran')" />
                </x-dl::card>
            </x-dl::action-form>
        @endif
    </x-mp::careers.page>
@endsection
