<?php

use App\Models\Master\EmpContract;
use App\Models\Master\EmpDisciplinary;
use App\Models\Master\EmpEducation;
use App\Models\Master\EmpEmployee;
use App\Models\Master\EmpFamily;
use App\Models\Master\EmpShift;
use App\Models\Master\OrgCompany;
use App\Models\Master\OrgPosition;
use App\Models\Master\ProdMachine;
use App\Models\Sys\SecUser;
use App\Services\Hrm\FamilyEmploymentCrossCheckService;
use App\Livewire\Concerns\HasGridStatFilter;
use App\Services\Migration\EmployeeLoginProvisionService;
use App\Support\Localization\Translator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

// mockup_master_employee.html. masteremployee_tier1.md §10: sisi HR Admin FULL-EDITABLE
// (tanpa workflow approval) — beda dari self-service karyawan yang selalu
// lewat pengajuan. §7: gaji TETAP/HARIAN & posisi salary_hidden_from_hr=1
// disembunyikan dari non-admin ("HR Officer"); is_admin dipakai sbg proxy
// role "Admin App" krn RBAC granular belum digarap (Non-Goals §12).
// Tab "Historical Audit Trail" mockup TIDAK direplikasi — tak ada tabel audit
// generik di DDL (TIER_3A_HRM.sql), log kedisiplinan sudah representatif di
// tab Kedisiplinan.
new #[Title('Master Employee — TELASIH MAKMUR ERP')] class extends Component
{
    use HasGridStatFilter;
    use WithFileUploads;

    public string $panel = 'grid'; // grid | form

    public string $formTab = 'overview';

    public ?int $editingId = null;

    // ---- foto profil (HR Admin full-editable, beda dari badge photo self-service
    // yang lewat review EmpBadgePhotoRequest) ----
    public $photo = null; // upload sementara (Livewire temp file)

    public bool $showPhotoModal = false;

    public array $genders = ['L' => 'Laki-laki', 'P' => 'Perempuan'];

    public array $religions = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu', 'Lainnya'];

    public array $workStatuses = ['WORKING' => 'Bekerja', 'DIRUMAHKAN' => 'Dirumahkan', 'CUTI' => 'Cuti'];

    public array $educationTypes = ['SERTIFIKASI', 'SEMINAR', 'PELATIHAN', 'PENDIDIKAN_FORMAL'];

    public array $fundedByOptions = ['PERUSAHAAN', 'PRIBADI', 'SHARING'];

    public array $familyRelations = ['SUAMI', 'ISTRI', 'ANAK', 'AYAH', 'IBU', 'MERTUA_AYAH', 'MERTUA_IBU'];

    // AYAH/IBU/MERTUA_AYAH/MERTUA_IBU wajib ada (keputusan user 2026-08-27) --
    // dicek di showDirectory() saat $justRegistered, BUKAN retroaktif ke data lama.
    private const MANDATORY_FAMILY_RELATIONS = [
        'AYAH' => 'Ayah', 'IBU' => 'Ibu', 'MERTUA_AYAH' => 'Mertua Ayah', 'MERTUA_IBU' => 'Mertua Ibu',
    ];

    private const MAX_CHILDREN = 20;

    public array $employmentTypes = ['TETAP', 'KONTRAK', 'HARIAN', 'BORONGAN'];

    public array $disciplinaryTypes = ['TEGURAN_VERBAL', 'SP1', 'SP2', 'SP3', 'ALPHA'];

    // ---- grid & filter ----
    public string $search = '';

    // Card View (docs/griduistandard.md §11, adopsi CardView DataTables.net
    // versi ringan -- keputusan user 2026-08-30). HANYA Employee, karena ini
    // satu-satunya master dgn foto asli (bukan avatar inisial generik) --
    // jangan roll out ke grid lain tanpa ditanya. Default List (konsisten
    // grid lain), Card opsional. Interaksi klik tetap 2x sama seperti List
    // (griduistandard.md §4) -- tidak ada pengecualian perilaku klik utk Card.
    public string $employeeView = 'list'; // list | card

    public function setEmployeeView(string $view): void
    {
        $this->employeeView = in_array($view, ['list', 'card'], true) ? $view : 'list';
    }

    // ---- form fields (Overview) ----
    public string $employee_code = '';

    public string $full_name = '';

    public string $nickname = '';

    public string $gender = 'L';

    public string $birth_place = '';

    public string $birth_date = '';

    public string $nik_ktp = '';

    public string $no_kk = '';

    public string $npwp_number = '';

    public string $religion = '';

    public string $phone = '';

    public string $email_personal = '';

    public string $address_detail_ktp = '';

    public string $address_detail_domisili = '';

    public string $join_date = '';

    public string $work_status = 'WORKING';

    public string $work_status_since = '';

    public string $emergency_contact_name = '';

    public string $emergency_contact_relation = '';

    public string $emergency_contact_phone = '';

    public bool $emp_activate = true;

    // true hanya sepanjang sesi Livewire yang BARU SAJA membuat employee ini
    // (bukan derived dari DB) -- dipakai showDirectory() utk memaksa lengkapi
    // 8 field wajib + 4 relasi keluarga wajib sebelum boleh kembali ke grid.
    // Tidak retroaktif ke 136 karyawan lama.
    public bool $justRegistered = false;

    // Kartu login sekali-tampil (keputusan user 2026-08-27: "diangkat jadi
    // employee" = login auto dibuat, dicetak IT/HR utk diserahkan ke
    // kandidat -- bukan lewat "Tambah Pengguna" manual yg khusus akun
    // non-employee spt customer/supplier). Session-only, tak disimpan.
    public ?array $newLoginCredentials = null;

    public bool $showCredentialModal = false;

    // ---- form fields (Job Alignment) ----
    public ?int $position_id = null;

    public ?int $default_machine_id = null;

    public ?int $default_shift_id = null;

    // ---- mini modal: education ----
    public bool $showEducationModal = false;

    public string $edu_education_type = 'PELATIHAN';

    public string $edu_activity_name = '';

    public string $edu_organizer = '';

    public string $edu_activity_date = '';

    public string $edu_cost = '0';

    public string $edu_funded_by = 'PERUSAHAAN';

    public bool $edu_is_bonding = false;

    // ---- mini modal: family ----
    public bool $showFamilyModal = false;

    public string $fam_relation = 'ANAK';

    public string $fam_full_name = '';

    public string $fam_birth_date = '';

    public string $fam_nik = '';

    // ---- mini modal: contract ----
    public bool $showContractModal = false;

    public string $ctr_employment_type = 'TETAP';

    public string $ctr_start_date = '';

    public string $ctr_end_date = '';

    public string $ctr_salary_type = 'PER_MONTH';

    public string $ctr_salary_amount = '0';

    public bool $ctr_is_probation = false;

    public string $ctr_probation_end_date = '';

    // ---- mini modal: disciplinary ----
    public bool $showDisciplinaryModal = false;

    public string $dis_type = 'TEGURAN_VERBAL';

    public string $dis_incident_date = '';

    public string $dis_reason = '';

    // Kotak statistik = filter data -- mekanismenya di trait HasGridStatFilter
    // (griduistandard.md §2b), layar cuma mendeklarasikan definisinya.
    protected function statFilterDefs(): array
    {
        $m = $this->metrics;

        return [
            'active' => ['label' => Translator::t('hrm_emp_stat_active', 'Employee Aktif'), 'value' => $m['active'], 'tone' => 'active',
                'filter' => fn ($q) => $q->where('activate', true)],
            'inactive' => ['label' => Translator::t('hrm_emp_stat_inactive', 'Employee Non Aktif'), 'value' => $m['inactive'], 'tone' => 'critical',
                'filter' => fn ($q) => $q->where('activate', false)],
            // Mingguan/bulanan = frekuensi gaji karyawan AKTIF (yang non aktif tak ikut payroll).
            'weekly' => ['label' => Translator::t('hrm_emp_stat_weekly', 'Employee Mingguan'), 'value' => $m['weekly'], 'tone' => 'warn',
                'filter' => fn ($q) => $q->where('activate', true)->where('payment_frequency', 'WEEKLY')],
            'monthly' => ['label' => Translator::t('hrm_emp_stat_monthly', 'Employee Bulanan'), 'value' => $m['monthly'], 'tone' => 'total',
                'filter' => fn ($q) => $q->where('activate', true)->where('payment_frequency', 'MONTHLY')],
        ];
    }

    private function searchQuery()
    {
        return EmpEmployee::query()
            ->when($this->search !== '', fn ($q) => $q->where(fn ($qq) => $qq
                ->where('full_name', 'like', "%{$this->search}%")
                ->orWhere('employee_code', 'like', "%{$this->search}%")
                ->orWhere('nik_ktp', 'like', "%{$this->search}%")));
    }

    #[Computed]
    public function employees()
    {
        return $this->applyStatFilter($this->searchQuery()->with(['position.department', 'disciplinaries']))
            ->orderBy('full_name')
            ->get();
    }

    #[Computed]
    public function metrics(): array
    {
        $r = $this->searchQuery()->toBase()->selectRaw(
            "SUM(activate = 1) active, SUM(activate = 0) inactive,
             SUM(activate = 1 AND payment_frequency = 'WEEKLY') weekly,
             SUM(activate = 1 AND payment_frequency = 'MONTHLY') monthly"
        )->first();

        return array_map('intval', (array) $r);
    }

    #[Computed]
    public function positionOptions()
    {
        return OrgPosition::query()->with('department')->where('activate', true)->get()
            ->sortBy(fn ($p) => $p->department?->department_name.$p->position_name);
    }

    #[Computed]
    public function machineOptions()
    {
        return ProdMachine::query()->where('activate', true)->orderBy('machine_code')->get();
    }

    #[Computed]
    public function shiftOptions()
    {
        return EmpShift::query()->where('activate', true)->orderBy('shift_name')->get();
    }

    #[Computed]
    public function currentEmployee(): ?EmpEmployee
    {
        if (! $this->editingId) {
            return null;
        }

        return EmpEmployee::with([
            'position.department', 'defaultMachine', 'defaultShift', 'documents',
            'contracts', 'educations', 'families', 'disciplinaries',
        ])->find($this->editingId);
    }

    public function canSeeSalary(?EmpContract $contract): bool
    {
        if (Auth::user()->is_admin) {
            return true;
        }

        if ($this->currentEmployee?->position?->salary_hidden_from_hr) {
            return false;
        }

        return $contract && in_array($contract->employment_type, ['KONTRAK', 'BORONGAN'], true);
    }

    public function disciplineBadge(EmpEmployee $e): array
    {
        $sp = $e->activeSpType();
        if ($sp) {
            return ['label' => $sp.' '.Translator::t('hrm_emp_sp_active_suffix', 'AKTIF'), 'class' => 'bg-[#fee2e2] text-[#991b1b]'];
        }

        $verbal = $e->verbalWarningCount();
        if ($verbal > 0) {
            return ['label' => Translator::t('hrm_emp_verbal_prefix', 'Verbal').": {$verbal}/6", 'class' => 'bg-[#fef3c7] text-[#b45309]'];
        }

        return ['label' => Translator::t('hrm_emp_clean_no_sp', 'Clean / No SP'), 'class' => 'bg-[#f0fdf4] text-[#166534]'];
    }

    // ============== NAVIGASI GRID <-> FORM ==============
    public function showDirectory(): void
    {
        if ($this->justRegistered && $this->editingId) {
            if ($this->missingMandatoryFamily()) {
                $this->formTab = 'family';

                return;
            }

            if (! $this->newLoginCredentials) {
                $employee = EmpEmployee::find($this->editingId);
                $cred = $employee ? app(EmployeeLoginProvisionService::class)->provisionForEmployee($employee, Auth::user()->login) : null;

                if ($cred) {
                    $this->newLoginCredentials = $cred;
                    $this->showCredentialModal = true;

                    return;
                }
            }
        }

        $this->justRegistered = false;
        $this->newLoginCredentials = null;
        $this->panel = 'grid';
        unset($this->employees, $this->metrics);
    }

    public function closeCredentialModal(): void
    {
        $this->showCredentialModal = false;
        $this->showDirectory();
    }

    public function relationLabel(string $code): string
    {
        return self::MANDATORY_FAMILY_RELATIONS[$code] ?? ucwords(strtolower($code));
    }

    public function missingMandatoryFamily(): array
    {
        $existing = EmpFamily::where('employee_id', $this->editingId)->pluck('relation')->unique();

        return collect(self::MANDATORY_FAMILY_RELATIONS)
            ->reject(fn ($label, $code) => $existing->contains($code))
            ->values()
            ->all();
    }

    public function openNew(): void
    {
        $this->resetErrorBag();
        $this->editingId = null;
        $this->formTab = 'overview';
        $this->photo = null;
        $this->justRegistered = false;
        $this->newLoginCredentials = null;
        $this->showCredentialModal = false;

        $this->reset([
            'employee_code', 'full_name', 'nickname', 'birth_place', 'birth_date',
            'nik_ktp', 'no_kk', 'npwp_number', 'religion', 'phone', 'email_personal',
            'address_detail_ktp', 'address_detail_domisili', 'join_date',
            'work_status_since', 'emergency_contact_name', 'emergency_contact_relation',
            'emergency_contact_phone', 'position_id', 'default_machine_id', 'default_shift_id',
        ]);
        $this->gender = 'L';
        $this->work_status = 'WORKING';
        $this->emp_activate = true;

        $this->panel = 'form';
    }

    public function openEdit(int $id): void
    {
        $this->resetErrorBag();
        $this->editingId = $id;
        $this->formTab = 'overview';
        $this->photo = null;
        $this->justRegistered = false;
        $this->newLoginCredentials = null;
        $this->showCredentialModal = false;

        $e = EmpEmployee::findOrFail($id);
        $this->employee_code = $e->employee_code;
        $this->full_name = $e->full_name;
        $this->nickname = (string) $e->nickname;
        $this->gender = (string) $e->gender;
        $this->birth_place = (string) $e->birth_place;
        $this->birth_date = (string) ($e->birth_date?->format('Y-m-d') ?? '');
        $this->nik_ktp = (string) $e->nik_ktp;
        $this->no_kk = (string) $e->no_kk;
        $this->npwp_number = (string) $e->npwp_number;
        $this->religion = (string) $e->religion;
        $this->phone = (string) $e->phone;
        $this->email_personal = (string) $e->email_personal;
        $this->address_detail_ktp = (string) $e->address_detail_ktp;
        $this->address_detail_domisili = (string) $e->address_detail_domisili;
        $this->join_date = (string) $e->join_date?->format('Y-m-d');
        $this->work_status = $e->work_status;
        $this->work_status_since = (string) ($e->work_status_since?->format('Y-m-d') ?? '');
        $this->emergency_contact_name = (string) $e->emergency_contact_name;
        $this->emergency_contact_relation = (string) $e->emergency_contact_relation;
        $this->emergency_contact_phone = (string) $e->emergency_contact_phone;
        $this->emp_activate = (bool) $e->activate;
        $this->position_id = $e->position_id;
        $this->default_machine_id = $e->default_machine_id;
        $this->default_shift_id = $e->default_shift_id;

        $this->panel = 'form';
    }

    private function nextEmployeeCode(int $companyId): string
    {
        $company = OrgCompany::findOrFail($companyId);

        $maxSeq = EmpEmployee::where('company_id', $companyId)
            ->where('employee_code', 'like', $company->company_code.'-%')
            ->get()
            ->map(fn ($e) => (int) substr($e->employee_code, strlen($company->company_code) + 1))
            ->max() ?? 0;

        return $company->company_code.'-'.str_pad((string) ($maxSeq + 1), 5, '0', STR_PAD_LEFT);
    }

    public function save(): void
    {
        // Field identitas WAJIB (keputusan user 2026-09-12, berlaku SELALU --
        // termasuk retroaktif ke karyawan lama yang datanya belum lengkap,
        // beda dari kebijakan lama 2026-08-27 yang cuma wajib saat registrasi
        // baru): no KTP (16 digit sesuai format NIK), no KK, tanggal lahir
        // (umur pekerja minimal 17 tahun), no HP/WA, alamat tinggal (domisili).
        // Nama & email tetap wajib khusus registrasi baru (kebijakan lama,
        // tidak diubah instruksi ini). Nama ibu kandung tetap dicek terpisah
        // di tab Keluarga (lihat showDirectory()/missingMandatoryFamily()).
        $isNew = ! $this->editingId;
        $reqNew = $isNew ? 'required' : 'nullable';
        $minBirthDate = now()->subYears(17)->format('Y-m-d');

        $data = $this->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'nickname' => ['nullable', 'string', 'max:50'],
            'gender' => ['required', 'in:L,P'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['required', 'date', 'before_or_equal:'.$minBirthDate],
            'nik_ktp' => ['required', 'digits:16'],
            'no_kk' => ['required', 'string', 'max:20'],
            'npwp_number' => ['nullable', 'string', 'max:30'],
            'religion' => ['nullable', 'string', 'in:'.implode(',', $this->religions)],
            'phone' => ['required', 'string', 'max:30'],
            'email_personal' => [$reqNew, 'email', 'max:100'],
            'address_detail_ktp' => ['nullable', 'string', 'max:255'],
            'address_detail_domisili' => ['required', 'string', 'max:255'],
            'join_date' => ['required', 'date'],
            'work_status' => ['required', 'in:WORKING,DIRUMAHKAN,CUTI'],
            'work_status_since' => ['nullable', 'date'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:50'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'emp_activate' => ['boolean'],
            'position_id' => ['required', 'integer', 'exists:tm2mst.org_position,position_id'],
            'default_machine_id' => ['nullable', 'integer', 'exists:tm2mst.prod_machine,machine_id'],
            'default_shift_id' => ['nullable', 'integer', 'exists:tm2mst.emp_shift,shift_id'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ], [
            'nik_ktp.digits' => 'NIK KTP harus tepat 16 digit angka.',
            'birth_date.before_or_equal' => 'Karyawan minimal berusia 17 tahun (tanggal lahir maksimal '.\Carbon\Carbon::parse($minBirthDate)->translatedFormat('d M Y').').',
        ]);

        $payload = [
            'full_name' => $data['full_name'],
            'nickname' => $data['nickname'] ?: null,
            'gender' => $data['gender'],
            'birth_place' => $data['birth_place'] ?: null,
            'birth_date' => $data['birth_date'] ?: null,
            'nik_ktp' => $data['nik_ktp'] ?: null,
            'no_kk' => $data['no_kk'] ?: null,
            'npwp_number' => $data['npwp_number'] ?: null,
            'religion' => $data['religion'] ?: null,
            'phone' => $data['phone'] ?: null,
            'email_personal' => $data['email_personal'] ?: null,
            'address_detail_ktp' => $data['address_detail_ktp'] ?: null,
            'address_detail_domisili' => $data['address_detail_domisili'] ?: null,
            'join_date' => $data['join_date'],
            'work_status' => $data['work_status'],
            'work_status_since' => $data['work_status_since'] ?: null,
            'emergency_contact_name' => $data['emergency_contact_name'] ?: null,
            'emergency_contact_relation' => $data['emergency_contact_relation'] ?: null,
            'emergency_contact_phone' => $data['emergency_contact_phone'] ?: null,
            'activate' => $data['emp_activate'],
            'position_id' => $data['position_id'],
            'default_machine_id' => $data['default_machine_id'] ?: null,
            'default_shift_id' => $data['default_shift_id'] ?: null,
            'updated_by' => Auth::user()->login,
        ];

        if ($this->editingId) {
            if ($this->photo) {
                $payload['photo_path'] = $this->photo->store("hrm/{$this->editingId}/profile", 'public');
            }
            EmpEmployee::whereKey($this->editingId)->update($payload);
        } else {
            $company = OrgCompany::query()->where('activate', true)->firstOrFail();
            $payload['company_id'] = $company->company_id;
            $payload['employee_code'] = $this->nextEmployeeCode($company->company_id);
            $payload['created_by'] = Auth::user()->login;
            $employee = EmpEmployee::create($payload);
            $this->editingId = $employee->employee_id;
            $this->employee_code = $employee->employee_code;
            $this->justRegistered = true;

            if ($this->photo) {
                $employee->update(['photo_path' => $this->photo->store("hrm/{$employee->employee_id}/profile", 'public')]);
            }
        }

        $this->photo = null;
        unset($this->currentEmployee);
        $this->showDirectory();
    }

    public function removePhoto(): void
    {
        if (! $this->editingId) {
            return;
        }

        $emp = EmpEmployee::findOrFail($this->editingId);
        if ($emp->photo_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($emp->photo_path);
            $emp->update(['photo_path' => null, 'updated_by' => Auth::user()->login]);
        }

        unset($this->currentEmployee);
    }

    public function openPhotoModal(): void
    {
        if ($this->currentEmployee?->photo_path) {
            $this->showPhotoModal = true;
        }
    }

    public function closePhotoModal(): void
    {
        $this->showPhotoModal = false;
    }

    public function toggleActive(int $id): void
    {
        $e = EmpEmployee::findOrFail($id);
        $e->activate = ! $e->activate;
        $e->updated_by = Auth::user()->login;
        $e->save();

        unset($this->employees);
    }

    // Componentization grid+form (2026-08-27): avatarInitials()/avatarGradient()
    // dipindah ke App\Support\Grid\Avatar (dipakai langsung sbg static call --
    // grid ini tetap markup manual krn perlu fallback foto profil, tidak
    // dipaksa <x-grid.identity-cell> yang tak dukung foto).

    // ============== SUB-MODAL: PENDIDIKAN ==============
    public function openEducationModal(): void
    {
        $this->reset(['edu_activity_name', 'edu_organizer', 'edu_activity_date']);
        $this->edu_education_type = 'PELATIHAN';
        $this->edu_cost = '0';
        $this->edu_funded_by = 'PERUSAHAAN';
        $this->edu_is_bonding = false;
        $this->showEducationModal = true;
    }

    public function saveEducation(): void
    {
        $data = $this->validate([
            'edu_education_type' => ['required', 'in:'.implode(',', $this->educationTypes)],
            'edu_activity_name' => ['required', 'string', 'max:200'],
            'edu_organizer' => ['nullable', 'string', 'max:150'],
            'edu_activity_date' => ['required', 'date'],
            'edu_cost' => ['required', 'numeric', 'min:0'],
            'edu_funded_by' => ['required', 'in:'.implode(',', $this->fundedByOptions)],
            'edu_is_bonding' => ['boolean'],
        ]);

        EmpEducation::create([
            'employee_id' => $this->editingId,
            'education_type' => $data['edu_education_type'],
            'activity_name' => $data['edu_activity_name'],
            'organizer' => $data['edu_organizer'] ?: null,
            'activity_date' => $data['edu_activity_date'],
            'cost' => $data['edu_cost'],
            'funded_by' => $data['edu_funded_by'],
            'is_bonding' => $data['edu_is_bonding'],
            'uploaded_by_role' => 'HR',
        ]);

        unset($this->currentEmployee);
        $this->showEducationModal = false;
    }

    // ============== SUB-MODAL: KELUARGA ==============
    public function openFamilyModal(): void
    {
        $this->reset(['fam_full_name', 'fam_birth_date', 'fam_nik']);
        $this->fam_relation = 'ANAK';
        $this->showFamilyModal = true;
    }

    public function saveFamily(): void
    {
        $data = $this->validate([
            'fam_relation' => ['required', 'in:'.implode(',', $this->familyRelations)],
            'fam_full_name' => ['required', 'string', 'max:150'],
            'fam_birth_date' => ['nullable', 'date'],
            'fam_nik' => ['nullable', 'string', 'max:20'],
        ]);

        if ($data['fam_relation'] === 'ANAK') {
            $childCount = EmpFamily::where('employee_id', $this->editingId)->where('relation', 'ANAK')->count();

            if ($childCount >= self::MAX_CHILDREN) {
                $this->addError('fam_relation', 'Maksimal '.self::MAX_CHILDREN.' anak per karyawan.');

                return;
            }
        }

        if (in_array($data['fam_relation'], ['AYAH', 'IBU', 'MERTUA_AYAH', 'MERTUA_IBU'], true)) {
            $already = EmpFamily::where('employee_id', $this->editingId)->where('relation', $data['fam_relation'])->exists();

            if ($already) {
                $this->addError('fam_relation', 'Data '.self::MANDATORY_FAMILY_RELATIONS[$data['fam_relation']].' sudah ada.');

                return;
            }
        }

        $family = EmpFamily::create([
            'employee_id' => $this->editingId,
            'relation' => $data['fam_relation'],
            'full_name' => $data['fam_full_name'],
            'birth_date' => $data['fam_birth_date'] ?: null,
            'nik' => $data['fam_nik'] ?: null,
            'activate' => true,
            'created_by' => Auth::user()->login,
            'updated_by' => Auth::user()->login,
        ]);

        app(FamilyEmploymentCrossCheckService::class)->checkFamilyMember($family);

        unset($this->currentEmployee);
        $this->showFamilyModal = false;
    }

    public function markDeceased(int $familyId): void
    {
        $f = EmpFamily::findOrFail($familyId);
        $f->is_deceased = true;
        $f->deceased_date = now()->toDateString();
        $f->updated_by = Auth::user()->login;
        $f->save();

        unset($this->currentEmployee);
    }

    // ============== SUB-MODAL: KONTRAK ==============
    public function openContractModal(): void
    {
        $this->reset(['ctr_end_date', 'ctr_probation_end_date']);
        $this->ctr_employment_type = 'TETAP';
        $this->ctr_start_date = now()->toDateString();
        $this->ctr_salary_type = 'PER_MONTH';
        $this->ctr_salary_amount = '0';
        $this->ctr_is_probation = false;
        $this->showContractModal = true;
    }

    public function saveContract(): void
    {
        $data = $this->validate([
            'ctr_employment_type' => ['required', 'in:'.implode(',', $this->employmentTypes)],
            'ctr_start_date' => ['required', 'date'],
            'ctr_end_date' => ['nullable', 'date', 'after:ctr_start_date'],
            'ctr_salary_type' => ['required', 'in:PER_MONTH,PER_DAY,PER_UNIT'],
            'ctr_salary_amount' => ['required', 'numeric', 'min:0'],
            'ctr_is_probation' => ['boolean'],
            'ctr_probation_end_date' => ['nullable', 'date', 'required_if:ctr_is_probation,true'],
        ]);

        // Hanya 1 kontrak aktif per employee (TIER_3A_HRM.sql §2.2, enforced app-level).
        EmpContract::where('employee_id', $this->editingId)->where('activate', true)->update(['activate' => false]);

        EmpContract::create([
            'employee_id' => $this->editingId,
            'employment_type' => $data['ctr_employment_type'],
            'start_date' => $data['ctr_start_date'],
            'end_date' => $data['ctr_end_date'] ?: null,
            'salary_type' => $data['ctr_salary_type'],
            'salary_amount' => $data['ctr_salary_amount'],
            'is_probation' => $data['ctr_is_probation'],
            'probation_end_date' => $data['ctr_probation_end_date'] ?: null,
            'activate' => true,
            'created_by' => Auth::user()->login,
        ]);

        unset($this->currentEmployee);
        $this->showContractModal = false;
    }

    // ============== SUB-MODAL: KEDISIPLINAN ==============
    public function openDisciplinaryModal(): void
    {
        $this->reset(['dis_reason']);
        $this->dis_type = 'TEGURAN_VERBAL';
        $this->dis_incident_date = now()->toDateString();
        $this->showDisciplinaryModal = true;
    }

    public function saveDisciplinary(): void
    {
        $data = $this->validate([
            'dis_type' => ['required', 'in:'.implode(',', $this->disciplinaryTypes)],
            'dis_incident_date' => ['required', 'date'],
            'dis_reason' => ['required', 'string', 'max:255'],
        ]);

        EmpDisciplinary::create([
            'employee_id' => $this->editingId,
            'type' => $data['dis_type'],
            'incident_date' => $data['dis_incident_date'],
            'reason' => $data['dis_reason'],
            'issued_by' => Auth::user()->login,
        ]);

        unset($this->currentEmployee);
        $this->showDisciplinaryModal = false;
    }
};
?>

<div>
    @if ($panel === 'grid')
        <div class="max-w-[1400px] mx-auto my-5 px-4">
            <x-grid.stat-filter-row :boxes="$this->statBoxes()" :active="$statFilter" />

            <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
                <x-grid.toolbar>
                    <div class="flex items-center gap-1.5 shrink-0">
                        <span class="text-sm">🔍</span>
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ Translator::t('hrm_emp_search_placeholder', 'Cari kode / nama / NIK…') }}"
                            class="w-56 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                    </div>
                    <x-slot:tools>
                        <div class="flex items-center gap-1 pr-2.5 mr-1 border-r border-[#e2e7ee] shrink-0">
                            <button type="button" wire:click="setEmployeeView('list')" title="{{ Translator::t('hrm_emp_view_list_title', 'Tampilan Daftar') }}"
                                class="px-2.5 py-1.5 rounded-md text-[11px] font-semibold cursor-pointer {{ $employeeView === 'list' ? 'bg-[#2c3e69] text-white' : 'bg-white border border-[#d7dbe2]' }}">☰ List</button>
                            <button type="button" wire:click="setEmployeeView('card')" title="{{ Translator::t('hrm_emp_view_card_title', 'Tampilan Kartu') }}"
                                class="px-2.5 py-1.5 rounded-md text-[11px] font-semibold cursor-pointer {{ $employeeView === 'card' ? 'bg-[#2c3e69] text-white' : 'bg-white border border-[#d7dbe2]' }}">▦ Card</button>
                        </div>
                    </x-slot:tools>
                    <x-slot:newRecord>
                        <button type="button" wire:click="openNew"
                            class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
                    </x-slot:newRecord>
                </x-grid.toolbar>

                @if ($employeeView === 'card')
                    <div class="[display:grid] grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 p-3.5">
                        @forelse ($this->employees as $e)
                            @php $badge = $this->disciplineBadge($e); @endphp
                            <div wire:key="emp-card-{{ $e->employee_id }}" wire:dblclick="openEdit({{ $e->employee_id }})"
                                class="cursor-pointer bg-white border border-[#e2e7ee] rounded-xl overflow-hidden hover:shadow-md hover:border-accent transition-shadow"
                                title="{{ Translator::t('hrm_emp_dblclick_detail_hint', 'Klik 2x untuk buka detail') }}">
                                <div class="h-[110px] bg-[#f1f4f9] flex items-center justify-center overflow-hidden">
                                    @if ($e->photo_path)
                                        <img src="{{ asset('storage/'.$e->photo_path) }}" class="w-full h-full object-cover">
                                    @else
                                        <div class="w-16 h-16 rounded-full bg-gradient-to-br {{ \App\Support\Grid\Avatar::gradient($e->employee_id) }} text-white text-lg font-bold flex items-center justify-center">{{ \App\Support\Grid\Avatar::initials($e->full_name) }}</div>
                                    @endif
                                </div>
                                <div class="p-3">
                                    <div class="font-semibold text-[13px] text-[#0f172a] truncate">{{ $e->full_name }}</div>
                                    <div class="text-[11px] text-muted truncate">{{ $e->employee_code }} @if($e->position?->position_name) · {{ $e->position->position_name }} @endif</div>
                                    <div class="text-[11px] text-muted truncate mt-0.5">{{ $e->position?->department?->department_name ?: '—' }}</div>
                                    <div class="flex items-center gap-1.5 flex-wrap mt-2">
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block {{ $e->activate ? 'bg-[#eaf7ee] text-[#1f8a4c]' : 'bg-[#eef0f3] text-[#8a8f98]' }}">{{ Translator::t('hrm_emp_workstatus_'.strtolower($e->work_status), $this->workStatuses[$e->work_status] ?? $e->work_status) }}</span>
                                    </div>
                                    <div class="flex justify-end gap-0.5 mt-2 pt-2 border-t border-[#eef1f5]">
                                        <x-grid.action-icon icon="fa-pen" title="{{ Translator::t('fin_coa_action_edit', 'Edit') }}" action="openEdit({{ $e->employee_id }})" />
                                        <x-grid.action-icon :active="$e->activate" :title="$e->activate ? Translator::t('fin_coa_deactivate', 'Nonaktifkan') : Translator::t('fin_coa_activate', 'Aktifkan')" action="toggleActive({{ $e->employee_id }})"
                                            :confirm="($e->activate ? Translator::t('fin_coa_deactivate', 'Nonaktifkan') : Translator::t('fin_coa_activate', 'Aktifkan')).' employee '.$e->full_name.'?'"
                                            :variant="$e->activate ? 'danger' : 'default'" />
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-span-full text-center py-8 text-muted text-sm">{{ Translator::t('hrm_emp_none', 'Tidak ada.') }}</div>
                        @endforelse
                    </div>
                @else
                <x-grid.table-shell>
                    <table class="w-full border-collapse text-[12.5px]">
                        <thead>
                            <tr>
                                <th class="text-left px-3.5 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Employee</th>
                                <th class="text-left px-3.5 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ Translator::t('hrm_emp_col_department', 'Departemen') }}</th>
                                <th class="text-left px-3.5 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[170px]">{{ Translator::t('hrm_emp_col_discipline', 'Kedisiplinan') }}</th>
                                <th class="text-left px-3.5 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[100px]">{{ Translator::t('hrm_emp_col_work_status', 'Status Kerja') }}</th>
                                <th class="text-right px-3.5 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[90px]">{{ Translator::t('proc_col_action', 'Aksi') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->employees as $e)
                                @php $badge = $this->disciplineBadge($e); @endphp
                                <tr wire:key="emp-{{ $e->employee_id }}" wire:dblclick="openEdit({{ $e->employee_id }})" class="cursor-pointer hover:bg-[#f7faff]" title="{{ Translator::t('hrm_emp_dblclick_detail_hint', 'Klik 2x untuk buka detail') }}">
                                    <td class="px-3.5 py-2 border-b border-[#eef1f5]">
                                        <div class="flex items-center gap-2.5">
                                            @if ($e->photo_path)
                                                <img src="{{ asset('storage/'.$e->photo_path) }}" class="w-8 h-8 rounded-md object-cover shrink-0 border border-[#d9dfe8]">
                                            @else
                                                <div class="w-8 h-8 rounded-md bg-gradient-to-br {{ \App\Support\Grid\Avatar::gradient($e->employee_id) }} text-white text-[11px] font-bold flex items-center justify-center shrink-0">{{ \App\Support\Grid\Avatar::initials($e->full_name) }}</div>
                                            @endif
                                            <div>
                                                <div class="font-semibold text-[#0f172a] text-[13px]">{{ $e->full_name }}</div>
                                                <div class="text-[11px] text-muted">{{ $e->employee_code }} @if($e->position?->position_name) · {{ $e->position->position_name }} @endif</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3.5 py-2 border-b border-[#eef1f5]">{{ $e->position?->department?->department_name ?: '—' }}</td>
                                    <td class="px-3.5 py-2 border-b border-[#eef1f5]">
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                                    </td>
                                    <td class="px-3.5 py-2 border-b border-[#eef1f5]">
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block {{ $e->activate ? 'bg-[#eaf7ee] text-[#1f8a4c]' : 'bg-[#eef0f3] text-[#8a8f98]' }}">{{ Translator::t('hrm_emp_workstatus_'.strtolower($e->work_status), $this->workStatuses[$e->work_status] ?? $e->work_status) }}</span>
                                    </td>
                                    <td class="px-3.5 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                        <x-grid.action-icon icon="fa-pen" title="{{ Translator::t('fin_coa_action_edit', 'Edit') }}" action="openEdit({{ $e->employee_id }})" />
                                        <x-grid.action-icon :active="$e->activate" :title="$e->activate ? Translator::t('fin_coa_deactivate', 'Nonaktifkan') : Translator::t('fin_coa_activate', 'Aktifkan')" action="toggleActive({{ $e->employee_id }})"
                                            :confirm="($e->activate ? Translator::t('fin_coa_deactivate', 'Nonaktifkan') : Translator::t('fin_coa_activate', 'Aktifkan')).' employee '.$e->full_name.'?'"
                                            :variant="$e->activate ? 'danger' : 'default'" />
                                    </td>
                                </tr>
                            @empty
                                <x-grid.empty-row :colspan="5" message="{{ Translator::t('hrm_emp_none', 'Tidak ada.') }}" />
                            @endforelse
                        </tbody>
                    </table>
                </x-grid.table-shell>
                @endif
            </div>
        </div>
    @else
        @php $emp = $this->currentEmployee; @endphp
        <div class="w-full px-5 py-4.5">
            <div class="flex items-center gap-2 flex-wrap bg-[#f8fafc] border border-[#e2e7ee] rounded-lg px-3.5 py-2.5 mb-3.5">
                <button type="button" wire:click="showDirectory" data-dirty-guard class="px-3 py-1.5 rounded-md text-xs font-semibold cursor-pointer bg-white border border-[#c7cfda]">← Kembali</button>
                <span class="text-sm font-bold text-navy ml-1">{{ $editingId ? 'Edit Employee · '.$employee_code : 'Tambah Employee Baru' }}</span>
                <span class="flex-1"></span>
                @if ($emp)
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block {{ $this->disciplineBadge($emp)['class'] }}">{{ $this->disciplineBadge($emp)['label'] }}</span>
                @endif
            </div>

            <div class="flex items-center gap-3.5 bg-white border border-[#d9dfe8] rounded-lg px-3.5 py-3 mb-3.5">
                <div class="relative shrink-0">
                    @if ($emp?->photo_path)
                        <img src="{{ asset('storage/'.$emp->photo_path) }}"
                            wire:dblclick="openPhotoModal" title="Klik 2x untuk lihat foto penuh"
                            class="w-[72px] h-[72px] rounded-lg object-cover border-2 border-accent cursor-pointer hover:opacity-90">
                    @else
                        <div class="w-[72px] h-[72px] rounded-lg bg-gradient-to-br {{ $emp ? \App\Support\Grid\Avatar::gradient($emp->employee_id) : 'from-[#94a3b8] to-[#64748b]' }} text-white text-xl font-bold flex items-center justify-center">
                            {{ $emp ? \App\Support\Grid\Avatar::initials($emp->full_name) : '＋' }}
                        </div>
                    @endif
                </div>
                <div class="flex-1 min-w-[180px]">
                    <div class="text-[15px] font-bold text-navy">{{ $emp?->full_name ?: $full_name ?: 'Employee Baru' }}</div>
                    <div class="text-xs text-muted mt-0.5">{{ $emp?->employee_code ?: '(kode otomatis)' }} @if($emp?->position?->position_name) · {{ $emp->position->position_name }} @endif</div>
                    @if ($emp?->position?->department?->department_name)
                        <div class="text-xs text-muted">{{ $emp->position->department->department_name }}</div>
                    @endif
                </div>
                @if ($editingId)
                    <div class="flex flex-col gap-1.5 shrink-0">
                        <label class="px-3 py-1.5 rounded-md text-[11px] font-semibold cursor-pointer bg-white border border-[#c7cfda] text-center hover:bg-[#f4f6fa]">
                            📷 {{ $emp?->photo_path ? 'Ganti Foto' : 'Unggah Foto' }}
                            <input type="file" wire:model="photo" accept="image/*" class="hidden">
                        </label>
                        @if ($emp?->photo_path)
                            <button type="button" wire:click="removePhoto" wire:confirm="Hapus foto profil?" class="px-3 py-1.5 rounded-md text-[11px] font-semibold cursor-pointer bg-white border border-[#f5c6c6] text-[#c0392b] hover:bg-[#fdecec]">Hapus Foto</button>
                        @endif
                        @error('photo') <span class="text-[10.5px] text-red-600">{{ $message }}</span> @enderror
                        @if ($photo)
                            <span class="text-[10.5px] text-[#2f7a52]">✓ Foto baru dipilih — klik Simpan utk menerapkan.</span>
                        @endif
                    </div>
                @endif
            </div>

            <div class="flex gap-1 border-b border-[#e2e7ee] mb-3.5 flex-wrap">
                @foreach (['overview' => 'Overview & Identitas', 'job' => 'Job Alignment', 'education' => 'Pendidikan & Sertifikasi', 'family' => 'Keluarga', 'career' => 'Karier & Kontrak', 'performance' => 'Kedisiplinan'] as $key => $label)
                    <button type="button" wire:click="$set('formTab', '{{ $key }}')"
                        class="px-3.5 py-2 text-xs font-semibold cursor-pointer border-b-2 {{ $formTab === $key ? 'text-accent border-accent' : 'text-muted border-transparent' }}">{{ $label }}</button>
                @endforeach
            </div>

            <form wire:submit="save">
                {{-- TAB: OVERVIEW --}}
                <div class="{{ $formTab === 'overview' ? '' : 'hidden' }} bg-white border border-[#d9dfe8] rounded-[10px] p-4 flex flex-col gap-3">
                    <div class="text-xs font-bold text-navy uppercase tracking-wide">Identitas</div>
                    <div class="grid gap-2.5">
                        <x-form.input label="Nama Lengkap *" name="full_name" wire:model="full_name" />
                        <x-form.input label="Panggilan" wire:model="nickname" />
                        <x-form.select label="Jenis Kelamin *" name="gender" wire:model="gender" :options="$genders" :selected="$gender" />
                        <x-form.select label="Agama" wire:model="religion" :options="array_combine($religions, $religions)" :selected="$religion" />
                    </div>
                    <div class="grid gap-2.5">
                        <x-form.input label="Kota Lahir" wire:model="birth_place" />
                        <x-form.date-picker label="Tanggal Lahir (sesuai KTP) * — usia minimal 17 tahun" name="birth_date" wire:model="birth_date" />
                        <x-form.input label="NIK KTP *" name="nik_ktp" wire:model="nik_ktp" maxlength="16" inputmode="numeric" hint="Wajib tepat 16 digit angka." />
                        <x-form.input label="No. Kartu Keluarga (KK) *" name="no_kk" wire:model="no_kk" />
                        <x-form.input label="NPWP" wire:model="npwp_number" />
                    </div>
                    <div class="grid two gap-2.5">
                        <x-form.input label="Telepon / WA (No. Smartphone Utama) *" name="phone" wire:model="phone" />
                        <x-form.input label="Email Pribadi" name="email_personal" type="email" wire:model="email_personal" />
                    </div>

                    <div class="text-xs font-bold text-navy uppercase tracking-wide mt-1">Alamat</div>
                    <div class="grid two gap-2.5">
                        <x-form.textarea label="Alamat KTP" name="address_detail_ktp" wire:model="address_detail_ktp" rows="2" />
                        <x-form.textarea label="Alamat Tinggal / Domisili *" name="address_detail_domisili" wire:model="address_detail_domisili" rows="2" />
                    </div>

                    <div class="text-xs font-bold text-navy uppercase tracking-wide mt-1">Status Kerja</div>
                    <div class="grid three gap-2.5">
                        <x-form.date-picker label="Tanggal Bergabung *" name="join_date" wire:model="join_date" />
                        <x-form.select label="Status Kerja *" wire:model="work_status" :options="$workStatuses" :selected="$work_status" :placeholder="false" />
                        <x-form.date-picker label="Berlaku Sejak" wire:model="work_status_since" />
                    </div>

                    <div class="text-xs font-bold text-navy uppercase tracking-wide mt-1">Kontak Darurat</div>
                    <div class="grid three gap-2.5">
                        <x-form.input label="Nama" wire:model="emergency_contact_name" />
                        <x-form.input label="Hubungan" wire:model="emergency_contact_relation" />
                        <x-form.input label="Telepon" wire:model="emergency_contact_phone" />
                    </div>

                    <x-form.checkbox label="Aktif" wire:model="emp_activate" />
                </div>

                {{-- TAB: JOB ALIGNMENT --}}
                <div class="{{ $formTab === 'job' ? '' : 'hidden' }} bg-white border border-[#d9dfe8] rounded-[10px] p-4 flex flex-col gap-3">
                    <div class="text-xs font-bold text-navy uppercase tracking-wide">Struktur Organisasi</div>
                    <div class="grid two gap-2.5">
                        <x-form.select label="Jabatan (Departemen — Posisi) *" name="position_id" wire:model="position_id"
                            :options="$this->positionOptions->mapWithKeys(fn ($p) => [$p->position_id => $p->department?->department_name.' — '.$p->position_name])" :selected="$position_id" />
                    </div>

                    <div class="text-xs font-bold text-navy uppercase tracking-wide mt-1">Default Produksi <span class="text-muted font-normal normal-case">(prefill jadwal, tak mengunci assignment)</span></div>
                    <div class="grid two gap-2.5">
                        <x-form.select label="Mesin Default" wire:model="default_machine_id" placeholder="— tanpa default —"
                            :options="$this->machineOptions->mapWithKeys(fn ($m) => [$m->machine_id => $m->machine_code.' — '.$m->machine_name])" :selected="$default_machine_id" />
                        <x-form.select label="Shift Default" wire:model="default_shift_id" placeholder="— tanpa default —"
                            :options="$this->shiftOptions->pluck('shift_name', 'shift_id')" :selected="$default_shift_id" />
                    </div>

                    @if ($emp)
                        <div class="text-[11px] text-muted mt-1">Ringkasan kedisiplinan ada di tab <b>Kedisiplinan</b>: {{ $emp->verbalWarningCount() }} teguran verbal, status {{ $emp->activeSpType() ?: 'bersih' }}.</div>
                    @endif
                </div>

                {{-- TAB: EDUCATION --}}
                <div class="{{ $formTab === 'education' ? '' : 'hidden' }} bg-white border border-[#d9dfe8] rounded-[10px] p-4">
                    @if (! $editingId)
                        <div class="text-xs text-muted py-6 text-center">Simpan data Overview dahulu sebelum menambah riwayat pendidikan.</div>
                    @else
                        <div class="flex items-center justify-between mb-2.5">
                            <div class="text-xs font-bold text-navy uppercase tracking-wide">Riwayat Pendidikan &amp; Pengembangan</div>
                            <button type="button" wire:click="openEducationModal" class="px-3 py-1.5 rounded-md text-[11px] font-semibold cursor-pointer bg-accent text-white">+ Tambah Riwayat</button>
                        </div>
                        <x-grid.table-shell>
                        <table class="w-full border-collapse text-[12px]">
                            <thead>
                                <tr>
                                    <th class="text-left px-2 py-1.5 text-[10px] text-muted uppercase font-semibold border-b border-[#eef1f5]">Kegiatan</th>
                                    <th class="text-left px-2 py-1.5 text-[10px] text-muted uppercase font-semibold border-b border-[#eef1f5]">Tanggal</th>
                                    <th class="text-left px-2 py-1.5 text-[10px] text-muted uppercase font-semibold border-b border-[#eef1f5]">Biaya</th>
                                    <th class="text-left px-2 py-1.5 text-[10px] text-muted uppercase font-semibold border-b border-[#eef1f5]">Dibiayai</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($emp?->educations ?? [] as $ed)
                                    <tr wire:key="edu-{{ $ed->education_id }}" class="border-b border-[#f0f3f7]">
                                        <td class="px-2 py-1.5">{{ $ed->activity_name }} <span class="text-muted">({{ $ed->education_type }})</span></td>
                                        <td class="px-2 py-1.5">{{ $ed->activity_date->format('d M Y') }}</td>
                                        <td class="px-2 py-1.5">Rp {{ number_format((float) $ed->cost, 0, ',', '.') }}</td>
                                        <td class="px-2 py-1.5">{{ $ed->funded_by }}{{ $ed->is_bonding ? ' · Ikatan Dinas' : '' }}</td>
                                    </tr>
                                @empty
                                    <x-grid.empty-row :colspan="4" message="Belum ada riwayat." />
                                @endforelse
                            </tbody>
                        </table>
                        </x-grid.table-shell>
                    @endif
                </div>

                {{-- TAB: FAMILY --}}
                <div class="{{ $formTab === 'family' ? '' : 'hidden' }} bg-white border border-[#d9dfe8] rounded-[10px] p-4">
                    @if (! $editingId)
                        <div class="text-xs text-muted py-6 text-center">Simpan data Overview dahulu sebelum menambah anggota keluarga.</div>
                    @else
                        @if ($justRegistered)
                            @php $missingFamily = $this->missingMandatoryFamily(); @endphp
                            <div class="text-[11px] font-semibold px-3 py-2 rounded-md mb-2.5 {{ $missingFamily ? 'bg-[#fef3c7] text-[#92400e] border border-[#fde68a]' : 'bg-[#eaf7ee] text-[#1f8a4c] border border-[#bbe6cc]' }}">
                                @if ($missingFamily)
                                    ⚠ Data keluarga wajib belum lengkap: {{ implode(', ', $missingFamily) }}. Lengkapi dulu sebelum keluar dari layar ini.
                                @else
                                    ✓ Data keluarga wajib (Ayah, Ibu, Mertua Ayah, Mertua Ibu) sudah lengkap.
                                @endif
                            </div>
                        @endif
                        <div class="flex items-center justify-between mb-2.5">
                            <div class="text-xs font-bold text-navy uppercase tracking-wide">Anggota Keluarga</div>
                            <button type="button" wire:click="openFamilyModal" class="px-3 py-1.5 rounded-md text-[11px] font-semibold cursor-pointer bg-accent text-white">+ Tambah Anggota</button>
                        </div>
                        <x-grid.table-shell>
                        <table class="w-full border-collapse text-[12px]">
                            <thead>
                                <tr>
                                    <th class="text-left px-2 py-1.5 text-[10px] text-muted uppercase font-semibold border-b border-[#eef1f5]">Hubungan</th>
                                    <th class="text-left px-2 py-1.5 text-[10px] text-muted uppercase font-semibold border-b border-[#eef1f5]">Nama</th>
                                    <th class="text-left px-2 py-1.5 text-[10px] text-muted uppercase font-semibold border-b border-[#eef1f5]">Tanggal Lahir</th>
                                    <th class="text-left px-2 py-1.5 text-[10px] text-muted uppercase font-semibold border-b border-[#eef1f5]">Status</th>
                                    <th class="text-right px-2 py-1.5 text-[10px] text-muted uppercase font-semibold border-b border-[#eef1f5]">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($emp?->families ?? [] as $f)
                                    <tr wire:key="fam-{{ $f->family_id }}" class="border-b border-[#f0f3f7]">
                                        <td class="px-2 py-1.5 font-semibold">{{ $this->relationLabel($f->relation) }}</td>
                                        <td class="px-2 py-1.5">{{ $f->full_name }}</td>
                                        <td class="px-2 py-1.5">{{ $f->birth_date?->format('d M Y') ?: '—' }}</td>
                                        <td class="px-2 py-1.5">
                                            @if ($f->is_deceased)
                                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block bg-[#eef0f3] text-[#8a8f98]">Meninggal — {{ $f->deceased_date?->format('d M Y') }}</span>
                                            @else
                                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block bg-[#eaf7ee] text-[#1f8a4c]">Tanggungan</span>
                                            @endif
                                        </td>
                                        <td class="px-2 py-1.5 text-right">
                                            @unless ($f->is_deceased)
                                                <button type="button" wire:click="markDeceased({{ $f->family_id }})" wire:confirm="Tandai {{ $f->full_name }} meninggal dunia?" class="px-2 py-1 rounded-md text-[10px] font-semibold cursor-pointer bg-[#fdeceb] text-[#c0392b]">Tandai Meninggal</button>
                                            @endunless
                                        </td>
                                    </tr>
                                @empty
                                    <x-grid.empty-row :colspan="5" message="Belum ada data keluarga." />
                                @endforelse
                            </tbody>
                        </table>
                        </x-grid.table-shell>
                    @endif
                </div>

                {{-- TAB: CAREER & CONTRACT --}}
                <div class="{{ $formTab === 'career' ? '' : 'hidden' }} bg-white border border-[#d9dfe8] rounded-[10px] p-4">
                    @if (! $editingId)
                        <div class="text-xs text-muted py-6 text-center">Simpan data Overview dahulu sebelum menambah kontrak.</div>
                    @else
                        <div class="flex items-center justify-between mb-2.5">
                            <div class="text-xs font-bold text-navy uppercase tracking-wide">Riwayat Kontrak Kerja</div>
                            <button type="button" wire:click="openContractModal" class="px-3 py-1.5 rounded-md text-[11px] font-semibold cursor-pointer bg-accent text-white">+ Kontrak Baru</button>
                        </div>
                        <x-grid.table-shell>
                        <table class="w-full border-collapse text-[12px]">
                            <thead>
                                <tr>
                                    <th class="text-left px-2 py-1.5 text-[10px] text-muted uppercase font-semibold border-b border-[#eef1f5]">Tipe</th>
                                    <th class="text-left px-2 py-1.5 text-[10px] text-muted uppercase font-semibold border-b border-[#eef1f5]">Mulai</th>
                                    <th class="text-left px-2 py-1.5 text-[10px] text-muted uppercase font-semibold border-b border-[#eef1f5]">Selesai</th>
                                    <th class="text-left px-2 py-1.5 text-[10px] text-muted uppercase font-semibold border-b border-[#eef1f5]">Gaji</th>
                                    <th class="text-left px-2 py-1.5 text-[10px] text-muted uppercase font-semibold border-b border-[#eef1f5]">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($emp?->contracts ?? [] as $c)
                                    <tr wire:key="ctr-{{ $c->contract_id }}" class="border-b border-[#f0f3f7]">
                                        <td class="px-2 py-1.5 font-semibold">{{ $c->employment_type }}{{ $c->is_probation ? ' (Probation)' : '' }}</td>
                                        <td class="px-2 py-1.5">{{ $c->start_date->format('d M Y') }}</td>
                                        <td class="px-2 py-1.5">{{ $c->end_date?->format('d M Y') ?: '—' }}</td>
                                        <td class="px-2 py-1.5">
                                            @if ($this->canSeeSalary($c))
                                                Rp {{ number_format((float) $c->salary_amount, 0, ',', '.') }} / {{ $c->salary_type }}
                                            @else
                                                <span class="text-muted italic">Disembunyikan</span>
                                            @endif
                                        </td>
                                        <td class="px-2 py-1.5">
                                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block {{ $c->activate ? 'bg-[#eaf7ee] text-[#1f8a4c]' : 'bg-[#eef0f3] text-[#8a8f98]' }}">{{ $c->activate ? 'AKTIF' : 'RIWAYAT' }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <x-grid.empty-row :colspan="5" message="Belum ada kontrak." />
                                @endforelse
                            </tbody>
                        </table>
                        </x-grid.table-shell>
                    @endif
                </div>

                {{-- TAB: PERFORMANCE / SP LOG --}}
                <div class="{{ $formTab === 'performance' ? '' : 'hidden' }} bg-white border border-[#d9dfe8] rounded-[10px] p-4">
                    @if (! $editingId)
                        <div class="text-xs text-muted py-6 text-center">Simpan data Overview dahulu sebelum mencatat kedisiplinan.</div>
                    @else
                        <div class="flex items-center justify-between mb-2.5">
                            <div class="text-xs font-bold text-navy uppercase tracking-wide">Log Kedisiplinan</div>
                            <button type="button" wire:click="openDisciplinaryModal" class="px-3 py-1.5 rounded-md text-[11px] font-semibold cursor-pointer bg-[#fdeceb] text-[#c0392b]">⚠️ Tambah Catatan</button>
                        </div>
                        <x-grid.table-shell>
                        <table class="w-full border-collapse text-[12px]">
                            <thead>
                                <tr>
                                    <th class="text-left px-2 py-1.5 text-[10px] text-muted uppercase font-semibold border-b border-[#eef1f5]">Tanggal</th>
                                    <th class="text-left px-2 py-1.5 text-[10px] text-muted uppercase font-semibold border-b border-[#eef1f5]">Tipe</th>
                                    <th class="text-left px-2 py-1.5 text-[10px] text-muted uppercase font-semibold border-b border-[#eef1f5]">Alasan</th>
                                    <th class="text-left px-2 py-1.5 text-[10px] text-muted uppercase font-semibold border-b border-[#eef1f5]">Penerbit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($emp?->disciplinaries ?? [] as $d)
                                    <tr wire:key="dis-{{ $d->disciplinary_id }}" class="border-b border-[#f0f3f7]">
                                        <td class="px-2 py-1.5">{{ $d->incident_date->format('d M Y') }}</td>
                                        <td class="px-2 py-1.5 font-semibold">{{ $d->type }}</td>
                                        <td class="px-2 py-1.5">{{ $d->reason }}</td>
                                        <td class="px-2 py-1.5">{{ $d->issued_by }}</td>
                                    </tr>
                                @empty
                                    <x-grid.empty-row :colspan="4" message="Belum ada catatan — clean record." />
                                @endforelse
                            </tbody>
                        </table>
                        </x-grid.table-shell>
                    @endif
                </div>

                <div class="flex gap-2 justify-end mt-3.5">
                    <button type="button" wire:click="showDirectory" data-dirty-guard class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">Batal</button>
                    <button type="submit" class="px-4 py-2 rounded-md text-xs font-semibold bg-accent text-white cursor-pointer">Simpan Overview &amp; Job Alignment</button>
                </div>
            </form>
        </div>
    @endif

    {{-- ══ MODAL: PENDIDIKAN ══ --}}
    @if ($showEducationModal)
        <div class="fixed inset-0 bg-[rgba(20,26,40,.45)] z-[100] flex items-center justify-center p-5" wire:click.self="$set('showEducationModal', false)">
            <div class="bg-white rounded-xl w-[480px] max-w-[92vw] shadow-2xl">
                <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold">Tambah Riwayat Pendidikan</div>
                <form wire:submit="saveEducation" class="p-4 flex flex-col gap-3">
                    <x-form.select label="Tipe *" wire:model="edu_education_type" :options="array_combine($educationTypes, $educationTypes)" :placeholder="false" />
                    <x-form.input label="Nama Kegiatan *" name="edu_activity_name" wire:model="edu_activity_name" />
                    <x-form.input label="Penyelenggara" wire:model="edu_organizer" />
                    <div class="flex gap-2.5">
                        <div class="flex-1">
                            <x-form.date-picker label="Tanggal *" name="edu_activity_date" wire:model="edu_activity_date" />
                        </div>
                        <div class="flex-1">
                            <x-form.input label="Biaya (Rp)" type="number" step="1" min="0" wire:model="edu_cost" />
                        </div>
                    </div>
                    <x-form.select label="Dibiayai Oleh *" wire:model="edu_funded_by" :options="array_combine($fundedByOptions, $fundedByOptions)" :placeholder="false" />
                    <x-form.checkbox label="Ikatan Dinas" wire:model="edu_is_bonding" />
                    <div class="flex gap-2 justify-end pt-2 border-t border-[#eef1f5] mt-1">
                        <button type="button" wire:click="$set('showEducationModal', false)" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">Batal</button>
                        <button type="submit" class="px-4 py-2 rounded-md text-xs font-semibold bg-accent text-white cursor-pointer">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ══ MODAL: KELUARGA ══ --}}
    @if ($showFamilyModal)
        <div class="fixed inset-0 bg-[rgba(20,26,40,.45)] z-[100] flex items-center justify-center p-5" wire:click.self="$set('showFamilyModal', false)">
            <div class="bg-white rounded-xl w-[420px] max-w-[92vw] shadow-2xl">
                <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold">Tambah Anggota Keluarga</div>
                <form wire:submit="saveFamily" class="p-4 flex flex-col gap-3">
                    <x-form.select label="Hubungan *" wire:model="fam_relation" :options="collect($familyRelations)->mapWithKeys(fn ($r) => [$r => $this->relationLabel($r)])" :placeholder="false" />
                    <x-form.input label="Nama Lengkap *" name="fam_full_name" wire:model="fam_full_name" />
                    <div class="flex gap-2.5">
                        <div class="flex-1">
                            <x-form.date-picker label="Tanggal Lahir" wire:model="fam_birth_date" />
                        </div>
                        <div class="flex-1">
                            <x-form.input label="NIK" wire:model="fam_nik" />
                        </div>
                    </div>
                    <div class="flex gap-2 justify-end pt-2 border-t border-[#eef1f5] mt-1">
                        <button type="button" wire:click="$set('showFamilyModal', false)" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">Batal</button>
                        <button type="submit" class="px-4 py-2 rounded-md text-xs font-semibold bg-accent text-white cursor-pointer">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ══ MODAL: KARTU LOGIN KARYAWAN BARU (sekali tampil, cetak IT/HR) ══ --}}
    @if ($showCredentialModal && $newLoginCredentials)
        <style>
            @media print {
                body * { visibility: hidden; }
                #credential-print-card, #credential-print-card * { visibility: visible; }
                #credential-print-card { position: fixed; inset: 0; }
            }
        </style>
        <div class="fixed inset-0 bg-[rgba(20,26,40,.6)] z-[100] flex items-center justify-center p-5">
            <div id="credential-print-card" class="bg-white rounded-xl w-[380px] max-w-[92vw] shadow-2xl overflow-hidden">
                <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold">Kartu Login Karyawan Baru</div>
                <div class="p-5 flex flex-col gap-3">
                    <div class="text-center text-xs text-muted">TELASIH MAKMUR ERP — untuk diserahkan ke karyawan</div>
                    <div>
                        <div class="text-[10px] text-muted uppercase font-semibold">Nama</div>
                        <div class="text-sm font-bold text-navy">{{ $newLoginCredentials['name'] }} @if($employee_code)({{ $employee_code }})@endif</div>
                    </div>
                    <div>
                        <div class="text-[10px] text-muted uppercase font-semibold">Username</div>
                        <div class="text-lg font-mono font-bold tracking-wide">{{ $newLoginCredentials['login'] }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] text-muted uppercase font-semibold">Password Awal</div>
                        <div class="text-lg font-mono font-bold tracking-wide">{{ $newLoginCredentials['password'] }}</div>
                    </div>
                    <div class="text-[10px] text-muted border-t border-[#eef1f5] pt-2 print:hidden">
                        Wajib ganti password saat login pertama. Password ini hanya tampil SEKALI — cetak atau catat sekarang sebelum ditutup.
                    </div>
                    <div class="flex gap-2 pt-1 print:hidden">
                        <button type="button" onclick="window.print()" class="flex-1 px-3 py-2 rounded-md text-xs font-semibold bg-accent text-white cursor-pointer">🖨 Cetak</button>
                        <button type="button" wire:click="closeCredentialModal" class="flex-1 px-3 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">Tutup &amp; Selesai</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ══ MODAL: KONTRAK ══ --}}
    @if ($showContractModal)
        <div class="fixed inset-0 bg-[rgba(20,26,40,.45)] z-[100] flex items-center justify-center p-5" wire:click.self="$set('showContractModal', false)">
            <div class="bg-white rounded-xl w-[480px] max-w-[92vw] shadow-2xl">
                <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold">Kontrak Baru <span class="font-normal opacity-80">(kontrak aktif lama otomatis ditutup)</span></div>
                <form wire:submit="saveContract" class="p-4 flex flex-col gap-3">
                    <div class="flex gap-2.5">
                        <div class="flex-1">
                            <x-form.select label="Tipe *" wire:model="ctr_employment_type" :options="array_combine($employmentTypes, $employmentTypes)" :placeholder="false" />
                        </div>
                        <div class="flex-1">
                            <x-form.select label="Jenis Gaji *" wire:model="ctr_salary_type" :placeholder="false"
                                :options="['PER_MONTH' => 'Per Bulan', 'PER_DAY' => 'Per Hari', 'PER_UNIT' => 'Per Unit']" />
                        </div>
                    </div>
                    <div class="flex gap-2.5">
                        <div class="flex-1">
                            <x-form.date-picker label="Mulai *" name="ctr_start_date" wire:model="ctr_start_date" />
                        </div>
                        <div class="flex-1">
                            <x-form.date-picker label="Selesai (kosong = TETAP)" name="ctr_end_date" wire:model="ctr_end_date" />
                        </div>
                    </div>
                    <x-form.input label="Nominal Gaji (Rp) *" type="number" step="1" min="0" wire:model="ctr_salary_amount" />
                    <x-form.checkbox label="Masa Probation" wire:model.live="ctr_is_probation" />
                    @if ($ctr_is_probation)
                        <x-form.date-picker label="Probation Selesai *" name="ctr_probation_end_date" wire:model="ctr_probation_end_date" />
                    @endif
                    <div class="flex gap-2 justify-end pt-2 border-t border-[#eef1f5] mt-1">
                        <button type="button" wire:click="$set('showContractModal', false)" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">Batal</button>
                        <button type="submit" class="px-4 py-2 rounded-md text-xs font-semibold bg-accent text-white cursor-pointer">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ══ MODAL: KEDISIPLINAN ══ --}}
    @if ($showDisciplinaryModal)
        <div class="fixed inset-0 bg-[rgba(20,26,40,.45)] z-[100] flex items-center justify-center p-5" wire:click.self="$set('showDisciplinaryModal', false)">
            <div class="bg-white rounded-xl w-[420px] max-w-[92vw] shadow-2xl">
                <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold">Tambah Catatan Kedisiplinan</div>
                <form wire:submit="saveDisciplinary" class="p-4 flex flex-col gap-3">
                    <x-form.select label="Tipe *" wire:model="dis_type" :options="array_combine($disciplinaryTypes, $disciplinaryTypes)" :placeholder="false" />
                    <x-form.date-picker label="Tanggal Kejadian *" wire:model="dis_incident_date" />
                    <x-form.textarea label="Alasan *" name="dis_reason" wire:model="dis_reason" rows="2" />
                    <div class="flex gap-2 justify-end pt-2 border-t border-[#eef1f5] mt-1">
                        <button type="button" wire:click="$set('showDisciplinaryModal', false)" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">Batal</button>
                        <button type="submit" class="px-4 py-2 rounded-md text-xs font-semibold bg-accent text-white cursor-pointer">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ══ MODAL: FOTO PROFIL DIPERBESAR (klik 2x foto di header form) ══ --}}
    @if ($showPhotoModal && $emp?->photo_path)
        <div class="fixed inset-0 bg-black/75 z-[110] flex items-center justify-center p-5" wire:click.self="closePhotoModal">
            <div class="bg-[#111822] rounded-xl w-[560px] max-w-[92vw] shadow-2xl p-4 flex flex-col items-center relative">
                <button type="button" wire:click="closePhotoModal" class="absolute top-3 right-3.5 text-white/70 hover:text-white text-lg cursor-pointer bg-transparent border-none">✕</button>
                <div class="w-full rounded-lg overflow-hidden bg-black flex items-center justify-center" style="height:420px">
                    <img src="{{ asset('storage/'.$emp->photo_path) }}" class="max-w-full max-h-full object-contain">
                </div>
                <div class="text-center mt-3.5">
                    <div class="text-white font-bold text-base">{{ $emp->full_name }}</div>
                    <div class="text-[#bdc3c7] text-xs mt-0.5">{{ $emp->employee_code }} @if($emp->position?->position_name) · {{ $emp->position->position_name }} @endif</div>
                </div>
            </div>
        </div>
    @endif
</div>
