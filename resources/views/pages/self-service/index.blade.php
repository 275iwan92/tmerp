<?php

use App\Services\Hrm\EmployeeBankService;
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

    // Ganti rekening (2026-09-23): bank dari Master Supplier kategori BANK.
    public string $corr_bank_supplier_id = '';

    public string $corr_bank_account_no = '';

    public string $corr_bank_holder = '';

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
            $this->accPasswordError = t('Old password is wrong.');

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
        $this->reset(['corr_new_value', 'corr_reason', 'corr_bank_supplier_id', 'corr_bank_account_no', 'corr_bank_holder']);
        $this->corr_field_name = $field;
        $this->corr_old_value = (string) $oldValue;

        if ($field === EmpCorrectionRequest::FIELD_BANK) {
            $bank = EmployeeBankService::primary($this->employeeId);
            $this->corr_old_value = $bank ? trim($bank->bank_name).' · '.$bank->account_no.' · a.n. '.$bank->account_holder_name : 'Cash / Tunai';
            $this->corr_bank_supplier_id = (string) ($bank?->bank_supplier_id ?? '');
            $this->corr_bank_account_no = (string) ($bank?->account_no ?? '');
            $this->corr_bank_holder = (string) ($bank?->account_holder_name ?? '');
        }

        $this->showCorrectionModal = true;
    }

    public function bankOptions(): array
    {
        return EmployeeBankService::bankOptions();
    }

    public function saveCorrection(): void
    {
        if ($this->corr_field_name === EmpCorrectionRequest::FIELD_BANK) {
            $bank = $this->validate([
                'corr_bank_supplier_id' => ['required', 'integer', 'exists:tm2mst.sup_supplier,id_supplier'],
                'corr_bank_account_no' => ['required', 'string', 'max:50'],
                'corr_bank_holder' => ['required', 'string', 'max:150'],
                'corr_reason' => ['nullable', 'string', 'max:255'],
            ]);
            $this->corr_new_value = json_encode([
                'bank_supplier_id' => (int) $bank['corr_bank_supplier_id'],
                'account_no' => trim($bank['corr_bank_account_no']),
                'account_holder_name' => trim($bank['corr_bank_holder']),
            ]);
        }

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
                $this->addError('famreq_relation', t('Maximum 20 children per employee.'));

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
            $this->addError('doc_file', t('This document was uploaded & locked by HR — request a correction through HR, it cannot be replaced yourself.'));

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
            $this->addError('leave_date_to', t('Invalid date.'));

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
                $this->addError('leave_date_from', t('Leave must be requested at least D+7 from today.'));

                return;
            }
            if ($totalDays > 3) {
                $this->addError('leave_date_to', t('Maximum 3 working days per leave request.'));

                return;
            }
            if (! $this->att_surat_cuti) {
                $this->addError('att_surat_cuti', t('The Leave Letter must be uploaded (administrative requirement).'));

                return;
            }

            $payload['attachment_surat_cuti'] = $this->storeUpload($this->att_surat_cuti, 'leave');
            $payload['is_paid'] = true;
            $payload['deducts_leave_balance'] = true;
        } elseif ($this->leave_type === 'SAKIT') {
            if ($this->sakit_via_jkn) {
                if (! $this->att_jkn_screenshot) {
                    $this->addError('att_jkn_screenshot', t('A screenshot of the JKN history must be uploaded.'));

                    return;
                }
                $payload['attachment_jkn_screenshot'] = $this->storeUpload($this->att_jkn_screenshot, 'leave');
            } else {
                if (! $this->att_surat_dokter || ! $this->att_kwitansi_resep) {
                    $this->addError('att_surat_dokter', t('The Doctor\'s Certificate AND the receipt/prescription must both be uploaded.'));

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
                $this->addError('leave_date_from', t('You still have leave left — Permission can only be requested when this year\'s leave balance is used up (0 days).'));

                return;
            }
            if ($totalDays > 3) {
                $this->addError('leave_date_to', t('Maximum 3 consecutive days per permission request.'));

                return;
            }
            if ($this->izinCountForMonth($from) >= 3) {
                $this->recordAutoAlpha($from, $this->izinCountForMonth($from) + 1);
                $this->addError('leave_date_from', t('Permission already requested 3x this month — this request is automatically recorded as Alpha in your disciplinary record, no need to report it to HR manually.'));

                return;
            }
            if ($this->izin_sifat === 'terjadwal' && $from->lt(now()->addDays(7)->startOfDay())) {
                $this->addError('leave_date_from', t('Scheduled permission must be requested at least D-7.'));

                return;
            }
            if ($this->izin_sifat === 'mendadak') {
                $payload['izin_category'] = $this->izin_category;
                if ($this->izin_category === 'INSTANSI_RESMI') {
                    $payload['izin_relasi_pemohon'] = $this->izin_relasi_pemohon;
                    $payload['izin_instansi_asal'] = $this->izin_instansi_asal;
                }
                if (! $this->att_generic) {
                    $this->addError('att_generic', t('A proof/letter attachment must be uploaded for urgent permission.'));

                    return;
                }
                $payload['attachment_path'] = $this->storeUpload($this->att_generic, 'leave');
            }

            $payload['is_paid'] = false;
            $payload['deducts_leave_balance'] = false;
        } else { // CUTI_KHUSUS
            if (! $this->att_generic) {
                $this->addError('att_generic', t('A proof attachment (marriage certificate/birth certificate/etc.) must be uploaded.'));

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
        session()->flash('leaveSubmitted', t('The request was sent to HRD, status Waiting for Approval.'));
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
        <div class="text-sm font-bold flex items-center gap-2">TMERP <span class="bg-white/15 px-2 py-0.5 rounded text-[11px] font-medium">{{ t('Employee Self-Service') }}</span></div>
        <a href="{{ route('menu') }}" x-data x-show="window.self === window.top" data-back class="text-xs text-white/80 hover:text-white">← {{ t('Back to Main Menu') }}</a>
    </div>

    @if (! $this->employee)
        <div class="max-w-xl mx-auto mt-16 bg-white border border-[#e2e8f0] rounded-xl p-6 text-center">
            <div class="text-sm font-bold text-[#0f172a] mb-1">{{ t('Account not linked to employee data yet') }}</div>
            <div class="text-xs text-muted">{{ t('Contact HR to link your account to the Master Employee data before you can use this portal.') }}</div>
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
                        <div class="text-base font-bold text-[#0f172a]">{{ $this->currentYearBalance?->remainingDays() }} {{ t('Days') }}</div>
                        <div class="text-[10px] text-muted uppercase font-semibold">{{ t('Leave Left') }}</div>
                    </div>
                    <div class="text-center">
                        <div class="text-base font-bold text-[#0f172a]">{{ $emp->work_status === 'WORKING' ? t('Active') : $emp->work_status }}</div>
                        <div class="text-[10px] text-muted uppercase font-semibold">{{ t('Work Status') }}</div>
                    </div>
                </div>
            </div>

            {{-- TABS --}}
            <div class="bg-white border border-[#e2e8f0] rounded-xl p-1.5 mb-4 flex gap-1 flex-wrap sticky top-[52px] z-30">
                @foreach (['profil' => '👤 '.t('My Data'), 'jobdesc' => '📋 '.t('Job Description'), 'sop' => '📘 '.t('SOP'), 'keluarga' => '👪 '.t('Family & Status'), 'dokumen' => '📄 '.t('Documents & Education'), 'cuti' => '📅 '.t('Leave/Sick/Permission'), 'izinkeluar' => '🚪 '.t('Exit Permit'), 'tukarshift' => '🔄 '.t('Shift Swap'), 'jadwal' => '🗓️ '.t('My Schedule'), 'badge' => '🪪 '.t('ID Badge'), 'security' => '🔒 '.t('Security')] as $key => $label)
                    <button type="button" wire:click="$set('tab', '{{ $key }}')" class="px-3 py-2 text-xs font-medium rounded-md cursor-pointer {{ $tab === $key ? 'bg-[#0f172a] text-white font-semibold' : 'text-muted hover:bg-[#f8fafc]' }}">{{ $label }}</button>
                @endforeach
            </div>

            {{-- ===================== TAB: DATA SAYA ===================== --}}
            @if ($tab === 'profil')
                <div class="text-xs text-[#334155] bg-[#f0f9ff] border-l-4 border-accent px-3.5 py-2.5 rounded-r-md mb-4">
                    <b>{{ t('Data Update SOP:') }}</b> {{ t('The information below is') }} <i>read-only</i>. {{ t('If there is an entry error, click') }} <b>"{{ t('Request Correction') }}"</b> — {{ t('the change takes effect after HR verifies it.') }}
                </div>

                <div class="grid two gap-4">
                    <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5">
                        <div class="text-sm font-bold text-[#0f172a] mb-3 pb-2 border-b border-[#f1f5f9]">{{ t('Main Profile') }}</div>
                        {{-- Elemen pertama = field_name yang disimpan ke emp_correction_request (kode, jangan diterjemahkan). --}}
                        @foreach ([
                            ['Nama Lengkap', $emp->full_name, 'Full Name'],
                            ['Tanggal Lahir', $emp->birth_date?->format('d M Y'), 'Date of Birth'],
                            ['NIK KTP', $emp->nik_ktp, 'ID Card No. (NIK)'],
                            ['Agama', $emp->religion, 'Religion'],
                            ['Telepon / WA', $emp->phone, 'Phone / WA'],
                        ] as [$label, $value, $labelEn])
                            <div class="flex gap-2.5 py-2 text-[13px] border-b border-[#f8fafc] items-center">
                                <div class="w-[130px] shrink-0 text-muted text-xs">{{ t($labelEn) }}</div>
                                <div class="flex-1 font-medium">{{ $value ?: '—' }}</div>
                                <button type="button" wire:click="openCorrection('{{ $label }}', @js($value))" class="text-[11px] font-semibold text-accent bg-[#eff6ff] border border-[#bfdbfe] px-2 py-1 rounded cursor-pointer">{{ t('Request Correction') }}</button>
                            </div>
                        @endforeach
                        <div class="flex gap-2.5 py-2 text-[13px] items-center">
                            <div class="w-[130px] shrink-0 text-muted text-xs">{{ t('Position') }}</div>
                            <div class="flex-1 font-medium">{{ $emp->position?->position_name }}</div>
                            <span class="text-[10px] font-semibold bg-[#f1f5f9] text-muted px-2 py-0.5 rounded">{{ t('HR only') }}</span>
                        </div>
                    </div>

                    <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5">
                        <div class="text-sm font-bold text-[#0f172a] mb-3 pb-2 border-b border-[#f1f5f9]">{{ t('Address') }}</div>
                        @foreach ([
                            ['Alamat KTP', $emp->address_detail_ktp, 'ID Card Address'],
                            ['Alamat Domisili', $emp->address_detail_domisili, 'Home Address'],
                        ] as [$label, $value, $labelEn])
                            <div class="flex gap-2.5 py-2 text-[13px] border-b border-[#f8fafc] items-start">
                                <div class="w-[130px] shrink-0 text-muted text-xs pt-1">{{ t($labelEn) }}</div>
                                <div class="flex-1 font-medium">{{ $value ?: '—' }}</div>
                                <button type="button" wire:click="openCorrection('{{ $label }}', @js($value))" class="text-[11px] font-semibold text-accent bg-[#eff6ff] border border-[#bfdbfe] px-2 py-1 rounded cursor-pointer">{{ t('Request Correction') }}</button>
                            </div>
                        @endforeach

                        <div class="text-sm font-bold text-[#0f172a] mt-4 mb-3 pb-2 border-b border-[#f1f5f9]">{{ t('BPJS & Social Security') }}</div>
                        <div class="flex gap-2.5 py-2 text-[13px] items-center">
                            <div class="w-[130px] shrink-0 text-muted text-xs">{{ t('BPJS Health') }}</div>
                            <div class="flex-1 font-medium">{{ $emp->bpjsKesehatan?->no_kepesertaan ?: '—' }}</div>
                            <button type="button" wire:click="openCorrection('No. BPJS Kesehatan', @js($emp->bpjsKesehatan?->no_kepesertaan))" class="text-[11px] font-semibold text-accent bg-[#eff6ff] border border-[#bfdbfe] px-2 py-1 rounded cursor-pointer">{{ t('Request Correction') }}</button>
                        </div>
                        <div class="flex gap-2.5 py-2 text-[13px] items-center">
                            <div class="w-[130px] shrink-0 text-muted text-xs">{{ t('BPJS Employment') }}</div>
                            <div class="flex-1 font-medium">{{ $emp->bpjsKetenagakerjaan?->no_kepesertaan ?: '—' }}</div>
                            <button type="button" wire:click="openCorrection('No. BPJS Ketenagakerjaan', @js($emp->bpjsKetenagakerjaan?->no_kepesertaan))" class="text-[11px] font-semibold text-accent bg-[#eff6ff] border border-[#bfdbfe] px-2 py-1 rounded cursor-pointer">{{ t('Request Correction') }}</button>
                        </div>
                    </div>

                    <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5 md:col-span-2">
                        <div class="text-sm font-bold text-[#0f172a] mb-3 pb-2 border-b border-[#f1f5f9]">{{ t('Data Correction Request History') }}</div>
                        <x-grid.table-shell>
                        <table class="w-full text-[12.5px]">
                            <thead><tr class="text-left text-[11px] uppercase text-muted"><th class="pb-2">{{ t('Field') }}</th><th class="pb-2">{{ t('New Value') }}</th><th class="pb-2">{{ t('Status') }}</th></tr></thead>
                            <tbody>
                                @forelse ($this->myCorrections as $c)
                                    <tr class="border-t border-[#f1f5f9]"><td class="py-2">{{ $c->field_name }}</td><td class="py-2">{{ $c->new_value }}</td><td class="py-2">
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded {{ match ($c->status) { 'APPROVED' => 'bg-[#dcfce7] text-[#166534]', 'REJECTED' => 'bg-[#fee2e2] text-[#991b1b]', default => 'bg-[#fef3c7] text-[#92400e]' } }}">{{ $c->status }}</span>
                                    </td></tr>
                                @empty
                                    <x-grid.empty-row :colspan="3" :message="t('No requests yet.')" />
                                @endforelse
                            </tbody>
                        </table>
                        </x-grid.table-shell>
                    </div>

                    <div class="bg-[#fff5f5] border border-[#fca5a5] rounded-xl p-4.5 md:col-span-2">
                        <div class="text-sm font-bold text-[#991b1b] mb-2">{{ t('Resignation') }}</div>
                        <div class="text-xs text-muted mb-3">{{ t('Official resignation procedure according to the employment contract — your superior & HR will contact you for the handover process.') }}</div>
                        <button type="button" wire:click="openResign" class="text-xs font-semibold bg-white border border-[#fca5a5] text-[#991b1b] px-3.5 py-2 rounded-md cursor-pointer">{{ t('Submit Resignation Form') }}</button>
                    </div>
                </div>
            @endif

            {{-- ===================== TAB: DESKRIPSI KERJA (read-only) =====================
                 Melekat ke posisi ($emp->position->job_description), diisi HR Officer di
                 layar Master Posisi -- keputusan user 2026-09-13. --}}
            @if ($tab === 'jobdesc')
                <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5">
                    <div class="flex items-center justify-between mb-3 pb-2 border-b border-[#f1f5f9]">
                        <div class="text-sm font-bold text-[#0f172a]">{{ t('Job Description') }}</div>
                        <div class="text-[11px] text-muted">{{ $emp?->position?->position_name ?? '—' }}</div>
                    </div>
                    @if ($this->myJobDescription)
                        <div class="text-[13px] text-[#334] leading-relaxed whitespace-pre-line">{{ $this->myJobDescription }}</div>
                    @else
                        <div class="text-xs text-muted italic">{{ t('No job description for this position yet. Contact the HR Officer if needed.') }}</div>
                    @endif
                    <div class="text-[10.5px] text-muted mt-3 pt-2 border-t border-[#f1f5f9]">{{ t('Only the HR Officer can change this description — it is read-only here.') }}</div>
                </div>
            @endif

            {{-- ===================== TAB: SOP (read-only) =====================
                 Dokumen SOP global + yang berlaku utk departemen pegawai ini
                 (App\Models\Master\SopDocument), diisi HR Officer di hrm/sop-documents.
                 Isi lengkap dibuka lewat modal, bukan langsung di daftar (bisa panjang). --}}
            @if ($tab === 'sop')
                <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5">
                    <div class="flex items-center justify-between mb-3 pb-2 border-b border-[#f1f5f9]">
                        <div class="text-sm font-bold text-[#0f172a]">{{ t('SOPs That Apply to Me') }}</div>
                        <div class="text-[11px] text-muted">{{ $this->mySopDocuments->count() }} {{ t('documents') }}</div>
                    </div>
                    <div class="flex flex-col gap-2">
                        @forelse ($this->mySopDocuments as $doc)
                            <button type="button" wire:click="openSop({{ $doc->sop_id }})"
                                class="text-left flex items-center gap-3 px-3.5 py-2.5 rounded-lg border border-[#e2e8f0] hover:border-accent hover:bg-[#f8fafc] cursor-pointer">
                                <span class="text-lg">📘</span>
                                <span class="flex-1 min-w-0">
                                    <span class="block text-[13px] font-semibold text-[#0f172a] truncate">{{ $doc->title }}</span>
                                    <span class="block text-[11px] text-muted font-mono">{{ $doc->sop_code }} · {{ t('Rev') }} {{ $doc->revision }} · {{ $doc->department_id ? $doc->department?->department_name : t('All Departments') }}</span>
                                </span>
                                <span class="text-xs text-accent font-semibold shrink-0">{{ t('Read') }} →</span>
                            </button>
                        @empty
                            <div class="text-xs text-muted italic">{{ t('No SOP documents apply to your department yet.') }}</div>
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
                                    <span class="text-[10.5px] font-mono opacity-75 shrink-0">{{ $viewedSop->sop_code }} · {{ t('Rev') }} {{ $viewedSop->revision }}</span>
                                </div>
                                <div class="p-5 flex flex-col gap-3">
                                    @if ($viewedSop->effective_date)
                                        <div class="text-[11px] text-muted">{{ t('Effective since:') }} <b class="text-[#233]">{{ $viewedSop->effective_date->format('d F Y') }}</b></div>
                                    @endif
                                    @if ($viewedSop->purpose)
                                        <div>
                                            <div class="text-xs font-bold text-navy uppercase tracking-wide mb-1">{{ t('Purpose') }}</div>
                                            <div class="text-[13px] text-[#334] leading-relaxed whitespace-pre-line">{{ $viewedSop->purpose }}</div>
                                        </div>
                                    @endif
                                    <div>
                                        <div class="text-xs font-bold text-navy uppercase tracking-wide mb-1">{{ t('SOP Content') }}</div>
                                        <div class="text-[13px] text-[#334] leading-relaxed whitespace-pre-line">{{ $viewedSop->content ?: '—' }}</div>
                                    </div>
                                </div>
                                <div class="px-4 py-3 border-t border-[#eef1f5] flex justify-end">
                                    <button type="button" wire:click="closeSop" class="px-4 py-2 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">{{ t('Close') }}</button>
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
                            <div class="text-sm font-bold text-[#0f172a]">{{ t('Registered Family Members') }}</div>
                            <div class="flex gap-2">
                                <button type="button" wire:click="openFamilyReq('LAHIR')" class="text-[11px] font-semibold bg-accent text-white px-3 py-1.5 rounded-md cursor-pointer">+ {{ t('Report a Birth') }}</button>
                                <button type="button" wire:click="openFamilyReq('MENINGGAL')" class="text-[11px] font-semibold bg-[#f1f5f9] text-[#334] px-3 py-1.5 rounded-md cursor-pointer">{{ t('Mark as Deceased') }}</button>
                            </div>
                        </div>
                        <x-grid.table-shell>
                        <table class="w-full text-[12.5px]">
                            <thead><tr class="text-left text-[11px] uppercase text-muted"><th class="pb-2">{{ t('Relation') }}</th><th class="pb-2">{{ t('Name') }}</th><th class="pb-2">{{ t('Date of Birth') }}</th><th class="pb-2">{{ t('Status') }}</th></tr></thead>
                            <tbody>
                                @forelse ($emp->families as $f)
                                    <tr class="border-t border-[#f1f5f9]">
                                        <td class="py-2 font-semibold">{{ $f->relation }}</td>
                                        <td class="py-2">{{ $f->full_name }}</td>
                                        <td class="py-2">{{ $f->birth_date?->format('d M Y') ?: '—' }}</td>
                                        <td class="py-2">
                                            @if ($f->is_deceased)
                                                <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-[#f1f5f9] text-muted line-through">{{ t('Deceased') }} — {{ $f->deceased_date?->format('d M Y') }}</span>
                                            @else
                                                <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-[#dcfce7] text-[#166534]">{{ t('Dependent') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <x-grid.empty-row :colspan="4" :message="t('No family data yet.')" />
                                @endforelse
                            </tbody>
                        </table>
                        </x-grid.table-shell>
                        <div class="text-xs text-[#334155] bg-[#f0f9ff] border-l-4 border-accent px-3.5 py-2.5 rounded-r-md mt-3">{{ t('Deceased family members are') }} <b>{{ t('not deleted') }}</b> {{ t('from the history — to keep past allowance & claim records accurate.') }}</div>
                    </div>

                    <div class="grid two gap-4">
                        <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5">
                            <div class="text-sm font-bold text-[#0f172a] mb-3 pb-2 border-b border-[#f1f5f9]">{{ t('Marital Status') }}</div>
                            <div class="flex gap-2.5 items-center">
                                <div class="w-[130px] shrink-0 text-muted text-xs">{{ t('Current Status') }}</div>
                                <div class="flex-1 font-medium">—</div>
                                <button type="button" wire:click="openFamilyReq('PASANGAN')" class="text-[11px] font-semibold text-accent bg-[#eff6ff] border border-[#bfdbfe] px-2 py-1 rounded cursor-pointer">{{ t('Request Change') }}</button>
                            </div>
                        </div>
                        <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5">
                            <div class="text-sm font-bold text-[#0f172a] mb-3 pb-2 border-b border-[#f1f5f9]">{{ t('Payroll Bank Account') }}</div>
                            <div class="flex gap-2.5 items-center">
                                <div class="w-[130px] shrink-0 text-muted text-xs">{{ t('Bank / Account No.') }}</div>
                                <div class="flex-1 font-medium">{{ $emp->bankAccounts->firstWhere('is_primary', true)?->bank_name }} {{ $emp->bankAccounts->firstWhere('is_primary', true)?->account_no }}</div>
                                <button type="button" wire:click="openCorrection('Rekening Bank Payroll', @js($emp->bankAccounts->firstWhere('is_primary', true)?->account_no))" class="text-[11px] font-semibold text-accent bg-[#eff6ff] border border-[#bfdbfe] px-2 py-1 rounded cursor-pointer">{{ t('Request Replacement') }}</button>
                            </div>
                        </div>
                        <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5 md:col-span-2">
                            <div class="text-sm font-bold text-[#0f172a] mb-3 pb-2 border-b border-[#f1f5f9]">{{ t('Emergency Contact') }}</div>
                            <div class="flex gap-2.5 items-center">
                                <div class="w-[130px] shrink-0 text-muted text-xs">{{ t('Name & Relationship') }}</div>
                                <div class="flex-1 font-medium">{{ $emp->emergency_contact_name ?: '—' }} @if ($emp->emergency_contact_relation) ({{ $emp->emergency_contact_relation }}) @endif</div>
                                <button type="button" wire:click="openCorrection('Kontak Darurat', @js($emp->emergency_contact_name))" class="text-[11px] font-semibold text-accent bg-[#eff6ff] border border-[#bfdbfe] px-2 py-1 rounded cursor-pointer">{{ t('Edit Contact') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- ===================== TAB: DOKUMEN & PENDIDIKAN ===================== --}}
            @if ($tab === 'dokumen')
                <div class="grid two gap-4">
                    <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5">
                        <div class="text-sm font-bold text-[#0f172a] mb-3 pb-2 border-b border-[#f1f5f9]">{{ t('Personal Identity Documents') }}</div>
                        @foreach (['KTP' => t('Electronic ID Card (KTP)'), 'NPWP' => t('NPWP'), 'SIM' => t('Driving License C (SIM C)'), 'KK' => t('Family Card'), 'PASSPORT' => t('Passport'), 'SKCK' => t('SKCK')] as $type => $label)
                            @php $doc = $emp->documents->firstWhere('document_type', $type); @endphp
                            <div class="flex items-center gap-3 py-2.5 border-b border-[#f8fafc] last:border-b-0">
                                <div class="w-9 h-9 rounded-md bg-[#f1f5f9] flex items-center justify-center text-base shrink-0">📄</div>
                                <div class="flex-1">
                                    <div class="text-[13px] font-semibold">{{ $label }}</div>
                                    <div class="text-[11px] text-muted">
                                        @if (! $doc) <span class="text-[#991b1b]">{{ t('No file uploaded yet') }}</span>
                                        @else {{ $doc->document_no ?: t('Uploaded') }} @if ($doc->uploaded_by_role === 'HR') <span class="text-[10px] font-bold bg-[#f1f5f9] text-muted px-1.5 py-0.5 rounded ml-1">{{ t('Locked by HR') }}</span> @endif
                                        @endif
                                    </div>
                                </div>
                                @if (! $doc || $doc->uploaded_by_role === 'SELF')
                                    <button type="button" wire:click="openDocModal('{{ $type }}')" class="text-[11px] font-semibold {{ $doc ? 'bg-[#f1f5f9] text-[#334]' : 'bg-accent text-white' }} px-2.5 py-1.5 rounded cursor-pointer shrink-0">{{ $doc ? t('Replace') : t('Upload') }}</button>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5 md:col-span-2">
                        <div class="flex items-center justify-between mb-3 pb-2 border-b border-[#f1f5f9]">
                            <div class="text-sm font-bold text-[#0f172a]">{{ t('Education & Development History') }}</div>
                            <button type="button" wire:click="openEduModal" class="text-[11px] font-semibold bg-accent text-white px-3 py-1.5 rounded-md cursor-pointer">+ {{ t('Add History') }}</button>
                        </div>
                        <x-grid.table-shell>
                        <table class="w-full text-[12.5px]">
                            <thead><tr class="text-left text-[11px] uppercase text-muted"><th class="pb-2">{{ t('Activity') }}</th><th class="pb-2">{{ t('Date') }}</th><th class="num pb-2">{{ t('Expense') }}</th><th class="pb-2">{{ t('Funded By') }}</th></tr></thead>
                            <tbody>
                                @forelse ($emp->educations as $ed)
                                    <tr class="border-t border-[#f1f5f9]">
                                        <td class="py-2">{{ $ed->activity_name }}</td>
                                        <td class="py-2">{{ $ed->activity_date->format('d M Y') }}</td>
                                        <td class="num py-2">Rp {{ number_format((float) $ed->cost, 0, ',', '.') }}</td>
                                        <td class="py-2">{{ $ed->funded_by }}</td>
                                    </tr>
                                @empty
                                    <x-grid.empty-row :colspan="4" :message="t('No history yet.')" />
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
                    <div class="text-sm font-bold text-[#0f172a] mb-3 pb-2 border-b border-[#f1f5f9]">{{ t('Annual Leave Balance') }} — {{ now()->year }}</div>
                    <div class="grid three gap-2.5">
                        <div class="bg-[#f8fafc] border border-[#e2e8f0] rounded-lg p-3 text-center"><div class="text-lg font-bold text-[#0f172a]">{{ $this->currentYearBalance?->entitlement_days }}</div><div class="text-[10px] text-muted uppercase font-bold">{{ t('Leave Entitlement') }}</div></div>
                        <div class="bg-[#f8fafc] border border-[#e2e8f0] rounded-lg p-3 text-center"><div class="text-lg font-bold text-[#92400e]">{{ $this->currentYearBalance?->used_days }}</div><div class="text-[10px] text-muted uppercase font-bold">{{ t('Used') }}</div></div>
                        <div class="bg-[#f8fafc] border border-[#e2e8f0] rounded-lg p-3 text-center"><div class="text-lg font-bold text-[#166534]">{{ $this->currentYearBalance?->remainingDays() }}</div><div class="text-[10px] text-muted uppercase font-bold">{{ t('Remaining Balance') }}</div></div>
                    </div>
                </div>

                <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5">
                    <div class="text-sm font-bold text-[#0f172a] mb-3 pb-2 border-b border-[#f1f5f9]">{{ t('Absence Request Form') }}</div>

                    <div class="mb-3">
                        <x-form.select :label="t('Request Type')" wire:model.live="leave_type" :placeholder="false" :options="[
                            'CUTI' => t('Annual Leave (Paid)'),
                            'SAKIT' => t('Sick'),
                            'IZIN' => t('Permission (Non-Sick)').($this->izinLocked ? ' — '.str_replace(':n', $this->currentYearBalance?->remainingDays(), t('Locked, :n days of leave left')) : ''),
                            'CUTI_KHUSUS' => t('Special Leave (Wedding, Bereavement, etc.)'),
                        ]" />
                    </div>

                    @if ($leave_type === 'CUTI')
                        <div class="text-xs text-[#334155] bg-[#f0f9ff] border-l-4 border-accent px-3.5 py-2.5 rounded-r-md mb-3">{{ t('Must be requested at least') }} <b>{{ t('D+7') }}</b> {{ t('from today. Maximum') }} <b>{{ t('3 working days') }}</b> {{ t('in a row per request. Sundays do not count as leave.') }}</div>
                    @elseif ($leave_type === 'SAKIT')
                        <div class="text-xs text-[#78350f] bg-[#fffbeb] border-l-4 border-[#f59e0b] px-3.5 py-2.5 rounded-r-md mb-3">{{ t('Must report to your superior & HRD no later than') }} <b>07:30 WIB</b> {{ t('on the day of absence.') }}</div>
                    @elseif ($leave_type === 'IZIN')
                        <div class="text-xs text-[#991b1b] bg-[#fef2f2] border-l-4 border-[#ef4444] px-3.5 py-2.5 rounded-r-md mb-3">{{ t('Permission is') }} <b>{{ t('Unpaid') }}</b> (no present no pay). {{ t('Maximum 3 consecutive days & 3 requests per month — beyond that is automatically Alpha.') }}</div>
                    @endif

                    <div class="grid two gap-2.5 mb-3">
                        <x-form.date-picker :label="t('From Date')" wire:model="leave_date_from" name="leave_date_from" />
                        <x-form.date-picker :label="t('To Date')" wire:model="leave_date_to" name="leave_date_to" />
                    </div>

                    @if ($leave_type === 'CUTI')
                        <div class="mb-3">
                            <x-form.file-dropzone :label="t('Leave Request Letter (required)')" wire:model="att_surat_cuti" />
                            @error('att_surat_cuti') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    @if ($leave_type === 'SAKIT')
                        <div class="grid two gap-2.5 mb-3">
                            <div>
                                <x-form.input :label="t('Time Reported to HRD')" type="time" wire:model.live="sakit_lapor_jam" />
                                @if ($this->sakitLaporTerlambat)
                                    <div class="text-[11px] text-red-600 font-semibold mt-1">⚠ {{ t('Past the 07:30 WIB limit — will be reviewed by HR.') }}</div>
                                @endif
                            </div>
                            <div class="flex items-end pb-2">
                                <x-form.checkbox wire:model.live="sakit_via_jkn" :label="t('JKN participant (history screenshot is enough)')" />
                            </div>
                        </div>
                        @if ($sakit_via_jkn)
                            <div class="mb-3">
                                <x-form.file-dropzone :label="t('JKN App History Screenshot')" wire:model="att_jkn_screenshot" />
                                @error('att_jkn_screenshot') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                            </div>
                        @else
                            <div class="grid two gap-2.5 mb-3">
                                <div>
                                    <x-form.file-dropzone :label="t('Doctor\'s Certificate')" wire:model="att_surat_dokter" />
                                </div>
                                <div>
                                    <x-form.file-dropzone :label="t('Receipt / Prescription')" wire:model="att_kwitansi_resep" />
                                </div>
                            </div>
                            @error('att_surat_dokter') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        @endif
                    @endif

                    @if ($leave_type === 'IZIN' && ! $this->izinLocked)
                        <div class="mb-3">
                            <x-form.select :label="t('Permission Nature')" wire:model.live="izin_sifat" :placeholder="false"
                                :options="['terjadwal' => t('Scheduled (min. D-7)'), 'mendadak' => t('Urgent (emergency/calamity)')]" />
                        </div>
                        @if ($izin_sifat === 'mendadak')
                            <div class="mb-3">
                                <x-form.select :label="t('Urgent Need Category')" wire:model.live="izin_category" :placeholder="false" :options="[
                                    'MUSIBAH' => t('Family Calamity / Accident / Disaster'),
                                    'INSTANSI_RESMI' => t('Official Government Agency Matter'),
                                    'KEPENGURUSAN_MUSIBAH' => t('Bereavement / Calamity Arrangements'),
                                ]" />
                            </div>
                            @if ($izin_category === 'INSTANSI_RESMI')
                                <div class="grid two gap-2.5 mb-3">
                                    <x-form.select :label="t('For')" wire:model="izin_relasi_pemohon" :placeholder="false" :options="[
                                        'DIRI_SENDIRI' => t('Myself'),
                                        'SUAMI_ISTRI' => t('Husband/Wife'),
                                        'ANAK_KANDUNG' => t('Biological Child'),
                                        'ORANG_TUA_KANDUNG' => t('Biological Parent'),
                                        'MERTUA' => t('Parent-in-law'),
                                    ]" />
                                    <x-form.select :label="t('Destination Agency')" wire:model="izin_instansi_asal" :placeholder="false" :options="[
                                        'BPJS' => t('BPJS'),
                                        'RUMAH_SAKIT' => t('Hospital'),
                                        'SEKOLAH' => t('School'),
                                        'KEPOLISIAN' => t('Police'),
                                        'INSTANSI_PEMERINTAH_LAINNYA' => t('Other Government Agency'),
                                    ]" />
                                </div>
                            @endif
                            <div class="mb-3">
                                <x-form.file-dropzone :label="t('Proof Attachment / Agency Letter')" wire:model="att_generic" />
                                @error('att_generic') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                            </div>
                        @endif
                    @endif

                    @if ($leave_type === 'CUTI_KHUSUS')
                        <div class="mb-3">
                            <x-form.select :label="t('Special Event Type')" wire:model="khusus_event_type" :placeholder="false" :options="[
                                'MENIKAH' => t('Employee\'s Wedding'),
                                'MENIKAHKAN_KHITAN_ANAK' => t('Child\'s Wedding / Circumcision'),
                                'ISTRI_MELAHIRKAN' => t('Wife Giving Birth / Miscarriage'),
                                'KELUARGA_MENINGGAL' => t('Death of an Immediate Family Member'),
                                'IBADAH' => t('Religious Worship'),
                            ]" />
                        </div>
                        <div class="mb-3">
                            <x-form.file-dropzone :label="t('Supporting Proof Attachment')" wire:model="att_generic" />
                            @error('att_generic') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    <div class="mb-3">
                        <x-form.textarea :label="t('Description / Reason')" wire:model="leave_reason" rows="2" />
                    </div>

                    <button type="button" wire:click="submitLeave" class="w-full bg-accent text-white text-xs font-semibold px-4 py-2.5 rounded-md cursor-pointer">{{ t('Send Request') }}</button>
                </div>

                <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5 mt-4">
                    <div class="text-sm font-bold text-[#0f172a] mb-3 pb-2 border-b border-[#f1f5f9]">{{ t('My Request History') }}</div>
                    <x-grid.table-shell>
                    <table class="w-full text-[12.5px]">
                        <thead><tr class="text-left text-[11px] uppercase text-muted"><th class="pb-2">{{ t('Type') }}</th><th class="pb-2">{{ t('Date') }}</th><th class="pb-2">{{ t('Pay Status') }}</th><th class="pb-2">{{ t('Approval') }}</th></tr></thead>
                        <tbody>
                            @forelse ($this->myLeaveRequests as $lr)
                                <tr class="border-t border-[#f1f5f9]">
                                    <td class="py-2 font-semibold">{{ $lr->leave_type }}</td>
                                    <td class="py-2">{{ $lr->date_from->format('d M') }}–{{ $lr->date_to->format('d M Y') }}</td>
                                    <td class="py-2"><span class="text-[10px] font-bold px-2 py-0.5 rounded {{ $lr->is_paid ? 'bg-[#dcfce7] text-[#166534]' : 'bg-[#fee2e2] text-[#991b1b]' }}">{{ $lr->is_paid ? t('Paid') : t('Unpaid') }}</span></td>
                                    <td class="py-2"><span class="text-[10px] font-bold px-2 py-0.5 rounded {{ match ($lr->status) { 'APPROVED' => 'bg-[#dcfce7] text-[#166534]', 'REJECTED' => 'bg-[#fee2e2] text-[#991b1b]', default => 'bg-[#fef3c7] text-[#92400e]' } }}">{{ $lr->status }}</span></td>
                                </tr>
                            @empty
                                <x-grid.empty-row :colspan="4" :message="t('No requests yet.')" />
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
                        <div class="text-sm font-bold text-[#0f172a] mb-3 pb-2 border-b border-[#f1f5f9]">{{ t('Work Area Exit Permit Form') }}</div>
                        <div class="text-xs text-[#78350f] bg-[#fffbeb] border-l-4 border-[#f59e0b] px-3.5 py-2.5 rounded-r-md mb-3">{{ t('Must be approved by') }} <b>{{ t('the Direct Superior AND HRD') }}</b> {{ t('before leaving the factory area.') }}</div>
                        <div class="mb-3"><x-form.date-picker :label="t('Date')" wire:model="op_permit_date" name="op_permit_date" /></div>
                        <div class="grid two gap-2.5 mb-3">
                            <x-form.input :label="t('Exit Time')" type="time" wire:model="op_out_time" name="op_out_time" />
                            <x-form.input :label="t('Return Time')" type="time" wire:model="op_return_time" />
                        </div>
                        <div class="mb-3"><x-form.textarea :label="t('Purpose')" wire:model="op_reason" rows="3" name="op_reason" /></div>
                        <button type="button" wire:click="submitOutPermit" class="w-full bg-accent text-white text-xs font-semibold px-4 py-2.5 rounded-md cursor-pointer">{{ t('Send Exit Permit Letter') }}</button>
                    </div>
                    <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5">
                        <div class="text-sm font-bold text-[#0f172a] mb-3 pb-2 border-b border-[#f1f5f9]">{{ t('Exit Permit History') }}</div>
                        <x-grid.table-shell>
                        <table class="w-full text-[12.5px]">
                            <thead><tr class="text-left text-[11px] uppercase text-muted"><th class="pb-2">{{ t('Date') }}</th><th class="pb-2">{{ t('Time') }}</th><th class="pb-2">{{ t('Status') }}</th></tr></thead>
                            <tbody>
                                @forelse ($this->myOutPermits as $op)
                                    <tr class="border-t border-[#f1f5f9]">
                                        <td class="py-2">{{ $op->permit_date->format('d M Y') }}</td>
                                        <td class="py-2">{{ \Illuminate\Support\Carbon::parse($op->planned_out_time)->format('H:i') }}</td>
                                        <td class="py-2"><span class="text-[10px] font-bold px-2 py-0.5 rounded {{ match ($op->status) { 'APPROVED' => 'bg-[#dcfce7] text-[#166534]', 'REJECTED' => 'bg-[#fee2e2] text-[#991b1b]', default => 'bg-[#fef3c7] text-[#92400e]' } }}">{{ $op->status }}</span></td>
                                    </tr>
                                @empty
                                    <x-grid.empty-row :colspan="3" :message="t('No requests yet.')" />
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
                        <div class="text-sm font-bold text-[#0f172a] mb-3 pb-2 border-b border-[#f1f5f9]">{{ t('Shift Swap Form') }}</div>
                        <div class="text-xs text-[#78350f] bg-[#fffbeb] border-l-4 border-[#f59e0b] px-3.5 py-2.5 rounded-r-md mb-3">{{ t('Request deadline is at most') }} <b>{{ t('D-1') }}</b>. {{ t('Must be approved by the swap partner, then PPIC.') }}</div>
                        <div class="mb-3">
                            <x-form.select :label="t('My Schedule to Swap')" wire:model="swap_requester_roster_id" name="swap_requester_roster_id"
                                :options="$this->myUpcomingRoster->mapWithKeys(fn ($r) => [$r->roster_id => $r->roster_date->translatedFormat('l, d M').' — '.$r->shift->shift_name])->all()" />
                        </div>
                        <div class="mb-3">
                            <x-form.select :label="t('Replacement Colleague')" wire:model.live="swap_target_employee_id"
                                :options="$this->swapColleagues->pluck('full_name', 'employee_id')->all()" />
                        </div>
                        @if ($swap_target_employee_id)
                            <div class="mb-3">
                                <x-form.select :label="t('Colleague\'s Schedule (Swap Target)')" wire:model="swap_target_roster_id" name="swap_target_roster_id"
                                    :options="$this->targetRosterOptions->mapWithKeys(fn ($r) => [$r->roster_id => $r->roster_date->translatedFormat('l, d M').' — '.$r->shift->shift_name])->all()" />
                            </div>
                        @endif
                        <div class="mb-3"><x-form.textarea :label="t('Reason')" wire:model="swap_reason" rows="2" /></div>
                        <button type="button" wire:click="submitSwap" class="w-full bg-accent text-white text-xs font-semibold px-4 py-2.5 rounded-md cursor-pointer">{{ t('Submit Swap Request') }}</button>
                    </div>
                    <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5">
                        <div class="text-sm font-bold text-[#0f172a] mb-3 pb-2 border-b border-[#f1f5f9]">{{ t('Shift Swap History') }}</div>
                        <x-grid.table-shell>
                        <table class="w-full text-[12.5px]">
                            <thead><tr class="text-left text-[11px] uppercase text-muted"><th class="pb-2">{{ t('My Date') }}</th><th class="pb-2">{{ t('Swapped With') }}</th><th class="pb-2">{{ t('Colleague') }}</th><th class="pb-2">{{ t('PPIC') }}</th></tr></thead>
                            <tbody>
                                @forelse ($this->mySwapRequests as $s)
                                    <tr class="border-t border-[#f1f5f9]">
                                        <td class="py-2">{{ $s->requesterRoster?->roster_date?->format('d M') }}</td>
                                        <td class="py-2">{{ $s->targetEmployee?->full_name }}</td>
                                        <td class="py-2"><span class="text-[10px] font-bold px-2 py-0.5 rounded {{ $s->partner_status === 'APPROVED' ? 'bg-[#dcfce7] text-[#166534]' : 'bg-[#fef3c7] text-[#92400e]' }}">{{ $s->partner_status }}</span></td>
                                        <td class="py-2"><span class="text-[10px] font-bold px-2 py-0.5 rounded {{ $s->ppic_status === 'APPROVED' ? 'bg-[#dcfce7] text-[#166534]' : 'bg-[#fef3c7] text-[#92400e]' }}">{{ $s->ppic_status }}</span></td>
                                    </tr>
                                @empty
                                    <x-grid.empty-row :colspan="4" :message="t('No requests yet.')" />
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
                        <div class="text-sm font-bold text-[#0f172a]">{{ t('Official Work Schedule (Published by PPIC/HR)') }}</div>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-[#dcfce7] text-[#166534]">{{ t('Confirmed Only') }}</span>
                    </div>
                    @forelse ($this->mySchedule as $s)
                        <div class="flex items-center gap-3 py-2.5 border-b border-[#f8fafc] last:border-b-0 text-[12.5px]">
                            <div class="w-24 font-semibold text-[#0f172a]">{{ $s->roster_date->translatedFormat('D, d M') }}</div>
                            <span class="text-[11px] font-semibold px-2.5 py-1 rounded bg-[#fef3c7] text-[#92400e]">{{ $s->shift?->shift_name }}</span>
                            @if ($s->is_overtime)
                                <span class="text-[10px] font-bold text-[#991b1b]">+ {{ t('Overtime') }}</span>
                            @endif
                            <div class="flex-1"></div>
                            @if ($s->machine)
                                <div class="text-[11px] text-muted">{{ t('Station:') }} {{ $s->machine->machine_name }}</div>
                            @endif
                        </div>
                    @empty
                        <div class="text-center py-6 text-muted text-xs">{{ t('No published schedule yet.') }}</div>
                    @endforelse
                </div>
            @endif

            {{-- ===================== TAB: KARTU PENGENAL ===================== --}}
            @if ($tab === 'badge')
                <div class="grid two gap-4">
                    <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5">
                        <div class="text-sm font-bold text-[#0f172a] mb-3 pb-2 border-b border-[#f1f5f9]">{{ t('Digital Employee ID Card') }}</div>
                        <div class="w-full max-w-[330px] h-[200px] bg-gradient-to-br from-[#1e293b] to-[#0f172a] rounded-2xl mx-auto overflow-hidden shadow-lg">
                            <div class="h-12 bg-white/5 px-4 flex items-center justify-between border-b border-white/10">
                                <div class="text-xs font-bold text-[#38bdf8]">CV TELASIH MAKMUR</div>
                                <div class="text-[10px] text-white/60">TMERP DIGITAL ID</div>
                            </div>
                            <div class="flex gap-3.5 p-4">
                                <div class="w-[70px] h-[90px] bg-white/10 rounded-lg flex items-center justify-center text-[10px] text-white/50">{{ t('Passport Photo') }}</div>
                                <div>
                                    <div class="text-[15px] font-bold text-white">{{ $emp->full_name }}</div>
                                    <div class="text-[11px] text-white/70 mt-0.5">{{ $emp->position?->position_name }}</div>
                                    <div class="text-xs font-bold text-[#38bdf8] mt-2.5 font-mono tracking-wide">{{ $emp->employee_code }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5">
                        <div class="text-sm font-bold text-[#0f172a] mb-3 pb-2 border-b border-[#f1f5f9]">{{ t('Request ID Photo Update') }}</div>
                        <div class="text-xs text-[#78350f] bg-[#fffbeb] border-l-4 border-[#f59e0b] px-3.5 py-2.5 rounded-r-md mb-3">
                            {{ t('Format 4×6cm (2:3 ratio), at least 600×900px, plain light background, face looking forward.') }}
                        </div>
                        <button type="button" wire:click="openBadgeModal" class="w-full bg-accent text-white text-xs font-semibold px-4 py-2.5 rounded-md cursor-pointer">{{ t('Upload New ID Photo') }}</button>
                    </div>
                </div>
            @endif

            {{-- ===================== TAB: SECURITY ===================== --}}
            @if ($tab === 'security')
                <div class="text-xs text-[#334155] bg-[#f0f9ff] border-l-4 border-accent px-3.5 py-2.5 rounded-r-md mb-4">
                    {{ t('Login account data (not HR data) — can be changed directly without HR approval.') }}
                </div>
                <div class="grid two gap-4">
                    <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5">
                        <div class="text-sm font-bold text-[#0f172a] mb-3 pb-2 border-b border-[#f1f5f9]">{{ t('Account profile') }}</div>
                        @if ($accSaved)
                            <div class="text-xs text-[#166534] bg-[#f0fdf4] border-l-4 border-[#16a34a] px-3.5 py-2.5 rounded-r-md mb-3">✅ {{ t('Account profile saved.') }}</div>
                        @endif
                        <div class="space-y-3">
                            <x-form.input :label="t('Full name')" wire:model="acc_name" name="acc_name" />
                            <x-form.input :label="t('Email')" type="email" wire:model="acc_email" name="acc_email" />
                            <x-form.input :label="t('Phone no.')" wire:model="acc_phone" />
                        </div>
                        <button type="button" wire:click="saveAccountProfile" class="w-full bg-accent text-white text-xs font-semibold px-4 py-2.5 rounded-md cursor-pointer mt-3">{{ t('Save') }}</button>
                    </div>
                    <div class="bg-white border border-[#e2e8f0] rounded-xl p-4.5">
                        <div class="text-sm font-bold text-[#0f172a] mb-3 pb-2 border-b border-[#f1f5f9]">{{ t('Change password') }}</div>
                        @if ($accPasswordSaved)
                            <div class="text-xs text-[#166534] bg-[#f0fdf4] border-l-4 border-[#16a34a] px-3.5 py-2.5 rounded-r-md mb-3">✅ {{ t('Password updated.') }}</div>
                        @endif
                        @if ($accPasswordError)
                            <div class="text-xs text-[#991b1b] bg-[#fef2f2] border-l-4 border-[#dc2626] px-3.5 py-2.5 rounded-r-md mb-3">{{ $accPasswordError }}</div>
                        @endif
                        <div class="space-y-3">
                            <x-form.input :label="t('Old password')" type="password" wire:model="acc_old_password" :placeholder="t('Your current password')" />
                            <x-form.input :label="t('New password')" type="password" wire:model="acc_new_password" :placeholder="t('Minimum 8 characters')" name="acc_new_password" />
                            <x-form.input :label="t('Confirm new password')" type="password" wire:model="acc_new_password_confirmation" :placeholder="t('Repeat the new password')" />
                        </div>
                        <button type="button" wire:click="changeAccountPassword" class="w-full bg-accent text-white text-xs font-semibold px-4 py-2.5 rounded-md cursor-pointer mt-3">{{ t('Update password') }}</button>
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- ══════════ MODALS ══════════ --}}

    @if ($showCorrectionModal)
        <div class="fixed inset-0 bg-[rgba(15,23,42,.5)] z-[100] flex items-center justify-center p-5" wire:click.self="$set('showCorrectionModal', false)">
            <div class="bg-white rounded-xl w-[420px] max-w-[92vw] p-5">
                <div class="text-base font-bold text-[#0f172a] mb-1">{{ t('Request Data Correction') }}</div>
                <div class="text-xs text-muted mb-3">{{ t('Field:') }} <b>{{ $corr_field_name }}</b></div>
                <form wire:submit="saveCorrection" class="flex flex-col gap-3">
                    <x-form.input :label="t('Current Value')" value="{{ $corr_old_value }}" disabled class="bg-[#f1f5f9]" />
                    @if ($corr_field_name === \App\Models\Master\EmpCorrectionRequest::FIELD_BANK)
                        <x-form.select :label="t('New Bank')" name="corr_bank_supplier_id" wire:model="corr_bank_supplier_id" :options="$this->bankOptions()"
                            :selected="$corr_bank_supplier_id" :placeholder="t('— select bank —')" />
                        <x-form.input :label="t('New Account No.')" wire:model="corr_bank_account_no" name="corr_bank_account_no" :case-toggle="false" />
                        <x-form.input :label="t('Account Holder')" wire:model="corr_bank_holder" name="corr_bank_holder" />
                    @else
                        <x-form.input :label="t('New Value')" wire:model="corr_new_value" name="corr_new_value" />
                    @endif
                    <x-form.textarea :label="t('Reason')" wire:model="corr_reason" rows="2" />
                    <div class="flex gap-2 mt-1"><button type="button" wire:click="$set('showCorrectionModal', false)" class="flex-1 px-4 py-2 rounded-md text-xs font-semibold bg-[#f1f5f9] text-[#334] cursor-pointer">{{ t('Cancel') }}</button><button type="submit" class="flex-1 px-4 py-2 rounded-md text-xs font-semibold bg-accent text-white cursor-pointer">{{ t('Submit Request') }}</button></div>
                </form>
            </div>
        </div>
    @endif

    @if ($showFamilyReqModal)
        <div class="fixed inset-0 bg-[rgba(15,23,42,.5)] z-[100] flex items-center justify-center p-5" wire:click.self="$set('showFamilyReqModal', false)">
            <div class="bg-white rounded-xl w-[420px] max-w-[92vw] p-5">
                <div class="text-base font-bold text-[#0f172a] mb-3">{{ match ($famreq_type) { 'LAHIR' => t('Report a New Child\'s Birth'), 'MENINGGAL' => t('Mark a Family Member as Deceased'), default => t('Request Spouse Status Change') } }}</div>
                <form wire:submit="saveFamilyReq" class="flex flex-col gap-3">
                    @if ($famreq_type === 'MENINGGAL')
                        <x-form.select :label="t('Select Family Member')" wire:model="famreq_family_id" name="famreq_family_id"
                            :options="$this->employee->families->where('is_deceased', false)->mapWithKeys(fn ($f) => [$f->family_id => $f->full_name.' ('.$f->relation.')'])->all()" />
                        <x-form.date-picker :label="t('Date of Death')" wire:model="famreq_deceased_date" name="famreq_deceased_date" />
                        <x-form.file-dropzone :label="t('Upload Death Certificate')" wire:model="famreq_attachment" />
                    @else
                        <x-form.select :label="t('Relation')" wire:model="famreq_relation" :placeholder="false"
                            :options="$famreq_type === 'PASANGAN' ? ['SUAMI' => t('Husband'), 'ISTRI' => t('Wife')] : ['ANAK' => t('Child')]" />
                        <x-form.input :label="t('Full Name')" wire:model="famreq_full_name" name="famreq_full_name" />
                        <x-form.date-picker :label="t('Date of Birth')" wire:model="famreq_birth_date" />
                        <x-form.file-dropzone :label="t('Upload Birth Certificate / Marriage Book')" wire:model="famreq_attachment" />
                    @endif
                    <div class="flex gap-2 mt-1"><button type="button" wire:click="$set('showFamilyReqModal', false)" class="flex-1 px-4 py-2 rounded-md text-xs font-semibold bg-[#f1f5f9] text-[#334] cursor-pointer">{{ t('Cancel') }}</button><button type="submit" class="flex-1 px-4 py-2 rounded-md text-xs font-semibold bg-accent text-white cursor-pointer">{{ t('Submit Request') }}</button></div>
                </form>
            </div>
        </div>
    @endif

    @if ($showResignModal)
        <div class="fixed inset-0 bg-[rgba(15,23,42,.5)] z-[100] flex items-center justify-center p-5" wire:click.self="$set('showResignModal', false)">
            <div class="bg-white rounded-xl w-[420px] max-w-[92vw] p-5">
                <div class="text-base font-bold text-[#991b1b] mb-3">{{ t('Resignation Request') }}</div>
                <form wire:submit="saveResign" class="flex flex-col gap-3">
                    <x-form.date-picker :label="t('Effective Date')" wire:model="resign_effective_date" name="resign_effective_date" />
                    <x-form.textarea :label="t('Reason')" wire:model="resign_reason" rows="3" name="resign_reason" />
                    <div class="flex gap-2 mt-1"><button type="button" wire:click="$set('showResignModal', false)" class="flex-1 px-4 py-2 rounded-md text-xs font-semibold bg-[#f1f5f9] text-[#334] cursor-pointer">{{ t('Cancel') }}</button><button type="submit" class="flex-1 px-4 py-2 rounded-md text-xs font-semibold bg-[#991b1b] text-white cursor-pointer">{{ t('Submit Request') }}</button></div>
                </form>
            </div>
        </div>
    @endif

    @if ($showDocModal)
        <div class="fixed inset-0 bg-[rgba(15,23,42,.5)] z-[100] flex items-center justify-center p-5" wire:click.self="$set('showDocModal', false)">
            <div class="bg-white rounded-xl w-[420px] max-w-[92vw] p-5">
                <div class="text-base font-bold text-[#0f172a] mb-3">{{ t('Upload Document') }} — {{ $doc_type }}</div>
                <form wire:submit="saveDoc" class="flex flex-col gap-3">
                    <x-form.input :label="t('Document Number')" wire:model="doc_document_no" />
                    <x-form.date-picker :label="t('Valid Until (if any)')" wire:model="doc_expiry_date" />
                    <div>
                        <x-form.file-dropzone :label="t('File (JPG/PNG/PDF, max 5MB)')" wire:model="doc_file" />
                        @error('doc_file') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex gap-2 mt-1"><button type="button" wire:click="$set('showDocModal', false)" class="flex-1 px-4 py-2 rounded-md text-xs font-semibold bg-[#f1f5f9] text-[#334] cursor-pointer">{{ t('Cancel') }}</button><button type="submit" class="flex-1 px-4 py-2 rounded-md text-xs font-semibold bg-accent text-white cursor-pointer">{{ t('Save & Upload') }}</button></div>
                </form>
            </div>
        </div>
    @endif

    @if ($showEduModal)
        <div class="fixed inset-0 bg-[rgba(15,23,42,.5)] z-[100] flex items-center justify-center p-5" wire:click.self="$set('showEduModal', false)">
            <div class="bg-white rounded-xl w-[420px] max-w-[92vw] p-5">
                <div class="text-base font-bold text-[#0f172a] mb-3">{{ t('Add Training History') }}</div>
                <form wire:submit="saveEdu" class="flex flex-col gap-3">
                    <x-form.input :label="t('Training / Certification Name')" wire:model="edu_activity_name" name="edu_activity_name" />
                    <x-form.input :label="t('Organizer')" wire:model="edu_organizer" />
                    <div class="grid two gap-2.5">
                        <x-form.date-picker :label="t('Date')" wire:model="edu_activity_date" name="edu_activity_date" />
                        <x-form.input :label="t('Cost (Rp)')" type="number" wire:model="edu_cost" />
                    </div>
                    <x-form.select :label="t('Paid By')" wire:model="edu_funded_by" :placeholder="false"
                        :options="['PRIBADI' => t('Personal'), 'PERUSAHAAN' => t('Company'), 'SHARING' => t('Sharing')]" />
                    <div class="flex gap-2 mt-1"><button type="button" wire:click="$set('showEduModal', false)" class="flex-1 px-4 py-2 rounded-md text-xs font-semibold bg-[#f1f5f9] text-[#334] cursor-pointer">{{ t('Cancel') }}</button><button type="submit" class="flex-1 px-4 py-2 rounded-md text-xs font-semibold bg-accent text-white cursor-pointer">{{ t('Save') }}</button></div>
                </form>
            </div>
        </div>
    @endif

    @if ($showBadgeModal)
        <div class="fixed inset-0 bg-[rgba(15,23,42,.5)] z-[100] flex items-center justify-center p-5" wire:click.self="$set('showBadgeModal', false)">
            <div class="bg-white rounded-xl w-[420px] max-w-[92vw] p-5">
                <div class="text-base font-bold text-[#0f172a] mb-1">{{ t('Upload New ID Card Photo') }}</div>
                <div class="text-xs text-muted mb-3">{{ t('The photo will be reviewed by HRD before it is reprinted.') }}</div>
                <form wire:submit="saveBadgePhoto" class="flex flex-col gap-3">
                    <div>
                        <x-form.file-dropzone :label="t('Choose Photo File (min. 600×900px, 2:3 ratio)')" wire:model="badge_photo" />
                        @error('badge_photo') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex gap-2 mt-1"><button type="button" wire:click="$set('showBadgeModal', false)" class="flex-1 px-4 py-2 rounded-md text-xs font-semibold bg-[#f1f5f9] text-[#334] cursor-pointer">{{ t('Cancel') }}</button><button type="submit" class="flex-1 px-4 py-2 rounded-md text-xs font-semibold bg-accent text-white cursor-pointer">{{ t('Send Photo') }}</button></div>
                </form>
            </div>
        </div>
    @endif
</div>
