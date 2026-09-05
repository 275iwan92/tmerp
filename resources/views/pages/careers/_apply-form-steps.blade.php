{{-- Form "Data Calon Karyawan" (LJM/HRD/P01/F02) -- SEMUA seksi tampil
     sekaligus 1 halaman (revisi user 2026-08-30: "saat pengisian langsung
     terbuka semuanya dalam model tabular yang jelas", bukan wizard
     step-per-step lagi). Seksi berulang (keluarga/pendidikan/dst) memakai
     <table> literal ("model tabular yang jelas") KECUALI Pengalaman Kerja
     (terlalu banyak kolom per baris utk tabel wajar, tetap kartu per-entri).
     SEMUA field wajib diisi (instruksi user "harus di isi semua tidak
     terkecuali") -- validasi lengkap ada di submitApplication() pemanggil,
     bukan di sini; file ini murni markup. Field Nama Ayah & Ibu di seksi
     Keluarga di-PRA-ISI 2 baris terkunci (tak bisa dihapus) supaya strukturnya
     tak mungkin kosong, bukan divalidasi cari-cari di array bebas.

     SUMBER TUNGGAL utk portal (careers/apply.blade.php) MAUPUN layar review
     HR (recruitment/candidate-review/detail.blade.php, keputusan user
     2026-09-05: "ubah salah satu harus otomatis berlaku ke yang lain").
     Toggle `$readonly` (default false) menentukan mode: false = form
     interaktif (wire:model, tombol tambah/hapus baris), true = tampilan
     baca-saja (label+nilai via <x-form.display>, tanpa tombol edit).
     Pemanggil WAJIB expose property dgn nama PERSIS sama (name, nickname,
     family, education, dst) baik dlm mode edit maupun readonly. Label/opsi
     pilihan (gender, status pernikahan, dst) diambil dari
     App\Support\Recruitment\CandidateFormOptions supaya teks pilihan juga
     1 sumber. --}}
@php
    use App\Support\Recruitment\CandidateFormOptions;
    $readonly = $readonly ?? false;
@endphp
<div class="space-y-8">

    {{-- ============ 1. DATA PRIBADI ============ --}}
    <section id="sec-pribadi">
        <h2 class="text-sm font-bold text-navy mb-3 pb-2 border-b border-[#eef1f5]">1. Data Pribadi</h2>
        <div class="space-y-3">
            @if ($readonly)
                <x-form.display label="No. KTP">{{ $national_id }}</x-form.display>
            @else
                <x-form.input label="No. KTP" wire:model="national_id" readonly class="bg-[#f8fafc]" />
            @endif
            <div class="grid two">
                @if ($readonly)
                    <x-form.display label="Nama Lengkap">{{ $name }}</x-form.display>
                    <x-form.display label="Nama Panggilan">{{ $nickname }}</x-form.display>
                @else
                    <x-form.input label="Nama Lengkap *" wire:model="name" />
                    <x-form.input label="Nama Panggilan *" wire:model="nickname" />
                @endif
            </div>
            <div class="grid two">
                @if ($readonly)
                    <x-form.display label="Jenis Kelamin">{{ CandidateFormOptions::GENDER[$gender] ?? '—' }}</x-form.display>
                    <x-form.display label="Agama">{{ $religion }}</x-form.display>
                @else
                    <x-form.select wire:model="gender" :options="CandidateFormOptions::GENDER"><x-slot:label>Jenis Kelamin *</x-slot:label></x-form.select>
                    <x-form.input label="Agama *" wire:model="religion" />
                @endif
            </div>
            @if ($readonly)
                <x-form.display label="Tanggal Lahir">{{ $birth_date }}</x-form.display>
            @else
                <x-form.date-picker label="Tanggal Lahir *" wire:model="birth_date" />
            @endif
            <div class="grid two">
                @if ($readonly)
                    <x-form.display label="Email">{{ $email }}</x-form.display>
                    <x-form.display label="No. HP">{{ $phone }}</x-form.display>
                @else
                    <x-form.input label="Email" type="email" wire:model="email" readonly class="bg-[#f8fafc]" />
                    <x-form.input label="No. HP *" wire:model="phone" />
                @endif
            </div>
            @if ($readonly)
                <x-form.display label="Alamat Sesuai KTP">{{ $address }}</x-form.display>
                <x-form.display label="Alamat Tinggal">{{ $address_domisili }}</x-form.display>
            @else
                <x-form.textarea rows="2" wire:model="address"><x-slot:label>Alamat Sesuai KTP *</x-slot:label></x-form.textarea>
                <x-form.textarea rows="2" wire:model="address_domisili"><x-slot:label>Alamat Tinggal (isi sama dgn KTP kalau tidak beda) *</x-slot:label></x-form.textarea>
            @endif
            <div class="grid two">
                @if ($readonly)
                    <x-form.display label="Kendaraan yang Dimiliki">{{ CandidateFormOptions::VEHICLE_TYPE[$vehicle_type] ?? '—' }}</x-form.display>
                    <x-form.display label="SIM yang Dimiliki">{{ implode(', ', $sim_types ?: []) ?: '—' }}</x-form.display>
                @else
                    <x-form.select wire:model="vehicle_type" :options="CandidateFormOptions::VEHICLE_TYPE" placeholder="— pilih —"><x-slot:label>Kendaraan yang Dimiliki *</x-slot:label></x-form.select>
                    <div>
                        <label class="text-[10px] text-muted uppercase font-semibold block mb-1">SIM yang Dimiliki</label>
                        <div class="flex gap-3 pt-1.5">
                            @foreach (['C', 'A', 'B', 'B1'] as $sim)
                                <label class="flex items-center gap-1.5 text-xs"><input type="checkbox" value="{{ $sim }}" wire:model="sim_types" class="!w-auto"> SIM {{ $sim }}</label>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- ============ 2. DATA KELUARGA ============ --}}
    <section id="sec-keluarga">
        <h2 class="text-sm font-bold text-navy mb-3 pb-2 border-b border-[#eef1f5]">2. Data Keluarga</h2>
        <div class="grid two mb-4">
            @if ($readonly)
                <x-form.display label="Status Pernikahan">{{ CandidateFormOptions::MARITAL_STATUS[$marital_status] ?? '—' }}</x-form.display>
                <x-form.display label="Tanggal Menikah/Bercerai">{{ $marital_date }}</x-form.display>
            @else
                <x-form.select wire:model="marital_status" :options="CandidateFormOptions::MARITAL_STATUS" placeholder="— pilih —"><x-slot:label>Status Pernikahan *</x-slot:label></x-form.select>
                <x-form.date-picker label="Tanggal Menikah/Bercerai (wajib kalau bukan Lajang)" wire:model="marital_date" />
            @endif
        </div>

        <p class="text-[10.5px] font-bold text-muted uppercase mb-2">Susunan Keluarga</p>
        <div class="overflow-x-auto border border-[#eef1f5] rounded-md">
            <table class="w-full text-[11.5px]">
                <thead class="bg-[#f8fafc] text-[10px] uppercase text-muted">
                    <tr>
                        <th class="text-left px-2 py-2 w-[110px]">Hubungan</th>
                        <th class="text-left px-2 py-2">Nama *</th>
                        <th class="text-left px-2 py-2 w-[60px]">L/P</th>
                        <th class="text-left px-2 py-2 w-[70px]">Usia</th>
                        <th class="text-left px-2 py-2">Pendidikan Terakhir</th>
                        <th class="text-left px-2 py-2">Pekerjaan</th>
                        @unless ($readonly) <th class="w-[40px]"></th> @endunless
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#eef1f5]">
                    @forelse ($family as $i => $f)
                        <tr wire:key="fam-{{ $i }}" class="{{ in_array($f['relation_type'] ?? '', ['FATHER', 'MOTHER']) ? 'bg-[#fffaf0]' : '' }}">
                            <td class="px-2 py-1.5 font-bold text-navy">
                                @if ($readonly)
                                    {{ CandidateFormOptions::FAMILY_RELATION_ALL[$f['relation_type'] ?? ''] ?? ($f['relation_type'] ?? '—') }}
                                @elseif (in_array($f['relation_type'] ?? '', ['FATHER', 'MOTHER']))
                                    {{ $f['relation_type'] === 'FATHER' ? 'Ayah' : 'Ibu' }}
                                    <input type="hidden" wire:model="family.{{ $i }}.relation_type">
                                @else
                                    <x-form.select wire:model="family.{{ $i }}.relation_type" :options="CandidateFormOptions::FAMILY_RELATION_EDITABLE" :placeholder="false" />
                                @endif
                            </td>
                            @if ($readonly)
                                <td class="px-2 py-1.5">{{ $f['name'] ?? '' }}</td>
                                <td class="px-2 py-1.5">{{ $f['gender'] ?? '' }}</td>
                                <td class="px-2 py-1.5">{{ $f['age'] ?? '' }}</td>
                                <td class="px-2 py-1.5">{{ $f['last_education'] ?? '' }}</td>
                                <td class="px-2 py-1.5">{{ $f['current_job'] ?? '' }}</td>
                            @else
                                <td class="px-2 py-1.5"><x-form.input wire:model="family.{{ $i }}.name" placeholder="Nama" /></td>
                                <td class="px-2 py-1.5"><x-form.select wire:model="family.{{ $i }}.gender" :options="['L' => 'L', 'P' => 'P']" placeholder="-" /></td>
                                <td class="px-2 py-1.5"><x-form.input type="number" wire:model="family.{{ $i }}.age" placeholder="-" /></td>
                                <td class="px-2 py-1.5"><x-form.input wire:model="family.{{ $i }}.last_education" placeholder="-" /></td>
                                <td class="px-2 py-1.5"><x-form.input wire:model="family.{{ $i }}.current_job" placeholder="-" /></td>
                                <td class="px-2 py-1.5 text-center">
                                    @unless (in_array($f['relation_type'] ?? '', ['FATHER', 'MOTHER']))
                                        <button type="button" wire:click="removeRow('family', {{ $i }})" class="text-[#a3241f] text-xs">✕</button>
                                    @endunless
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-2 py-3 text-center text-muted">Belum ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @unless ($readonly)
            <button type="button" wire:click="addRow('family', {{ json_encode(['relation_type' => 'SPOUSE', 'name' => '', 'gender' => '', 'age' => '', 'last_education' => '', 'current_job' => '', 'current_employer' => '']) }})" class="text-[11px] font-bold text-navy border border-[#d7dbe2] px-3 py-1.5 rounded mt-2">＋ Tambah Anggota Keluarga Lain</button>
        @endunless
    </section>

    {{-- ============ 3. RIWAYAT PENDIDIKAN ============ --}}
    <section id="sec-pendidikan">
        <h2 class="text-sm font-bold text-navy mb-3 pb-2 border-b border-[#eef1f5]">3. Riwayat Pendidikan</h2>

        <p class="text-[10.5px] font-bold text-muted uppercase mb-2">Pendidikan Formal * <span class="font-normal normal-case">(minimal 1 baris)</span></p>
        <div class="overflow-x-auto border border-[#eef1f5] rounded-md mb-2">
            <table class="w-full text-[11.5px]">
                <thead class="bg-[#f8fafc] text-[10px] uppercase text-muted">
                    <tr>
                        <th class="text-left px-2 py-2 w-[110px]">Jenjang</th>
                        <th class="text-left px-2 py-2">Instansi</th>
                        <th class="text-left px-2 py-2">Jurusan</th>
                        <th class="text-left px-2 py-2 w-[80px]">Dari Th</th>
                        <th class="text-left px-2 py-2 w-[80px]">S/d Th</th>
                        <th class="text-left px-2 py-2 w-[90px]">Nilai Akhir</th>
                        @unless ($readonly) <th class="w-[40px]"></th> @endunless
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#eef1f5]">
                    @forelse ($education as $i => $e)
                        <tr wire:key="edu-{{ $i }}">
                            @if ($readonly)
                                <td class="px-2 py-1.5">{{ $e['level'] ?? '' }}</td>
                                <td class="px-2 py-1.5">{{ $e['institution'] ?? '' }}</td>
                                <td class="px-2 py-1.5">{{ $e['major'] ?? '' }}</td>
                                <td class="px-2 py-1.5">{{ $e['period_start_year'] ?? '' }}</td>
                                <td class="px-2 py-1.5">{{ $e['graduation_year'] ?? '' }}</td>
                                <td class="px-2 py-1.5">{{ $e['final_score'] ?? '' }}</td>
                            @else
                                <td class="px-2 py-1.5"><x-form.input wire:model="education.{{ $i }}.level" placeholder="SLTP/SLTA/D3/S1" /></td>
                                <td class="px-2 py-1.5"><x-form.input wire:model="education.{{ $i }}.institution" placeholder="Instansi" /></td>
                                <td class="px-2 py-1.5"><x-form.input wire:model="education.{{ $i }}.major" placeholder="Jurusan" /></td>
                                <td class="px-2 py-1.5"><x-form.input type="number" wire:model="education.{{ $i }}.period_start_year" /></td>
                                <td class="px-2 py-1.5"><x-form.input type="number" wire:model="education.{{ $i }}.graduation_year" /></td>
                                <td class="px-2 py-1.5"><x-form.input wire:model="education.{{ $i }}.final_score" /></td>
                                <td class="px-2 py-1.5 text-center"><button type="button" wire:click="removeRow('education', {{ $i }})" class="text-[#a3241f] text-xs">✕</button></td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-2 py-3 text-center text-muted">Belum ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @unless ($readonly)
            <button type="button" wire:click="addRow('education', {{ json_encode(['level' => '', 'institution' => '', 'major' => '', 'period_start_year' => '', 'graduation_year' => '', 'final_score' => '']) }})" class="text-[11px] font-bold text-navy border border-[#d7dbe2] px-3 py-1.5 rounded mb-5">＋ Tambah Pendidikan Formal</button>
        @endunless

        <p class="text-[10.5px] font-bold text-muted uppercase mb-2">Pendidikan Non-Formal (Kursus/Pelatihan) * <span class="font-normal normal-case">(minimal 1 baris -- isi "Tidak ada" kalau memang belum pernah)</span></p>
        <div class="overflow-x-auto border border-[#eef1f5] rounded-md mb-2">
            <table class="w-full text-[11.5px]">
                <thead class="bg-[#f8fafc] text-[10px] uppercase text-muted">
                    <tr>
                        <th class="text-left px-2 py-2">Jenis Kursus/Pelatihan</th>
                        <th class="text-left px-2 py-2">Lembaga</th>
                        <th class="text-left px-2 py-2 w-[80px]">Dari Th</th>
                        <th class="text-left px-2 py-2 w-[80px]">S/d Th</th>
                        <th class="text-left px-2 py-2 w-[120px]">No. Sertifikat</th>
                        @unless ($readonly) <th class="w-[40px]"></th> @endunless
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#eef1f5]">
                    @forelse ($educationNonformal as $i => $e)
                        <tr wire:key="nonformal-{{ $i }}">
                            @if ($readonly)
                                <td class="px-2 py-1.5">{{ $e['course_type'] ?? '' }}</td>
                                <td class="px-2 py-1.5">{{ $e['institution'] ?? '' }}</td>
                                <td class="px-2 py-1.5">{{ $e['period_start_year'] ?? '' }}</td>
                                <td class="px-2 py-1.5">{{ $e['period_end_year'] ?? '' }}</td>
                                <td class="px-2 py-1.5">{{ $e['certificate_no'] ?? '' }}</td>
                            @else
                                <td class="px-2 py-1.5"><x-form.input wire:model="educationNonformal.{{ $i }}.course_type" /></td>
                                <td class="px-2 py-1.5"><x-form.input wire:model="educationNonformal.{{ $i }}.institution" /></td>
                                <td class="px-2 py-1.5"><x-form.input type="number" wire:model="educationNonformal.{{ $i }}.period_start_year" /></td>
                                <td class="px-2 py-1.5"><x-form.input type="number" wire:model="educationNonformal.{{ $i }}.period_end_year" /></td>
                                <td class="px-2 py-1.5"><x-form.input wire:model="educationNonformal.{{ $i }}.certificate_no" /></td>
                                <td class="px-2 py-1.5 text-center"><button type="button" wire:click="removeRow('educationNonformal', {{ $i }})" class="text-[#a3241f] text-xs">✕</button></td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-2 py-3 text-center text-muted">Belum ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @unless ($readonly)
            <button type="button" wire:click="addRow('educationNonformal', {{ json_encode(['course_type' => '', 'institution' => '', 'period_start_year' => '', 'period_end_year' => '', 'certificate_no' => '']) }})" class="text-[11px] font-bold text-navy border border-[#d7dbe2] px-3 py-1.5 rounded">＋ Tambah Kursus/Pelatihan</button>
        @endunless
    </section>

    {{-- ============ 4. PENGALAMAN ORGANISASI ============ --}}
    <section id="sec-organisasi">
        <h2 class="text-sm font-bold text-navy mb-3 pb-2 border-b border-[#eef1f5]">4. Pengalaman Organisasi * <span class="font-normal normal-case text-[11px] text-muted">(minimal 1 baris -- isi "Tidak ada" kalau memang belum pernah)</span></h2>
        <div class="overflow-x-auto border border-[#eef1f5] rounded-md mb-2">
            <table class="w-full text-[11.5px]">
                <thead class="bg-[#f8fafc] text-[10px] uppercase text-muted">
                    <tr>
                        <th class="text-left px-2 py-2">Nama Organisasi</th>
                        <th class="text-left px-2 py-2 w-[140px]">Jabatan</th>
                        <th class="text-left px-2 py-2 w-[130px]">Periode</th>
                        <th class="text-left px-2 py-2">Uraian Tugas</th>
                        @unless ($readonly) <th class="w-[40px]"></th> @endunless
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#eef1f5]">
                    @forelse ($orgExperience as $i => $o)
                        <tr wire:key="org-{{ $i }}">
                            @if ($readonly)
                                <td class="px-2 py-1.5">{{ $o['organization_name'] ?? '' }}</td>
                                <td class="px-2 py-1.5">{{ $o['position'] ?? '' }}</td>
                                <td class="px-2 py-1.5">{{ $o['period'] ?? '' }}</td>
                                <td class="px-2 py-1.5">{{ $o['task_description'] ?? '' }}</td>
                            @else
                                <td class="px-2 py-1.5"><x-form.input wire:model="orgExperience.{{ $i }}.organization_name" /></td>
                                <td class="px-2 py-1.5"><x-form.input wire:model="orgExperience.{{ $i }}.position" /></td>
                                <td class="px-2 py-1.5"><x-form.input wire:model="orgExperience.{{ $i }}.period" placeholder="2020-2022" /></td>
                                <td class="px-2 py-1.5"><x-form.input wire:model="orgExperience.{{ $i }}.task_description" /></td>
                                <td class="px-2 py-1.5 text-center"><button type="button" wire:click="removeRow('orgExperience', {{ $i }})" class="text-[#a3241f] text-xs">✕</button></td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-2 py-3 text-center text-muted">Belum ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @unless ($readonly)
            <button type="button" wire:click="addRow('orgExperience', {{ json_encode(['organization_name' => '', 'position' => '', 'period' => '', 'task_description' => '']) }})" class="text-[11px] font-bold text-navy border border-[#d7dbe2] px-3 py-1.5 rounded">＋ Tambah Pengalaman Organisasi</button>
        @endunless
    </section>

    {{-- ============ 5. PENGALAMAN KERJA (tetap kartu, kolom terlalu banyak utk tabel) ============ --}}
    <section id="sec-kerja">
        <h2 class="text-sm font-bold text-navy mb-1 pb-2 border-b border-[#eef1f5]">5. Pengalaman Kerja * <span class="font-normal text-[11px] text-muted">(minimal 1, urutan dari yang terakhir)</span></h2>
        @unless ($readonly)
            <p class="text-[10.5px] text-muted mb-3 mt-2">Belum pernah bekerja? Tetap tambah 1 baris, isi "Belum ada pengalaman" di Nama Perusahaan.</p>
        @endunless
        @forelse ($workExp as $i => $w)
            <div class="border border-[#d7dbe2] rounded-md p-3 mb-3" wire:key="work-{{ $i }}">
                @if ($readonly)
                    <div class="grid two mb-2">
                        <x-form.display label="Nama Perusahaan">{{ $w['company'] ?? '' }}</x-form.display>
                        <x-form.display label="Lokasi & Jenis Usaha">{{ $w['location'] ?? '' }}</x-form.display>
                    </div>
                    <div class="grid two mb-2">
                        <x-form.display label="Jumlah Karyawan">{{ $w['employee_count'] ?? '' }}</x-form.display>
                        <x-form.display label="No. Telepon Perusahaan">{{ $w['company_phone'] ?? '' }}</x-form.display>
                    </div>
                    <p class="text-[10px] font-bold text-muted uppercase mt-2 mb-1">Jabatan Awal</p>
                    <div class="grid gap-2 mb-2" style="grid-template-columns:1fr 130px 130px;">
                        <x-form.display label="Jabatan">{{ $w['position'] ?? '' }}</x-form.display>
                        <x-form.display label="Mulai">{{ $w['start_date'] ?? '' }}</x-form.display>
                        <x-form.display label="Selesai">{{ $w['end_date'] ?? '' }}</x-form.display>
                    </div>
                    <p class="text-[10px] font-bold text-muted uppercase mt-2 mb-1">Jabatan Terakhir</p>
                    <div class="grid gap-2 mb-2" style="grid-template-columns:1fr 130px 130px;">
                        <x-form.display label="Jabatan">{{ $w['position_end'] ?? '' }}</x-form.display>
                        <x-form.display label="Mulai">{{ $w['position_end_start_date'] ?? '' }}</x-form.display>
                        <x-form.display label="Selesai">{{ $w['position_end_end_date'] ?? '' }}</x-form.display>
                    </div>
                    <div class="grid gap-2 mb-2" style="grid-template-columns:1fr 1fr 1fr 1fr;">
                        <x-form.display label="Bawahan (awal)">{{ $w['subordinate_count_start'] ?? '' }}</x-form.display>
                        <x-form.display label="Bawahan (akhir)">{{ $w['subordinate_count_end'] ?? '' }}</x-form.display>
                        <x-form.display label="Gaji Awal">{{ is_numeric($w['salary_start'] ?? null) ? number_format((float) $w['salary_start'], 0, ',', '.') : ($w['salary_start'] ?? '') }}</x-form.display>
                        <x-form.display label="Gaji Terakhir">{{ is_numeric($w['salary_end'] ?? null) ? number_format((float) $w['salary_end'], 0, ',', '.') : ($w['salary_end'] ?? '') }}</x-form.display>
                    </div>
                    <x-form.display label="Fasilitas selain gaji">{{ $w['benefits'] ?? '' }}</x-form.display>
                    <div class="grid two mt-2">
                        <x-form.display label="Atasan Langsung">{{ $w['supervisor_name'] ?? '' }}</x-form.display>
                        <x-form.display label="No. Telp Atasan">{{ $w['supervisor_phone'] ?? '' }}</x-form.display>
                    </div>
                    <div class="mt-2 space-y-2">
                        <x-form.display label="Prestasi">{{ $w['achievement'] ?? '' }}</x-form.display>
                        <x-form.display label="Uraian Pekerjaan">{{ $w['description'] ?? '' }}</x-form.display>
                        <x-form.display label="Struktur Organisasi">{{ $w['org_structure_note'] ?? '' }}</x-form.display>
                        <x-form.display label="Masalah penting & cara mengatasi">{{ $w['key_challenge'] ?? '' }}</x-form.display>
                        <x-form.display label="Perubahan/pembaruan yang dilakukan">{{ $w['improvement_made'] ?? '' }}</x-form.display>
                        <x-form.display label="Alasan Keluar">{{ $w['leave_reason'] ?? '' }}</x-form.display>
                    </div>
                @else
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
                        <x-form.date-picker wire:model="workExp.{{ $i }}.start_date" />
                        <x-form.date-picker wire:model="workExp.{{ $i }}.end_date" />
                    </div>
                    <p class="text-[10px] font-bold text-muted uppercase mt-2 mb-1">Jabatan Terakhir</p>
                    <div class="grid gap-2 mb-2" style="grid-template-columns:1fr 130px 130px;">
                        <x-form.input wire:model="workExp.{{ $i }}.position_end" placeholder="Jabatan Terakhir" />
                        <x-form.date-picker wire:model="workExp.{{ $i }}.position_end_start_date" />
                        <x-form.date-picker wire:model="workExp.{{ $i }}.position_end_end_date" />
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
                @endif
            </div>
        @empty
            <p class="text-xs text-muted">Belum ada data.</p>
        @endforelse
        @unless ($readonly)
            <button type="button" wire:click="addRow('workExp', {{ json_encode(['company' => '', 'location' => '', 'business_type' => '', 'employee_count' => '', 'company_phone' => '', 'position' => '', 'start_date' => '', 'end_date' => '', 'position_end' => '', 'position_end_start_date' => '', 'position_end_end_date' => '', 'subordinate_count_start' => '', 'subordinate_count_end' => '', 'salary_start' => '', 'salary_end' => '', 'benefits' => '', 'supervisor_name' => '', 'supervisor_phone' => '', 'achievement' => '', 'description' => '', 'org_structure_note' => '', 'key_challenge' => '', 'improvement_made' => '', 'leave_reason' => '']) }})" class="text-[11px] font-bold text-navy border border-[#d7dbe2] px-3 py-1.5 rounded">＋ Tambah Pengalaman Kerja</button>
        @endunless
    </section>

    {{-- ============ 6. SOSIAL & REFERENSI ============ --}}
    <section id="sec-sosial">
        <h2 class="text-sm font-bold text-navy mb-3 pb-2 border-b border-[#eef1f5]">6. Aktivitas Sosial, Kegiatan & Referensi</h2>
        <div class="space-y-3 mb-4">
            @if ($readonly)
                <x-form.display label="Kenalan di perusahaan kami (nama & jabatan)">{{ $company_acquaintance }}</x-form.display>
                <x-form.display label="Hobi">{{ $hobby }}</x-form.display>
                <x-form.display label="Mengisi waktu luang">{{ $leisure_activity }}</x-form.display>
                <x-form.display label="Topik-topik yang dibaca">{{ $reading_topics }}</x-form.display>
                <x-form.display label="Pernah ke luar negeri, kapan & untuk keperluan apa">{{ $went_abroad }}</x-form.display>
            @else
                <x-form.input label="Apakah Anda memiliki kenalan di perusahaan kami? (nama & jabatan) *" wire:model="company_acquaintance" />
                <x-form.input label="Hobi *" wire:model="hobby" />
                <x-form.input label="Bagaimana Anda mengisi waktu luang? *" wire:model="leisure_activity" />
                <x-form.input label="Topik-topik apa saja yang Anda baca? *" wire:model="reading_topics" />
                <x-form.textarea rows="2" wire:model="went_abroad"><x-slot:label>Apakah Anda pernah ke luar negeri? Kapan & untuk keperluan apa? *</x-slot:label></x-form.textarea>
            @endif
        </div>

        <p class="text-[10.5px] font-bold text-muted uppercase mb-2">Referensi * <span class="font-normal normal-case">(minimal 2 orang, bukan keluarga)</span></p>
        <div class="overflow-x-auto border border-[#eef1f5] rounded-md mb-2">
            <table class="w-full text-[11.5px]">
                <thead class="bg-[#f8fafc] text-[10px] uppercase text-muted">
                    <tr><th class="text-left px-2 py-2">Nama</th><th class="text-left px-2 py-2 w-[160px]">No. Telp</th>@unless ($readonly)<th class="w-[40px]"></th>@endunless</tr>
                </thead>
                <tbody class="divide-y divide-[#eef1f5]">
                    @forelse ($professionalReferences as $i => $r)
                        <tr wire:key="ref-{{ $i }}">
                            @if ($readonly)
                                <td class="px-2 py-1.5">{{ $r['name'] ?? '' }}</td>
                                <td class="px-2 py-1.5">{{ $r['phone'] ?? '' }}</td>
                            @else
                                <td class="px-2 py-1.5"><x-form.input wire:model="professionalReferences.{{ $i }}.name" /></td>
                                <td class="px-2 py-1.5"><x-form.input wire:model="professionalReferences.{{ $i }}.phone" /></td>
                                <td class="px-2 py-1.5 text-center"><button type="button" wire:click="removeRow('professionalReferences', {{ $i }})" class="text-[#a3241f] text-xs">✕</button></td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-2 py-3 text-center text-muted">Belum ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @unless ($readonly)
            <button type="button" wire:click="addRow('professionalReferences', {{ json_encode(['name' => '', 'phone' => '']) }})" class="text-[11px] font-bold text-navy border border-[#d7dbe2] px-3 py-1.5 rounded mb-5">＋ Tambah Referensi</button>
        @endunless

        <p class="text-[10.5px] font-bold text-muted uppercase mb-2">Kontak Darurat * <span class="font-normal normal-case">(minimal 3 orang, dengan hubungan & alamat)</span></p>
        <div class="overflow-x-auto border border-[#eef1f5] rounded-md mb-2">
            <table class="w-full text-[11.5px]">
                <thead class="bg-[#f8fafc] text-[10px] uppercase text-muted">
                    <tr><th class="text-left px-2 py-2">Nama</th><th class="text-left px-2 py-2 w-[120px]">Hubungan</th><th class="text-left px-2 py-2 w-[130px]">No. Telp</th><th class="text-left px-2 py-2">Alamat</th>@unless ($readonly)<th class="w-[40px]"></th>@endunless</tr>
                </thead>
                <tbody class="divide-y divide-[#eef1f5]">
                    @forelse ($emergencyContacts as $i => $c)
                        <tr wire:key="emg-{{ $i }}">
                            @if ($readonly)
                                <td class="px-2 py-1.5">{{ $c['name'] ?? '' }}</td>
                                <td class="px-2 py-1.5">{{ $c['relation'] ?? '' }}</td>
                                <td class="px-2 py-1.5">{{ $c['phone'] ?? '' }}</td>
                                <td class="px-2 py-1.5">{{ $c['address'] ?? '' }}</td>
                            @else
                                <td class="px-2 py-1.5"><x-form.input wire:model="emergencyContacts.{{ $i }}.name" /></td>
                                <td class="px-2 py-1.5"><x-form.input wire:model="emergencyContacts.{{ $i }}.relation" /></td>
                                <td class="px-2 py-1.5"><x-form.input wire:model="emergencyContacts.{{ $i }}.phone" /></td>
                                <td class="px-2 py-1.5"><x-form.input wire:model="emergencyContacts.{{ $i }}.address" /></td>
                                <td class="px-2 py-1.5 text-center"><button type="button" wire:click="removeRow('emergencyContacts', {{ $i }})" class="text-[#a3241f] text-xs">✕</button></td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-2 py-3 text-center text-muted">Belum ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @unless ($readonly)
            <button type="button" wire:click="addRow('emergencyContacts', {{ json_encode(['name' => '', 'relation' => '', 'phone' => '', 'address' => '']) }})" class="text-[11px] font-bold text-navy border border-[#d7dbe2] px-3 py-1.5 rounded">＋ Tambah Kontak Darurat</button>
        @endunless
    </section>

    {{-- ============ 7. MINAT & KONSEP PRIBADI ============ --}}
    <section id="sec-minat">
        <h2 class="text-sm font-bold text-navy mb-3 pb-2 border-b border-[#eef1f5]">7. Minat & Konsep Pribadi</h2>
        <div class="space-y-3">
            @if ($readonly)
                <x-form.display label="Mengapa Anda ingin bekerja di Perusahaan kami?">{{ $why_join_us }}</x-form.display>
                <x-form.display label="Apa yang Anda ketahui mengenai perusahaan kami?">{{ $what_you_know_about_us }}</x-form.display>
                <x-form.display label="Kontribusi apa yang dapat Anda berikan sehubungan dengan posisi yang dilamar?">{{ $contribution_plan }}</x-form.display>
                <x-form.display label="Uraian apa yang akan Anda lakukan jika menduduki posisi tersebut?">{{ $role_plan_if_hired }}</x-form.display>
                <x-form.display label="Jenis pekerjaan apa yang Anda senangi? (Perencana/Pelaksana)">{{ $job_type_preference }}</x-form.display>
                <x-form.display label="Sifat pekerjaan apa yang Anda senangi? (Penelitian/Teknis/Non-Teknis)">{{ $job_nature_preference }}</x-form.display>
                <x-form.display label="Lingkungan/suasana kerja yang Anda senangi? (Pabrik/Kantor/Lapangan/Laboratorium)">{{ $work_environment_preference }}</x-form.display>
                <x-form.display label="Pekerjaan/lingkungan seperti apa yang dapat menghambat pekerjaan & memicu human error?">{{ $hindering_environment }}</x-form.display>
                <x-form.display label="Tipe orang seperti apa yang Anda senangi bekerja sama?">{{ $preferred_colleague_type }}</x-form.display>
                <x-form.display label="Terhadap hal-hal apa Anda paling sulit mengambil keputusan tegas berdampak besar?">{{ $difficult_decision_topic }}</x-form.display>
                <x-form.display label="Apa kelebihan & kekurangan Anda? (minimal masing-masing 5)">{{ $strengths_weaknesses }}</x-form.display>
                <x-form.display label="Jika mengalami kesulitan menyelesaikan masalah, kepada siapa Anda bertanya? Mengapa?">{{ $who_you_ask_when_stuck }}</x-form.display>
            @else
                <x-form.textarea rows="2" wire:model="why_join_us"><x-slot:label>Mengapa Anda ingin bekerja di Perusahaan kami? *</x-slot:label></x-form.textarea>
                <x-form.textarea rows="2" wire:model="what_you_know_about_us"><x-slot:label>Apa yang Anda ketahui mengenai perusahaan kami? *</x-slot:label></x-form.textarea>
                <x-form.textarea rows="2" wire:model="contribution_plan"><x-slot:label>Kontribusi apa yang dapat Anda berikan sehubungan dengan posisi yang dilamar? *</x-slot:label></x-form.textarea>
                <x-form.textarea rows="2" wire:model="role_plan_if_hired"><x-slot:label>Uraian apa yang akan Anda lakukan jika menduduki posisi tersebut? *</x-slot:label></x-form.textarea>
                <x-form.textarea rows="2" wire:model="job_type_preference"><x-slot:label>Jenis pekerjaan apa yang Anda senangi? (Perencana/Pelaksana) — sebutkan alasan *</x-slot:label></x-form.textarea>
                <x-form.textarea rows="2" wire:model="job_nature_preference"><x-slot:label>Sifat pekerjaan apa yang Anda senangi? (Penelitian/Teknis/Non-Teknis) — sebutkan alasan *</x-slot:label></x-form.textarea>
                <x-form.textarea rows="2" wire:model="work_environment_preference"><x-slot:label>Lingkungan/suasana kerja yang Anda senangi? (Pabrik/Kantor/Lapangan/Laboratorium) — sebutkan alasan *</x-slot:label></x-form.textarea>
                <x-form.textarea rows="2" wire:model="hindering_environment"><x-slot:label>Pekerjaan/lingkungan seperti apa yang dapat menghambat pekerjaan & memicu human error? *</x-slot:label></x-form.textarea>
                <x-form.textarea rows="2" wire:model="preferred_colleague_type"><x-slot:label>Tipe orang seperti apa yang Anda senangi bekerja sama? *</x-slot:label></x-form.textarea>
                <x-form.textarea rows="2" wire:model="difficult_decision_topic"><x-slot:label>Terhadap hal-hal apa Anda paling sulit mengambil keputusan tegas berdampak besar? *</x-slot:label></x-form.textarea>
                <x-form.textarea rows="3" wire:model="strengths_weaknesses"><x-slot:label>Apa kelebihan & kekurangan Anda? (minimal masing-masing 5) *</x-slot:label></x-form.textarea>
                <x-form.textarea rows="2" wire:model="who_you_ask_when_stuck"><x-slot:label>Jika mengalami kesulitan menyelesaikan masalah, kepada siapa Anda bertanya? Mengapa? *</x-slot:label></x-form.textarea>
            @endif
        </div>
    </section>

    {{-- ============ 8. RIWAYAT KESEHATAN ============ --}}
    <section id="sec-kesehatan">
        <h2 class="text-sm font-bold text-navy mb-3 pb-2 border-b border-[#eef1f5]">8. Riwayat Kesehatan</h2>
        <div class="grid gap-2 mb-3" style="grid-template-columns:1fr 1fr 1fr;">
            @if ($readonly)
                <x-form.display label="Tinggi Badan (cm)">{{ $height_cm }}</x-form.display>
                <x-form.display label="Berat Badan (kg)">{{ $weight_kg }}</x-form.display>
                <x-form.display label="Golongan Darah">{{ $blood_type }}</x-form.display>
            @else
                <x-form.input label="Tinggi Badan (cm) *" type="number" wire:model="height_cm" />
                <x-form.input label="Berat Badan (kg) *" type="number" wire:model="weight_kg" />
                <x-form.input label="Golongan Darah *" wire:model="blood_type" />
            @endif
        </div>
        <div class="space-y-3">
            @if ($readonly)
                <x-form.display label="Penyakit apa yang pernah diderita? Berapa lama? Kondisi saat ini?">{{ $illness_history }}</x-form.display>
                <x-form.display label="Pernah dirawat di RS? Karena apa? Akibat yang diderita sekarang?">{{ $hospitalized_history }}</x-form.display>
                <div class="grid two">
                    <x-form.display label="Merokok?">{{ CandidateFormOptions::YES_NO[(int) $is_smoker] ?? ($is_smoker === null ? '—' : ($is_smoker ? 'Ya' : 'Tidak')) }}</x-form.display>
                    <x-form.display label="Kesehatan keluarga baik?">{{ CandidateFormOptions::FAMILY_HEALTH[(int) $family_health_ok] ?? ($family_health_ok === null ? '—' : ($family_health_ok ? 'Ya, baik-baik saja' : 'Ada masalah')) }}</x-form.display>
                </div>
                <x-form.display label="Gangguan jasmani yang tetap/keras">{{ $physical_disability }}</x-form.display>
            @else
                <x-form.textarea rows="2" wire:model="illness_history"><x-slot:label>Penyakit apa yang pernah Anda derita? Berapa lama? Bagaimana kondisi saat ini? *</x-slot:label></x-form.textarea>
                <x-form.textarea rows="2" wire:model="hospitalized_history"><x-slot:label>Apakah pernah dirawat di RS? Karena apa? Akibat apa yang diderita sekarang? *</x-slot:label></x-form.textarea>
                <div class="grid two">
                    <x-form.select wire:model="is_smoker" :options="CandidateFormOptions::YES_NO" placeholder="— pilih —"><x-slot:label>Apakah Anda merokok? *</x-slot:label></x-form.select>
                    <x-form.select wire:model="family_health_ok" :options="CandidateFormOptions::FAMILY_HEALTH" placeholder="— pilih —"><x-slot:label>Apakah kesehatan keluarga Anda baik? *</x-slot:label></x-form.select>
                </div>
                <x-form.textarea rows="2" wire:model="physical_disability"><x-slot:label>Apakah Anda mempunyai gangguan jasmani yang tetap/keras? *</x-slot:label></x-form.textarea>
            @endif
        </div>
    </section>

    {{-- ============ 9. BAHASA, KOMPUTER & INFO LOWONGAN ============ --}}
    <section id="sec-bahasa">
        <h2 class="text-sm font-bold text-navy mb-3 pb-2 border-b border-[#eef1f5]">9. Bahasa, Komputer & Info Lowongan</h2>

        <p class="text-[10.5px] font-bold text-muted uppercase mb-2">Penguasaan Bahasa Asing * <span class="font-normal normal-case">(minimal 1)</span></p>
        <div class="overflow-x-auto border border-[#eef1f5] rounded-md mb-2">
            <table class="w-full text-[11.5px]">
                <thead class="bg-[#f8fafc] text-[10px] uppercase text-muted">
                    <tr><th class="text-left px-2 py-2">Bahasa</th><th class="text-left px-2 py-2 w-[130px]">Tertulis</th><th class="text-left px-2 py-2 w-[130px]">Lisan</th><th class="text-left px-2 py-2">Keterangan</th>@unless ($readonly)<th class="w-[40px]"></th>@endunless</tr>
                </thead>
                <tbody class="divide-y divide-[#eef1f5]">
                    @forelse ($languageSkills as $i => $l)
                        <tr wire:key="lang-{{ $i }}">
                            @if ($readonly)
                                <td class="px-2 py-1.5">{{ $l['language'] ?? '' }}</td>
                                <td class="px-2 py-1.5">{{ CandidateFormOptions::SKILL_LEVEL[$l['written'] ?? ''] ?? '—' }}</td>
                                <td class="px-2 py-1.5">{{ CandidateFormOptions::SKILL_LEVEL[$l['oral'] ?? ''] ?? '—' }}</td>
                                <td class="px-2 py-1.5">{{ $l['notes'] ?? '' }}</td>
                            @else
                                <td class="px-2 py-1.5"><x-form.input wire:model="languageSkills.{{ $i }}.language" /></td>
                                <td class="px-2 py-1.5"><x-form.select wire:model="languageSkills.{{ $i }}.written" :options="CandidateFormOptions::SKILL_LEVEL" placeholder="-" /></td>
                                <td class="px-2 py-1.5"><x-form.select wire:model="languageSkills.{{ $i }}.oral" :options="CandidateFormOptions::SKILL_LEVEL" placeholder="-" /></td>
                                <td class="px-2 py-1.5"><x-form.input wire:model="languageSkills.{{ $i }}.notes" /></td>
                                <td class="px-2 py-1.5 text-center"><button type="button" wire:click="removeRow('languageSkills', {{ $i }})" class="text-[#a3241f] text-xs">✕</button></td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-2 py-3 text-center text-muted">Belum ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @unless ($readonly)
            <button type="button" wire:click="addRow('languageSkills', {{ json_encode(['language' => '', 'written' => '', 'oral' => '', 'notes' => '']) }})" class="text-[11px] font-bold text-navy border border-[#d7dbe2] px-3 py-1.5 rounded mb-5">＋ Tambah Bahasa</button>
        @endunless

        <p class="text-[10.5px] font-bold text-muted uppercase mb-2">Penguasaan Komputer * <span class="font-normal normal-case">(minimal 1)</span></p>
        <div class="overflow-x-auto border border-[#eef1f5] rounded-md mb-2">
            <table class="w-full text-[11.5px]">
                <thead class="bg-[#f8fafc] text-[10px] uppercase text-muted">
                    <tr><th class="text-left px-2 py-2">Skill</th><th class="text-left px-2 py-2 w-[130px]">Level</th><th class="text-left px-2 py-2">Keterangan</th>@unless ($readonly)<th class="w-[40px]"></th>@endunless</tr>
                </thead>
                <tbody class="divide-y divide-[#eef1f5]">
                    @forelse ($computerSkills as $i => $c)
                        <tr wire:key="comp-{{ $i }}">
                            @if ($readonly)
                                <td class="px-2 py-1.5">{{ $c['skill'] ?? '' }}</td>
                                <td class="px-2 py-1.5">{{ CandidateFormOptions::SKILL_LEVEL[$c['level'] ?? ''] ?? '—' }}</td>
                                <td class="px-2 py-1.5">{{ $c['notes'] ?? '' }}</td>
                            @else
                                <td class="px-2 py-1.5"><x-form.input wire:model="computerSkills.{{ $i }}.skill" placeholder="mis. Desain Grafis" /></td>
                                <td class="px-2 py-1.5"><x-form.select wire:model="computerSkills.{{ $i }}.level" :options="CandidateFormOptions::SKILL_LEVEL" placeholder="-" /></td>
                                <td class="px-2 py-1.5"><x-form.input wire:model="computerSkills.{{ $i }}.notes" /></td>
                                <td class="px-2 py-1.5 text-center"><button type="button" wire:click="removeRow('computerSkills', {{ $i }})" class="text-[#a3241f] text-xs">✕</button></td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-2 py-3 text-center text-muted">Belum ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @unless ($readonly)
            <button type="button" wire:click="addRow('computerSkills', {{ json_encode(['skill' => '', 'level' => '', 'notes' => '']) }})" class="text-[11px] font-bold text-navy border border-[#d7dbe2] px-3 py-1.5 rounded mb-5">＋ Tambah Skill Komputer</button>
        @endunless

        <div class="space-y-3">
            @if ($readonly)
                <x-form.display label="Masih mendapatkan bantuan? Dari siapa & berapa besar/lamanya?">{{ $still_receiving_aid }}</x-form.display>
                <x-form.display label="Tanggungan lain selain anak & istri/suami?">{{ $other_dependents }}</x-form.display>
                <x-form.display label="Dari mana menerima info lowongan di perusahaan kami?">{{ $source_of_info }}</x-form.display>
                <div class="grid two">
                    <x-form.display label="Gaji yang diinginkan">{{ is_numeric($expected_salary) ? number_format((float) $expected_salary, 0, ',', '.') : $expected_salary }}</x-form.display>
                    <x-form.display label="Kesediaan mulai bekerja">{{ $availability_date }}</x-form.display>
                </div>
                <x-form.display label="Fasilitas yang diinginkan">{{ $expected_facility }}</x-form.display>
            @else
                <x-form.textarea rows="2" wire:model="still_receiving_aid"><x-slot:label>Apakah Anda masih mendapatkan bantuan? Dari siapa & berapa besar/lamanya? *</x-slot:label></x-form.textarea>
                <x-form.textarea rows="2" wire:model="other_dependents"><x-slot:label>Apakah Anda punya tanggungan lain selain anak & istri/suami? Sebutkan & berapa besar *</x-slot:label></x-form.textarea>
                <x-form.input label="Dari mana Anda menerima info lowongan di perusahaan kami? *" wire:model="source_of_info" />
                <div class="grid two">
                    <x-form.input label="Gaji yang diinginkan *" type="number" wire:model="expected_salary" />
                    <x-form.date-picker label="Kesediaan mulai bekerja *" wire:model="availability_date" />
                </div>
                <x-form.input label="Fasilitas yang diinginkan *" wire:model="expected_facility" />
            @endif
        </div>
    </section>

    {{-- ============ 10. KESEDIAAN & PERNYATAAN ============ --}}
    <section id="sec-kesediaan">
        <h2 class="text-sm font-bold text-navy mb-3 pb-2 border-b border-[#eef1f5]">10. Kesediaan & Pernyataan <span class="font-normal text-[11px] text-muted">(semua wajib dicentang)</span></h2>
        <div class="space-y-2 mb-4">
            @foreach ($this->willingItems as $key => $label)
                <label class="flex items-start gap-2 text-[12px] border border-[#eef1f5] rounded-md p-2.5">
                    @if ($readonly)
                        <span class="mt-0.5">{{ ($willingness[$key] ?? false) ? '✅' : '⬜' }}</span>
                    @else
                        <input type="checkbox" wire:model="willingness.{{ $key }}" class="!w-auto mt-0.5">
                    @endif
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>
        <div class="bg-[#f8fafc] border border-[#eef1f5] rounded-md p-3 text-[11px] text-muted mb-3">
            "Demikian informasi yang saya berikan adalah benar sesuai dengan kondisi saya, jika terdapat ketidaksesuaian dari informasi ini maka saya bersedia untuk berhenti/diberhentikan tanpa pesangon dan kompensasi berupa apapun serta seluruh konsekuensi yang diberikan oleh Telasih Makmur dan bersedia diproses sesuai hukum yang berlaku."
        </div>
        @if ($readonly)
            <div class="grid two">
                <x-form.display label="Menyetujui Pernyataan">{{ $agreement_signed ? 'Ya' : 'Tidak' }}</x-form.display>
                <x-form.display label="Tanda Tangan (nama)">{{ $signature_name }}</x-form.display>
            </div>
        @else
            <label class="flex items-center gap-2 text-xs mb-2"><input type="checkbox" wire:model="agreement_signed" class="!w-auto"> Saya menyetujui pernyataan di atas *</label>
            <x-form.input label="Ketik nama lengkap Anda sebagai tanda tangan *" wire:model="signature_name" placeholder="Nama lengkap" />
        @endif
    </section>

    {{-- ============ 11. UPLOAD DOKUMEN ============ --}}
    <section id="sec-dokumen">
        <h2 class="text-sm font-bold text-navy mb-3 pb-2 border-b border-[#eef1f5]">11. {{ $readonly ? 'Dokumen' : 'Upload Dokumen' }}</h2>
        @if ($readonly)
            <div class="space-y-2">
                @forelse (($documents ?? []) as $doc)
                    <a href="{{ $doc->file_path }}" target="_blank" rel="noopener" class="flex items-center gap-2 text-[12.5px] text-accent hover:underline">
                        📎 {{ strtoupper($doc->doc_type) }} — buka/download
                    </a>
                @empty
                    <p class="text-xs text-muted">Belum ada dokumen diunggah.</p>
                @endforelse
            </div>
        @else
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
    </section>
</div>
