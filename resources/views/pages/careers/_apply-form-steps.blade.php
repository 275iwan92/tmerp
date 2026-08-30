{{-- Partial step 1-11 form "Data Calon Karyawan" (LJM/HRD/P01/F02) -- field
     PERSIS SAMA dgn resources/views/pages/recruitment/portal.blade.php (versi
     internal, TIDAK diubah utk pakai partial ini -- sengaja, hindari risiko
     regresi di file yang sudah teruji, lihat docs/recruitmentportal_web_tier1.md).
     Dipakai HANYA oleh careers/apply.blade.php (portal publik profesional).
     Beda 1 hal dari versi internal: field Email di sini READONLY (terkunci ke
     akun terverifikasi candidate, bukan diisi bebas -- versi internal masih
     KTP-only tanpa akun jadi email tetap bisa diedit di sana).
     Butuh property Livewire component induk PERSIS nama yang sama (name,
     nickname, family, education, dst) -- lihat careers/apply.blade.php. --}}
@if ($step === 1)
    <h2 class="text-sm font-bold text-navy mb-3">Data Pribadi</h2>
    @error('name') <p class="text-[11px] text-[#a3241f] mb-2">⚠ {{ $message }}</p> @enderror
    <div class="space-y-3">
        <x-form.input label="No. KTP" wire:model="national_id" readonly class="bg-[#f8fafc]" />
        <div class="grid two">
            <x-form.input label="Nama Lengkap" wire:model="name" />
            <x-form.input label="Nama Panggilan" wire:model="nickname" />
        </div>
        <div class="grid two">
            <x-form.select wire:model="gender" :options="['LAKI_LAKI' => 'Laki-laki', 'PEREMPUAN' => 'Perempuan']"><x-slot:label>Jenis Kelamin</x-slot:label></x-form.select>
            <x-form.input label="Agama" wire:model="religion" />
        </div>
        <x-form.input label="Tanggal Lahir" type="date" wire:model="birth_date" />
        <div class="grid two">
            <x-form.input label="Email" type="email" wire:model="email" readonly class="bg-[#f8fafc]" />
            <x-form.input label="No. HP" wire:model="phone" />
        </div>
        <x-form.textarea rows="2" wire:model="address"><x-slot:label>Alamat Sesuai KTP</x-slot:label></x-form.textarea>
        <x-form.textarea rows="2" wire:model="address_domisili"><x-slot:label>Alamat Tinggal (kalau beda dari KTP)</x-slot:label></x-form.textarea>
        <div class="grid two">
            <x-form.select wire:model="vehicle_type" :options="['MOTOR' => 'Motor', 'MOBIL' => 'Mobil', 'TIDAK_ADA' => 'Tidak Ada']" placeholder="— pilih —"><x-slot:label>Kendaraan yang Dimiliki</x-slot:label></x-form.select>
            <div>
                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">SIM yang Dimiliki</label>
                <div class="flex gap-3 pt-1.5">
                    @foreach (['C', 'A', 'B', 'B1'] as $sim)
                        <label class="flex items-center gap-1.5 text-xs"><input type="checkbox" value="{{ $sim }}" wire:model="sim_types" class="!w-auto"> SIM {{ $sim }}</label>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@elseif ($step === 2)
    <h2 class="text-sm font-bold text-navy mb-3">Data Keluarga</h2>
    <div class="grid two mb-3">
        <x-form.select wire:model="marital_status" :options="['LAJANG' => 'Lajang', 'MENIKAH' => 'Menikah', 'DUDA' => 'Duda', 'JANDA' => 'Janda']" placeholder="— pilih —"><x-slot:label>Status Pernikahan</x-slot:label></x-form.select>
        <x-form.input label="Tanggal Menikah/Bercerai" type="date" wire:model="marital_date" />
    </div>
    <p class="text-[10.5px] font-bold text-muted uppercase mb-2">Susunan Keluarga <span class="font-normal normal-case">(suami/istri, anak, orang tua, saudara kandung)</span></p>
    @foreach ($family as $i => $f)
        <div class="border border-[#eef1f5] rounded-md p-3 mb-2" wire:key="fam-{{ $i }}">
            <div class="grid two mb-2">
                <x-form.select wire:model="family.{{ $i }}.relation_type" :options="['FATHER' => 'Ayah', 'MOTHER' => 'Ibu', 'SPOUSE' => 'Suami/Istri', 'CHILD' => 'Anak', 'SIBLING' => 'Saudara Kandung', 'FATHER_IN_LAW' => 'Mertua Ayah', 'MOTHER_IN_LAW' => 'Mertua Ibu']" :placeholder="false" />
                <x-form.input wire:model="family.{{ $i }}.name" placeholder="Nama" />
            </div>
            <div class="grid gap-2 mb-2" style="grid-template-columns: 90px 100px 1fr;">
                <x-form.select wire:model="family.{{ $i }}.gender" :options="['L' => 'L', 'P' => 'P']" placeholder="L/P" />
                <x-form.input type="number" wire:model="family.{{ $i }}.age" placeholder="Usia" />
                <x-form.input wire:model="family.{{ $i }}.last_education" placeholder="Pendidikan terakhir" />
            </div>
            <x-form.input wire:model="family.{{ $i }}.current_job" placeholder="Pekerjaan" />
            <button type="button" wire:click="removeRow('family', {{ $i }})" class="text-[10.5px] text-[#a3241f] mt-2">✕ Hapus</button>
        </div>
    @endforeach
    <button type="button" wire:click="addRow('family', {{ json_encode(['relation_type' => 'FATHER', 'name' => '', 'gender' => '', 'age' => '', 'last_education' => '', 'current_job' => '', 'current_employer' => '']) }})" class="text-[11px] font-bold text-navy border border-[#d7dbe2] px-3 py-1.5 rounded">＋ Tambah Anggota Keluarga</button>
@elseif ($step === 3)
    <h2 class="text-sm font-bold text-navy mb-3">Riwayat Pendidikan</h2>
    <p class="text-[10.5px] font-bold text-muted uppercase mb-2">Pendidikan Formal</p>
    @foreach ($education as $i => $e)
        <div class="border border-[#eef1f5] rounded-md p-3 mb-2" wire:key="edu-{{ $i }}">
            <div class="grid two mb-2">
                <x-form.input wire:model="education.{{ $i }}.level" placeholder="Jenjang (SLTP/SLTA/D3/S1/S2)" />
                <x-form.input wire:model="education.{{ $i }}.institution" placeholder="Instansi" />
            </div>
            <div class="grid gap-2" style="grid-template-columns: 1fr 90px 90px 90px;">
                <x-form.input wire:model="education.{{ $i }}.major" placeholder="Jurusan" />
                <x-form.input type="number" wire:model="education.{{ $i }}.period_start_year" placeholder="Dari Th" />
                <x-form.input type="number" wire:model="education.{{ $i }}.graduation_year" placeholder="S/d Th" />
                <x-form.input wire:model="education.{{ $i }}.final_score" placeholder="Nilai Akhir" />
            </div>
            <button type="button" wire:click="removeRow('education', {{ $i }})" class="text-[10.5px] text-[#a3241f] mt-2">✕ Hapus</button>
        </div>
    @endforeach
    <button type="button" wire:click="addRow('education', {{ json_encode(['level' => '', 'institution' => '', 'major' => '', 'period_start_year' => '', 'graduation_year' => '', 'final_score' => '']) }})" class="text-[11px] font-bold text-navy border border-[#d7dbe2] px-3 py-1.5 rounded mb-4">＋ Tambah Pendidikan Formal</button>

    <p class="text-[10.5px] font-bold text-muted uppercase mb-2 mt-4">Pendidikan Non-Formal (Kursus/Pelatihan)</p>
    @foreach ($educationNonformal as $i => $e)
        <div class="border border-[#eef1f5] rounded-md p-3 mb-2" wire:key="nonformal-{{ $i }}">
            <div class="grid two mb-2">
                <x-form.input wire:model="educationNonformal.{{ $i }}.course_type" placeholder="Jenis Kursus/Pelatihan" />
                <x-form.input wire:model="educationNonformal.{{ $i }}.institution" placeholder="Nama Lembaga" />
            </div>
            <div class="grid gap-2" style="grid-template-columns: 90px 90px 1fr;">
                <x-form.input type="number" wire:model="educationNonformal.{{ $i }}.period_start_year" placeholder="Dari Th" />
                <x-form.input type="number" wire:model="educationNonformal.{{ $i }}.period_end_year" placeholder="S/d Th" />
                <x-form.input wire:model="educationNonformal.{{ $i }}.certificate_no" placeholder="No. Ijazah/Sertifikat" />
            </div>
            <button type="button" wire:click="removeRow('educationNonformal', {{ $i }})" class="text-[10.5px] text-[#a3241f] mt-2">✕ Hapus</button>
        </div>
    @endforeach
    <button type="button" wire:click="addRow('educationNonformal', {{ json_encode(['course_type' => '', 'institution' => '', 'period_start_year' => '', 'period_end_year' => '', 'certificate_no' => '']) }})" class="text-[11px] font-bold text-navy border border-[#d7dbe2] px-3 py-1.5 rounded">＋ Tambah Kursus/Pelatihan</button>
@elseif ($step === 4)
    <h2 class="text-sm font-bold text-navy mb-3">Pengalaman Organisasi</h2>
    @foreach ($orgExperience as $i => $o)
        <div class="border border-[#eef1f5] rounded-md p-3 mb-2" wire:key="org-{{ $i }}">
            <div class="grid two mb-2">
                <x-form.input wire:model="orgExperience.{{ $i }}.organization_name" placeholder="Nama Organisasi" />
                <x-form.input wire:model="orgExperience.{{ $i }}.position" placeholder="Jabatan" />
            </div>
            <x-form.input wire:model="orgExperience.{{ $i }}.period" placeholder="Periode (mis. 2020-2022)" class="mb-2" />
            <x-form.textarea rows="2" wire:model="orgExperience.{{ $i }}.task_description"><x-slot:label>Uraian Tugas</x-slot:label></x-form.textarea>
            <button type="button" wire:click="removeRow('orgExperience', {{ $i }})" class="text-[10.5px] text-[#a3241f] mt-2">✕ Hapus</button>
        </div>
    @endforeach
    <button type="button" wire:click="addRow('orgExperience', {{ json_encode(['organization_name' => '', 'position' => '', 'period' => '', 'task_description' => '']) }})" class="text-[11px] font-bold text-navy border border-[#d7dbe2] px-3 py-1.5 rounded">＋ Tambah Pengalaman Organisasi</button>
@elseif ($step === 5)
    <h2 class="text-sm font-bold text-navy mb-1">Pengalaman Kerja</h2>
    <p class="text-[10.5px] text-muted mb-3">Urutan dari pengalaman kerja perusahaan terakhir.</p>
    @foreach ($workExp as $i => $w)
        <div class="border border-[#d7dbe2] rounded-md p-3 mb-3" wire:key="work-{{ $i }}">
            <div class="grid two mb-2">
                <x-form.input wire:model="workExp.{{ $i }}.company" placeholder="Nama Perusahaan" />
                <x-form.input wire:model="workExp.{{ $i }}.location" placeholder="Lokasi & Jenis Usaha" />
            </div>
            <div class="grid two mb-2">
                <x-form.input wire:model="workExp.{{ $i }}.employee_count" placeholder="Jumlah Karyawan" />
                <x-form.input wire:model="workExp.{{ $i }}.company_phone" placeholder="No. Telepon Perusahaan" />
            </div>
            <p class="text-[10px] font-bold text-muted uppercase mt-2 mb-1">Jabatan Awal</p>
            <div class="grid gap-2 mb-2" style="grid-template-columns:1fr 130px 130px;">
                <x-form.input wire:model="workExp.{{ $i }}.position" placeholder="Jabatan Awal" />
                <x-form.input type="date" wire:model="workExp.{{ $i }}.start_date" />
                <x-form.input type="date" wire:model="workExp.{{ $i }}.end_date" />
            </div>
            <p class="text-[10px] font-bold text-muted uppercase mt-2 mb-1">Jabatan Terakhir</p>
            <div class="grid gap-2 mb-2" style="grid-template-columns:1fr 130px 130px;">
                <x-form.input wire:model="workExp.{{ $i }}.position_end" placeholder="Jabatan Terakhir" />
                <x-form.input type="date" wire:model="workExp.{{ $i }}.position_end_start_date" />
                <x-form.input type="date" wire:model="workExp.{{ $i }}.position_end_end_date" />
            </div>
            <div class="grid gap-2 mb-2" style="grid-template-columns:1fr 1fr 1fr 1fr;">
                <x-form.input wire:model="workExp.{{ $i }}.subordinate_count_start" placeholder="Bawahan (awal)" />
                <x-form.input wire:model="workExp.{{ $i }}.subordinate_count_end" placeholder="Bawahan (akhir)" />
                <x-form.input type="number" wire:model="workExp.{{ $i }}.salary_start" placeholder="Gaji Awal" />
                <x-form.input type="number" wire:model="workExp.{{ $i }}.salary_end" placeholder="Gaji Terakhir" />
            </div>
            <x-form.input wire:model="workExp.{{ $i }}.benefits" placeholder="Fasilitas selain gaji" class="mb-2" />
            <div class="grid two mb-2">
                <x-form.input wire:model="workExp.{{ $i }}.supervisor_name" placeholder="Atasan Langsung" />
                <x-form.input wire:model="workExp.{{ $i }}.supervisor_phone" placeholder="No. Telp Atasan" />
            </div>
            <x-form.input wire:model="workExp.{{ $i }}.achievement" placeholder="Prestasi" class="mb-2" />
            <x-form.textarea rows="2" wire:model="workExp.{{ $i }}.description" class="mb-2"><x-slot:label>Uraian Pekerjaan</x-slot:label></x-form.textarea>
            <x-form.input wire:model="workExp.{{ $i }}.org_structure_note" placeholder="Gambarkan struktur organisasi yang menunjukkan posisi Anda" class="mb-2" />
            <x-form.textarea rows="2" wire:model="workExp.{{ $i }}.key_challenge" class="mb-2"><x-slot:label>Masalah penting yang pernah dihadapi & cara mengatasinya</x-slot:label></x-form.textarea>
            <x-form.textarea rows="2" wire:model="workExp.{{ $i }}.improvement_made" class="mb-2"><x-slot:label>Perubahan/pembaruan yang pernah Anda lakukan</x-slot:label></x-form.textarea>
            <x-form.input wire:model="workExp.{{ $i }}.leave_reason" placeholder="Alasan Keluar" />
            <button type="button" wire:click="removeRow('workExp', {{ $i }})" class="text-[10.5px] text-[#a3241f] mt-2">✕ Hapus Pengalaman Ini</button>
        </div>
    @endforeach
    <button type="button" wire:click="addRow('workExp', {{ json_encode(['company' => '', 'location' => '', 'business_type' => '', 'employee_count' => '', 'company_phone' => '', 'position' => '', 'start_date' => '', 'end_date' => '', 'position_end' => '', 'position_end_start_date' => '', 'position_end_end_date' => '', 'subordinate_count_start' => '', 'subordinate_count_end' => '', 'salary_start' => '', 'salary_end' => '', 'benefits' => '', 'supervisor_name' => '', 'supervisor_phone' => '', 'achievement' => '', 'description' => '', 'org_structure_note' => '', 'key_challenge' => '', 'improvement_made' => '', 'leave_reason' => '']) }})" class="text-[11px] font-bold text-navy border border-[#d7dbe2] px-3 py-1.5 rounded">＋ Tambah Pengalaman Kerja</button>
@elseif ($step === 6)
    <h2 class="text-sm font-bold text-navy mb-3">Aktivitas Sosial, Kegiatan & Referensi</h2>
    <div class="space-y-3 mb-4">
        <x-form.input label="Apakah Anda memiliki kenalan di perusahaan kami? (nama & jabatan)" wire:model="company_acquaintance" />
        <x-form.input label="Hobi" wire:model="hobby" />
        <x-form.input label="Bagaimana Anda mengisi waktu luang?" wire:model="leisure_activity" />
        <x-form.input label="Topik-topik apa saja yang Anda baca?" wire:model="reading_topics" />
        <x-form.textarea rows="2" wire:model="went_abroad"><x-slot:label>Apakah Anda pernah ke luar negeri? Kapan & untuk keperluan apa?</x-slot:label></x-form.textarea>
    </div>
    <p class="text-[10.5px] font-bold text-muted uppercase mb-2">Referensi <span class="font-normal normal-case">(minimal 2 orang, bukan keluarga)</span></p>
    @foreach ($professionalReferences as $i => $r)
        <div class="grid two gap-2 mb-2" wire:key="ref-{{ $i }}">
            <x-form.input wire:model="professionalReferences.{{ $i }}.name" placeholder="Nama" />
            <div class="flex gap-1">
                <x-form.input wire:model="professionalReferences.{{ $i }}.phone" placeholder="No. Telp" class="flex-1" />
                <button type="button" wire:click="removeRow('professionalReferences', {{ $i }})" class="text-[#a3241f] text-xs px-2">✕</button>
            </div>
        </div>
    @endforeach
    <button type="button" wire:click="addRow('professionalReferences', {{ json_encode(['name' => '', 'phone' => '']) }})" class="text-[11px] font-bold text-navy border border-[#d7dbe2] px-3 py-1.5 rounded mb-4">＋ Tambah Referensi</button>

    <p class="text-[10.5px] font-bold text-muted uppercase mb-2 mt-4">Kontak Darurat <span class="font-normal normal-case">(minimal 3 orang, dengan hubungan & alamat)</span></p>
    @foreach ($emergencyContacts as $i => $c)
        <div class="border border-[#eef1f5] rounded-md p-3 mb-2" wire:key="emg-{{ $i }}">
            <div class="grid two mb-2">
                <x-form.input wire:model="emergencyContacts.{{ $i }}.name" placeholder="Nama" />
                <x-form.input wire:model="emergencyContacts.{{ $i }}.relation" placeholder="Hubungan" />
            </div>
            <div class="grid two mb-2">
                <x-form.input wire:model="emergencyContacts.{{ $i }}.phone" placeholder="No. Telp" />
                <x-form.input wire:model="emergencyContacts.{{ $i }}.address" placeholder="Alamat" />
            </div>
            <button type="button" wire:click="removeRow('emergencyContacts', {{ $i }})" class="text-[10.5px] text-[#a3241f]">✕ Hapus</button>
        </div>
    @endforeach
    <button type="button" wire:click="addRow('emergencyContacts', {{ json_encode(['name' => '', 'relation' => '', 'phone' => '', 'address' => '']) }})" class="text-[11px] font-bold text-navy border border-[#d7dbe2] px-3 py-1.5 rounded">＋ Tambah Kontak Darurat</button>
@elseif ($step === 7)
    <h2 class="text-sm font-bold text-navy mb-3">Minat & Konsep Pribadi</h2>
    <div class="space-y-3">
        <x-form.textarea rows="2" wire:model="why_join_us"><x-slot:label>Mengapa Anda ingin bekerja di Perusahaan kami?</x-slot:label></x-form.textarea>
        <x-form.textarea rows="2" wire:model="what_you_know_about_us"><x-slot:label>Apa yang Anda ketahui mengenai perusahaan kami?</x-slot:label></x-form.textarea>
        <x-form.textarea rows="2" wire:model="contribution_plan"><x-slot:label>Kontribusi apa yang dapat Anda berikan sehubungan dengan posisi yang dilamar?</x-slot:label></x-form.textarea>
        <x-form.textarea rows="2" wire:model="role_plan_if_hired"><x-slot:label>Uraian apa yang akan Anda lakukan jika menduduki posisi tersebut?</x-slot:label></x-form.textarea>
        <x-form.textarea rows="2" wire:model="job_type_preference"><x-slot:label>Jenis pekerjaan apa yang Anda senangi? (Perencana/Pelaksana) — sebutkan alasan</x-slot:label></x-form.textarea>
        <x-form.textarea rows="2" wire:model="job_nature_preference"><x-slot:label>Sifat pekerjaan apa yang Anda senangi? (Penelitian/Teknis/Non-Teknis) — sebutkan alasan</x-slot:label></x-form.textarea>
        <x-form.textarea rows="2" wire:model="work_environment_preference"><x-slot:label>Lingkungan/suasana kerja yang Anda senangi? (Pabrik/Kantor/Lapangan/Laboratorium) — sebutkan alasan</x-slot:label></x-form.textarea>
        <x-form.textarea rows="2" wire:model="hindering_environment"><x-slot:label>Pekerjaan/lingkungan seperti apa yang dapat menghambat pekerjaan & memicu human error?</x-slot:label></x-form.textarea>
        <x-form.textarea rows="2" wire:model="preferred_colleague_type"><x-slot:label>Tipe orang seperti apa yang Anda senangi bekerja sama?</x-slot:label></x-form.textarea>
        <x-form.textarea rows="2" wire:model="difficult_decision_topic"><x-slot:label>Terhadap hal-hal apa Anda paling sulit mengambil keputusan tegas berdampak besar?</x-slot:label></x-form.textarea>
        <x-form.textarea rows="3" wire:model="strengths_weaknesses"><x-slot:label>Apa kelebihan & kekurangan Anda? (minimal masing-masing 5)</x-slot:label></x-form.textarea>
        <x-form.textarea rows="2" wire:model="who_you_ask_when_stuck"><x-slot:label>Jika mengalami kesulitan menyelesaikan masalah, kepada siapa Anda bertanya? Mengapa?</x-slot:label></x-form.textarea>
    </div>
@elseif ($step === 8)
    <h2 class="text-sm font-bold text-navy mb-3">Riwayat Kesehatan</h2>
    <div class="grid gap-2 mb-3" style="grid-template-columns:1fr 1fr 1fr;">
        <x-form.input label="Tinggi Badan (cm)" type="number" wire:model="height_cm" />
        <x-form.input label="Berat Badan (kg)" type="number" wire:model="weight_kg" />
        <x-form.input label="Golongan Darah" wire:model="blood_type" />
    </div>
    <div class="space-y-3">
        <x-form.textarea rows="2" wire:model="illness_history"><x-slot:label>Penyakit apa yang pernah Anda derita? Berapa lama? Bagaimana kondisi saat ini?</x-slot:label></x-form.textarea>
        <x-form.textarea rows="2" wire:model="hospitalized_history"><x-slot:label>Apakah pernah dirawat di RS? Karena apa? Akibat apa yang diderita sekarang?</x-slot:label></x-form.textarea>
        <div class="grid two">
            <x-form.select wire:model="is_smoker" :options="[1 => 'Ya', 0 => 'Tidak']" placeholder="— pilih —"><x-slot:label>Apakah Anda merokok?</x-slot:label></x-form.select>
            <x-form.select wire:model="family_health_ok" :options="[1 => 'Ya, baik-baik saja', 0 => 'Ada masalah']" placeholder="— pilih —"><x-slot:label>Apakah kesehatan keluarga Anda baik?</x-slot:label></x-form.select>
        </div>
        <x-form.textarea rows="2" wire:model="physical_disability"><x-slot:label>Apakah Anda mempunyai gangguan jasmani yang tetap/keras?</x-slot:label></x-form.textarea>
    </div>
@elseif ($step === 9)
    <h2 class="text-sm font-bold text-navy mb-3">Bahasa, Komputer & Info Lowongan</h2>
    <p class="text-[10.5px] font-bold text-muted uppercase mb-2">Penguasaan Bahasa Asing</p>
    @foreach ($languageSkills as $i => $l)
        <div class="grid gap-2 mb-2" style="grid-template-columns:1fr 110px 110px 1fr auto;" wire:key="lang-{{ $i }}">
            <x-form.input wire:model="languageSkills.{{ $i }}.language" placeholder="Bahasa" />
            <x-form.select wire:model="languageSkills.{{ $i }}.written" :options="['S' => 'Tertulis: Sedang', 'CB' => 'Tertulis: Cukup Baik', 'B' => 'Tertulis: Baik']" placeholder="Tertulis" />
            <x-form.select wire:model="languageSkills.{{ $i }}.oral" :options="['S' => 'Lisan: Sedang', 'CB' => 'Lisan: Cukup Baik', 'B' => 'Lisan: Baik']" placeholder="Lisan" />
            <x-form.input wire:model="languageSkills.{{ $i }}.notes" placeholder="Keterangan" />
            <button type="button" wire:click="removeRow('languageSkills', {{ $i }})" class="text-[#a3241f] text-xs">✕</button>
        </div>
    @endforeach
    <button type="button" wire:click="addRow('languageSkills', {{ json_encode(['language' => '', 'written' => '', 'oral' => '', 'notes' => '']) }})" class="text-[11px] font-bold text-navy border border-[#d7dbe2] px-3 py-1.5 rounded mb-4">＋ Tambah Bahasa</button>

    <p class="text-[10.5px] font-bold text-muted uppercase mb-2 mt-4">Penguasaan Komputer</p>
    @foreach ($computerSkills as $i => $c)
        <div class="grid gap-2 mb-2" style="grid-template-columns:1fr 130px 1fr auto;" wire:key="comp-{{ $i }}">
            <x-form.input wire:model="computerSkills.{{ $i }}.skill" placeholder="Skill (mis. Desain Grafis)" />
            <x-form.select wire:model="computerSkills.{{ $i }}.level" :options="['S' => 'Sedang', 'CB' => 'Cukup Baik', 'B' => 'Baik']" placeholder="Level" />
            <x-form.input wire:model="computerSkills.{{ $i }}.notes" placeholder="Keterangan" />
            <button type="button" wire:click="removeRow('computerSkills', {{ $i }})" class="text-[#a3241f] text-xs">✕</button>
        </div>
    @endforeach
    <button type="button" wire:click="addRow('computerSkills', {{ json_encode(['skill' => '', 'level' => '', 'notes' => '']) }})" class="text-[11px] font-bold text-navy border border-[#d7dbe2] px-3 py-1.5 rounded mb-4">＋ Tambah Skill Komputer</button>

    <div class="space-y-3 mt-4">
        <x-form.textarea rows="2" wire:model="still_receiving_aid"><x-slot:label>Apakah Anda masih mendapatkan bantuan? Dari siapa & berapa besar/lamanya?</x-slot:label></x-form.textarea>
        <x-form.textarea rows="2" wire:model="other_dependents"><x-slot:label>Apakah Anda punya tanggungan lain selain anak & istri/suami? Sebutkan & berapa besar</x-slot:label></x-form.textarea>
        <x-form.input label="Dari mana Anda menerima info lowongan di perusahaan kami?" wire:model="source_of_info" />
        <div class="grid two">
            <x-form.input label="Gaji yang diinginkan" type="number" wire:model="expected_salary" />
            <x-form.input label="Kesediaan mulai bekerja" type="date" wire:model="availability_date" />
        </div>
        <x-form.input label="Fasilitas yang diinginkan" wire:model="expected_facility" />
    </div>
@elseif ($step === 10)
    <h2 class="text-sm font-bold text-navy mb-3">Kesediaan & Pernyataan</h2>
    <div class="space-y-2 mb-4">
        @foreach ($this->willingItems as $key => $label)
            <label class="flex items-start gap-2 text-[12px] border border-[#eef1f5] rounded-md p-2.5">
                <input type="checkbox" wire:model="willingness.{{ $key }}" class="!w-auto mt-0.5">
                <span>{{ $label }}</span>
            </label>
        @endforeach
    </div>
    <div class="bg-[#f8fafc] border border-[#eef1f5] rounded-md p-3 text-[11px] text-muted mb-3">
        "Demikian informasi yang saya berikan adalah benar sesuai dengan kondisi saya, jika terdapat ketidaksesuaian dari informasi ini maka saya bersedia untuk berhenti/diberhentikan tanpa pesangon dan kompensasi berupa apapun serta seluruh konsekuensi yang diberikan oleh Telasih Makmur dan bersedia diproses sesuai hukum yang berlaku."
    </div>
    @error('apply') <p class="text-[11px] text-[#a3241f] mb-2">⚠ {{ $message }}</p> @enderror
    <label class="flex items-center gap-2 text-xs mb-2"><input type="checkbox" wire:model="agreement_signed" class="!w-auto"> Saya menyetujui pernyataan di atas</label>
    <x-form.input label="Ketik nama lengkap Anda sebagai tanda tangan" wire:model="signature_name" placeholder="Nama lengkap" />
@elseif ($step === 11)
    <h2 class="text-sm font-bold text-navy mb-3">Upload Dokumen</h2>
    <div class="space-y-3">
        <div>
            <label class="text-[10px] text-muted uppercase font-semibold block mb-1">CV <span class="text-[#a3241f]">*</span></label>
            <input type="file" wire:model="cvFile" class="text-xs">
            @if ($cvFile) <p class="text-[10.5px] text-[#1f8a4c] mt-1">✓ {{ $cvFile->getClientOriginalName() }}</p> @endif
        </div>
        <div>
            <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Ijazah <span class="text-[#a3241f]">*</span></label>
            <input type="file" wire:model="ijazahFile" class="text-xs">
            @if ($ijazahFile) <p class="text-[10.5px] text-[#1f8a4c] mt-1">✓ {{ $ijazahFile->getClientOriginalName() }}</p> @endif
        </div>
    </div>
@endif
