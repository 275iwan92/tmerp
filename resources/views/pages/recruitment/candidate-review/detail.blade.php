<?php

use App\Models\Hrr\AppApplication;
use App\Models\Hrr\AppPipelineStageConfig;
use App\Models\Hrr\CndCandidate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Review Formulir Kandidat -- tampilan read-only lengkap "Data Calon
// Karyawan" (careers/apply.blade.php + _apply-form-steps.blade.php, 11
// seksi) per lamaran, utk HR meninjau sebelum memutuskan lanjut/reject di
// Pipeline Board. Sumber data: cnd_candidate + child table (hasil
// PortalSyncService menarik submission dari portal) -- layar ini TIDAK
// mengedit apapun.
new #[Title('Review Formulir Kandidat — TELASIH MAKMUR ERP')] class extends Component
{
    public int $applicationId;

    public function mount(int $application): void
    {
        $this->applicationId = $application;
    }

    #[Computed]
    public function application(): AppApplication
    {
        return AppApplication::with('candidate', 'vacancy.requisition')->findOrFail($this->applicationId);
    }

    #[Computed]
    public function candidate(): CndCandidate
    {
        return CndCandidate::with([
            'familyMembers', 'education', 'educationNonformal', 'organizationExperience', 'workExperience', 'documents',
        ])->findOrFail($this->application->id_candidate);
    }

    #[Computed]
    public function stageName(): ?string
    {
        $trackId = $this->application->vacancy->requisition->id_track ?? null;

        if (! $trackId) {
            return null;
        }

        return AppPipelineStageConfig::where('id_track', $trackId)
            ->where('stage_seq', $this->application->current_stage_seq)
            ->value('stage_name');
    }

    private const WILLING_ITEMS = [
        'probation' => 'Mengikuti masa percobaan',
        'training' => 'Mengikuti training sesuai ketentuan Perusahaan',
        'overtime' => 'Bekerja lembur (termasuk Sabtu/Minggu/hari besar)',
        'guarantee_docs' => 'Menyerahkan jaminan Ijazah/BPKB jika dibutuhkan',
        'resign_notice' => 'Mengajukan permohonan tertulis 1 (satu) bulan sebelumnya apabila hendak mengundurkan diri',
        'obey_rules' => 'Mematuhi segala peraturan perusahaan yang berlaku',
        'resign_if_married_pregnant' => 'Bersedia mengundurkan diri jika menikah dengan rekan sekerja atau hamil sebelum masa kerja 1 (satu) tahun',
        'relocation' => 'Bersedia setiap saat dipindah/ditugaskan ke bagian/cabang lain',
    ];

    #[Computed]
    public function willingItems(): array
    {
        return self::WILLING_ITEMS;
    }

    private const LEVEL_LABELS = ['S' => 'Sedang', 'CB' => 'Cukup Baik', 'B' => 'Baik'];

    public function levelLabel(?string $code): string
    {
        return self::LEVEL_LABELS[$code] ?? '—';
    }

    public function optionLabel(array $map, $key): string
    {
        return $map[$key] ?? '—';
    }
};
?>

<div>
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50">
        <b class="text-[15px]">📄 Review Formulir Kandidat</b>
        <span class="text-xs text-white/75">{{ $this->candidate->name }} — {{ $this->application->vacancy->title ?? '' }}</span>
        <span class="text-[10.5px] font-bold px-2.5 py-0.5 rounded-full
            {{ match ($this->application->status) {
                'APPLIED' => 'bg-[#fff5e0] text-[#8a5a00]',
                'OFFERING' => 'bg-[#f1ecfc] text-[#6d28d9]',
                'HIRED' => 'bg-[#eaf7ee] text-[#1f8a4c]',
                'REJECTED', 'WITHDRAWN' => 'bg-[#fbeaea] text-[#a3241f]',
                default => 'bg-[#eef1f5] text-[#4b5568]',
            } }}">{{ $this->application->status }}{{ $this->stageName ? ' · '.$this->stageName : '' }}</span>
        <a href="{{ route('hrm.recruitment.candidate-review.list') }}" title="Kembali"
            class="ml-auto inline-flex items-center gap-2 pl-3.5 pr-1 py-1 bg-[#43508a] rounded-full no-underline hover:brightness-110">
            <span class="text-[11px] font-bold uppercase tracking-wide text-white">Back</span>
            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-white text-[#43508a] text-xs font-bold">←</span>
        </a>
    </header>

    @php
        $c = $this->candidate;
        $genderMap = ['LAKI_LAKI' => 'Laki-laki', 'PEREMPUAN' => 'Perempuan'];
        $vehicleMap = ['MOTOR' => 'Motor', 'MOBIL' => 'Mobil', 'TIDAK_ADA' => 'Tidak Ada'];
        $maritalMap = ['LAJANG' => 'Lajang', 'MENIKAH' => 'Menikah', 'DUDA' => 'Duda', 'JANDA' => 'Janda'];
    @endphp

    <div class="max-w-[900px] mx-auto my-5 px-4 space-y-5">

        {{-- ============ 1. DATA PRIBADI ============ --}}
        <section class="bg-white border border-[#d7dbe2] rounded-md p-4">
            <h2 class="text-sm font-bold text-navy mb-3 pb-2 border-b border-[#eef1f5]">1. Data Pribadi</h2>
            <div class="grid two gap-y-3">
                <x-form.display label="No. KTP">{{ $c->national_id }}</x-form.display>
                <x-form.display label="Nama Lengkap">{{ $c->name }}</x-form.display>
                <x-form.display label="Nama Panggilan">{{ $c->nickname }}</x-form.display>
                <x-form.display label="Jenis Kelamin">{{ $this->optionLabel($genderMap, $c->gender) }}</x-form.display>
                <x-form.display label="Agama">{{ $c->religion }}</x-form.display>
                <x-form.display label="Tanggal Lahir">{{ $c->birth_date?->translatedFormat('d M Y') }}</x-form.display>
                <x-form.display label="Email">{{ $c->email }}</x-form.display>
                <x-form.display label="No. HP">{{ $c->phone }}</x-form.display>
                <x-form.display label="Kendaraan yang Dimiliki">{{ $this->optionLabel($vehicleMap, $c->vehicle_type) }}</x-form.display>
                <x-form.display label="SIM yang Dimiliki">{{ implode(', ', $c->sim_types ?? []) ?: '—' }}</x-form.display>
            </div>
            <div class="grid two gap-y-3 mt-3">
                <x-form.display label="Alamat Sesuai KTP">{{ $c->address }}</x-form.display>
                <x-form.display label="Alamat Tinggal">{{ $c->address_domisili }}</x-form.display>
            </div>
        </section>

        {{-- ============ 2. DATA KELUARGA ============ --}}
        <section class="bg-white border border-[#d7dbe2] rounded-md p-4">
            <h2 class="text-sm font-bold text-navy mb-3 pb-2 border-b border-[#eef1f5]">2. Data Keluarga</h2>
            <div class="grid two gap-y-3 mb-4">
                <x-form.display label="Status Pernikahan">{{ $this->optionLabel($maritalMap, $c->marital_status) }}</x-form.display>
                <x-form.display label="Tanggal Menikah/Bercerai">{{ $c->marital_date?->translatedFormat('d M Y') }}</x-form.display>
            </div>
            <p class="text-[10.5px] font-bold text-muted uppercase mb-2">Susunan Keluarga</p>
            <div class="overflow-x-auto border border-[#eef1f5] rounded-md">
                <table class="w-full text-[11.5px]">
                    <thead class="bg-[#f8fafc] text-[10px] uppercase text-muted">
                        <tr>
                            <th class="text-left px-2 py-2">Hubungan</th><th class="text-left px-2 py-2">Nama</th>
                            <th class="text-left px-2 py-2">L/P</th><th class="text-left px-2 py-2">Usia</th>
                            <th class="text-left px-2 py-2">Pendidikan Terakhir</th><th class="text-left px-2 py-2">Pekerjaan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#eef1f5]">
                        @forelse ($c->familyMembers as $f)
                            <tr>
                                <td class="px-2 py-1.5 font-bold text-navy">{{ match ($f->relation_type) {
                                    'FATHER' => 'Ayah', 'MOTHER' => 'Ibu', 'SPOUSE' => 'Suami/Istri', 'CHILD' => 'Anak',
                                    'SIBLING' => 'Saudara Kandung', 'FATHER_IN_LAW' => 'Mertua Ayah', 'MOTHER_IN_LAW' => 'Mertua Ibu',
                                    default => $f->relation_type,
                                } }}</td>
                                <td class="px-2 py-1.5">{{ $f->name }}</td>
                                <td class="px-2 py-1.5">{{ $f->gender }}</td>
                                <td class="px-2 py-1.5">{{ $f->age }}</td>
                                <td class="px-2 py-1.5">{{ $f->last_education }}</td>
                                <td class="px-2 py-1.5">{{ $f->current_job }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-2 py-3 text-center text-muted">Belum ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- ============ 3. RIWAYAT PENDIDIKAN ============ --}}
        <section class="bg-white border border-[#d7dbe2] rounded-md p-4">
            <h2 class="text-sm font-bold text-navy mb-3 pb-2 border-b border-[#eef1f5]">3. Riwayat Pendidikan</h2>
            <p class="text-[10.5px] font-bold text-muted uppercase mb-2">Pendidikan Formal</p>
            <div class="overflow-x-auto border border-[#eef1f5] rounded-md mb-4">
                <table class="w-full text-[11.5px]">
                    <thead class="bg-[#f8fafc] text-[10px] uppercase text-muted">
                        <tr>
                            <th class="text-left px-2 py-2">Jenjang</th><th class="text-left px-2 py-2">Instansi</th>
                            <th class="text-left px-2 py-2">Jurusan</th><th class="text-left px-2 py-2">Dari Th</th>
                            <th class="text-left px-2 py-2">S/d Th</th><th class="text-left px-2 py-2">Nilai Akhir</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#eef1f5]">
                        @forelse ($c->education as $e)
                            <tr>
                                <td class="px-2 py-1.5">{{ $e->level }}</td><td class="px-2 py-1.5">{{ $e->institution }}</td>
                                <td class="px-2 py-1.5">{{ $e->major }}</td><td class="px-2 py-1.5">{{ $e->period_start_year }}</td>
                                <td class="px-2 py-1.5">{{ $e->graduation_year }}</td><td class="px-2 py-1.5">{{ $e->final_score }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-2 py-3 text-center text-muted">Belum ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="text-[10.5px] font-bold text-muted uppercase mb-2">Pendidikan Non-Formal (Kursus/Pelatihan)</p>
            <div class="overflow-x-auto border border-[#eef1f5] rounded-md">
                <table class="w-full text-[11.5px]">
                    <thead class="bg-[#f8fafc] text-[10px] uppercase text-muted">
                        <tr>
                            <th class="text-left px-2 py-2">Jenis Kursus/Pelatihan</th><th class="text-left px-2 py-2">Lembaga</th>
                            <th class="text-left px-2 py-2">Dari Th</th><th class="text-left px-2 py-2">S/d Th</th>
                            <th class="text-left px-2 py-2">No. Sertifikat</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#eef1f5]">
                        @forelse ($c->educationNonformal as $e)
                            <tr>
                                <td class="px-2 py-1.5">{{ $e->course_type }}</td><td class="px-2 py-1.5">{{ $e->institution }}</td>
                                <td class="px-2 py-1.5">{{ $e->period_start_year }}</td><td class="px-2 py-1.5">{{ $e->period_end_year }}</td>
                                <td class="px-2 py-1.5">{{ $e->certificate_no }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-2 py-3 text-center text-muted">Belum ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- ============ 4. PENGALAMAN ORGANISASI ============ --}}
        <section class="bg-white border border-[#d7dbe2] rounded-md p-4">
            <h2 class="text-sm font-bold text-navy mb-3 pb-2 border-b border-[#eef1f5]">4. Pengalaman Organisasi</h2>
            <div class="overflow-x-auto border border-[#eef1f5] rounded-md">
                <table class="w-full text-[11.5px]">
                    <thead class="bg-[#f8fafc] text-[10px] uppercase text-muted">
                        <tr>
                            <th class="text-left px-2 py-2">Nama Organisasi</th><th class="text-left px-2 py-2">Jabatan</th>
                            <th class="text-left px-2 py-2">Periode</th><th class="text-left px-2 py-2">Uraian Tugas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#eef1f5]">
                        @forelse ($c->organizationExperience as $o)
                            <tr>
                                <td class="px-2 py-1.5">{{ $o->organization_name }}</td><td class="px-2 py-1.5">{{ $o->position }}</td>
                                <td class="px-2 py-1.5">{{ $o->period }}</td><td class="px-2 py-1.5">{{ $o->task_description }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-2 py-3 text-center text-muted">Belum ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- ============ 5. PENGALAMAN KERJA ============ --}}
        <section class="bg-white border border-[#d7dbe2] rounded-md p-4">
            <h2 class="text-sm font-bold text-navy mb-3 pb-2 border-b border-[#eef1f5]">5. Pengalaman Kerja</h2>
            @forelse ($c->workExperience as $w)
                <div class="border border-[#eef1f5] rounded-md p-3 mb-3 last:mb-0">
                    <div class="grid two gap-y-3 mb-2">
                        <x-form.display label="Nama Perusahaan">{{ $w->company }}</x-form.display>
                        <x-form.display label="Lokasi & Jenis Usaha">{{ $w->location }}</x-form.display>
                        <x-form.display label="Jumlah Karyawan">{{ $w->employee_count }}</x-form.display>
                        <x-form.display label="No. Telepon Perusahaan">{{ $w->company_phone }}</x-form.display>
                    </div>
                    <p class="text-[10px] font-bold text-muted uppercase mt-2 mb-1">Jabatan Awal</p>
                    <div class="grid gap-y-3" style="grid-template-columns:1fr 130px 130px;">
                        <x-form.display label="Jabatan">{{ $w->position }}</x-form.display>
                        <x-form.display label="Mulai">{{ $w->start_date?->translatedFormat('d M Y') }}</x-form.display>
                        <x-form.display label="Selesai">{{ $w->end_date?->translatedFormat('d M Y') }}</x-form.display>
                    </div>
                    <p class="text-[10px] font-bold text-muted uppercase mt-2 mb-1">Jabatan Terakhir</p>
                    <div class="grid gap-y-3" style="grid-template-columns:1fr 130px 130px;">
                        <x-form.display label="Jabatan">{{ $w->position_end }}</x-form.display>
                        <x-form.display label="Mulai">{{ $w->position_end_start_date?->translatedFormat('d M Y') }}</x-form.display>
                        <x-form.display label="Selesai">{{ $w->position_end_end_date?->translatedFormat('d M Y') }}</x-form.display>
                    </div>
                    <div class="grid gap-y-3 mt-2" style="grid-template-columns:1fr 1fr 1fr 1fr;">
                        <x-form.display label="Bawahan (awal)">{{ $w->subordinate_count_start }}</x-form.display>
                        <x-form.display label="Bawahan (akhir)">{{ $w->subordinate_count_end }}</x-form.display>
                        <x-form.display label="Gaji Awal">{{ $w->salary_start }}</x-form.display>
                        <x-form.display label="Gaji Terakhir">{{ $w->salary_end }}</x-form.display>
                    </div>
                    <div class="mt-2"><x-form.display label="Fasilitas selain gaji">{{ $w->benefits }}</x-form.display></div>
                    <div class="grid two gap-y-3 mt-2">
                        <x-form.display label="Atasan Langsung">{{ $w->supervisor_name }}</x-form.display>
                        <x-form.display label="No. Telp Atasan">{{ $w->supervisor_phone }}</x-form.display>
                    </div>
                    <div class="mt-2 space-y-2">
                        <x-form.display label="Prestasi">{{ $w->achievement }}</x-form.display>
                        <x-form.display label="Uraian Pekerjaan">{{ $w->description }}</x-form.display>
                        <x-form.display label="Struktur Organisasi">{{ $w->org_structure_note }}</x-form.display>
                        <x-form.display label="Masalah penting & cara mengatasi">{{ $w->key_challenge }}</x-form.display>
                        <x-form.display label="Perubahan/pembaruan yang dilakukan">{{ $w->improvement_made }}</x-form.display>
                        <x-form.display label="Alasan Keluar">{{ $w->leave_reason }}</x-form.display>
                    </div>
                </div>
            @empty
                <p class="text-xs text-muted">Belum ada data.</p>
            @endforelse
        </section>

        {{-- ============ 6. SOSIAL & REFERENSI ============ --}}
        <section class="bg-white border border-[#d7dbe2] rounded-md p-4">
            <h2 class="text-sm font-bold text-navy mb-3 pb-2 border-b border-[#eef1f5]">6. Aktivitas Sosial, Kegiatan & Referensi</h2>
            <div class="space-y-3 mb-4">
                <x-form.display label="Kenalan di perusahaan kami">{{ $c->company_acquaintance }}</x-form.display>
                <x-form.display label="Hobi">{{ $c->hobby }}</x-form.display>
                <x-form.display label="Mengisi waktu luang">{{ $c->leisure_activity }}</x-form.display>
                <x-form.display label="Topik-topik yang dibaca">{{ $c->reading_topics }}</x-form.display>
                <x-form.display label="Pernah ke luar negeri">{{ $c->went_abroad }}</x-form.display>
            </div>
            <p class="text-[10.5px] font-bold text-muted uppercase mb-2">Referensi</p>
            <div class="overflow-x-auto border border-[#eef1f5] rounded-md mb-4">
                <table class="w-full text-[11.5px]">
                    <thead class="bg-[#f8fafc] text-[10px] uppercase text-muted"><tr><th class="text-left px-2 py-2">Nama</th><th class="text-left px-2 py-2">No. Telp</th></tr></thead>
                    <tbody class="divide-y divide-[#eef1f5]">
                        @forelse (($c->professional_references ?? []) as $r)
                            <tr><td class="px-2 py-1.5">{{ $r['name'] ?? '' }}</td><td class="px-2 py-1.5">{{ $r['phone'] ?? '' }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="px-2 py-3 text-center text-muted">Belum ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="text-[10.5px] font-bold text-muted uppercase mb-2">Kontak Darurat</p>
            <div class="overflow-x-auto border border-[#eef1f5] rounded-md">
                <table class="w-full text-[11.5px]">
                    <thead class="bg-[#f8fafc] text-[10px] uppercase text-muted"><tr><th class="text-left px-2 py-2">Nama</th><th class="text-left px-2 py-2">Hubungan</th><th class="text-left px-2 py-2">No. Telp</th><th class="text-left px-2 py-2">Alamat</th></tr></thead>
                    <tbody class="divide-y divide-[#eef1f5]">
                        @forelse (($c->emergency_contacts ?? []) as $e)
                            <tr><td class="px-2 py-1.5">{{ $e['name'] ?? '' }}</td><td class="px-2 py-1.5">{{ $e['relation'] ?? '' }}</td><td class="px-2 py-1.5">{{ $e['phone'] ?? '' }}</td><td class="px-2 py-1.5">{{ $e['address'] ?? '' }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="px-2 py-3 text-center text-muted">Belum ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- ============ 7. MINAT & KONSEP PRIBADI ============ --}}
        <section class="bg-white border border-[#d7dbe2] rounded-md p-4">
            <h2 class="text-sm font-bold text-navy mb-3 pb-2 border-b border-[#eef1f5]">7. Minat & Konsep Pribadi</h2>
            <div class="space-y-3">
                <x-form.display label="Mengapa ingin bekerja di perusahaan kami">{{ $c->why_join_us }}</x-form.display>
                <x-form.display label="Yang diketahui mengenai perusahaan kami">{{ $c->what_you_know_about_us }}</x-form.display>
                <x-form.display label="Kontribusi untuk posisi yang dilamar">{{ $c->contribution_plan }}</x-form.display>
                <x-form.display label="Yang akan dilakukan jika menduduki posisi tersebut">{{ $c->role_plan_if_hired }}</x-form.display>
                <x-form.display label="Jenis pekerjaan yang disenangi">{{ $c->job_type_preference }}</x-form.display>
                <x-form.display label="Sifat pekerjaan yang disenangi">{{ $c->job_nature_preference }}</x-form.display>
                <x-form.display label="Lingkungan/suasana kerja yang disenangi">{{ $c->work_environment_preference }}</x-form.display>
                <x-form.display label="Yang dapat menghambat pekerjaan & memicu human error">{{ $c->hindering_environment }}</x-form.display>
                <x-form.display label="Tipe rekan kerja yang disenangi">{{ $c->preferred_colleague_type }}</x-form.display>
                <x-form.display label="Hal yang paling sulit diambil keputusan tegas">{{ $c->difficult_decision_topic }}</x-form.display>
                <x-form.display label="Kelebihan & Kekurangan">{{ $c->strengths_weaknesses }}</x-form.display>
                <x-form.display label="Kepada siapa bertanya jika mengalami kesulitan">{{ $c->who_you_ask_when_stuck }}</x-form.display>
            </div>
        </section>

        {{-- ============ 8. RIWAYAT KESEHATAN ============ --}}
        <section class="bg-white border border-[#d7dbe2] rounded-md p-4">
            <h2 class="text-sm font-bold text-navy mb-3 pb-2 border-b border-[#eef1f5]">8. Riwayat Kesehatan</h2>
            <div class="grid gap-y-3 mb-3" style="grid-template-columns:1fr 1fr 1fr;">
                <x-form.display label="Tinggi Badan (cm)">{{ $c->height_cm }}</x-form.display>
                <x-form.display label="Berat Badan (kg)">{{ $c->weight_kg }}</x-form.display>
                <x-form.display label="Golongan Darah">{{ $c->blood_type }}</x-form.display>
            </div>
            <div class="space-y-3">
                <x-form.display label="Riwayat Penyakit">{{ $c->illness_history }}</x-form.display>
                <x-form.display label="Riwayat Dirawat di RS">{{ $c->hospitalized_history }}</x-form.display>
                <div class="grid two gap-y-3">
                    <x-form.display label="Merokok">{{ $c->is_smoker === null ? '—' : ($c->is_smoker ? 'Ya' : 'Tidak') }}</x-form.display>
                    <x-form.display label="Kesehatan Keluarga">{{ $c->family_health_ok === null ? '—' : ($c->family_health_ok ? 'Ya, baik-baik saja' : 'Ada masalah') }}</x-form.display>
                </div>
                <x-form.display label="Gangguan Jasmani">{{ $c->physical_disability }}</x-form.display>
            </div>
        </section>

        {{-- ============ 9. BAHASA, KOMPUTER & INFO LOWONGAN ============ --}}
        <section class="bg-white border border-[#d7dbe2] rounded-md p-4">
            <h2 class="text-sm font-bold text-navy mb-3 pb-2 border-b border-[#eef1f5]">9. Bahasa, Komputer & Info Lowongan</h2>
            <p class="text-[10.5px] font-bold text-muted uppercase mb-2">Penguasaan Bahasa Asing</p>
            <div class="overflow-x-auto border border-[#eef1f5] rounded-md mb-4">
                <table class="w-full text-[11.5px]">
                    <thead class="bg-[#f8fafc] text-[10px] uppercase text-muted"><tr><th class="text-left px-2 py-2">Bahasa</th><th class="text-left px-2 py-2">Tertulis</th><th class="text-left px-2 py-2">Lisan</th><th class="text-left px-2 py-2">Keterangan</th></tr></thead>
                    <tbody class="divide-y divide-[#eef1f5]">
                        @forelse (($c->language_skills ?? []) as $l)
                            <tr><td class="px-2 py-1.5">{{ $l['language'] ?? '' }}</td><td class="px-2 py-1.5">{{ $this->levelLabel($l['written'] ?? null) }}</td><td class="px-2 py-1.5">{{ $this->levelLabel($l['oral'] ?? null) }}</td><td class="px-2 py-1.5">{{ $l['notes'] ?? '' }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="px-2 py-3 text-center text-muted">Belum ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="text-[10.5px] font-bold text-muted uppercase mb-2">Penguasaan Komputer</p>
            <div class="overflow-x-auto border border-[#eef1f5] rounded-md mb-4">
                <table class="w-full text-[11.5px]">
                    <thead class="bg-[#f8fafc] text-[10px] uppercase text-muted"><tr><th class="text-left px-2 py-2">Skill</th><th class="text-left px-2 py-2">Level</th><th class="text-left px-2 py-2">Keterangan</th></tr></thead>
                    <tbody class="divide-y divide-[#eef1f5]">
                        @forelse (($c->computer_skills ?? []) as $s)
                            <tr><td class="px-2 py-1.5">{{ $s['skill'] ?? '' }}</td><td class="px-2 py-1.5">{{ $this->levelLabel($s['level'] ?? null) }}</td><td class="px-2 py-1.5">{{ $s['notes'] ?? '' }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="px-2 py-3 text-center text-muted">Belum ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="space-y-3">
                <x-form.display label="Masih mendapatkan bantuan">{{ $c->still_receiving_aid }}</x-form.display>
                <x-form.display label="Tanggungan lain">{{ $c->other_dependents }}</x-form.display>
                <x-form.display label="Sumber info lowongan">{{ $c->source_of_info }}</x-form.display>
                <div class="grid two gap-y-3">
                    <x-form.display label="Gaji yang diinginkan">{{ $c->expected_salary !== null ? number_format((float) $c->expected_salary, 0, ',', '.') : null }}</x-form.display>
                    <x-form.display label="Kesediaan mulai bekerja">{{ $c->availability_date?->translatedFormat('d M Y') }}</x-form.display>
                </div>
                <x-form.display label="Fasilitas yang diinginkan">{{ $c->expected_facility }}</x-form.display>
            </div>
        </section>

        {{-- ============ 10. KESEDIAAN & PERNYATAAN ============ --}}
        <section class="bg-white border border-[#d7dbe2] rounded-md p-4">
            <h2 class="text-sm font-bold text-navy mb-3 pb-2 border-b border-[#eef1f5]">10. Kesediaan & Pernyataan</h2>
            <div class="space-y-2 mb-4">
                @foreach ($this->willingItems as $key => $label)
                    <div class="flex items-start gap-2 text-[12px] border border-[#eef1f5] rounded-md p-2.5">
                        <span class="mt-0.5">{{ ($c->willingness_checklist[$key] ?? false) ? '✅' : '⬜' }}</span>
                        <span>{{ $label }}</span>
                    </div>
                @endforeach
            </div>
            <div class="grid two gap-y-3">
                <x-form.display label="Menyetujui Pernyataan">{{ $c->agreement_signed ? 'Ya' : 'Tidak' }}</x-form.display>
                <x-form.display label="Tanda Tangan (nama)">{{ $c->signature_name }}</x-form.display>
            </div>
        </section>

        {{-- ============ 11. DOKUMEN ============ --}}
        <section class="bg-white border border-[#d7dbe2] rounded-md p-4">
            <h2 class="text-sm font-bold text-navy mb-3 pb-2 border-b border-[#eef1f5]">11. Dokumen</h2>
            <div class="space-y-2">
                @forelse ($c->documents as $doc)
                    <a href="{{ $doc->file_path }}" target="_blank" rel="noopener" class="flex items-center gap-2 text-[12.5px] text-accent hover:underline">
                        📎 {{ strtoupper($doc->doc_type) }} — buka/download
                    </a>
                @empty
                    <p class="text-xs text-muted">Belum ada dokumen diunggah.</p>
                @endforelse
            </div>
        </section>
    </div>
</div>
