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
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

// mockup_master_employee.html. TIER_1_HRM.md §10: sisi HR Admin FULL-EDITABLE
// (tanpa workflow approval) — beda dari self-service karyawan yang selalu
// lewat pengajuan. §7: gaji TETAP/HARIAN & posisi salary_hidden_from_hr=1
// disembunyikan dari non-admin ("HR Officer"); is_admin dipakai sbg proxy
// role "Admin App" krn RBAC granular belum digarap (Non-Goals §12).
// Tab "Historical Audit Trail" mockup TIDAK direplikasi — tak ada tabel audit
// generik di DDL (TIER_3A_HRM.sql), log kedisiplinan sudah representatif di
// tab Kedisiplinan.
new #[Title('Master Employee — TELASIH MAKMUR ERP')] class extends Component
{
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

    public array $familyRelations = ['SUAMI', 'ISTRI', 'ANAK', 'ORANG_TUA'];

    public array $employmentTypes = ['TETAP', 'KONTRAK', 'HARIAN', 'BORONGAN'];

    public array $disciplinaryTypes = ['TEGURAN_VERBAL', 'SP1', 'SP2', 'SP3', 'ALPHA'];

    // ---- grid & filter ----
    public string $search = '';

    // ---- form fields (Overview) ----
    public string $employee_code = '';

    public string $full_name = '';

    public string $nickname = '';

    public string $gender = 'L';

    public string $birth_place = '';

    public string $birth_date = '';

    public string $nik_ktp = '';

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

    public bool $emp_is_active = true;

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

    #[Computed]
    public function employees()
    {
        return EmpEmployee::query()
            ->with(['position.department', 'disciplinaries'])
            ->when($this->search !== '', fn ($q) => $q->where(fn ($qq) => $qq
                ->where('full_name', 'like', "%{$this->search}%")
                ->orWhere('employee_code', 'like', "%{$this->search}%")
                ->orWhere('nik_ktp', 'like', "%{$this->search}%")))
            ->orderBy('full_name')
            ->get();
    }

    #[Computed]
    public function metricTotal(): int
    {
        return $this->employees->count();
    }

    #[Computed]
    public function metricClean(): int
    {
        return $this->employees->filter(fn ($e) => $e->verbalWarningCount() === 0 && ! $e->activeSpType())->count();
    }

    #[Computed]
    public function metricVerbal(): int
    {
        return $this->employees->filter(fn ($e) => $e->verbalWarningCount() > 0 && ! $e->activeSpType())->count();
    }

    #[Computed]
    public function metricSp(): int
    {
        return $this->employees->filter(fn ($e) => (bool) $e->activeSpType())->count();
    }

    #[Computed]
    public function positionOptions()
    {
        return OrgPosition::query()->with('department')->where('is_active', true)->get()
            ->sortBy(fn ($p) => $p->department?->department_name.$p->position_name);
    }

    #[Computed]
    public function machineOptions()
    {
        return ProdMachine::query()->where('is_active', true)->orderBy('machine_code')->get();
    }

    #[Computed]
    public function shiftOptions()
    {
        return EmpShift::query()->where('is_active', true)->orderBy('shift_name')->get();
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
            return ['label' => $sp.' AKTIF', 'class' => 'bg-[#fee2e2] text-[#991b1b]'];
        }

        $verbal = $e->verbalWarningCount();
        if ($verbal > 0) {
            return ['label' => "Verbal: {$verbal}/6", 'class' => 'bg-[#fef3c7] text-[#b45309]'];
        }

        return ['label' => 'Clean / No SP', 'class' => 'bg-[#f0fdf4] text-[#166534]'];
    }

    // ============== NAVIGASI GRID <-> FORM ==============
    public function showDirectory(): void
    {
        $this->panel = 'grid';
        unset($this->employees, $this->metricTotal, $this->metricClean, $this->metricVerbal, $this->metricSp);
    }

    public function openNew(): void
    {
        $this->resetErrorBag();
        $this->editingId = null;
        $this->formTab = 'overview';
        $this->photo = null;

        $this->reset([
            'employee_code', 'full_name', 'nickname', 'birth_place', 'birth_date',
            'nik_ktp', 'npwp_number', 'religion', 'phone', 'email_personal',
            'address_detail_ktp', 'address_detail_domisili', 'join_date',
            'work_status_since', 'emergency_contact_name', 'emergency_contact_relation',
            'emergency_contact_phone', 'position_id', 'default_machine_id', 'default_shift_id',
        ]);
        $this->gender = 'L';
        $this->work_status = 'WORKING';
        $this->emp_is_active = true;

        $this->panel = 'form';
    }

    public function openEdit(int $id): void
    {
        $this->resetErrorBag();
        $this->editingId = $id;
        $this->formTab = 'overview';
        $this->photo = null;

        $e = EmpEmployee::findOrFail($id);
        $this->employee_code = $e->employee_code;
        $this->full_name = $e->full_name;
        $this->nickname = (string) $e->nickname;
        $this->gender = (string) $e->gender;
        $this->birth_place = (string) $e->birth_place;
        $this->birth_date = (string) ($e->birth_date?->format('Y-m-d') ?? '');
        $this->nik_ktp = (string) $e->nik_ktp;
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
        $this->emp_is_active = (bool) $e->is_active;
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
        $data = $this->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'nickname' => ['nullable', 'string', 'max:50'],
            'gender' => ['required', 'in:L,P'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date'],
            'nik_ktp' => ['nullable', 'string', 'max:20'],
            'npwp_number' => ['nullable', 'string', 'max:30'],
            'religion' => ['nullable', 'string', 'in:'.implode(',', $this->religions)],
            'phone' => ['nullable', 'string', 'max:30'],
            'email_personal' => ['nullable', 'email', 'max:100'],
            'address_detail_ktp' => ['nullable', 'string', 'max:255'],
            'address_detail_domisili' => ['nullable', 'string', 'max:255'],
            'join_date' => ['required', 'date'],
            'work_status' => ['required', 'in:WORKING,DIRUMAHKAN,CUTI'],
            'work_status_since' => ['nullable', 'date'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:50'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'emp_is_active' => ['boolean'],
            'position_id' => ['required', 'integer', 'exists:tm2mst.org_position,position_id'],
            'default_machine_id' => ['nullable', 'integer', 'exists:tm2mst.prod_machine,machine_id'],
            'default_shift_id' => ['nullable', 'integer', 'exists:tm2mst.emp_shift,shift_id'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ]);

        $payload = [
            'full_name' => $data['full_name'],
            'nickname' => $data['nickname'] ?: null,
            'gender' => $data['gender'],
            'birth_place' => $data['birth_place'] ?: null,
            'birth_date' => $data['birth_date'] ?: null,
            'nik_ktp' => $data['nik_ktp'] ?: null,
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
            'is_active' => $data['emp_is_active'],
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
            $company = OrgCompany::query()->where('is_active', true)->firstOrFail();
            $payload['company_id'] = $company->company_id;
            $payload['employee_code'] = $this->nextEmployeeCode($company->company_id);
            $payload['created_by'] = Auth::user()->login;
            $employee = EmpEmployee::create($payload);
            $this->editingId = $employee->employee_id;
            $this->employee_code = $employee->employee_code;

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
        $e->is_active = ! $e->is_active;
        $e->updated_by = Auth::user()->login;
        $e->save();

        unset($this->employees);
    }

    // GRID_UI_STANDARD.md #3 -- avatar inisial+warna utk kolom identitas.
    public function avatarInitials(string $name): string
    {
        $words = array_values(array_filter(explode(' ', trim($name))));
        if (count($words) >= 2) {
            return mb_strtoupper(mb_substr($words[0], 0, 1).mb_substr($words[1], 0, 1));
        }

        return mb_strtoupper(mb_substr($name, 0, 2));
    }

    private const AVATAR_PALETTE = [
        'from-[#2563eb] to-[#1d4ed8]', 'from-[#10b981] to-[#059669]', 'from-[#ef4444] to-[#dc2626]',
        'from-[#8b5cf6] to-[#6d28d9]', 'from-[#f59e0b] to-[#d97706]', 'from-[#0ea5e9] to-[#0284c7]',
    ];

    public function avatarGradient(int $id): string
    {
        return self::AVATAR_PALETTE[$id % count(self::AVATAR_PALETTE)];
    }

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

        EmpFamily::create([
            'employee_id' => $this->editingId,
            'relation' => $data['fam_relation'],
            'full_name' => $data['fam_full_name'],
            'birth_date' => $data['fam_birth_date'] ?: null,
            'nik' => $data['fam_nik'] ?: null,
            'is_active' => true,
        ]);

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
        EmpContract::where('employee_id', $this->editingId)->where('is_active', true)->update(['is_active' => false]);

        EmpContract::create([
            'employee_id' => $this->editingId,
            'employment_type' => $data['ctr_employment_type'],
            'start_date' => $data['ctr_start_date'],
            'end_date' => $data['ctr_end_date'] ?: null,
            'salary_type' => $data['ctr_salary_type'],
            'salary_amount' => $data['ctr_salary_amount'],
            'is_probation' => $data['ctr_is_probation'],
            'probation_end_date' => $data['ctr_probation_end_date'] ?: null,
            'is_active' => true,
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
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-3.5">
                <div class="rounded-lg p-3.5 shadow-sm bg-[#eaf2ff] border border-[#c8ddfb]">
                    <p class="text-[10.5px] font-bold uppercase text-[#3b5b99]">Total Headcount</p>
                    <p class="text-xl font-extrabold text-[#1d4ed8] mt-0.5">{{ $this->metricTotal }}</p>
                </div>
                <div class="rounded-lg p-3.5 shadow-sm bg-[#e9f9ef] border border-[#c8ecd6]">
                    <p class="text-[10.5px] font-bold uppercase text-[#2f7a52]">Clean / No SP</p>
                    <p class="text-xl font-extrabold text-[#16a34a] mt-0.5">{{ $this->metricClean }}</p>
                </div>
                <div class="rounded-lg p-3.5 shadow-sm bg-[#fef6e7] border border-[#f5e0b3]">
                    <p class="text-[10.5px] font-bold uppercase text-[#8a6d00]">Verbal Warning</p>
                    <p class="text-xl font-extrabold text-[#b45309] mt-0.5">{{ $this->metricVerbal }}</p>
                </div>
                <div class="rounded-lg p-3.5 shadow-sm bg-[#fdecec] border border-[#f5c6c6]">
                    <p class="text-[10.5px] font-bold uppercase text-[#a3453f]">SP Aktif</p>
                    <p class="text-xl font-extrabold text-[#c0392b] mt-0.5">{{ $this->metricSp }}</p>
                </div>
            </div>

            <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
                <div class="flex items-center gap-2 bg-white border-b border-[#e2e7ee] px-2.5 py-2 overflow-x-auto">
                    <div class="flex items-center gap-1.5 shrink-0">
                        <span class="text-sm">🔍</span>
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari kode / nama / NIK…"
                            class="w-56 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                    </div>
                    <span class="flex-1"></span>
                    <button type="button" wire:click="openNew"
                        class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
                </div>

                <div class="max-h-[65vh] overflow-auto">
                    <table class="w-full border-collapse text-[12.5px]">
                        <thead>
                            <tr>
                                <th class="text-left px-3.5 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Employee</th>
                                <th class="text-left px-3.5 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Departemen</th>
                                <th class="text-left px-3.5 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[170px]">Kedisiplinan</th>
                                <th class="text-left px-3.5 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[100px]">Status Kerja</th>
                                <th class="text-right px-3.5 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[90px]">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->employees as $e)
                                @php $badge = $this->disciplineBadge($e); @endphp
                                <tr wire:key="emp-{{ $e->employee_id }}" wire:dblclick="openEdit({{ $e->employee_id }})" class="cursor-pointer hover:bg-[#f7faff]" title="Klik 2x untuk buka detail">
                                    <td class="px-3.5 py-2 border-b border-[#eef1f5]">
                                        <div class="flex items-center gap-2.5">
                                            @if ($e->photo_path)
                                                <img src="{{ asset('storage/'.$e->photo_path) }}" class="w-8 h-8 rounded-md object-cover shrink-0 border border-[#d9dfe8]">
                                            @else
                                                <div class="w-8 h-8 rounded-md bg-gradient-to-br {{ $this->avatarGradient($e->employee_id) }} text-white text-[11px] font-bold flex items-center justify-center shrink-0">{{ $this->avatarInitials($e->full_name) }}</div>
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
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block {{ $e->is_active ? 'bg-[#eaf7ee] text-[#1f8a4c]' : 'bg-[#eef0f3] text-[#8a8f98]' }}">{{ $this->workStatuses[$e->work_status] ?? $e->work_status }}</span>
                                    </td>
                                    <td class="px-3.5 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                        <button type="button" wire:click.stop="openEdit({{ $e->employee_id }})" title="Edit" class="inline-flex items-center justify-center w-7 h-7 rounded-md text-accent hover:bg-[#eef3fb] cursor-pointer">✎</button>
                                        <button type="button" wire:click.stop="toggleActive({{ $e->employee_id }})" wire:confirm="{{ $e->is_active ? 'Nonaktifkan' : 'Aktifkan' }} employee {{ $e->full_name }}?"
                                            title="{{ $e->is_active ? 'Nonaktifkan' : 'Aktifkan' }}" class="inline-flex items-center justify-center w-7 h-7 rounded-md cursor-pointer {{ $e->is_active ? 'text-[#c0392b] hover:bg-[#fdeceb]' : 'text-accent hover:bg-[#eef3fb]' }}">⏻</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center py-8 text-muted text-sm">Tidak ada.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @else
        @php $emp = $this->currentEmployee; @endphp
        <div class="w-full px-5 py-4.5">
            <div class="flex items-center gap-2 flex-wrap bg-[#f8fafc] border border-[#e2e7ee] rounded-lg px-3.5 py-2.5 mb-3.5">
                <button type="button" wire:click="showDirectory" class="px-3 py-1.5 rounded-md text-xs font-semibold cursor-pointer bg-white border border-[#c7cfda]">← Kembali</button>
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
                        <div class="w-[72px] h-[72px] rounded-lg bg-gradient-to-br {{ $emp ? $this->avatarGradient($emp->employee_id) : 'from-[#94a3b8] to-[#64748b]' }} text-white text-xl font-bold flex items-center justify-center">
                            {{ $emp ? $this->avatarInitials($emp->full_name) : '＋' }}
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
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-2.5">
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Nama Lengkap *</label>
                            <input type="text" wire:model="full_name" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                            @error('full_name') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Panggilan</label>
                            <input type="text" wire:model="nickname" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Jenis Kelamin *</label>
                            <select wire:model="gender" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                                <option value="">— pilih —</option>
                                @foreach ($genders as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('gender') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Agama</label>
                            <select wire:model="religion" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                                <option value="">— pilih —</option>
                                @foreach ($religions as $r)
                                    <option value="{{ $r }}">{{ $r }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-2.5">
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Kota Lahir</label>
                            <input type="text" wire:model="birth_place" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Tanggal Lahir</label>
                            <input type="date" wire:model="birth_date" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">NIK KTP</label>
                            <input type="text" wire:model="nik_ktp" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">NPWP</label>
                            <input type="text" wire:model="npwp_number" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Telepon / WA</label>
                            <input type="text" wire:model="phone" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Email Pribadi</label>
                            <input type="email" wire:model="email_personal" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                            @error('email_personal') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="text-xs font-bold text-navy uppercase tracking-wide mt-1">Alamat</div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Alamat KTP</label>
                            <textarea wire:model="address_detail_ktp" rows="2" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]"></textarea>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Alamat Domisili <span class="text-muted font-normal">(kosongkan jika sama dgn KTP)</span></label>
                            <textarea wire:model="address_detail_domisili" rows="2" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]"></textarea>
                        </div>
                    </div>

                    <div class="text-xs font-bold text-navy uppercase tracking-wide mt-1">Status Kerja</div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2.5">
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Tanggal Bergabung *</label>
                            <input type="date" wire:model="join_date" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                            @error('join_date') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Status Kerja *</label>
                            <select wire:model="work_status" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                                @foreach ($workStatuses as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Berlaku Sejak</label>
                            <input type="date" wire:model="work_status_since" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                        </div>
                    </div>

                    <div class="text-xs font-bold text-navy uppercase tracking-wide mt-1">Kontak Darurat</div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2.5">
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Nama</label>
                            <input type="text" wire:model="emergency_contact_name" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Hubungan</label>
                            <input type="text" wire:model="emergency_contact_relation" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Telepon</label>
                            <input type="text" wire:model="emergency_contact_phone" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-[13px] text-[#334] mt-1">
                        <input type="checkbox" wire:model="emp_is_active"> Aktif
                    </label>
                </div>

                {{-- TAB: JOB ALIGNMENT --}}
                <div class="{{ $formTab === 'job' ? '' : 'hidden' }} bg-white border border-[#d9dfe8] rounded-[10px] p-4 flex flex-col gap-3">
                    <div class="text-xs font-bold text-navy uppercase tracking-wide">Struktur Organisasi</div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Jabatan (Departemen — Posisi) *</label>
                            <select wire:model="position_id" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                                <option value="">— pilih —</option>
                                @foreach ($this->positionOptions as $p)
                                    <option value="{{ $p->position_id }}">{{ $p->department?->department_name }} — {{ $p->position_name }}</option>
                                @endforeach
                            </select>
                            @error('position_id') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="text-xs font-bold text-navy uppercase tracking-wide mt-1">Default Produksi <span class="text-muted font-normal normal-case">(prefill jadwal, tak mengunci assignment)</span></div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Mesin Default</label>
                            <select wire:model="default_machine_id" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                                <option value="">— tanpa default —</option>
                                @foreach ($this->machineOptions as $m)
                                    <option value="{{ $m->machine_id }}">{{ $m->machine_code }} — {{ $m->machine_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Shift Default</label>
                            <select wire:model="default_shift_id" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                                <option value="">— tanpa default —</option>
                                @foreach ($this->shiftOptions as $s)
                                    <option value="{{ $s->shift_id }}">{{ $s->shift_name }}</option>
                                @endforeach
                            </select>
                        </div>
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
                                    <tr><td colspan="4" class="text-center py-5 text-muted text-xs">Belum ada riwayat.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    @endif
                </div>

                {{-- TAB: FAMILY --}}
                <div class="{{ $formTab === 'family' ? '' : 'hidden' }} bg-white border border-[#d9dfe8] rounded-[10px] p-4">
                    @if (! $editingId)
                        <div class="text-xs text-muted py-6 text-center">Simpan data Overview dahulu sebelum menambah anggota keluarga.</div>
                    @else
                        <div class="flex items-center justify-between mb-2.5">
                            <div class="text-xs font-bold text-navy uppercase tracking-wide">Anggota Keluarga</div>
                            <button type="button" wire:click="openFamilyModal" class="px-3 py-1.5 rounded-md text-[11px] font-semibold cursor-pointer bg-accent text-white">+ Tambah Anggota</button>
                        </div>
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
                                        <td class="px-2 py-1.5 font-semibold">{{ $f->relation }}</td>
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
                                    <tr><td colspan="5" class="text-center py-5 text-muted text-xs">Belum ada data keluarga.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
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
                                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block {{ $c->is_active ? 'bg-[#eaf7ee] text-[#1f8a4c]' : 'bg-[#eef0f3] text-[#8a8f98]' }}">{{ $c->is_active ? 'AKTIF' : 'RIWAYAT' }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center py-5 text-muted text-xs">Belum ada kontrak.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
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
                                    <tr><td colspan="4" class="text-center py-5 text-muted text-xs">Belum ada catatan — clean record.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    @endif
                </div>

                <div class="flex gap-2 justify-end mt-3.5">
                    <button type="button" wire:click="showDirectory" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">Batal</button>
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
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-[#556]">Tipe *</label>
                        <select wire:model="edu_education_type" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                            @foreach ($educationTypes as $t)
                                <option value="{{ $t }}">{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-[#556]">Nama Kegiatan *</label>
                        <input type="text" wire:model="edu_activity_name" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                        @error('edu_activity_name') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-[#556]">Penyelenggara</label>
                        <input type="text" wire:model="edu_organizer" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                    </div>
                    <div class="flex gap-2.5">
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Tanggal *</label>
                            <input type="date" wire:model="edu_activity_date" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                            @error('edu_activity_date') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Biaya (Rp)</label>
                            <input type="number" step="1" min="0" wire:model="edu_cost" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                        </div>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-[#556]">Dibiayai Oleh *</label>
                        <select wire:model="edu_funded_by" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                            @foreach ($fundedByOptions as $f)
                                <option value="{{ $f }}">{{ $f }}</option>
                            @endforeach
                        </select>
                    </div>
                    <label class="flex items-center gap-2 text-[13px] text-[#334]">
                        <input type="checkbox" wire:model="edu_is_bonding"> Ikatan Dinas
                    </label>
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
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-[#556]">Hubungan *</label>
                        <select wire:model="fam_relation" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                            @foreach ($familyRelations as $r)
                                <option value="{{ $r }}">{{ $r }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-[#556]">Nama Lengkap *</label>
                        <input type="text" wire:model="fam_full_name" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                        @error('fam_full_name') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex gap-2.5">
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Tanggal Lahir</label>
                            <input type="date" wire:model="fam_birth_date" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                        </div>
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">NIK</label>
                            <input type="text" wire:model="fam_nik" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
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

    {{-- ══ MODAL: KONTRAK ══ --}}
    @if ($showContractModal)
        <div class="fixed inset-0 bg-[rgba(20,26,40,.45)] z-[100] flex items-center justify-center p-5" wire:click.self="$set('showContractModal', false)">
            <div class="bg-white rounded-xl w-[480px] max-w-[92vw] shadow-2xl">
                <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold">Kontrak Baru <span class="font-normal opacity-80">(kontrak aktif lama otomatis ditutup)</span></div>
                <form wire:submit="saveContract" class="p-4 flex flex-col gap-3">
                    <div class="flex gap-2.5">
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Tipe *</label>
                            <select wire:model="ctr_employment_type" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                                @foreach ($employmentTypes as $t)
                                    <option value="{{ $t }}">{{ $t }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Jenis Gaji *</label>
                            <select wire:model="ctr_salary_type" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                                <option value="PER_MONTH">Per Bulan</option>
                                <option value="PER_DAY">Per Hari</option>
                                <option value="PER_UNIT">Per Unit</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex gap-2.5">
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Mulai *</label>
                            <input type="date" wire:model="ctr_start_date" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                            @error('ctr_start_date') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Selesai <span class="text-muted font-normal">(kosong = TETAP)</span></label>
                            <input type="date" wire:model="ctr_end_date" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                            @error('ctr_end_date') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-[#556]">Nominal Gaji (Rp) *</label>
                        <input type="number" step="1" min="0" wire:model="ctr_salary_amount" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                    </div>
                    <label class="flex items-center gap-2 text-[13px] text-[#334]">
                        <input type="checkbox" wire:model.live="ctr_is_probation"> Masa Probation
                    </label>
                    @if ($ctr_is_probation)
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Probation Selesai *</label>
                            <input type="date" wire:model="ctr_probation_end_date" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                            @error('ctr_probation_end_date') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
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
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-[#556]">Tipe *</label>
                        <select wire:model="dis_type" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                            @foreach ($disciplinaryTypes as $t)
                                <option value="{{ $t }}">{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-[#556]">Tanggal Kejadian *</label>
                        <input type="date" wire:model="dis_incident_date" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-[#556]">Alasan *</label>
                        <textarea wire:model="dis_reason" rows="2" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]"></textarea>
                        @error('dis_reason') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                    </div>
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
