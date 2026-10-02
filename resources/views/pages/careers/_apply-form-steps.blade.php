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
        <h2 class="text-sm font-bold text-navy mb-3 pb-2 border-b border-[#eef1f5]">1. {{ t('Personal Data') }}</h2>
        <div class="space-y-3">
            @if ($readonly)
                <x-form.display :label="t('ID Card No.')">{{ $national_id }}</x-form.display>
            @else
                <x-form.input :label="t('ID Card No.')" wire:model="national_id" readonly class="bg-[#f8fafc]" />
            @endif
            <div class="grid two">
                @if ($readonly)
                    <x-form.display :label="t('Full Name')">{{ $name }}</x-form.display>
                    <x-form.display :label="t('Nickname')">{{ $nickname }}</x-form.display>
                @else
                    <x-form.input :label="t('Full Name').' *'" wire:model="name" />
                    <x-form.input :label="t('Nickname').' *'" wire:model="nickname" />
                @endif
            </div>
            <div class="grid two">
                @if ($readonly)
                    <x-form.display :label="t('Gender')">{{ CandidateFormOptions::labels(CandidateFormOptions::GENDER)[$gender] ?? '—' }}</x-form.display>
                    <x-form.display :label="t('Religion')">{{ $religion }}</x-form.display>
                @else
                    <x-form.select wire:model="gender" :options="CandidateFormOptions::labels(CandidateFormOptions::GENDER)"><x-slot:label>{{ t('Gender') }} *</x-slot:label></x-form.select>
                    <x-form.input :label="t('Religion').' *'" wire:model="religion" />
                @endif
            </div>
            @if ($readonly)
                <x-form.display :label="t('Date of Birth')">{{ $birth_date }}</x-form.display>
            @else
                <x-form.date-picker :label="t('Date of Birth').' *'" wire:model="birth_date" />
            @endif
            <div class="grid two">
                @if ($readonly)
                    <x-form.display :label="t('Email')">{{ $email }}</x-form.display>
                    <x-form.display :label="t('Mobile No.')">{{ $phone }}</x-form.display>
                @else
                    <x-form.input :label="t('Email')" type="email" wire:model="email" readonly class="bg-[#f8fafc]" />
                    <x-form.input :label="t('Mobile No.').' *'" wire:model="phone" />
                @endif
            </div>
            @if ($readonly)
                <x-form.display :label="t('Address as on ID Card')">{{ $address }}</x-form.display>
                <x-form.display :label="t('Residential Address')">{{ $address_domisili }}</x-form.display>
            @else
                <x-form.textarea rows="2" wire:model="address"><x-slot:label>{{ t('Address as on ID Card') }} *</x-slot:label></x-form.textarea>
                <x-form.textarea rows="2" wire:model="address_domisili"><x-slot:label>{{ t('Residential Address (same as ID card if no different)') }} *</x-slot:label></x-form.textarea>
            @endif
            <div class="grid two">
                @if ($readonly)
                    <x-form.display :label="t('Vehicle Owned')">{{ CandidateFormOptions::labels(CandidateFormOptions::VEHICLE_TYPE)[$vehicle_type] ?? '—' }}</x-form.display>
                    <x-form.display :label="t('Driving Licenses Held')">{{ implode(', ', $sim_types ?: []) ?: '—' }}</x-form.display>
                @else
                    <x-form.select wire:model="vehicle_type" :options="CandidateFormOptions::labels(CandidateFormOptions::VEHICLE_TYPE)" :placeholder="t('— select —')"><x-slot:label>{{ t('Vehicle Owned') }} *</x-slot:label></x-form.select>
                    <div>
                        <label class="text-[10px] text-muted uppercase font-semibold block mb-1">{{ t('Driving Licenses Held') }}</label>
                        <div class="flex gap-3 pt-1.5">
                            @foreach (['C', 'A', 'B', 'B1'] as $sim)
                                <label class="flex items-center gap-1.5 text-xs"><input type="checkbox" value="{{ $sim }}" wire:model="sim_types" class="!w-auto"> {{ t('License') }} {{ $sim }}</label>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- ============ 2. DATA KELUARGA ============ --}}
    <section id="sec-keluarga">
        <h2 class="text-sm font-bold text-navy mb-3 pb-2 border-b border-[#eef1f5]">2. {{ t('Family Data') }}</h2>
        <div class="grid two mb-4">
            @if ($readonly)
                <x-form.display :label="t('Marital Status')">{{ CandidateFormOptions::labels(CandidateFormOptions::MARITAL_STATUS)[$marital_status] ?? '—' }}</x-form.display>
                <x-form.display :label="t('Marriage/Divorce Date')">{{ $marital_date }}</x-form.display>
            @else
                <x-form.select wire:model="marital_status" :options="CandidateFormOptions::labels(CandidateFormOptions::MARITAL_STATUS)" :placeholder="t('— select —')"><x-slot:label>{{ t('Marital Status') }} *</x-slot:label></x-form.select>
                <x-form.date-picker :label="t('Marriage/Divorce Date (required if not Single)')" wire:model="marital_date" />
            @endif
        </div>

        <p class="text-[10.5px] font-bold text-muted uppercase mb-2">{{ t('Family Members') }}</p>
        <div class="overflow-x-auto border border-[#eef1f5] rounded-md">
            <table class="w-full text-[11.5px]">
                <thead class="bg-[#f8fafc] text-[10px] uppercase text-muted">
                    <tr>
                        <th class="text-left px-2 py-2 w-[110px]">{{ t('Relation') }}</th>
                        <th class="text-left px-2 py-2">{{ t('Name') }} *</th>
                        <th class="text-left px-2 py-2 w-[60px]">{{ t('M/F') }}</th>
                        <th class="text-left px-2 py-2 w-[70px]">{{ t('Age') }}</th>
                        <th class="text-left px-2 py-2">{{ t('Last Education') }}</th>
                        <th class="text-left px-2 py-2">{{ t('Occupation') }}</th>
                        @unless ($readonly) <th class="w-[40px]"></th> @endunless
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#eef1f5]">
                    @forelse ($family as $i => $f)
                        <tr wire:key="fam-{{ $i }}" class="{{ in_array($f['relation_type'] ?? '', ['FATHER', 'MOTHER']) ? 'bg-[#fffaf0]' : '' }}">
                            <td class="px-2 py-1.5 font-bold text-navy">
                                @if ($readonly)
                                    {{ CandidateFormOptions::labels(CandidateFormOptions::FAMILY_RELATION_ALL)[$f['relation_type'] ?? ''] ?? ($f['relation_type'] ?? '—') }}
                                @elseif (in_array($f['relation_type'] ?? '', ['FATHER', 'MOTHER']))
                                    {{ $f['relation_type'] === 'FATHER' ? t('Father') : t('Mother') }}
                                    <input type="hidden" wire:model="family.{{ $i }}.relation_type">
                                @else
                                    <x-form.select wire:model="family.{{ $i }}.relation_type" :options="CandidateFormOptions::labels(CandidateFormOptions::FAMILY_RELATION_EDITABLE)" :placeholder="false" />
                                @endif
                            </td>
                            @if ($readonly)
                                <td class="px-2 py-1.5">{{ $f['name'] ?? '' }}</td>
                                <td class="px-2 py-1.5">{{ $f['gender'] ?? '' }}</td>
                                <td class="px-2 py-1.5">{{ $f['age'] ?? '' }}</td>
                                <td class="px-2 py-1.5">{{ $f['last_education'] ?? '' }}</td>
                                <td class="px-2 py-1.5">{{ $f['current_job'] ?? '' }}</td>
                            @else
                                <td class="px-2 py-1.5"><x-form.input wire:model="family.{{ $i }}.name" :placeholder="t('Name')" /></td>
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
                        <tr><td colspan="7" class="px-2 py-3 text-center text-muted">{{ t('No data yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @unless ($readonly)
            <button type="button" wire:click="addRow('family', {{ json_encode(['relation_type' => 'SPOUSE', 'name' => '', 'gender' => '', 'age' => '', 'last_education' => '', 'current_job' => '', 'current_employer' => '']) }})" class="text-[11px] font-bold text-navy border border-[#d7dbe2] px-3 py-1.5 rounded mt-2">＋ {{ t('Add Another Family Member') }}</button>
        @endunless
    </section>

    {{-- ============ 3. RIWAYAT PENDIDIKAN ============ --}}
    <section id="sec-pendidikan">
        <h2 class="text-sm font-bold text-navy mb-3 pb-2 border-b border-[#eef1f5]">3. {{ t('Education History') }}</h2>

        <p class="text-[10.5px] font-bold text-muted uppercase mb-2">{{ t('Formal Education') }} * <span class="font-normal normal-case">({{ t('at least 1 row') }})</span></p>
        <div class="overflow-x-auto border border-[#eef1f5] rounded-md mb-2">
            <table class="w-full text-[11.5px]">
                <thead class="bg-[#f8fafc] text-[10px] uppercase text-muted">
                    <tr>
                        <th class="text-left px-2 py-2 w-[110px]">{{ t('Level') }}</th>
                        <th class="text-left px-2 py-2">{{ t('Institution') }}</th>
                        <th class="text-left px-2 py-2">{{ t('Major') }}</th>
                        <th class="text-left px-2 py-2 w-[80px]">{{ t('From Yr') }}</th>
                        <th class="text-left px-2 py-2 w-[80px]">{{ t('To Yr') }}</th>
                        <th class="text-left px-2 py-2 w-[90px]">{{ t('Final Score') }}</th>
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
                                <td class="px-2 py-1.5"><x-form.input wire:model="education.{{ $i }}.institution" :placeholder="t('Institution')" /></td>
                                <td class="px-2 py-1.5"><x-form.input wire:model="education.{{ $i }}.major" :placeholder="t('Major')" /></td>
                                <td class="px-2 py-1.5"><x-form.input type="number" wire:model="education.{{ $i }}.period_start_year" /></td>
                                <td class="px-2 py-1.5"><x-form.input type="number" wire:model="education.{{ $i }}.graduation_year" /></td>
                                <td class="px-2 py-1.5"><x-form.input wire:model="education.{{ $i }}.final_score" /></td>
                                <td class="px-2 py-1.5 text-center"><button type="button" wire:click="removeRow('education', {{ $i }})" class="text-[#a3241f] text-xs">✕</button></td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-2 py-3 text-center text-muted">{{ t('No data yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @unless ($readonly)
            <button type="button" wire:click="addRow('education', {{ json_encode(['level' => '', 'institution' => '', 'major' => '', 'period_start_year' => '', 'graduation_year' => '', 'final_score' => '']) }})" class="text-[11px] font-bold text-navy border border-[#d7dbe2] px-3 py-1.5 rounded mb-5">＋ {{ t('Add Formal Education') }}</button>
        @endunless

        <p class="text-[10.5px] font-bold text-muted uppercase mb-2">{{ t('Non-Formal Education (Courses/Training)') }} * <span class="font-normal normal-case">({{ t('at least 1 row -- write "None" if you never had any') }})</span></p>
        <div class="overflow-x-auto border border-[#eef1f5] rounded-md mb-2">
            <table class="w-full text-[11.5px]">
                <thead class="bg-[#f8fafc] text-[10px] uppercase text-muted">
                    <tr>
                        <th class="text-left px-2 py-2">{{ t('Course/Training Type') }}</th>
                        <th class="text-left px-2 py-2">{{ t('Provider') }}</th>
                        <th class="text-left px-2 py-2 w-[80px]">{{ t('From Yr') }}</th>
                        <th class="text-left px-2 py-2 w-[80px]">{{ t('To Yr') }}</th>
                        <th class="text-left px-2 py-2 w-[120px]">{{ t('Certificate No.') }}</th>
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
                        <tr><td colspan="6" class="px-2 py-3 text-center text-muted">{{ t('No data yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @unless ($readonly)
            <button type="button" wire:click="addRow('educationNonformal', {{ json_encode(['course_type' => '', 'institution' => '', 'period_start_year' => '', 'period_end_year' => '', 'certificate_no' => '']) }})" class="text-[11px] font-bold text-navy border border-[#d7dbe2] px-3 py-1.5 rounded">＋ {{ t('Add Course/Training') }}</button>
        @endunless
    </section>

    {{-- ============ 4. PENGALAMAN ORGANISASI ============ --}}
    <section id="sec-organisasi">
        <h2 class="text-sm font-bold text-navy mb-3 pb-2 border-b border-[#eef1f5]">4. {{ t('Organization Experience') }} * <span class="font-normal normal-case text-[11px] text-muted">({{ t('at least 1 row -- write "None" if you never had any') }})</span></h2>
        <div class="overflow-x-auto border border-[#eef1f5] rounded-md mb-2">
            <table class="w-full text-[11.5px]">
                <thead class="bg-[#f8fafc] text-[10px] uppercase text-muted">
                    <tr>
                        <th class="text-left px-2 py-2">{{ t('Organization Name') }}</th>
                        <th class="text-left px-2 py-2 w-[140px]">{{ t('Job Title') }}</th>
                        <th class="text-left px-2 py-2 w-[130px]">{{ t('Period') }}</th>
                        <th class="text-left px-2 py-2">{{ t('Duties') }}</th>
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
                        <tr><td colspan="5" class="px-2 py-3 text-center text-muted">{{ t('No data yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @unless ($readonly)
            <button type="button" wire:click="addRow('orgExperience', {{ json_encode(['organization_name' => '', 'position' => '', 'period' => '', 'task_description' => '']) }})" class="text-[11px] font-bold text-navy border border-[#d7dbe2] px-3 py-1.5 rounded">＋ {{ t('Add Organization Experience') }}</button>
        @endunless
    </section>

    {{-- ============ 5. PENGALAMAN KERJA (tetap kartu, kolom terlalu banyak utk tabel) ============ --}}
    <section id="sec-kerja">
        <h2 class="text-sm font-bold text-navy mb-1 pb-2 border-b border-[#eef1f5]">5. {{ t('Work Experience') }} * <span class="font-normal text-[11px] text-muted">({{ t('at least 1, most recent first') }})</span></h2>
        @unless ($readonly)
            <p class="text-[10.5px] text-muted mb-3 mt-2">{{ t('Never worked before? Still add 1 row and write "No experience yet" in Company Name.') }}</p>
        @endunless
        @forelse ($workExp as $i => $w)
            <div class="border border-[#d7dbe2] rounded-md p-3 mb-3" wire:key="work-{{ $i }}">
                @if ($readonly)
                    <div class="grid two mb-2">
                        <x-form.display :label="t('Company Name')">{{ $w['company'] ?? '' }}</x-form.display>
                        <x-form.display :label="t('Location & Business Type')">{{ $w['location'] ?? '' }}</x-form.display>
                    </div>
                    <div class="grid two mb-2">
                        <x-form.display :label="t('Number of Employees')">{{ $w['employee_count'] ?? '' }}</x-form.display>
                        <x-form.display :label="t('Company Phone No.')">{{ $w['company_phone'] ?? '' }}</x-form.display>
                    </div>
                    <p class="text-[10px] font-bold text-muted uppercase mt-2 mb-1">{{ t('First Job Title') }}</p>
                    <div class="grid gap-2 mb-2" style="grid-template-columns:1fr 130px 130px;">
                        <x-form.display :label="t('Job Title')">{{ $w['position'] ?? '' }}</x-form.display>
                        <x-form.display :label="t('Start')">{{ $w['start_date'] ?? '' }}</x-form.display>
                        <x-form.display :label="t('End')">{{ $w['end_date'] ?? '' }}</x-form.display>
                    </div>
                    <p class="text-[10px] font-bold text-muted uppercase mt-2 mb-1">{{ t('Last Job Title') }}</p>
                    <div class="grid gap-2 mb-2" style="grid-template-columns:1fr 130px 130px;">
                        <x-form.display :label="t('Job Title')">{{ $w['position_end'] ?? '' }}</x-form.display>
                        <x-form.display :label="t('Start')">{{ $w['position_end_start_date'] ?? '' }}</x-form.display>
                        <x-form.display :label="t('End')">{{ $w['position_end_end_date'] ?? '' }}</x-form.display>
                    </div>
                    <div class="grid gap-2 mb-2" style="grid-template-columns:1fr 1fr 1fr 1fr;">
                        <x-form.display :label="t('Subordinates (start)')">{{ $w['subordinate_count_start'] ?? '' }}</x-form.display>
                        <x-form.display :label="t('Subordinates (end)')">{{ $w['subordinate_count_end'] ?? '' }}</x-form.display>
                        <x-form.display :label="t('Starting Salary')">{{ is_numeric($w['salary_start'] ?? null) ? number_format((float) $w['salary_start'], 0, ',', '.') : ($w['salary_start'] ?? '') }}</x-form.display>
                        <x-form.display :label="t('Last Salary')">{{ is_numeric($w['salary_end'] ?? null) ? number_format((float) $w['salary_end'], 0, ',', '.') : ($w['salary_end'] ?? '') }}</x-form.display>
                    </div>
                    <x-form.display :label="t('Benefits besides salary')">{{ $w['benefits'] ?? '' }}</x-form.display>
                    <div class="grid two mt-2">
                        <x-form.display :label="t('Direct Supervisor')">{{ $w['supervisor_name'] ?? '' }}</x-form.display>
                        <x-form.display :label="t('Supervisor Phone No.')">{{ $w['supervisor_phone'] ?? '' }}</x-form.display>
                    </div>
                    <div class="mt-2 space-y-2">
                        <x-form.display :label="t('Achievements')">{{ $w['achievement'] ?? '' }}</x-form.display>
                        <x-form.display :label="t('Job Description')">{{ $w['description'] ?? '' }}</x-form.display>
                        <x-form.display :label="t('Organization Structure')">{{ $w['org_structure_note'] ?? '' }}</x-form.display>
                        <x-form.display :label="t('Key problems & how they were solved')">{{ $w['key_challenge'] ?? '' }}</x-form.display>
                        <x-form.display :label="t('Changes/improvements made')">{{ $w['improvement_made'] ?? '' }}</x-form.display>
                        <x-form.display :label="t('Reason for Leaving')">{{ $w['leave_reason'] ?? '' }}</x-form.display>
                    </div>
                @else
                    <div class="grid two mb-2">
                        <x-form.input wire:model="workExp.{{ $i }}.company" :placeholder="t('Company Name')" />
                        <x-form.input wire:model="workExp.{{ $i }}.location" :placeholder="t('Location & Business Type')" />
                    </div>
                    <div class="grid two mb-2">
                        <x-form.input wire:model="workExp.{{ $i }}.employee_count" :placeholder="t('Number of Employees')" />
                        <x-form.input wire:model="workExp.{{ $i }}.company_phone" :placeholder="t('Company Phone No.')" />
                    </div>
                    <p class="text-[10px] font-bold text-muted uppercase mt-2 mb-1">{{ t('First Job Title') }}</p>
                    <div class="grid gap-2 mb-2" style="grid-template-columns:1fr 130px 130px;">
                        <x-form.input wire:model="workExp.{{ $i }}.position" :placeholder="t('First Job Title')" />
                        <x-form.date-picker wire:model="workExp.{{ $i }}.start_date" />
                        <x-form.date-picker wire:model="workExp.{{ $i }}.end_date" />
                    </div>
                    <p class="text-[10px] font-bold text-muted uppercase mt-2 mb-1">{{ t('Last Job Title') }}</p>
                    <div class="grid gap-2 mb-2" style="grid-template-columns:1fr 130px 130px;">
                        <x-form.input wire:model="workExp.{{ $i }}.position_end" :placeholder="t('Last Job Title')" />
                        <x-form.date-picker wire:model="workExp.{{ $i }}.position_end_start_date" />
                        <x-form.date-picker wire:model="workExp.{{ $i }}.position_end_end_date" />
                    </div>
                    <div class="grid gap-2 mb-2" style="grid-template-columns:1fr 1fr 1fr 1fr;">
                        <x-form.input wire:model="workExp.{{ $i }}.subordinate_count_start" :placeholder="t('Subordinates (start)')" />
                        <x-form.input wire:model="workExp.{{ $i }}.subordinate_count_end" :placeholder="t('Subordinates (end)')" />
                        <x-form.input type="number" wire:model="workExp.{{ $i }}.salary_start" :placeholder="t('Starting Salary')" />
                        <x-form.input type="number" wire:model="workExp.{{ $i }}.salary_end" :placeholder="t('Last Salary')" />
                    </div>
                    <x-form.input wire:model="workExp.{{ $i }}.benefits" :placeholder="t('Benefits besides salary')" class="mb-2" />
                    <div class="grid two mb-2">
                        <x-form.input wire:model="workExp.{{ $i }}.supervisor_name" :placeholder="t('Direct Supervisor')" />
                        <x-form.input wire:model="workExp.{{ $i }}.supervisor_phone" :placeholder="t('Supervisor Phone No.')" />
                    </div>
                    <x-form.input wire:model="workExp.{{ $i }}.achievement" :placeholder="t('Achievements')" class="mb-2" />
                    <x-form.textarea rows="2" wire:model="workExp.{{ $i }}.description" class="mb-2"><x-slot:label>{{ t('Job Description') }}</x-slot:label></x-form.textarea>
                    <x-form.input wire:model="workExp.{{ $i }}.org_structure_note" :placeholder="t('Describe the organization structure showing your position')" class="mb-2" />
                    <x-form.textarea rows="2" wire:model="workExp.{{ $i }}.key_challenge" class="mb-2"><x-slot:label>{{ t('Key problems you have faced & how you solved them') }}</x-slot:label></x-form.textarea>
                    <x-form.textarea rows="2" wire:model="workExp.{{ $i }}.improvement_made" class="mb-2"><x-slot:label>{{ t('Changes/improvements you have made') }}</x-slot:label></x-form.textarea>
                    <x-form.input wire:model="workExp.{{ $i }}.leave_reason" :placeholder="t('Reason for Leaving')" />
                    <button type="button" wire:click="removeRow('workExp', {{ $i }})" class="text-[10.5px] text-[#a3241f] mt-2">✕ {{ t('Remove This Experience') }}</button>
                @endif
            </div>
        @empty
            <p class="text-xs text-muted">{{ t('No data yet.') }}</p>
        @endforelse
        @unless ($readonly)
            <button type="button" wire:click="addRow('workExp', {{ json_encode(['company' => '', 'location' => '', 'business_type' => '', 'employee_count' => '', 'company_phone' => '', 'position' => '', 'start_date' => '', 'end_date' => '', 'position_end' => '', 'position_end_start_date' => '', 'position_end_end_date' => '', 'subordinate_count_start' => '', 'subordinate_count_end' => '', 'salary_start' => '', 'salary_end' => '', 'benefits' => '', 'supervisor_name' => '', 'supervisor_phone' => '', 'achievement' => '', 'description' => '', 'org_structure_note' => '', 'key_challenge' => '', 'improvement_made' => '', 'leave_reason' => '']) }})" class="text-[11px] font-bold text-navy border border-[#d7dbe2] px-3 py-1.5 rounded">＋ {{ t('Add Work Experience') }}</button>
        @endunless
    </section>

    {{-- ============ 6. SOSIAL & REFERENSI ============ --}}
    <section id="sec-sosial">
        <h2 class="text-sm font-bold text-navy mb-3 pb-2 border-b border-[#eef1f5]">6. {{ t('Social Activities, Activities & References') }}</h2>
        <div class="space-y-3 mb-4">
            @if ($readonly)
                <x-form.display :label="t('Acquaintances in our company (name & job title)')">{{ $company_acquaintance }}</x-form.display>
                <x-form.display :label="t('Hobbies')">{{ $hobby }}</x-form.display>
                <x-form.display :label="t('Leisure activities')">{{ $leisure_activity }}</x-form.display>
                <x-form.display :label="t('Reading topics')">{{ $reading_topics }}</x-form.display>
                <x-form.display :label="t('Been abroad, when & for what purpose')">{{ $went_abroad }}</x-form.display>
            @else
                <x-form.input :label="t('Do you know anyone in our company? (name & job title)').' *'" wire:model="company_acquaintance" />
                <x-form.input :label="t('Hobbies').' *'" wire:model="hobby" />
                <x-form.input :label="t('How do you spend your leisure time?').' *'" wire:model="leisure_activity" />
                <x-form.input :label="t('What topics do you read about?').' *'" wire:model="reading_topics" />
                <x-form.textarea rows="2" wire:model="went_abroad"><x-slot:label>{{ t('Have you ever been abroad? When & for what purpose?') }} *</x-slot:label></x-form.textarea>
            @endif
        </div>

        <p class="text-[10.5px] font-bold text-muted uppercase mb-2">{{ t('References') }} * <span class="font-normal normal-case">({{ t('at least 2 people, not family') }})</span></p>
        <div class="overflow-x-auto border border-[#eef1f5] rounded-md mb-2">
            <table class="w-full text-[11.5px]">
                <thead class="bg-[#f8fafc] text-[10px] uppercase text-muted">
                    <tr><th class="text-left px-2 py-2">{{ t('Name') }}</th><th class="text-left px-2 py-2 w-[160px]">{{ t('Phone No.') }}</th>@unless ($readonly)<th class="w-[40px]"></th>@endunless</tr>
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
                        <tr><td colspan="3" class="px-2 py-3 text-center text-muted">{{ t('No data yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @unless ($readonly)
            <button type="button" wire:click="addRow('professionalReferences', {{ json_encode(['name' => '', 'phone' => '']) }})" class="text-[11px] font-bold text-navy border border-[#d7dbe2] px-3 py-1.5 rounded mb-5">＋ {{ t('Add Reference') }}</button>
        @endunless

        <p class="text-[10.5px] font-bold text-muted uppercase mb-2">{{ t('Emergency Contacts') }} * <span class="font-normal normal-case">({{ t('at least 3 people, with relation & address') }})</span></p>
        <div class="overflow-x-auto border border-[#eef1f5] rounded-md mb-2">
            <table class="w-full text-[11.5px]">
                <thead class="bg-[#f8fafc] text-[10px] uppercase text-muted">
                    <tr><th class="text-left px-2 py-2">{{ t('Name') }}</th><th class="text-left px-2 py-2 w-[120px]">{{ t('Relation') }}</th><th class="text-left px-2 py-2 w-[130px]">{{ t('Phone No.') }}</th><th class="text-left px-2 py-2">{{ t('Address') }}</th>@unless ($readonly)<th class="w-[40px]"></th>@endunless</tr>
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
                        <tr><td colspan="5" class="px-2 py-3 text-center text-muted">{{ t('No data yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @unless ($readonly)
            <button type="button" wire:click="addRow('emergencyContacts', {{ json_encode(['name' => '', 'relation' => '', 'phone' => '', 'address' => '']) }})" class="text-[11px] font-bold text-navy border border-[#d7dbe2] px-3 py-1.5 rounded">＋ {{ t('Add Emergency Contact') }}</button>
        @endunless
    </section>

    {{-- ============ 7. MINAT & KONSEP PRIBADI ============ --}}
    <section id="sec-minat">
        <h2 class="text-sm font-bold text-navy mb-3 pb-2 border-b border-[#eef1f5]">7. {{ t('Interests & Self-Concept') }}</h2>
        <div class="space-y-3">
            @if ($readonly)
                <x-form.display :label="t('Why do you want to work at our Company?')">{{ $why_join_us }}</x-form.display>
                <x-form.display :label="t('What do you know about our company?')">{{ $what_you_know_about_us }}</x-form.display>
                <x-form.display :label="t('What contribution can you make in the position applied for?')">{{ $contribution_plan }}</x-form.display>
                <x-form.display :label="t('What would you do if you held that position?')">{{ $role_plan_if_hired }}</x-form.display>
                <x-form.display :label="t('What type of work do you enjoy? (Planner/Executor)')">{{ $job_type_preference }}</x-form.display>
                <x-form.display :label="t('What nature of work do you enjoy? (Research/Technical/Non-Technical)')">{{ $job_nature_preference }}</x-form.display>
                <x-form.display :label="t('What work environment do you enjoy? (Factory/Office/Field/Laboratory)')">{{ $work_environment_preference }}</x-form.display>
                <x-form.display :label="t('What kind of work/environment can hinder your work & trigger human error?')">{{ $hindering_environment }}</x-form.display>
                <x-form.display :label="t('What type of people do you like to work with?')">{{ $preferred_colleague_type }}</x-form.display>
                <x-form.display :label="t('On what matters do you find it hardest to make firm, high-impact decisions?')">{{ $difficult_decision_topic }}</x-form.display>
                <x-form.display :label="t('What are your strengths & weaknesses? (at least 5 each)')">{{ $strengths_weaknesses }}</x-form.display>
                <x-form.display :label="t('When you have difficulty solving a problem, whom do you ask? Why?')">{{ $who_you_ask_when_stuck }}</x-form.display>
            @else
                <x-form.textarea rows="2" wire:model="why_join_us"><x-slot:label>{{ t('Why do you want to work at our Company?') }} *</x-slot:label></x-form.textarea>
                <x-form.textarea rows="2" wire:model="what_you_know_about_us"><x-slot:label>{{ t('What do you know about our company?') }} *</x-slot:label></x-form.textarea>
                <x-form.textarea rows="2" wire:model="contribution_plan"><x-slot:label>{{ t('What contribution can you make in the position applied for?') }} *</x-slot:label></x-form.textarea>
                <x-form.textarea rows="2" wire:model="role_plan_if_hired"><x-slot:label>{{ t('What would you do if you held that position?') }} *</x-slot:label></x-form.textarea>
                <x-form.textarea rows="2" wire:model="job_type_preference"><x-slot:label>{{ t('What type of work do you enjoy? (Planner/Executor) — give your reason') }} *</x-slot:label></x-form.textarea>
                <x-form.textarea rows="2" wire:model="job_nature_preference"><x-slot:label>{{ t('What nature of work do you enjoy? (Research/Technical/Non-Technical) — give your reason') }} *</x-slot:label></x-form.textarea>
                <x-form.textarea rows="2" wire:model="work_environment_preference"><x-slot:label>{{ t('What work environment do you enjoy? (Factory/Office/Field/Laboratory) — give your reason') }} *</x-slot:label></x-form.textarea>
                <x-form.textarea rows="2" wire:model="hindering_environment"><x-slot:label>{{ t('What kind of work/environment can hinder your work & trigger human error?') }} *</x-slot:label></x-form.textarea>
                <x-form.textarea rows="2" wire:model="preferred_colleague_type"><x-slot:label>{{ t('What type of people do you like to work with?') }} *</x-slot:label></x-form.textarea>
                <x-form.textarea rows="2" wire:model="difficult_decision_topic"><x-slot:label>{{ t('On what matters do you find it hardest to make firm, high-impact decisions?') }} *</x-slot:label></x-form.textarea>
                <x-form.textarea rows="3" wire:model="strengths_weaknesses"><x-slot:label>{{ t('What are your strengths & weaknesses? (at least 5 each)') }} *</x-slot:label></x-form.textarea>
                <x-form.textarea rows="2" wire:model="who_you_ask_when_stuck"><x-slot:label>{{ t('When you have difficulty solving a problem, whom do you ask? Why?') }} *</x-slot:label></x-form.textarea>
            @endif
        </div>
    </section>

    {{-- ============ 8. RIWAYAT KESEHATAN ============ --}}
    <section id="sec-kesehatan">
        <h2 class="text-sm font-bold text-navy mb-3 pb-2 border-b border-[#eef1f5]">8. {{ t('Health History') }}</h2>
        <div class="grid gap-2 mb-3" style="grid-template-columns:1fr 1fr 1fr;">
            @if ($readonly)
                <x-form.display :label="t('Height (cm)')">{{ $height_cm }}</x-form.display>
                <x-form.display :label="t('Weight (kg)')">{{ $weight_kg }}</x-form.display>
                <x-form.display :label="t('Blood Type')">{{ $blood_type }}</x-form.display>
            @else
                <x-form.input :label="t('Height (cm)').' *'" type="number" wire:model="height_cm" />
                <x-form.input :label="t('Weight (kg)').' *'" type="number" wire:model="weight_kg" />
                <x-form.input :label="t('Blood Type').' *'" wire:model="blood_type" />
            @endif
        </div>
        <div class="space-y-3">
            @if ($readonly)
                <x-form.display :label="t('Illnesses suffered? For how long? Current condition?')">{{ $illness_history }}</x-form.display>
                <x-form.display :label="t('Ever hospitalized? For what? Lasting effects now?')">{{ $hospitalized_history }}</x-form.display>
                <div class="grid two">
                    <x-form.display :label="t('Smoker?')">{{ CandidateFormOptions::labels(CandidateFormOptions::YES_NO)[(int) $is_smoker] ?? ($is_smoker === null ? '—' : ($is_smoker ? t('Yes') : t('No'))) }}</x-form.display>
                    <x-form.display :label="t('Family in good health?')">{{ CandidateFormOptions::labels(CandidateFormOptions::FAMILY_HEALTH)[(int) $family_health_ok] ?? ($family_health_ok === null ? '—' : ($family_health_ok ? t('Yes, all fine') : t('There are problems'))) }}</x-form.display>
                </div>
                <x-form.display :label="t('Permanent/severe physical impairment')">{{ $physical_disability }}</x-form.display>
            @else
                <x-form.textarea rows="2" wire:model="illness_history"><x-slot:label>{{ t('What illnesses have you suffered? For how long? What is your current condition?') }} *</x-slot:label></x-form.textarea>
                <x-form.textarea rows="2" wire:model="hospitalized_history"><x-slot:label>{{ t('Have you ever been hospitalized? For what? What lasting effects do you have now?') }} *</x-slot:label></x-form.textarea>
                <div class="grid two">
                    <x-form.select wire:model="is_smoker" :options="CandidateFormOptions::labels(CandidateFormOptions::YES_NO)" :placeholder="t('— select —')"><x-slot:label>{{ t('Do you smoke?') }} *</x-slot:label></x-form.select>
                    <x-form.select wire:model="family_health_ok" :options="CandidateFormOptions::labels(CandidateFormOptions::FAMILY_HEALTH)" :placeholder="t('— select —')"><x-slot:label>{{ t('Is your family in good health?') }} *</x-slot:label></x-form.select>
                </div>
                <x-form.textarea rows="2" wire:model="physical_disability"><x-slot:label>{{ t('Do you have any permanent/severe physical impairment?') }} *</x-slot:label></x-form.textarea>
            @endif
        </div>
    </section>

    {{-- ============ 9. BAHASA, KOMPUTER & INFO LOWONGAN ============ --}}
    <section id="sec-bahasa">
        <h2 class="text-sm font-bold text-navy mb-3 pb-2 border-b border-[#eef1f5]">9. {{ t('Languages, Computer & Vacancy Info') }}</h2>

        <p class="text-[10.5px] font-bold text-muted uppercase mb-2">{{ t('Foreign Language Proficiency') }} * <span class="font-normal normal-case">({{ t('at least 1') }})</span></p>
        <div class="overflow-x-auto border border-[#eef1f5] rounded-md mb-2">
            <table class="w-full text-[11.5px]">
                <thead class="bg-[#f8fafc] text-[10px] uppercase text-muted">
                    <tr><th class="text-left px-2 py-2">{{ t('Language Name') }}</th><th class="text-left px-2 py-2 w-[130px]">{{ t('Written') }}</th><th class="text-left px-2 py-2 w-[130px]">{{ t('Spoken') }}</th><th class="text-left px-2 py-2">{{ t('Description') }}</th>@unless ($readonly)<th class="w-[40px]"></th>@endunless</tr>
                </thead>
                <tbody class="divide-y divide-[#eef1f5]">
                    @forelse ($languageSkills as $i => $l)
                        <tr wire:key="lang-{{ $i }}">
                            @if ($readonly)
                                <td class="px-2 py-1.5">{{ $l['language'] ?? '' }}</td>
                                <td class="px-2 py-1.5">{{ CandidateFormOptions::labels(CandidateFormOptions::SKILL_LEVEL)[$l['written'] ?? ''] ?? '—' }}</td>
                                <td class="px-2 py-1.5">{{ CandidateFormOptions::labels(CandidateFormOptions::SKILL_LEVEL)[$l['oral'] ?? ''] ?? '—' }}</td>
                                <td class="px-2 py-1.5">{{ $l['notes'] ?? '' }}</td>
                            @else
                                <td class="px-2 py-1.5"><x-form.input wire:model="languageSkills.{{ $i }}.language" /></td>
                                <td class="px-2 py-1.5"><x-form.select wire:model="languageSkills.{{ $i }}.written" :options="CandidateFormOptions::labels(CandidateFormOptions::SKILL_LEVEL)" placeholder="-" /></td>
                                <td class="px-2 py-1.5"><x-form.select wire:model="languageSkills.{{ $i }}.oral" :options="CandidateFormOptions::labels(CandidateFormOptions::SKILL_LEVEL)" placeholder="-" /></td>
                                <td class="px-2 py-1.5"><x-form.input wire:model="languageSkills.{{ $i }}.notes" /></td>
                                <td class="px-2 py-1.5 text-center"><button type="button" wire:click="removeRow('languageSkills', {{ $i }})" class="text-[#a3241f] text-xs">✕</button></td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-2 py-3 text-center text-muted">{{ t('No data yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @unless ($readonly)
            <button type="button" wire:click="addRow('languageSkills', {{ json_encode(['language' => '', 'written' => '', 'oral' => '', 'notes' => '']) }})" class="text-[11px] font-bold text-navy border border-[#d7dbe2] px-3 py-1.5 rounded mb-5">＋ {{ t('Add Language') }}</button>
        @endunless

        <p class="text-[10.5px] font-bold text-muted uppercase mb-2">{{ t('Computer Skills') }} * <span class="font-normal normal-case">({{ t('at least 1') }})</span></p>
        <div class="overflow-x-auto border border-[#eef1f5] rounded-md mb-2">
            <table class="w-full text-[11.5px]">
                <thead class="bg-[#f8fafc] text-[10px] uppercase text-muted">
                    <tr><th class="text-left px-2 py-2">{{ t('Skill') }}</th><th class="text-left px-2 py-2 w-[130px]">{{ t('Level') }}</th><th class="text-left px-2 py-2">{{ t('Description') }}</th>@unless ($readonly)<th class="w-[40px]"></th>@endunless</tr>
                </thead>
                <tbody class="divide-y divide-[#eef1f5]">
                    @forelse ($computerSkills as $i => $c)
                        <tr wire:key="comp-{{ $i }}">
                            @if ($readonly)
                                <td class="px-2 py-1.5">{{ $c['skill'] ?? '' }}</td>
                                <td class="px-2 py-1.5">{{ CandidateFormOptions::labels(CandidateFormOptions::SKILL_LEVEL)[$c['level'] ?? ''] ?? '—' }}</td>
                                <td class="px-2 py-1.5">{{ $c['notes'] ?? '' }}</td>
                            @else
                                <td class="px-2 py-1.5"><x-form.input wire:model="computerSkills.{{ $i }}.skill" :placeholder="t('e.g. Graphic Design')" /></td>
                                <td class="px-2 py-1.5"><x-form.select wire:model="computerSkills.{{ $i }}.level" :options="CandidateFormOptions::labels(CandidateFormOptions::SKILL_LEVEL)" placeholder="-" /></td>
                                <td class="px-2 py-1.5"><x-form.input wire:model="computerSkills.{{ $i }}.notes" /></td>
                                <td class="px-2 py-1.5 text-center"><button type="button" wire:click="removeRow('computerSkills', {{ $i }})" class="text-[#a3241f] text-xs">✕</button></td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-2 py-3 text-center text-muted">{{ t('No data yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @unless ($readonly)
            <button type="button" wire:click="addRow('computerSkills', {{ json_encode(['skill' => '', 'level' => '', 'notes' => '']) }})" class="text-[11px] font-bold text-navy border border-[#d7dbe2] px-3 py-1.5 rounded mb-5">＋ {{ t('Add Computer Skill') }}</button>
        @endunless

        <div class="space-y-3">
            @if ($readonly)
                <x-form.display :label="t('Still receiving support? From whom & how much/how long?')">{{ $still_receiving_aid }}</x-form.display>
                <x-form.display :label="t('Other dependents besides children & spouse?')">{{ $other_dependents }}</x-form.display>
                <x-form.display :label="t('Where did you hear about vacancies at our company?')">{{ $source_of_info }}</x-form.display>
                <div class="grid two">
                    <x-form.display :label="t('Expected salary')">{{ is_numeric($expected_salary) ? number_format((float) $expected_salary, 0, ',', '.') : $expected_salary }}</x-form.display>
                    <x-form.display :label="t('Available to start work')">{{ $availability_date }}</x-form.display>
                </div>
                <x-form.display :label="t('Expected benefits')">{{ $expected_facility }}</x-form.display>
            @else
                <x-form.textarea rows="2" wire:model="still_receiving_aid"><x-slot:label>{{ t('Are you still receiving support? From whom & how much/how long?') }} *</x-slot:label></x-form.textarea>
                <x-form.textarea rows="2" wire:model="other_dependents"><x-slot:label>{{ t('Do you have other dependents besides children & spouse? Name them & how much') }} *</x-slot:label></x-form.textarea>
                <x-form.input :label="t('Where did you hear about vacancies at our company?').' *'" wire:model="source_of_info" />
                <div class="grid two">
                    <x-form.input :label="t('Expected salary').' *'" type="number" wire:model="expected_salary" />
                    <x-form.date-picker :label="t('Available to start work').' *'" wire:model="availability_date" />
                </div>
                <x-form.input :label="t('Expected benefits').' *'" wire:model="expected_facility" />
            @endif
        </div>
    </section>

    {{-- ============ 10. KESEDIAAN & PERNYATAAN ============ --}}
    <section id="sec-kesediaan">
        <h2 class="text-sm font-bold text-navy mb-3 pb-2 border-b border-[#eef1f5]">10. {{ t('Willingness & Statement') }} <span class="font-normal text-[11px] text-muted">({{ t('all must be checked') }})</span></h2>
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
            "{{ t('I declare that the information I have given is true and reflects my condition.') }} {{ t('If any of this information is found to be inaccurate, I am willing to resign/be dismissed without severance or compensation of any kind,') }} {{ t('accept all consequences imposed by Telasih Makmur, and be processed according to the applicable law.') }}"
        </div>
        @if ($readonly)
            <div class="grid two">
                <x-form.display :label="t('Agrees to Statement')">{{ $agreement_signed ? t('Yes') : t('No') }}</x-form.display>
                <x-form.display :label="t('Signature (name)')">{{ $signature_name }}</x-form.display>
            </div>
        @else
            <label class="flex items-center gap-2 text-xs mb-2"><input type="checkbox" wire:model="agreement_signed" class="!w-auto"> {{ t('I agree to the statement above') }} *</label>
            <x-form.input :label="t('Type your full name as signature').' *'" wire:model="signature_name" :placeholder="t('Full name')" />
        @endif
    </section>

    {{-- ============ 11. UPLOAD DOKUMEN ============ --}}
    <section id="sec-dokumen">
        <h2 class="text-sm font-bold text-navy mb-3 pb-2 border-b border-[#eef1f5]">11. {{ $readonly ? t('Documents') : t('Upload Documents') }}</h2>
        @if ($readonly)
            <div class="space-y-2">
                @forelse (($documents ?? []) as $doc)
                    <a href="{{ $doc->file_path }}" target="_blank" rel="noopener" class="flex items-center gap-2 text-[12.5px] text-accent hover:underline">
                        📎 {{ strtoupper($doc->doc_type) }} — {{ t('open/download') }}
                    </a>
                @empty
                    <p class="text-xs text-muted">{{ t('No documents uploaded yet.') }}</p>
                @endforelse
            </div>
        @else
            <div class="space-y-3">
                <div>
                    <label class="text-[10px] text-muted uppercase font-semibold block mb-1">{{ t('CV') }} <span class="text-[#a3241f]">*</span></label>
                    <input type="file" wire:model="cvFile" class="text-xs">
                    @if ($cvFile) <p class="text-[10.5px] text-[#1f8a4c] mt-1">✓ {{ $cvFile->getClientOriginalName() }}</p> @endif
                </div>
                <div>
                    <label class="text-[10px] text-muted uppercase font-semibold block mb-1">{{ t('Diploma') }} <span class="text-[#a3241f]">*</span></label>
                    <input type="file" wire:model="ijazahFile" class="text-xs">
                    @if ($ijazahFile) <p class="text-[10.5px] text-[#1f8a4c] mt-1">✓ {{ $ijazahFile->getClientOriginalName() }}</p> @endif
                </div>
            </div>
        @endif
    </section>
</div>
