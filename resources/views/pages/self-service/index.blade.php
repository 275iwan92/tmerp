<?php

use App\Models\Master\EmpBadgePhotoRequest;
use App\Models\Master\EmpCorrectionRequest;
use App\Models\Master\EmpDisciplinary;
use App\Models\Master\EmpDocument;
use App\Models\Master\EmpEducation;
use App\Models\Master\EmpEmployee;
use App\Models\Master\EmpFamily;
use App\Models\Master\EmpFamilyRequest;
use App\Models\Master\EmpLeaveBalance;
use App\Models\Master\EmpLeaveRequest;
use App\Models\Master\EmpOutPermitRequest;
use App\Models\Master\EmpResignRequest;
use App\Models\Master\EmpShiftRoster;
use App\Models\Master\EmpShiftSwapRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

// mockup_personal_data_self_service.html. masteremployee_tier1.md §5: portal HANYA
// milik akun sendiri (guard employee_id di mount()), data pribadi READ-ONLY —
// semua perubahan lewat pengajuan+approval HR (BEDA dari Master Employee sisi
// HR yang full-editable). masteremployee_tier2.md §B.5 (engine cuti, SOP
// 002/RP/TM/VII/2026) diimplementasi nyata di sini (bukan sekadar UI), §B.4
// (koreksi data generik) & B.6/B.7 (izin keluar, tukar shift) juga nyata.
// Kalender pemilihan tanggal cuti mockup (grid custom klik-multi) DISEDERHANAKAN
// jadi date-range input + validasi server penuh (H+7/H-7, maks hari, dst) —
// mengganti widget visual, bukan aturannya (CLAUDE.md §7 "adjust slightly").
// HR-side approval panel TIDAK dibangun di sini (di luar scope mockup ini).
new #[Title('Portal Karyawan — TELASIH MAKMUR ERP')] class extends Component
{
    use WithFileUploads;

    #[Url]
    public string $tab = 'profil';

    public ?int $employeeId = null;

    // ---- modal: koreksi data generik ----
    public bool $showCorrectionModal = false;

    public string $corr_field_name = '';

    public string $corr_old_value = '';

    public string $corr_new_value = '';

    public string $corr_reason = '';

    // ---- modal: keluarga ----
    public bool $showFamilyReqModal = false;

    public string $famreq_type = 'LAHIR'; // LAHIR | MENINGGAL | PASANGAN

    public string $famreq_relation = 'ANAK';

    public string $famreq_full_name = '';

    public string $famreq_birth_date = '';

    public ?int $famreq_family_id = null;

    public string $famreq_deceased_date = '';

    public $famreq_attachment = null;

    // ---- modal: resign ----
    public bool $showResignModal = false;

    public string $resign_effective_date = '';

    public string $resign_reason = '';

    // ---- modal: dokumen ----
    public bool $showDocModal = false;

    public string $doc_type = 'KTP';

    public string $doc_document_no = '';

    public string $doc_expiry_date = '';

    public $doc_file = null;

    // ---- modal: pendidikan ----
    public bool $showEduModal = false;

    public string $edu_activity_name = '';

    public string $edu_activity_date = '';

    public string $edu_cost = '0';

    public string $edu_funded_by = 'PRIBADI';

    public string $edu_organizer = '';

    // ---- modal: badge photo ----
    public bool $showBadgeModal = false;

    public $badge_photo = null;

    // ---- modal: baca SOP (read-only) ----
    public ?int $viewingSopId = null;

    public function openSop(int $id): void
    {
        $this->viewingSopId = $id;
    }

    public function closeSop(): void
    {
        $this->viewingSopId = null;
    }

    // ---- form: cuti/sakit/izin ----
    public string $leave_type = 'CUTI';

    public string $khusus_event_type = 'MENIKAH';

    public string $izin_sifat = 'terjadwal';

    public string $izin_category = 'MUSIBAH';

    public string $izin_relasi_pemohon = 'DIRI_SENDIRI';

    public string $izin_instansi_asal = 'BPJS';

    public string $leave_date_from = '';

    public string $leave_date_to = '';

    public string $leave_reason = '';

    public string $sakit_lapor_jam = '';

    public bool $sakit_via_jkn = false;

    public $att_surat_cuti = null;

    public $att_surat_dokter = null;

    public $att_kwitansi_resep = null;

    public $att_jkn_screenshot = null;

    public $att_generic = null;

    // ---- form: izin keluar ----
    public string $op_permit_date = '';

    public string $op_out_time = '';

    public string $op_return_time = '';

    public string $op_reason = '';

    // ---- form: tukar shift ----
    public ?int $swap_requester_roster_id = null;

    public ?int $swap_target_employee_id = null;

    public ?int $swap_target_roster_id = null;

    public string $swap_reason = '';

    // ---- tab: Security (akun login sec_user -- BUKAN data emp_employee,
    // makanya edit LANGSUNG tanpa alur pengajuan+approval HR spt tab lain di
    // sini. Gabungan dari "Edit profil"+"Ubah password" yg dulu modal
    // terpisah di dropdown topbar -- instruksi user 2026-08-29, "jadikan
    // satu". Topbar tetap simpan modal itu sbg fallback KHUSUS akun yg
    // employee_id-nya null (tidak bisa akses Portal Karyawan sama sekali). ----
    public string $acc_name = '';

    public string $acc_email = '';

    public string $acc_phone = '';

    public bool $accSaved = false;

    public string $acc_old_password = '';

    public string $acc_new_password = '';

    public string $acc_new_password_confirmation = '';

    public ?string $accPasswordError = null;

    public bool $accPasswordSaved = false;

    public function mount(): void
    {
        $this->employeeId = Auth::user()->employee_id;

        if (! in_array($this->tab, ['profil', 'keluarga', 'dokumen', 'cuti', 'izinkeluar', 'tukarshift', 'jadwal', 'badge', 'security'], true)) {
            $this->tab = 'profil';
        }

        $user = Auth::user();
        $this->acc_name = $user->name ?? '';
        $this->acc_email = $user->email ?? '';
        $this->acc_phone = $user->phone ?? '';
    }

    public function saveAccountProfile(): void
    {
        $this->validate([
            'acc_name' => ['required', 'string', 'max:255'],
            'acc_email' => ['required', 'email', 'max:255'],
            'acc_phone' => ['nullable', 'string', 'max:64'],
        ]);

        Auth::user()->update([
            'name' => $this->acc_name,
            'email' => $this->acc_email,
            'phone' => $this->acc_phone,
        ]);

        $this->accSaved = true;
    }

    public function changeAccountPassword(): void
    {
        $this->accPasswordError = null;
        $this->accPasswordSaved = false;

        $user = Auth::user();

        if (! Hash::check($this->acc_old_password, (string) $user->pswd)) {
            $this->accPasswordError = 'Password lama salah.';

            return;
        }

        $this->validate(['acc_new_password' => ['required', 'string', 'min:8', 'confirmed']]);

        $user->update(['pswd' => Hash::make($this->acc_new_password), 'pswd_last_updated' => now()]);

        $this->acc_old_password = '';
        $this->acc_new_password = '';
        $this->acc_new_password_confirmation = '';
        $this->accPasswordSaved = true;
    }

    #[Computed]
    public function employee(): ?EmpEmployee
    {
        if (! $this->employeeId) {
            return null;
        }

        return EmpEmployee::with(['position.department', 'company', 'families', 'documents', 'educations', 'bankAccounts'])->find($this->employeeId);
    }

    // Deskripsi Kerja & SOP -- read-only, diisi HR Officer di layar terpisah
    // (hrm/position.blade.php field job_description, hrm/sop-documents.blade.php)
    // (keputusan user 2026-09-13). Melekat ke POSISI, bukan per pegawai --
    // semua pemegang posisi yang sama melihat teks yang sama.
    #[Computed]
    public function myJobDescription(): ?string
    {
        return $this->employee?->position?->job_description;
    }

    #[Computed]
    public function mySopDocuments()
    {
        $departmentId = $this->employee?->position?->department_id;

        return \App\Models\Master\SopDocument::where('activate', true)
            ->where(fn ($q) => $q->whereNull('department_id')->when($departmentId, fn ($q2) => $q2->orWhere('department_id', $departmentId)))
            ->orderBy('title')
            ->get();
    }

    #[Computed]
    public function currentYearBalance(): ?EmpLeaveBalance
    {
        if (! $this->employeeId) {
            return null;
        }

        return EmpLeaveBalance::firstOrCreate(
            ['employee_id' => $this->employeeId, 'year' => (int) now()->year],
            ['entitlement_days' => 12, 'used_days' => 0, 'carried_over_days' => 0]
        );
    }

    private function storeUpload($file, string $folder): ?string
    {
        return $file ? $file->store("hrm/{$this->employeeId}/{$folder}", 'public') : null;
    }

    // ============== TAB 1: DATA SAYA — KOREKSI & RESIGN ==============
    public function openCorrection(string $field, ?string $oldValue): void
    {
        $this->reset(['corr_new_value', 'corr_reason']);
        $this->corr_field_name = $field;
        $this->corr_old_value = (string) $oldValue;
        $this->showCorrectionModal = true;
    }

    public function saveCorrection(): void
    {
        $data = $this->validate([
            'corr_new_value' => ['required', 'string', 'max:255'],
            'corr_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $sensitive = in_array($this->corr_field_name, ['NIK KTP', 'Tanggal Lahir'], true);

        EmpCorrectionRequest::create([
            'employee_id' => $this->employeeId,
            'field_name' => $this->corr_field_name,
            'old_value' => $this->corr_old_value,
            'new_value' => $data['corr_new_value'],
            'reason' => $data['corr_reason'] ?: null,
            'requires_hr_approval' => true, // masteremployee_tier2.md B.4: semua koreksi lewat HR, wajib utk field sensitif khususnya
            'status' => 'PENDING',
            'requested_at' => now(),
        ]);

        unset($this->employee);
        $this->showCorrectionModal = false;
    }

    public function openResign(): void
    {
        $this->reset(['resign_effective_date', 'resign_reason']);
        $this->showResignModal = true;
    }

    public function saveResign(): void
    {
        $data = $this->validate([
            'resign_effective_date' => ['required', 'date', 'after:today'],
            'resign_reason' => ['required', 'string', 'max:255'],
        ]);

        EmpResignRequest::create([
            'employee_id' => $this->employeeId,
            'effective_date' => $data['resign_effective_date'],
            'reason' => $data['resign_reason'],
            'status' => 'PENDING',
            'requested_at' => now(),
        ]);

        $this->showResignModal = false;
    }

    #[Computed]
    public function myCorrections()
    {
        return $this->employeeId
            ? EmpCorrectionRequest::where('employee_id', $this->employeeId)->orderByDesc('requested_at')->get()
            : collect();
    }

    // ============== TAB 2: KELUARGA ==============
    public function openFamilyReq(string $type): void
    {
        $this->reset(['famreq_full_name', 'famreq_birth_date', 'famreq_family_id', 'famreq_deceased_date', 'famreq_attachment']);
        $this->famreq_type = $type;
        $this->famreq_relation = $type === 'PASANGAN' ? 'ISTRI' : 'ANAK';
        $this->showFamilyReqModal = true;
    }

    public function saveFamilyReq(): void
    {
        $rules = ['famreq_relation' => ['required', 'in:SUAMI,ISTRI,ANAK']];

        if ($this->famreq_type === 'MENINGGAL') {
            $rules['famreq_family_id'] = ['required', 'integer', 'exists:tm2mst.emp_family,family_id'];
            $rules['famreq_deceased_date'] = ['required', 'date'];
        } else {
            $rules['famreq_full_name'] = ['required', 'string', 'max:150'];
            $rules['famreq_birth_date'] = ['nullable', 'date'];
        }

        $data = $this->validate($rules);

        if ($this->famreq_type === 'LAHIR' && $data['famreq_relation'] === 'ANAK') {
            $childCount = EmpFamily::where('employee_id', $this->employeeId)->where('relation', 'ANAK')->count();

            if ($childCount >= 20) {
                $this->addError('famreq_relation', 'Maksimal 20 anak per karyawan.');

                return;
            }
        }

        $family = $this->famreq_family_id ? EmpFamily::find($this->famreq_family_id) : null;

        EmpFamilyRequest::create([
            'employee_id' => $this->employeeId,
            'request_type' => $this->famreq_type,
            'family_id' => $this->famreq_family_id,
            'relation' => $data['famreq_relation'],
            'full_name' => $data['famreq_full_name'] ?? $family?->full_name,
            'birth_date' => $data['famreq_birth_date'] ?? null,
            'deceased_date' => $data['famreq_deceased_date'] ?? null,
            'attachment_path' => $this->storeUpload($this->famreq_attachment, 'family'),
            'status' => 'PENDING',
            'requested_at' => now(),
        ]);

        unset($this->employee);
        $this->showFamilyReqModal = false;
    }

    #[Computed]
    public function myFamilyRequests()
    {
        return $this->employeeId
            ? EmpFamilyRequest::where('employee_id', $this->employeeId)->orderByDesc('requested_at')->get()
            : collect();
    }

    // ============== TAB 3: DOKUMEN & PENDIDIKAN ==============
    public function openDocModal(string $type): void
    {
        $this->reset(['doc_document_no', 'doc_expiry_date', 'doc_file']);
        $this->doc_type = $type;
        $this->showDocModal = true;
    }

    public function saveDoc(): void
    {
        $data = $this->validate([
            'doc_file' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
            'doc_document_no' => ['nullable', 'string', 'max:50'],
            'doc_expiry_date' => ['nullable', 'date'],
        ]);

        $existing = EmpDocument::where('employee_id', $this->employeeId)->where('document_type', $this->doc_type)->first();

        if ($existing && $existing->uploaded_by_role === 'HR') {
            $this->addError('doc_file', 'Dokumen ini diunggah &amp; dikunci oleh HR — ajukan koreksi lewat HR, tidak bisa diganti sendiri.');

            return;
        }

        EmpDocument::updateOrCreate(
            ['employee_id' => $this->employeeId, 'document_type' => $this->doc_type],
            [
                'document_no' => $data['doc_document_no'] ?: null,
                'file_path' => $this->storeUpload($this->doc_file, 'documents'),
                'expiry_date' => $data['doc_expiry_date'] ?: null,
                'uploaded_by_role' => 'SELF',
                'activate' => true,
            ]
        );

        unset($this->employee);
        $this->showDocModal = false;
    }

    public function openEduModal(): void
    {
        $this->reset(['edu_activity_name', 'edu_activity_date', 'edu_organizer']);
        $this->edu_cost = '0';
        $this->edu_funded_by = 'PRIBADI';
        $this->showEduModal = true;
    }

    public function saveEdu(): void
    {
        $data = $this->validate([
            'edu_activity_name' => ['required', 'string', 'max:200'],
            'edu_activity_date' => ['required', 'date'],
            'edu_cost' => ['required', 'numeric', 'min:0'],
            'edu_funded_by' => ['required', 'in:PERUSAHAAN,PRIBADI,SHARING'],
            'edu_organizer' => ['nullable', 'string', 'max:150'],
        ]);

        EmpEducation::create([
            'employee_id' => $this->employeeId,
            'education_type' => 'PELATIHAN',
            'activity_name' => $data['edu_activity_name'],
            'organizer' => $data['edu_organizer'] ?: null,
            'activity_date' => $data['edu_activity_date'],
            'cost' => $data['edu_cost'],
            'funded_by' => $data['edu_funded_by'],
            'uploaded_by_role' => 'SELF',
        ]);

        unset($this->employee);
        $this->showEduModal = false;
    }

    // ============== TAB 4: CUTI / SAKIT / IZIN (ENGINE) ==============
    public function updatedLeaveType(): void
    {
        $this->resetErrorBag();
    }

    #[Computed]
    public function izinLocked(): bool
    {
        return ($this->currentYearBalance?->remainingDays() ?? 0) > 0;
    }

    // Dihitung dari BULAN pengajuan (date_from yang diajukan), BUKAN bulan
    // kalender saat ini — pengajuan untuk bulan depan harus dicek terhadap
    // pengajuan lain di bulan depan itu juga, bukan bulan berjalan.
    private function izinCountForMonth(Carbon $referenceDate): int
    {
        if (! $this->employeeId) {
            return 0;
        }

        return EmpLeaveRequest::where('employee_id', $this->employeeId)
            ->where('leave_type', 'IZIN')
            ->whereBetween('date_from', [$referenceDate->copy()->startOfMonth(), $referenceDate->copy()->endOfMonth()])
            ->count();
    }

    // sop_perizinan_resmi.ketentuan_alpha_dan_disiplin: "Izin ke-4 dst dalam
    // bulan yang sama DIANGGAP ALPHA". Pengajuannya sendiri tetap diblok (bukan
    // hal yang bisa "diajukan"), tapi kejadiannya tercatat otomatis ke
    // emp_disciplinary supaya HR tahu tanpa menunggu karyawan lapor manual --
    // dedup per employee+tanggal supaya klik berulang tidak dobel-catat.
    private function recordAutoAlpha(Carbon $date, int $attemptNumber): void
    {
        if (! $this->employeeId) {
            return;
        }

        $exists = EmpDisciplinary::where('employee_id', $this->employeeId)
            ->where('type', 'ALPHA')
            ->where('incident_date', $date->toDateString())
            ->exists();

        if ($exists) {
            return;
        }

        EmpDisciplinary::create([
            'employee_id' => $this->employeeId,
            'type' => 'ALPHA',
            'incident_date' => $date->toDateString(),
            'reason' => "Percobaan pengajuan Izin ke-{$attemptNumber} bulan {$date->format('F Y')} — melebihi batas 3x/bulan sesuai SOP Perizinan 002/RP/TM/VII/2026, otomatis tercatat Alpha.",
            'issued_by' => 'SYSTEM',
        ]);
    }

    #[Computed]
    public function sakitLaporTerlambat(): bool
    {
        if (! $this->sakit_lapor_jam) {
            return false;
        }

        return Carbon::createFromFormat('H:i', $this->sakit_lapor_jam)->gt(Carbon::createFromTime(7, 30));
    }

    public function submitLeave(): void
    {
        $this->resetErrorBag();

        $from = $this->leave_date_from ? Carbon::parse($this->leave_date_from) : null;
        $to = $this->leave_date_to ? Carbon::parse($this->leave_date_to) : null;

        if (! $from || ! $to || $to->lt($from)) {
            $this->addError('leave_date_to', 'Tanggal tidak valid.');

            return;
        }

        // Hitung hari kerja (exclude Minggu) — hari besar/libur nasional belum
        // punya tabel referensi, jadi belum tervalidasi di sini (DELTA).
        $totalDays = 0;
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            if (! $d->isSunday()) {
                $totalDays++;
            }
        }

        $payload = [
            'employee_id' => $this->employeeId,
            'leave_type' => $this->leave_type,
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'total_days' => $totalDays,
            'reason' => $this->leave_reason ?: null,
            'status' => 'PENDING',
            'requested_at' => now(),
        ];

        if ($this->leave_type === 'CUTI') {
            if ($from->lt(now()->addDays(7)->startOfDay())) {
                $this->addError('leave_date_from', 'Cuti wajib diajukan minimal H+7 dari hari ini.');

                return;
            }
            if ($totalDays > 3) {
                $this->addError('leave_date_to', 'Maksimal 3 hari kerja per pengajuan cuti.');

                return;
            }
            if (! $this->att_surat_cuti) {
                $this->addError('att_surat_cuti', 'Surat Cuti wajib diunggah (syarat administrasi).');

                return;
            }

            $payload['attachment_surat_cuti'] = $this->storeUpload($this->att_surat_cuti, 'leave');
            $payload['is_paid'] = true;
            $payload['deducts_leave_balance'] = true;
        } elseif ($this->leave_type === 'SAKIT') {
            if ($this->sakit_via_jkn) {
                if (! $this->att_jkn_screenshot) {
                    $this->addError('att_jkn_screenshot', 'Screenshot riwayat JKN wajib diunggah.');

                    return;
                }
                $payload['attachment_jkn_screenshot'] = $this->storeUpload($this->att_jkn_screenshot, 'leave');
            } else {
                if (! $this->att_surat_dokter || ! $this->att_kwitansi_resep) {
                    $this->addError('att_surat_dokter', 'Surat Keterangan Dokter DAN kwitansi/resep wajib diunggah keduanya.');

                    return;
                }
                $payload['attachment_surat_dokter'] = $this->storeUpload($this->att_surat_dokter, 'leave');
                $payload['attachment_kwitansi_resep'] = $this->storeUpload($this->att_kwitansi_resep, 'leave');
            }

            $payload['sakit_lapor_jam'] = $this->sakit_lapor_jam ?: null;
            $payload['is_paid'] = true;
            $payload['deducts_leave_balance'] = false;
        } elseif ($this->leave_type === 'IZIN') {
            if ($this->izinLocked) {
                $this->addError('leave_date_from', 'Anda masih punya sisa cuti — Izin hanya bisa diajukan bila saldo cuti tahun ini habis (0 hari).');

                return;
            }
            if ($totalDays > 3) {
                $this->addError('leave_date_to', 'Maksimal 3 hari berturut-turut per pengajuan izin.');

                return;
            }
            if ($this->izinCountForMonth($from) >= 3) {
                $this->recordAutoAlpha($from, $this->izinCountForMonth($from) + 1);
                $this->addError('leave_date_from', 'Sudah 3x mengajukan Izin bulan ini — pengajuan ini otomatis dicatat sebagai Alpha di rekam jejak kedisiplinan Anda, tanpa perlu lapor manual ke HR.');

                return;
            }
            if ($this->izin_sifat === 'terjadwal' && $from->lt(now()->addDays(7)->startOfDay())) {
                $this->addError('leave_date_from', 'Izin terjadwal wajib diajukan minimal H-7.');

                return;
            }
            if ($this->izin_sifat === 'mendadak') {
                $payload['izin_category'] = $this->izin_category;
                if ($this->izin_category === 'INSTANSI_RESMI') {
                    $payload['izin_relasi_pemohon'] = $this->izin_relasi_pemohon;
                    $payload['izin_instansi_asal'] = $this->izin_instansi_asal;
                }
                if (! $this->att_generic) {
                    $this->addError('att_generic', 'Lampiran bukti/surat wajib diunggah untuk izin mendadak.');

                    return;
                }
                $payload['attachment_path'] = $this->storeUpload($this->att_generic, 'leave');
            }

            $payload['is_paid'] = false;
            $payload['deducts_leave_balance'] = false;
        } else { // CUTI_KHUSUS
            if (! $this->att_generic) {
                $this->addError('att_generic', 'Lampiran bukti (surat nikah/akta/dst) wajib diunggah.');

                return;
            }

            $payload['khusus_event_type'] = $this->khusus_event_type;
            $payload['attachment_path'] = $this->storeUpload($this->att_generic, 'leave');
            $payload['is_paid'] = true;
            $payload['deducts_leave_balance'] = false;
        }

        EmpLeaveRequest::create($payload);

        $this->reset([
            'leave_date_from', 'leave_date_to', 'leave_reason', 'sakit_lapor_jam', 'sakit_via_jkn',
            'att_surat_cuti', 'att_surat_dokter', 'att_kwitansi_resep', 'att_jkn_screenshot', 'att_generic',
        ]);
        session()->flash('leaveSubmitted', 'Pengajuan berhasil dikirim ke HRD, status Menunggu Approval.');
    }

    #[Computed]
    public function myLeaveRequests()
    {
        return $this->employeeId
            ? EmpLeaveRequest::where('employee_id', $this->employeeId)->orderByDesc('requested_at')->get()
            : collect();
    }

    // ============== TAB 5: IZIN KELUAR ==============
    public function submitOutPermit(): void
    {
        $data = $this->validate([
            'op_permit_date' => ['required', 'date'],
            'op_out_time' => ['required', 'date_format:H:i'],
            'op_return_time' => ['nullable', 'date_format:H:i'],
            'op_reason' => ['required', 'string', 'max:255'],
        ]);

        EmpOutPermitRequest::create([
            'employee_id' => $this->employeeId,
            'permit_date' => $data['op_permit_date'],
            'planned_out_time' => $data['op_out_time'],
            'planned_return_time' => $data['op_return_time'] ?: null,
            'reason' => $data['op_reason'],
            'status' => 'PENDING',
            'requested_at' => now(),
        ]);

        $this->reset(['op_permit_date', 'op_out_time', 'op_return_time', 'op_reason']);
        unset($this->myOutPermits);
    }

    #[Computed]
    public function myOutPermits()
    {
        return $this->employeeId
            ? EmpOutPermitRequest::where('employee_id', $this->employeeId)->orderByDesc('requested_at')->get()
            : collect();
    }

    // ============== TAB 6: TUKAR SHIFT ==============
    #[Computed]
    public function myUpcomingRoster()
    {
        if (! $this->employeeId) {
            return collect();
        }

        return EmpShiftRoster::query()
            ->with(['shift', 'batch'])
            ->where('employee_id', $this->employeeId)
            ->where('roster_date', '>=', now()->toDateString())
            ->whereHas('batch', fn ($q) => $q->where('status', 'CONFIRMED'))
            ->orderBy('roster_date')
            ->get();
    }

    #[Computed]
    public function swapColleagues()
    {
        if (! $this->employee?->position?->department) {
            return collect();
        }

        return EmpEmployee::query()
            ->where('employee_id', '!=', $this->employeeId)
            ->whereHas('position.department', fn ($q) => $q->where('division_id', $this->employee->position->department->division_id))
            ->where('activate', true)
            ->orderBy('full_name')
            ->get();
    }

    #[Computed]
    public function targetRosterOptions()
    {
        if (! $this->swap_target_employee_id) {
            return collect();
        }

        return EmpShiftRoster::query()
            ->with('shift')
            ->where('employee_id', $this->swap_target_employee_id)
            ->where('roster_date', '>=', now()->toDateString())
            ->whereHas('batch', fn ($q) => $q->where('status', 'CONFIRMED'))
            ->orderBy('roster_date')
            ->get();
    }

    public function submitSwap(): void
    {
        $data = $this->validate([
            'swap_requester_roster_id' => ['required', 'integer', 'exists:tm2mst.emp_shift_roster,roster_id'],
            'swap_target_employee_id' => ['required', 'integer'],
            'swap_target_roster_id' => ['required', 'integer', 'exists:tm2mst.emp_shift_roster,roster_id'],
            'swap_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $requesterRoster = EmpShiftRoster::find($data['swap_requester_roster_id']);
        $onTime = $requesterRoster && now()->lt($requesterRoster->roster_date->copy()->subDay());

        EmpShiftSwapRequest::create([
            'requester_employee_id' => $this->employeeId,
            'requester_roster_id' => $data['swap_requester_roster_id'],
            'target_employee_id' => $data['swap_target_employee_id'],
            'target_roster_id' => $data['swap_target_roster_id'],
            'reason' => $data['swap_reason'] ?: null,
            'submitted_on_time' => $onTime,
            'partner_status' => 'PENDING',
            'ppic_status' => 'PENDING',
            'requested_at' => now(),
        ]);

        $this->reset(['swap_requester_roster_id', 'swap_target_employee_id', 'swap_target_roster_id', 'swap_reason']);
        unset($this->mySwapRequests);
    }

    #[Computed]
    public function mySwapRequests()
    {
        return $this->employeeId
            ? EmpShiftSwapRequest::with(['targetEmployee', 'requesterRoster.shift', 'targetRoster.shift'])
                ->where('requester_employee_id', $this->employeeId)
                ->orderByDesc('requested_at')
                ->get()
            : collect();
    }

    // ============== TAB 7: JADWAL SAYA ==============
    #[Computed]
    public function mySchedule()
    {
        return $this->employeeId
            ? EmpShiftRoster::query()
                ->with(['shift', 'machine'])
                ->where('employee_id', $this->employeeId)
                ->where('roster_date', '>=', now()->toDateString())
                ->whereHas('batch', fn ($q) => $q->where('status', 'CONFIRMED'))
                ->orderBy('roster_date')
                ->get()
            : collect();
    }

    // ============== TAB 8: KARTU PENGENAL ==============
    public function openBadgeModal(): void
    {
        $this->reset(['badge_photo']);
        $this->showBadgeModal = true;
    }

    public function saveBadgePhoto(): void
    {
        $this->validate(['badge_photo' => ['required', 'image', 'max:5120']]);

        [$width, $height] = @getimagesize($this->badge_photo->getRealPath()) ?: [null, null];

        EmpBadgePhotoRequest::create([
            'employee_id' => $this->employeeId,
            'photo_path' => $this->storeUpload($this->badge_photo, 'badge'),
            'width_px' => $width,
            'height_px' => $height,
            'status' => 'PENDING',
            'requested_at' => now(),
        ]);

        $this->showBadgeModal = false;
    }
};
?>

<div class="min-h-screen bg-[#f8fafc]">
    <div class="bg-[#0f172a] text-white px-6 py-3 flex items-center justify-between sticky top-0 z-40">
        <div class="text-sm font-bold flex items-center gap-2">TMERP <span class="bg-white/15 px-2 py-0.5 rounded text-[11px] font-medium">Self-Service Karyawan</span></div>
        <a href="{{ route('menu') }}" x-data x-show="window.self === window.top" data-back class="text-xs text-white/80 hover:text-white">← Kembali ke Menu Utama</a>
    </div>

    @if (! $this->employee)
        <div class="max-w-xl mx-auto mt-16 bg-white border border-[#e2e8f0] rounded-xl p-6 text-center">
            <div class="text-sm font-bold text-[#0f172a] mb-1">Akun belum tertaut ke data pegawai</div>
            <div class="text-xs text-muted">Hubungi HR untuk menautkan akun Anda ke data Master Employee sebelum bisa memakai portal ini.</div>
        </div>
    @else
        @php $emp = $this->employee; @endphp
        <div class="max-w-[1200px] mx-auto my-5 px-5">
            {{-- HERO --}}
            <div class="bg-white border border-[#e2e8f0] rounded-2xl p-5 mb-4 flex items-center justify-between flex-wrap gap-4">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 rounded-full bg-[#e2e8f0] flex items-center justify-center text-lg font-bold text-[#0f172a] border-4 border-white shadow">{{ strtoupper(substr($emp->full_name, 0, 2)) }}</div>
                    <div>
                        <h1 class="text-lg font-bold text-[#0f172a]">{{ $emp->full_name }} <span class="text-[11px] font-semibold bg-[#dcfce7] text-[#166534] px-2 py-0.5 rounded ml-1">{{ $emp->employee_code }}</span></h1>
                        <div class="text-xs text-muted mt-0.5">💼 {{ $emp->position?->position_name }} • 🏢 {{ $emp->position?->department?->department_name }}</div>
                    </div>
                </div>
                <div class="flex gap-5 border-l border-[#e2e8f0] pl-5">
                    <div class="text-center">
                        <div class="text-base font-bold text-[#0f172a]">{{ $this->currentYearBalance?->remainingDays() }} Hari</div>
                        <div class="text-[10px] text-muted uppercase font-semibold">Sisa Cuti</div>
                    </div>
                    <div class="text-center">
                        <div class="text-base font-bold text-[#0f172a]">{{ $emp->work_status === 'WORKING' ? 'Aktif' : $emp->work_status }}</div>
                        <div class="text-[10px] text-muted uppercase font-semibold">Status Kerja</div>
                    </div>
                </div>
            </div>

            {{-- TABS --}}
            <div class="bg-white border border-[#e2e8f0] rounded-xl p-1.5 mb-4 flex gap-1 flex-wrap sticky top-[52px] z-30">
                @foreach (['profil' => '👤 Data Saya', 'jobdesc' => '📋 Deskripsi Kerja', 'sop' => '📘 SOP', 'keluarga' => '👪 Keluarga & Status', 'dokumen' => '📄 Dokumen & Pendidikan', 'cuti' => '📅 Cuti/Sakit/Izin', 'izinkeluar' => '🚪 Izin Keluar', 'tukarshift' => '🔄 Tukar Shift', 'jadwal' => '🗓️ Jadwal Saya', 'badge' => '🪪 Kartu Pengenal', 'security' => '🔒 Security'] as $key => $label)
                    <button type="button" wire:click="$set('tab', '{{ $key }}')" class="px-3 py-2 text-xs font-medium rounded-md cursor-pointer {{ $tab === $key ? 'bg-[#0f172a] text-white font-semibold' : 'text-muted hover:bg-[#f8fafc]' }}">{{ $label }}</button>
                @endforeach
            </div>

            {{-- ===================== TAB: DATA SAYA ===================== --}}
            @if ($tab === 'profil')
                <div class="text-xs text-[#334155] bg-[#f0f9ff] border-l-4 border-accent px-3.5 py-2.5 rounded-r-md mb-4">
                    <b>SOP Pembaruan Data:</b> Informasi di bawah bersifat <i>read-only</i>. Jika ada kesalahan entri, klik <b>"Ajukan Koreksi"</b> — perubahan aktif setelah diverifikasi HR.
                </div>

                <div class="grid two gap-4">
                    <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5">
                        <div class="text-sm font-bold text-[#0f172a] mb-3 pb-2 border-b border-[#f1f5f9]">Profil Utama</div>
                        @foreach ([
                            ['Nama Lengkap', $emp->full_name],
                            ['Tanggal Lahir', $emp->birth_date?->format('d M Y')],
                            ['NIK KTP', $emp->nik_ktp],
                            ['Agama', $emp->religion],
                            ['Telepon / WA', $emp->phone],
                        ] as [$label, $value])
                            <div class="flex gap-2.5 py-2 text-[13px] border-b border-[#f8fafc] items-center">
                                <div class="w-[130px] shrink-0 text-muted text-xs">{{ $label }}</div>
                                <div class="flex-1 font-medium">{{ $value ?: '—' }}</div>
                                <button type="button" wire:click="openCorrection('{{ $label }}', @js($value))" class="text-[11px] font-semibold text-accent bg-[#eff6ff] border border-[#bfdbfe] px-2 py-1 rounded cursor-pointer">Ajukan Koreksi</button>
                            </div>
                        @endforeach
                        <div class="flex gap-2.5 py-2 text-[13px] items-center">
                            <div class="w-[130px] shrink-0 text-muted text-xs">Posisi</div>
                            <div class="flex-1 font-medium">{{ $emp->position?->position_name }}</div>
                            <span class="text-[10px] font-semibold bg-[#f1f5f9] text-muted px-2 py-0.5 rounded">Hanya HR</span>
                        </div>
                    </div>

                    <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5">
                        <div class="text-sm font-bold text-[#0f172a] mb-3 pb-2 border-b border-[#f1f5f9]">Alamat</div>
                        @foreach ([
                            ['Alamat KTP', $emp->address_detail_ktp],
                            ['Alamat Domisili', $emp->address_detail_domisili],
                        ] as [$label, $value])
                            <div class="flex gap-2.5 py-2 text-[13px] border-b border-[#f8fafc] items-start">
                                <div class="w-[130px] shrink-0 text-muted text-xs pt-1">{{ $label }}</div>
                                <div class="flex-1 font-medium">{{ $value ?: '—' }}</div>
                                <button type="button" wire:click="openCorrection('{{ $label }}', @js($value))" class="text-[11px] font-semibold text-accent bg-[#eff6ff] border border-[#bfdbfe] px-2 py-1 rounded cursor-pointer">Ajukan Koreksi</button>
                            </div>
                        @endforeach

                        <div class="text-sm font-bold text-[#0f172a] mt-4 mb-3 pb-2 border-b border-[#f1f5f9]">BPJS &amp; Jaminan Sosial</div>
                        <div class="flex gap-2.5 py-2 text-[13px] items-center">
                            <div class="w-[130px] shrink-0 text-muted text-xs">BPJS Kesehatan</div>
                            <div class="flex-1 font-medium">{{ $emp->bpjsKesehatan?->no_kepesertaan ?: '—' }}</div>
                            <button type="button" wire:click="openCorrection('No. BPJS Kesehatan', @js($emp->bpjsKesehatan?->no_kepesertaan))" class="text-[11px] font-semibold text-accent bg-[#eff6ff] border border-[#bfdbfe] px-2 py-1 rounded cursor-pointer">Ajukan Koreksi</button>
                        </div>
                        <div class="flex gap-2.5 py-2 text-[13px] items-center">
                            <div class="w-[130px] shrink-0 text-muted text-xs">BPJS TK</div>
                            <div class="flex-1 font-medium">{{ $emp->bpjsKetenagakerjaan?->no_kepesertaan ?: '—' }}</div>
                            <button type="button" wire:click="openCorrection('No. BPJS Ketenagakerjaan', @js($emp->bpjsKetenagakerjaan?->no_kepesertaan))" class="text-[11px] font-semibold text-accent bg-[#eff6ff] border border-[#bfdbfe] px-2 py-1 rounded cursor-pointer">Ajukan Koreksi</button>
                        </div>
                    </div>

                    <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5 md:col-span-2">
                        <div class="text-sm font-bold text-[#0f172a] mb-3 pb-2 border-b border-[#f1f5f9]">Riwayat Pengajuan Koreksi Data</div>
                        <x-grid.table-shell>
                        <table class="w-full text-[12.5px]">
                            <thead><tr class="text-left text-[11px] uppercase text-muted"><th class="pb-2">Field</th><th class="pb-2">Nilai Baru</th><th class="pb-2">Status</th></tr></thead>
                            <tbody>
                                @forelse ($this->myCorrections as $c)
                                    <tr class="border-t border-[#f1f5f9]"><td class="py-2">{{ $c->field_name }}</td><td class="py-2">{{ $c->new_value }}</td><td class="py-2">
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded {{ match ($c->status) { 'APPROVED' => 'bg-[#dcfce7] text-[#166534]', 'REJECTED' => 'bg-[#fee2e2] text-[#991b1b]', default => 'bg-[#fef3c7] text-[#92400e]' } }}">{{ $c->status }}</span>
                                    </td></tr>
                                @empty
                                    <x-grid.empty-row :colspan="3" message="Belum ada pengajuan." />
                                @endforelse
                            </tbody>
                        </table>
                        </x-grid.table-shell>
                    </div>

                    <div class="bg-[#fff5f5] border border-[#fca5a5] rounded-xl p-4.5 md:col-span-2">
                        <div class="text-sm font-bold text-[#991b1b] mb-2">Pengunduran Diri (Resign)</div>
                        <div class="text-xs text-muted mb-3">Prosedur resign resmi sesuai ketentuan kontrak kerja — atasan &amp; HR akan menghubungi untuk proses handover.</div>
                        <button type="button" wire:click="openResign" class="text-xs font-semibold bg-white border border-[#fca5a5] text-[#991b1b] px-3.5 py-2 rounded-md cursor-pointer">Ajukan Form Pengunduran Diri</button>
                    </div>
                </div>
            @endif

            {{-- ===================== TAB: DESKRIPSI KERJA (read-only) =====================
                 Melekat ke posisi ($emp->position->job_description), diisi HR Officer di
                 layar Master Posisi -- keputusan user 2026-09-13. --}}
            @if ($tab === 'jobdesc')
                <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5">
                    <div class="flex items-center justify-between mb-3 pb-2 border-b border-[#f1f5f9]">
                        <div class="text-sm font-bold text-[#0f172a]">Deskripsi Kerja</div>
                        <div class="text-[11px] text-muted">{{ $emp?->position?->position_name ?? '—' }}</div>
                    </div>
                    @if ($this->myJobDescription)
                        <div class="text-[13px] text-[#334] leading-relaxed whitespace-pre-line">{{ $this->myJobDescription }}</div>
                    @else
                        <div class="text-xs text-muted italic">Belum ada deskripsi kerja untuk posisi ini. Hubungi HR Officer bila diperlukan.</div>
                    @endif
                    <div class="text-[10.5px] text-muted mt-3 pt-2 border-t border-[#f1f5f9]">Hanya HR Officer yang dapat mengubah deskripsi ini — bersifat read-only di sini.</div>
                </div>
            @endif

            {{-- ===================== TAB: SOP (read-only) =====================
                 Dokumen SOP global + yang berlaku utk departemen pegawai ini
                 (App\Models\Master\SopDocument), diisi HR Officer di hrm/sop-documents.
                 Isi lengkap dibuka lewat modal, bukan langsung di daftar (bisa panjang). --}}
            @if ($tab === 'sop')
                <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5">
                    <div class="flex items-center justify-between mb-3 pb-2 border-b border-[#f1f5f9]">
                        <div class="text-sm font-bold text-[#0f172a]">SOP Berlaku untuk Saya</div>
                        <div class="text-[11px] text-muted">{{ $this->mySopDocuments->count() }} dokumen</div>
                    </div>
                    <div class="flex flex-col gap-2">
                        @forelse ($this->mySopDocuments as $doc)
                            <button type="button" wire:click="openSop({{ $doc->sop_id }})"
                                class="text-left flex items-center gap-3 px-3.5 py-2.5 rounded-lg border border-[#e2e8f0] hover:border-accent hover:bg-[#f8fafc] cursor-pointer">
                                <span class="text-lg">📘</span>
                                <span class="flex-1 min-w-0">
                                    <span class="block text-[13px] font-semibold text-[#0f172a] truncate">{{ $doc->title }}</span>
                                    <span class="block text-[11px] text-muted font-mono">{{ $doc->sop_code }} · Rev {{ $doc->revision }} · {{ $doc->department_id ? $doc->department?->department_name : 'Semua Departemen' }}</span>
                                </span>
                                <span class="text-xs text-accent font-semibold shrink-0">Baca →</span>
                            </button>
                        @empty
                            <div class="text-xs text-muted italic">Belum ada dokumen SOP yang berlaku untuk departemen Anda.</div>
                        @endforelse
                    </div>
                </div>

                @if ($viewingSopId)
                    @php $viewedSop = $this->mySopDocuments->firstWhere('sop_id', $viewingSopId); @endphp
                    @if ($viewedSop)
                        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-[60] p-4" wire:click.self="closeSop">
                            <div class="bg-white rounded-xl w-full max-w-[640px] max-h-[85vh] overflow-y-auto">
                                <div class="flex items-center gap-2 justify-between bg-navy text-white px-4 py-3 rounded-t-xl sticky top-0">
                                    <div class="text-sm font-bold">{{ $viewedSop->title }}</div>
                                    <span class="text-[10.5px] font-mono opacity-75 shrink-0">{{ $viewedSop->sop_code }} · Rev {{ $viewedSop->revision }}</span>
                                </div>
                                <div class="p-5 flex flex-col gap-3">
                                    @if ($viewedSop->effective_date)
                                        <div class="text-[11px] text-muted">Berlaku sejak: <b class="text-[#233]">{{ $viewedSop->effective_date->format('d F Y') }}</b></div>
                                    @endif
                                    @if ($viewedSop->purpose)
                                        <div>
                                            <div class="text-xs font-bold text-navy uppercase tracking-wide mb-1">Tujuan</div>
                                            <div class="text-[13px] text-[#334] leading-relaxed whitespace-pre-line">{{ $viewedSop->purpose }}</div>
                                        </div>
                                    @endif
                                    <div>
                                        <div class="text-xs font-bold text-navy uppercase tracking-wide mb-1">Isi SOP</div>
                                        <div class="text-[13px] text-[#334] leading-relaxed whitespace-pre-line">{{ $viewedSop->content ?: '—' }}</div>
                                    </div>
                                </div>
                                <div class="px-4 py-3 border-t border-[#eef1f5] flex justify-end">
                                    <button type="button" wire:click="closeSop" class="px-4 py-2 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">Tutup</button>
                                </div>
                            </div>
                        </div>
                    @endif
                @endif
            @endif

            {{-- ===================== TAB: KELUARGA & STATUS ===================== --}}
            @if ($tab === 'keluarga')
                <div class="flex flex-col gap-4">
                    <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5">
                        <div class="flex items-center justify-between mb-3 pb-2 border-b border-[#f1f5f9]">
                            <div class="text-sm font-bold text-[#0f172a]">Anggota Keluarga Terdaftar</div>
                            <div class="flex gap-2">
                                <button type="button" wire:click="openFamilyReq('LAHIR')" class="text-[11px] font-semibold bg-accent text-white px-3 py-1.5 rounded-md cursor-pointer">+ Ajukan Kelahiran</button>
                                <button type="button" wire:click="openFamilyReq('MENINGGAL')" class="text-[11px] font-semibold bg-[#f1f5f9] text-[#334] px-3 py-1.5 rounded-md cursor-pointer">Tandai Meninggal Dunia</button>
                            </div>
                        </div>
                        <x-grid.table-shell>
                        <table class="w-full text-[12.5px]">
                            <thead><tr class="text-left text-[11px] uppercase text-muted"><th class="pb-2">Hubungan</th><th class="pb-2">Nama</th><th class="pb-2">Tanggal Lahir</th><th class="pb-2">Status</th></tr></thead>
                            <tbody>
                                @forelse ($emp->families as $f)
                                    <tr class="border-t border-[#f1f5f9]">
                                        <td class="py-2 font-semibold">{{ $f->relation }}</td>
                                        <td class="py-2">{{ $f->full_name }}</td>
                                        <td class="py-2">{{ $f->birth_date?->format('d M Y') ?: '—' }}</td>
                                        <td class="py-2">
                                            @if ($f->is_deceased)
                                                <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-[#f1f5f9] text-muted line-through">Meninggal — {{ $f->deceased_date?->format('d M Y') }}</span>
                                            @else
                                                <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-[#dcfce7] text-[#166534]">Tanggungan</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <x-grid.empty-row :colspan="4" message="Belum ada data keluarga." />
                                @endforelse
                            </tbody>
                        </table>
                        </x-grid.table-shell>
                        <div class="text-xs text-[#334155] bg-[#f0f9ff] border-l-4 border-accent px-3.5 py-2.5 rounded-r-md mt-3">Anggota keluarga meninggal <b>tidak dihapus</b> dari histori — demi keakuratan rekaman tunjangan &amp; klaim masa lalu.</div>
                    </div>

                    <div class="grid two gap-4">
                        <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5">
                            <div class="text-sm font-bold text-[#0f172a] mb-3 pb-2 border-b border-[#f1f5f9]">Status Pernikahan</div>
                            <div class="flex gap-2.5 items-center">
                                <div class="w-[130px] shrink-0 text-muted text-xs">Status Saat Ini</div>
                                <div class="flex-1 font-medium">—</div>
                                <button type="button" wire:click="openFamilyReq('PASANGAN')" class="text-[11px] font-semibold text-accent bg-[#eff6ff] border border-[#bfdbfe] px-2 py-1 rounded cursor-pointer">Ajukan Perubahan</button>
                            </div>
                        </div>
                        <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5">
                            <div class="text-sm font-bold text-[#0f172a] mb-3 pb-2 border-b border-[#f1f5f9]">Rekening Bank Payroll</div>
                            <div class="flex gap-2.5 items-center">
                                <div class="w-[130px] shrink-0 text-muted text-xs">Bank / No. Rek</div>
                                <div class="flex-1 font-medium">{{ $emp->bankAccounts->firstWhere('is_primary', true)?->bank_name }} {{ $emp->bankAccounts->firstWhere('is_primary', true)?->account_no }}</div>
                                <button type="button" wire:click="openCorrection('Rekening Bank Payroll', @js($emp->bankAccounts->firstWhere('is_primary', true)?->account_no))" class="text-[11px] font-semibold text-accent bg-[#eff6ff] border border-[#bfdbfe] px-2 py-1 rounded cursor-pointer">Ajukan Ganti</button>
                            </div>
                        </div>
                        <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5 md:col-span-2">
                            <div class="text-sm font-bold text-[#0f172a] mb-3 pb-2 border-b border-[#f1f5f9]">Kontak Darurat</div>
                            <div class="flex gap-2.5 items-center">
                                <div class="w-[130px] shrink-0 text-muted text-xs">Nama &amp; Hubungan</div>
                                <div class="flex-1 font-medium">{{ $emp->emergency_contact_name ?: '—' }} @if ($emp->emergency_contact_relation) ({{ $emp->emergency_contact_relation }}) @endif</div>
                                <button type="button" wire:click="openCorrection('Kontak Darurat', @js($emp->emergency_contact_name))" class="text-[11px] font-semibold text-accent bg-[#eff6ff] border border-[#bfdbfe] px-2 py-1 rounded cursor-pointer">Edit Kontak</button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- ===================== TAB: DOKUMEN & PENDIDIKAN ===================== --}}
            @if ($tab === 'dokumen')
                <div class="grid two gap-4">
                    <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5">
                        <div class="text-sm font-bold text-[#0f172a] mb-3 pb-2 border-b border-[#f1f5f9]">Dokumen Identitas Diri</div>
                        @foreach (['KTP' => 'KTP Elektronik', 'NPWP' => 'NPWP', 'SIM' => 'SIM C', 'KK' => 'Kartu Keluarga', 'PASSPORT' => 'Paspor', 'SKCK' => 'SKCK'] as $type => $label)
                            @php $doc = $emp->documents->firstWhere('document_type', $type); @endphp
                            <div class="flex items-center gap-3 py-2.5 border-b border-[#f8fafc] last:border-b-0">
                                <div class="w-9 h-9 rounded-md bg-[#f1f5f9] flex items-center justify-center text-base shrink-0">📄</div>
                                <div class="flex-1">
                                    <div class="text-[13px] font-semibold">{{ $label }}</div>
                                    <div class="text-[11px] text-muted">
                                        @if (! $doc) <span class="text-[#991b1b]">Belum ada file diunggah</span>
                                        @else {{ $doc->document_no ?: 'Terunggah' }} @if ($doc->uploaded_by_role === 'HR') <span class="text-[10px] font-bold bg-[#f1f5f9] text-muted px-1.5 py-0.5 rounded ml-1">Dikunci HR</span> @endif
                                        @endif
                                    </div>
                                </div>
                                @if (! $doc || $doc->uploaded_by_role === 'SELF')
                                    <button type="button" wire:click="openDocModal('{{ $type }}')" class="text-[11px] font-semibold {{ $doc ? 'bg-[#f1f5f9] text-[#334]' : 'bg-accent text-white' }} px-2.5 py-1.5 rounded cursor-pointer shrink-0">{{ $doc ? 'Ganti' : 'Upload' }}</button>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5 md:col-span-2">
                        <div class="flex items-center justify-between mb-3 pb-2 border-b border-[#f1f5f9]">
                            <div class="text-sm font-bold text-[#0f172a]">Riwayat Pendidikan &amp; Pengembangan</div>
                            <button type="button" wire:click="openEduModal" class="text-[11px] font-semibold bg-accent text-white px-3 py-1.5 rounded-md cursor-pointer">+ Tambah Riwayat</button>
                        </div>
                        <x-grid.table-shell>
                        <table class="w-full text-[12.5px]">
                            <thead><tr class="text-left text-[11px] uppercase text-muted"><th class="pb-2">Kegiatan</th><th class="pb-2">Tanggal</th><th class="num pb-2">Biaya</th><th class="pb-2">Dibiayai</th></tr></thead>
                            <tbody>
                                @forelse ($emp->educations as $ed)
                                    <tr class="border-t border-[#f1f5f9]">
                                        <td class="py-2">{{ $ed->activity_name }}</td>
                                        <td class="py-2">{{ $ed->activity_date->format('d M Y') }}</td>
                                        <td class="num py-2">Rp {{ number_format((float) $ed->cost, 0, ',', '.') }}</td>
                                        <td class="py-2">{{ $ed->funded_by }}</td>
                                    </tr>
                                @empty
                                    <x-grid.empty-row :colspan="4" message="Belum ada riwayat." />
                                @endforelse
                            </tbody>
                        </table>
                        </x-grid.table-shell>
                    </div>
                </div>
            @endif

            {{-- ===================== TAB: CUTI / SAKIT / IZIN ===================== --}}
            @if ($tab === 'cuti')
                <x-alert.banner type="success" :message="session('leaveSubmitted')" />

                <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5 mb-4">
                    <div class="text-sm font-bold text-[#0f172a] mb-3 pb-2 border-b border-[#f1f5f9]">Saldo Cuti Tahunan — {{ now()->year }}</div>
                    <div class="grid three gap-2.5">
                        <div class="bg-[#f8fafc] border border-[#e2e8f0] rounded-lg p-3 text-center"><div class="text-lg font-bold text-[#0f172a]">{{ $this->currentYearBalance?->entitlement_days }}</div><div class="text-[10px] text-muted uppercase font-bold">Hak Cuti</div></div>
                        <div class="bg-[#f8fafc] border border-[#e2e8f0] rounded-lg p-3 text-center"><div class="text-lg font-bold text-[#92400e]">{{ $this->currentYearBalance?->used_days }}</div><div class="text-[10px] text-muted uppercase font-bold">Terpakai</div></div>
                        <div class="bg-[#f8fafc] border border-[#e2e8f0] rounded-lg p-3 text-center"><div class="text-lg font-bold text-[#166534]">{{ $this->currentYearBalance?->remainingDays() }}</div><div class="text-[10px] text-muted uppercase font-bold">Sisa Saldo</div></div>
                    </div>
                </div>

                <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5">
                    <div class="text-sm font-bold text-[#0f172a] mb-3 pb-2 border-b border-[#f1f5f9]">Form Pengajuan Absensi</div>

                    <div class="mb-3">
                        <x-form.select label="Jenis Pengajuan" wire:model.live="leave_type" :placeholder="false" :options="[
                            'CUTI' => 'Cuti Tahunan (Dibayar)',
                            'SAKIT' => 'Sakit',
                            'IZIN' => 'Izin (Non-Sakit)'.($this->izinLocked ? ' — Terkunci, sisa cuti '.$this->currentYearBalance?->remainingDays().' hari' : ''),
                            'CUTI_KHUSUS' => 'Cuti Khusus (Nikah, Duka, dll)',
                        ]" />
                    </div>

                    @if ($leave_type === 'CUTI')
                        <div class="text-xs text-[#334155] bg-[#f0f9ff] border-l-4 border-accent px-3.5 py-2.5 rounded-r-md mb-3">Wajib diajukan minimal <b>H+7</b> dari hari ini. Maksimal <b>3 hari kerja</b> berturut-turut per pengajuan. Minggu tidak menghitung cuti.</div>
                    @elseif ($leave_type === 'SAKIT')
                        <div class="text-xs text-[#78350f] bg-[#fffbeb] border-l-4 border-[#f59e0b] px-3.5 py-2.5 rounded-r-md mb-3">Wajib lapor ke atasan &amp; HRD paling lambat pukul <b>07:30 WIB</b> pada hari ketidakhadiran.</div>
                    @elseif ($leave_type === 'IZIN')
                        <div class="text-xs text-[#991b1b] bg-[#fef2f2] border-l-4 border-[#ef4444] px-3.5 py-2.5 rounded-r-md mb-3">Izin bersifat <b>Unpaid</b> (no present no pay). Maksimal 3 hari berturut &amp; 3x pengajuan per bulan — lebih dari itu otomatis Alpha.</div>
                    @endif

                    <div class="grid two gap-2.5 mb-3">
                        <x-form.date-picker label="Dari Tanggal" wire:model="leave_date_from" name="leave_date_from" />
                        <x-form.date-picker label="Sampai Tanggal" wire:model="leave_date_to" name="leave_date_to" />
                    </div>

                    @if ($leave_type === 'CUTI')
                        <div class="mb-3">
                            <x-form.file-dropzone label="Surat Permohonan Cuti (wajib)" wire:model="att_surat_cuti" />
                            @error('att_surat_cuti') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    @if ($leave_type === 'SAKIT')
                        <div class="grid two gap-2.5 mb-3">
                            <div>
                                <x-form.input label="Jam Lapor ke HRD" type="time" wire:model.live="sakit_lapor_jam" />
                                @if ($this->sakitLaporTerlambat)
                                    <div class="text-[11px] text-red-600 font-semibold mt-1">⚠ Melewati batas 07:30 WIB — akan direview HR.</div>
                                @endif
                            </div>
                            <div class="flex items-end pb-2">
                                <x-form.checkbox wire:model.live="sakit_via_jkn" label="Peserta JKN (cukup screenshot riwayat)" />
                            </div>
                        </div>
                        @if ($sakit_via_jkn)
                            <div class="mb-3">
                                <x-form.file-dropzone label="Screenshot Riwayat Aplikasi JKN" wire:model="att_jkn_screenshot" />
                                @error('att_jkn_screenshot') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                            </div>
                        @else
                            <div class="grid two gap-2.5 mb-3">
                                <div>
                                    <x-form.file-dropzone label="Surat Keterangan Dokter" wire:model="att_surat_dokter" />
                                </div>
                                <div>
                                    <x-form.file-dropzone label="Kwitansi / Resep Obat" wire:model="att_kwitansi_resep" />
                                </div>
                            </div>
                            @error('att_surat_dokter') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        @endif
                    @endif

                    @if ($leave_type === 'IZIN' && ! $this->izinLocked)
                        <div class="mb-3">
                            <x-form.select label="Sifat Izin" wire:model.live="izin_sifat" :placeholder="false"
                                :options="['terjadwal' => 'Terjadwal (min. H-7)', 'mendadak' => 'Mendadak (darurat/musibah)']" />
                        </div>
                        @if ($izin_sifat === 'mendadak')
                            <div class="mb-3">
                                <x-form.select label="Kategori Keperluan Mendadak" wire:model.live="izin_category" :placeholder="false" :options="[
                                    'MUSIBAH' => 'Musibah Keluarga / Kecelakaan / Bencana',
                                    'INSTANSI_RESMI' => 'Keperluan Instansi Pemerintah Resmi',
                                    'KEPENGURUSAN_MUSIBAH' => 'Kepengurusan Duka / Musibah',
                                ]" />
                            </div>
                            @if ($izin_category === 'INSTANSI_RESMI')
                                <div class="grid two gap-2.5 mb-3">
                                    <x-form.select label="Untuk Keperluan" wire:model="izin_relasi_pemohon" :placeholder="false" :options="[
                                        'DIRI_SENDIRI' => 'Diri Sendiri',
                                        'SUAMI_ISTRI' => 'Suami/Istri',
                                        'ANAK_KANDUNG' => 'Anak Kandung',
                                        'ORANG_TUA_KANDUNG' => 'Orang Tua Kandung',
                                        'MERTUA' => 'Mertua',
                                    ]" />
                                    <x-form.select label="Instansi Tujuan" wire:model="izin_instansi_asal" :placeholder="false" :options="[
                                        'BPJS' => 'BPJS',
                                        'RUMAH_SAKIT' => 'Rumah Sakit',
                                        'SEKOLAH' => 'Sekolah',
                                        'KEPOLISIAN' => 'Kepolisian',
                                        'INSTANSI_PEMERINTAH_LAINNYA' => 'Instansi Pemerintah Lainnya',
                                    ]" />
                                </div>
                            @endif
                            <div class="mb-3">
                                <x-form.file-dropzone label="Lampiran Bukti / Surat Instansi" wire:model="att_generic" />
                                @error('att_generic') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                            </div>
                        @endif
                    @endif

                    @if ($leave_type === 'CUTI_KHUSUS')
                        <div class="mb-3">
                            <x-form.select label="Jenis Peristiwa Khusus" wire:model="khusus_event_type" :placeholder="false" :options="[
                                'MENIKAH' => 'Pernikahan Karyawan',
                                'MENIKAHKAN_KHITAN_ANAK' => 'Menikahkan / Mengkhitankan Anak',
                                'ISTRI_MELAHIRKAN' => 'Istri Melahirkan / Keguguran',
                                'KELUARGA_MENINGGAL' => 'Keluarga Inti Meninggal Dunia',
                                'IBADAH' => 'Ibadah',
                            ]" />
                        </div>
                        <div class="mb-3">
                            <x-form.file-dropzone label="Lampiran Bukti Pendukung" wire:model="att_generic" />
                            @error('att_generic') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    <div class="mb-3">
                        <x-form.textarea label="Keterangan / Alasan" wire:model="leave_reason" rows="2" />
                    </div>

                    <button type="button" wire:click="submitLeave" class="w-full bg-accent text-white text-xs font-semibold px-4 py-2.5 rounded-md cursor-pointer">Kirimkan Pengajuan</button>
                </div>

                <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5 mt-4">
                    <div class="text-sm font-bold text-[#0f172a] mb-3 pb-2 border-b border-[#f1f5f9]">Riwayat Pengajuan Saya</div>
                    <x-grid.table-shell>
                    <table class="w-full text-[12.5px]">
                        <thead><tr class="text-left text-[11px] uppercase text-muted"><th class="pb-2">Jenis</th><th class="pb-2">Tanggal</th><th class="pb-2">Status Gaji</th><th class="pb-2">Approval</th></tr></thead>
                        <tbody>
                            @forelse ($this->myLeaveRequests as $lr)
                                <tr class="border-t border-[#f1f5f9]">
                                    <td class="py-2 font-semibold">{{ $lr->leave_type }}</td>
                                    <td class="py-2">{{ $lr->date_from->format('d M') }}–{{ $lr->date_to->format('d M Y') }}</td>
                                    <td class="py-2"><span class="text-[10px] font-bold px-2 py-0.5 rounded {{ $lr->is_paid ? 'bg-[#dcfce7] text-[#166534]' : 'bg-[#fee2e2] text-[#991b1b]' }}">{{ $lr->is_paid ? 'Dibayar' : 'Unpaid' }}</span></td>
                                    <td class="py-2"><span class="text-[10px] font-bold px-2 py-0.5 rounded {{ match ($lr->status) { 'APPROVED' => 'bg-[#dcfce7] text-[#166534]', 'REJECTED' => 'bg-[#fee2e2] text-[#991b1b]', default => 'bg-[#fef3c7] text-[#92400e]' } }}">{{ $lr->status }}</span></td>
                                </tr>
                            @empty
                                <x-grid.empty-row :colspan="4" message="Belum ada pengajuan." />
                            @endforelse
                        </tbody>
                    </table>
                    </x-grid.table-shell>
                </div>
            @endif

            {{-- ===================== TAB: IZIN KELUAR ===================== --}}
            @if ($tab === 'izinkeluar')
                <div class="grid two gap-4">
                    <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5">
                        <div class="text-sm font-bold text-[#0f172a] mb-3 pb-2 border-b border-[#f1f5f9]">Form Izin Keluar Area Kerja</div>
                        <div class="text-xs text-[#78350f] bg-[#fffbeb] border-l-4 border-[#f59e0b] px-3.5 py-2.5 rounded-r-md mb-3">Wajib disetujui <b>Atasan Langsung DAN HRD</b> sebelum meninggalkan area pabrik.</div>
                        <div class="mb-3"><x-form.date-picker label="Tanggal" wire:model="op_permit_date" name="op_permit_date" /></div>
                        <div class="grid two gap-2.5 mb-3">
                            <x-form.input label="Jam Keluar" type="time" wire:model="op_out_time" name="op_out_time" />
                            <x-form.input label="Jam Kembali" type="time" wire:model="op_return_time" />
                        </div>
                        <div class="mb-3"><x-form.textarea label="Keperluan" wire:model="op_reason" rows="3" name="op_reason" /></div>
                        <button type="button" wire:click="submitOutPermit" class="w-full bg-accent text-white text-xs font-semibold px-4 py-2.5 rounded-md cursor-pointer">Kirim Surat Izin Keluar</button>
                    </div>
                    <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5">
                        <div class="text-sm font-bold text-[#0f172a] mb-3 pb-2 border-b border-[#f1f5f9]">Riwayat Izin Keluar</div>
                        <x-grid.table-shell>
                        <table class="w-full text-[12.5px]">
                            <thead><tr class="text-left text-[11px] uppercase text-muted"><th class="pb-2">Tanggal</th><th class="pb-2">Jam</th><th class="pb-2">Status</th></tr></thead>
                            <tbody>
                                @forelse ($this->myOutPermits as $op)
                                    <tr class="border-t border-[#f1f5f9]">
                                        <td class="py-2">{{ $op->permit_date->format('d M Y') }}</td>
                                        <td class="py-2">{{ \Illuminate\Support\Carbon::parse($op->planned_out_time)->format('H:i') }}</td>
                                        <td class="py-2"><span class="text-[10px] font-bold px-2 py-0.5 rounded {{ match ($op->status) { 'APPROVED' => 'bg-[#dcfce7] text-[#166534]', 'REJECTED' => 'bg-[#fee2e2] text-[#991b1b]', default => 'bg-[#fef3c7] text-[#92400e]' } }}">{{ $op->status }}</span></td>
                                    </tr>
                                @empty
                                    <x-grid.empty-row :colspan="3" message="Belum ada pengajuan." />
                                @endforelse
                            </tbody>
                        </table>
                        </x-grid.table-shell>
                    </div>
                </div>
            @endif

            {{-- ===================== TAB: TUKAR SHIFT ===================== --}}
            @if ($tab === 'tukarshift')
                <div class="grid two gap-4">
                    <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5">
                        <div class="text-sm font-bold text-[#0f172a] mb-3 pb-2 border-b border-[#f1f5f9]">Form Tukar Shift</div>
                        <div class="text-xs text-[#78350f] bg-[#fffbeb] border-l-4 border-[#f59e0b] px-3.5 py-2.5 rounded-r-md mb-3">Batas pengajuan maksimal <b>H-1</b>. Harus disetujui rekan tukar, lalu PPIC.</div>
                        <div class="mb-3">
                            <x-form.select label="Jadwal Saya yang Ditukar" wire:model="swap_requester_roster_id" name="swap_requester_roster_id"
                                :options="$this->myUpcomingRoster->mapWithKeys(fn ($r) => [$r->roster_id => $r->roster_date->translatedFormat('l, d M').' — '.$r->shift->shift_name])->all()" />
                        </div>
                        <div class="mb-3">
                            <x-form.select label="Rekan Pengganti" wire:model.live="swap_target_employee_id"
                                :options="$this->swapColleagues->pluck('full_name', 'employee_id')->all()" />
                        </div>
                        @if ($swap_target_employee_id)
                            <div class="mb-3">
                                <x-form.select label="Jadwal Rekan (Target Tukar)" wire:model="swap_target_roster_id" name="swap_target_roster_id"
                                    :options="$this->targetRosterOptions->mapWithKeys(fn ($r) => [$r->roster_id => $r->roster_date->translatedFormat('l, d M').' — '.$r->shift->shift_name])->all()" />
                            </div>
                        @endif
                        <div class="mb-3"><x-form.textarea label="Alasan" wire:model="swap_reason" rows="2" /></div>
                        <button type="button" wire:click="submitSwap" class="w-full bg-accent text-white text-xs font-semibold px-4 py-2.5 rounded-md cursor-pointer">Ajukan Permohonan Tukar</button>
                    </div>
                    <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5">
                        <div class="text-sm font-bold text-[#0f172a] mb-3 pb-2 border-b border-[#f1f5f9]">Riwayat Tukar Shift</div>
                        <x-grid.table-shell>
                        <table class="w-full text-[12.5px]">
                            <thead><tr class="text-left text-[11px] uppercase text-muted"><th class="pb-2">Tanggal Saya</th><th class="pb-2">Ditukar Dengan</th><th class="pb-2">Rekan</th><th class="pb-2">PPIC</th></tr></thead>
                            <tbody>
                                @forelse ($this->mySwapRequests as $s)
                                    <tr class="border-t border-[#f1f5f9]">
                                        <td class="py-2">{{ $s->requesterRoster?->roster_date?->format('d M') }}</td>
                                        <td class="py-2">{{ $s->targetEmployee?->full_name }}</td>
                                        <td class="py-2"><span class="text-[10px] font-bold px-2 py-0.5 rounded {{ $s->partner_status === 'APPROVED' ? 'bg-[#dcfce7] text-[#166534]' : 'bg-[#fef3c7] text-[#92400e]' }}">{{ $s->partner_status }}</span></td>
                                        <td class="py-2"><span class="text-[10px] font-bold px-2 py-0.5 rounded {{ $s->ppic_status === 'APPROVED' ? 'bg-[#dcfce7] text-[#166534]' : 'bg-[#fef3c7] text-[#92400e]' }}">{{ $s->ppic_status }}</span></td>
                                    </tr>
                                @empty
                                    <x-grid.empty-row :colspan="4" message="Belum ada pengajuan." />
                                @endforelse
                            </tbody>
                        </table>
                        </x-grid.table-shell>
                    </div>
                </div>
            @endif

            {{-- ===================== TAB: JADWAL SAYA ===================== --}}
            @if ($tab === 'jadwal')
                <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5">
                    <div class="flex items-center justify-between mb-3 pb-2 border-b border-[#f1f5f9]">
                        <div class="text-sm font-bold text-[#0f172a]">Jadwal Kerja Resmi (Dipublikasi PPIC/HR)</div>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-[#dcfce7] text-[#166534]">Confirmed Only</span>
                    </div>
                    @forelse ($this->mySchedule as $s)
                        <div class="flex items-center gap-3 py-2.5 border-b border-[#f8fafc] last:border-b-0 text-[12.5px]">
                            <div class="w-24 font-semibold text-[#0f172a]">{{ $s->roster_date->translatedFormat('D, d M') }}</div>
                            <span class="text-[11px] font-semibold px-2.5 py-1 rounded bg-[#fef3c7] text-[#92400e]">{{ $s->shift?->shift_name }}</span>
                            @if ($s->is_overtime)
                                <span class="text-[10px] font-bold text-[#991b1b]">+ Lembur</span>
                            @endif
                            <div class="flex-1"></div>
                            @if ($s->machine)
                                <div class="text-[11px] text-muted">Stasiun: {{ $s->machine->machine_name }}</div>
                            @endif
                        </div>
                    @empty
                        <div class="text-center py-6 text-muted text-xs">Belum ada jadwal terpublikasi.</div>
                    @endforelse
                </div>
            @endif

            {{-- ===================== TAB: KARTU PENGENAL ===================== --}}
            @if ($tab === 'badge')
                <div class="grid two gap-4">
                    <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5">
                        <div class="text-sm font-bold text-[#0f172a] mb-3 pb-2 border-b border-[#f1f5f9]">Kartu ID Karyawan Digital</div>
                        <div class="w-full max-w-[330px] h-[200px] bg-gradient-to-br from-[#1e293b] to-[#0f172a] rounded-2xl mx-auto overflow-hidden shadow-lg">
                            <div class="h-12 bg-white/5 px-4 flex items-center justify-between border-b border-white/10">
                                <div class="text-xs font-bold text-[#38bdf8]">CV TELASIH MAKMUR</div>
                                <div class="text-[10px] text-white/60">TMERP DIGITAL ID</div>
                            </div>
                            <div class="flex gap-3.5 p-4">
                                <div class="w-[70px] h-[90px] bg-white/10 rounded-lg flex items-center justify-center text-[10px] text-white/50">Pas Foto</div>
                                <div>
                                    <div class="text-[15px] font-bold text-white">{{ $emp->full_name }}</div>
                                    <div class="text-[11px] text-white/70 mt-0.5">{{ $emp->position?->position_name }}</div>
                                    <div class="text-xs font-bold text-[#38bdf8] mt-2.5 font-mono tracking-wide">{{ $emp->employee_code }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5">
                        <div class="text-sm font-bold text-[#0f172a] mb-3 pb-2 border-b border-[#f1f5f9]">Ajukan Pembaruan Foto ID</div>
                        <div class="text-xs text-[#78350f] bg-[#fffbeb] border-l-4 border-[#f59e0b] px-3.5 py-2.5 rounded-r-md mb-3">
                            Format 4×6cm (rasio 2:3), minimal 600×900px, latar polos terang, wajah menghadap depan.
                        </div>
                        <button type="button" wire:click="openBadgeModal" class="w-full bg-accent text-white text-xs font-semibold px-4 py-2.5 rounded-md cursor-pointer">Upload Foto ID Baru</button>
                    </div>
                </div>
            @endif

            {{-- ===================== TAB: SECURITY ===================== --}}
            @if ($tab === 'security')
                <div class="text-xs text-[#334155] bg-[#f0f9ff] border-l-4 border-accent px-3.5 py-2.5 rounded-r-md mb-4">
                    Data akun login (bukan data HR) — bisa diubah langsung tanpa persetujuan HR.
                </div>
                <div class="grid two gap-4">
                    <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5">
                        <div class="text-sm font-bold text-[#0f172a] mb-3 pb-2 border-b border-[#f1f5f9]">Account profile</div>
                        @if ($accSaved)
                            <div class="text-xs text-[#166534] bg-[#f0fdf4] border-l-4 border-[#16a34a] px-3.5 py-2.5 rounded-r-md mb-3">✅ Profil akun tersimpan.</div>
                        @endif
                        <div class="space-y-3">
                            <x-form.input label="Nama lengkap" wire:model="acc_name" name="acc_name" />
                            <x-form.input label="Email" type="email" wire:model="acc_email" name="acc_email" />
                            <x-form.input label="No. telepon" wire:model="acc_phone" />
                        </div>
                        <button type="button" wire:click="saveAccountProfile" class="w-full bg-accent text-white text-xs font-semibold px-4 py-2.5 rounded-md cursor-pointer mt-3">Simpan</button>
                    </div>
                    <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5">
                        <div class="text-sm font-bold text-[#0f172a] mb-3 pb-2 border-b border-[#f1f5f9]">Change password</div>
                        @if ($accPasswordSaved)
                            <div class="text-xs text-[#166534] bg-[#f0fdf4] border-l-4 border-[#16a34a] px-3.5 py-2.5 rounded-r-md mb-3">✅ Password diperbarui.</div>
                        @endif
                        @if ($accPasswordError)
                            <div class="text-xs text-[#991b1b] bg-[#fef2f2] border-l-4 border-[#dc2626] px-3.5 py-2.5 rounded-r-md mb-3">{{ $accPasswordError }}</div>
                        @endif
                        <div class="space-y-3">
                            <x-form.input label="Password lama" type="password" wire:model="acc_old_password" placeholder="Password saat ini" />
                            <x-form.input label="Password baru" type="password" wire:model="acc_new_password" placeholder="Minimal 8 karakter" name="acc_new_password" />
                            <x-form.input label="Konfirmasi password baru" type="password" wire:model="acc_new_password_confirmation" placeholder="Ulangi password baru" />
                        </div>
                        <button type="button" wire:click="changeAccountPassword" class="w-full bg-accent text-white text-xs font-semibold px-4 py-2.5 rounded-md cursor-pointer mt-3">Update password</button>
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- ══════════ MODALS ══════════ --}}

    @if ($showCorrectionModal)
        <div class="fixed inset-0 bg-[rgba(15,23,42,.5)] z-[100] flex items-center justify-center p-5" wire:click.self="$set('showCorrectionModal', false)">
            <div class="bg-white rounded-xl w-[420px] max-w-[92vw] p-5">
                <div class="text-base font-bold text-[#0f172a] mb-1">Ajukan Koreksi Data</div>
                <div class="text-xs text-muted mb-3">Field: <b>{{ $corr_field_name }}</b></div>
                <form wire:submit="saveCorrection" class="flex flex-col gap-3">
                    <x-form.input label="Nilai Saat Ini" value="{{ $corr_old_value }}" disabled class="bg-[#f1f5f9]" />
                    <x-form.input label="Nilai Baru" wire:model="corr_new_value" name="corr_new_value" />
                    <x-form.textarea label="Alasan" wire:model="corr_reason" rows="2" />
                    <div class="flex gap-2 mt-1"><button type="button" wire:click="$set('showCorrectionModal', false)" class="flex-1 px-4 py-2 rounded-md text-xs font-semibold bg-[#f1f5f9] text-[#334] cursor-pointer">Batal</button><button type="submit" class="flex-1 px-4 py-2 rounded-md text-xs font-semibold bg-accent text-white cursor-pointer">Kirim Pengajuan</button></div>
                </form>
            </div>
        </div>
    @endif

    @if ($showFamilyReqModal)
        <div class="fixed inset-0 bg-[rgba(15,23,42,.5)] z-[100] flex items-center justify-center p-5" wire:click.self="$set('showFamilyReqModal', false)">
            <div class="bg-white rounded-xl w-[420px] max-w-[92vw] p-5">
                <div class="text-base font-bold text-[#0f172a] mb-3">{{ match ($famreq_type) { 'LAHIR' => 'Ajukan Kelahiran Anak Baru', 'MENINGGAL' => 'Tandai Anggota Keluarga Meninggal', default => 'Ajukan Perubahan Status Pasangan' } }}</div>
                <form wire:submit="saveFamilyReq" class="flex flex-col gap-3">
                    @if ($famreq_type === 'MENINGGAL')
                        <x-form.select label="Pilih Anggota Keluarga" wire:model="famreq_family_id" name="famreq_family_id"
                            :options="$this->employee->families->where('is_deceased', false)->mapWithKeys(fn ($f) => [$f->family_id => $f->full_name.' ('.$f->relation.')'])->all()" />
                        <x-form.date-picker label="Tanggal Meninggal" wire:model="famreq_deceased_date" name="famreq_deceased_date" />
                        <x-form.file-dropzone label="Upload Surat Kematian" wire:model="famreq_attachment" />
                    @else
                        <x-form.select label="Hubungan" wire:model="famreq_relation" :placeholder="false"
                            :options="$famreq_type === 'PASANGAN' ? ['SUAMI' => 'Suami', 'ISTRI' => 'Istri'] : ['ANAK' => 'Anak']" />
                        <x-form.input label="Nama Lengkap" wire:model="famreq_full_name" name="famreq_full_name" />
                        <x-form.date-picker label="Tanggal Lahir" wire:model="famreq_birth_date" />
                        <x-form.file-dropzone label="Upload Akta / Buku Nikah" wire:model="famreq_attachment" />
                    @endif
                    <div class="flex gap-2 mt-1"><button type="button" wire:click="$set('showFamilyReqModal', false)" class="flex-1 px-4 py-2 rounded-md text-xs font-semibold bg-[#f1f5f9] text-[#334] cursor-pointer">Batal</button><button type="submit" class="flex-1 px-4 py-2 rounded-md text-xs font-semibold bg-accent text-white cursor-pointer">Kirim Pengajuan</button></div>
                </form>
            </div>
        </div>
    @endif

    @if ($showResignModal)
        <div class="fixed inset-0 bg-[rgba(15,23,42,.5)] z-[100] flex items-center justify-center p-5" wire:click.self="$set('showResignModal', false)">
            <div class="bg-white rounded-xl w-[420px] max-w-[92vw] p-5">
                <div class="text-base font-bold text-[#991b1b] mb-3">Pengajuan Pengunduran Diri</div>
                <form wire:submit="saveResign" class="flex flex-col gap-3">
                    <x-form.date-picker label="Tanggal Efektif" wire:model="resign_effective_date" name="resign_effective_date" />
                    <x-form.textarea label="Alasan" wire:model="resign_reason" rows="3" name="resign_reason" />
                    <div class="flex gap-2 mt-1"><button type="button" wire:click="$set('showResignModal', false)" class="flex-1 px-4 py-2 rounded-md text-xs font-semibold bg-[#f1f5f9] text-[#334] cursor-pointer">Batal</button><button type="submit" class="flex-1 px-4 py-2 rounded-md text-xs font-semibold bg-[#991b1b] text-white cursor-pointer">Kirim Pengajuan</button></div>
                </form>
            </div>
        </div>
    @endif

    @if ($showDocModal)
        <div class="fixed inset-0 bg-[rgba(15,23,42,.5)] z-[100] flex items-center justify-center p-5" wire:click.self="$set('showDocModal', false)">
            <div class="bg-white rounded-xl w-[420px] max-w-[92vw] p-5">
                <div class="text-base font-bold text-[#0f172a] mb-3">Upload Dokumen — {{ $doc_type }}</div>
                <form wire:submit="saveDoc" class="flex flex-col gap-3">
                    <x-form.input label="Nomor Dokumen" wire:model="doc_document_no" />
                    <x-form.date-picker label="Masa Berlaku (jika ada)" wire:model="doc_expiry_date" />
                    <div>
                        <x-form.file-dropzone label="File (JPG/PNG/PDF, maks 5MB)" wire:model="doc_file" />
                        @error('doc_file') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex gap-2 mt-1"><button type="button" wire:click="$set('showDocModal', false)" class="flex-1 px-4 py-2 rounded-md text-xs font-semibold bg-[#f1f5f9] text-[#334] cursor-pointer">Batal</button><button type="submit" class="flex-1 px-4 py-2 rounded-md text-xs font-semibold bg-accent text-white cursor-pointer">Simpan &amp; Upload</button></div>
                </form>
            </div>
        </div>
    @endif

    @if ($showEduModal)
        <div class="fixed inset-0 bg-[rgba(15,23,42,.5)] z-[100] flex items-center justify-center p-5" wire:click.self="$set('showEduModal', false)">
            <div class="bg-white rounded-xl w-[420px] max-w-[92vw] p-5">
                <div class="text-base font-bold text-[#0f172a] mb-3">Tambah Riwayat Pelatihan</div>
                <form wire:submit="saveEdu" class="flex flex-col gap-3">
                    <x-form.input label="Nama Pelatihan / Sertifikasi" wire:model="edu_activity_name" name="edu_activity_name" />
                    <x-form.input label="Penyelenggara" wire:model="edu_organizer" />
                    <div class="grid two gap-2.5">
                        <x-form.date-picker label="Tanggal" wire:model="edu_activity_date" name="edu_activity_date" />
                        <x-form.input label="Biaya (Rp)" type="number" wire:model="edu_cost" />
                    </div>
                    <x-form.select label="Ditanggung Oleh" wire:model="edu_funded_by" :placeholder="false"
                        :options="['PRIBADI' => 'Pribadi', 'PERUSAHAAN' => 'Perusahaan', 'SHARING' => 'Sharing']" />
                    <div class="flex gap-2 mt-1"><button type="button" wire:click="$set('showEduModal', false)" class="flex-1 px-4 py-2 rounded-md text-xs font-semibold bg-[#f1f5f9] text-[#334] cursor-pointer">Batal</button><button type="submit" class="flex-1 px-4 py-2 rounded-md text-xs font-semibold bg-accent text-white cursor-pointer">Simpan</button></div>
                </form>
            </div>
        </div>
    @endif

    @if ($showBadgeModal)
        <div class="fixed inset-0 bg-[rgba(15,23,42,.5)] z-[100] flex items-center justify-center p-5" wire:click.self="$set('showBadgeModal', false)">
            <div class="bg-white rounded-xl w-[420px] max-w-[92vw] p-5">
                <div class="text-base font-bold text-[#0f172a] mb-1">Upload Foto ID Card Baru</div>
                <div class="text-xs text-muted mb-3">Foto akan direview HRD sebelum dicetak ulang.</div>
                <form wire:submit="saveBadgePhoto" class="flex flex-col gap-3">
                    <div>
                        <x-form.file-dropzone label="Pilih File Foto (min. 600×900px, rasio 2:3)" wire:model="badge_photo" />
                        @error('badge_photo') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex gap-2 mt-1"><button type="button" wire:click="$set('showBadgeModal', false)" class="flex-1 px-4 py-2 rounded-md text-xs font-semibold bg-[#f1f5f9] text-[#334] cursor-pointer">Batal</button><button type="submit" class="flex-1 px-4 py-2 rounded-md text-xs font-semibold bg-accent text-white cursor-pointer">Kirim Foto</button></div>
                </form>
            </div>
        </div>
    @endif
</div>
