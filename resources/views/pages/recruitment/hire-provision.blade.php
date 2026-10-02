<?php

use App\Models\Hrr\AppApplication;
use App\Models\Hrr\CndDocument;
use App\Models\Master\EmpContract;
use App\Models\Master\EmpDocument;
use App\Models\Master\EmpEmployee;
use App\Models\Master\OrgCompany;
use App\Models\Master\OrgPosition;
use App\Services\Migration\EmployeeLoginProvisionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use App\Livewire\Concerns\OpensFromQuery;
use Livewire\Component;

// L9 -- docs/recruitment_tier4.md SECTION 14 & Tier2 SECTION 7 (hire trigger).
// Reuse App\Services\Migration\EmployeeLoginProvisionService (persis alur
// registrasi karyawan baru di master/employee.blade.php) utk step sec_user.
// position_id emp_employee TIDAK ADA mapping otomatis dari requisition
// (requisition cuma simpan position_title teks bebas) -- HR WAJIB konfirmasi
// org_position riil di modal ini ("engine suggests, officer confirms",
// CLAUDE.md §4), bukan ditebak sistem.
new #[Title('Provisioning ke ERP — TELASIH MAKMUR ERP')] class extends Component
{
    use OpensFromQuery;

    protected function openFromQuery(int $id): void
    {
        $this->openProvision($id);
    }

    public ?int $provisioningId = null;

    public ?int $position_id = null;

    public ?array $credentials = null;

    #[Computed]
    public function readyToProvision()
    {
        return AppApplication::with(['candidate', 'vacancy.requisition', 'offers'])
            ->whereNull('provisioned_employee_id')
            ->whereHas('offers', fn ($q) => $q->where('status', 'ACCEPTED'))
            ->get();
    }

    #[Computed]
    public function provisionedRecently()
    {
        return AppApplication::with('candidate')->whereNotNull('provisioned_employee_id')->orderByDesc('provisioned_at')->limit(20)->get();
    }

    #[Computed]
    public function positionsFor(): array
    {
        if (! $this->provisioningId) {
            return [];
        }

        $app = AppApplication::with('vacancy.requisition')->find($this->provisioningId);
        $deptId = $app?->vacancy->requisition->department_id;

        $query = OrgPosition::where('activate', true);
        if ($deptId) {
            $query->orderByRaw('department_id = ? DESC', [$deptId]);
        }

        return $query->orderBy('position_name')->pluck('position_name', 'position_id')->all();
    }

    public function openProvision(int $applicationId): void
    {
        $this->provisioningId = $applicationId;
        $this->position_id = null;
        $this->credentials = null;
    }

    public function provision(): void
    {
        if (! $this->position_id) {
            $this->addError('provision', t('Select the new employee\'s position/job title first.'));

            return;
        }

        $app = AppApplication::with('candidate', 'vacancy.requisition')->findOrFail($this->provisioningId);

        if ($app->provisioned_employee_id) {
            return; // idempotency guard -- sudah pernah diprovision
        }

        $offer = $app->offers()->where('status', 'ACCEPTED')->latest('id_offer')->firstOrFail();
        $candidate = $app->candidate;
        $company = OrgCompany::findOrFail(1);

        $maxSeq = EmpEmployee::where('company_id', 1)->where('employee_code', 'like', $company->company_code.'-%')
            ->get()->map(fn ($e) => (int) substr($e->employee_code, strlen($company->company_code) + 1))->max() ?? 0;

        $employee = EmpEmployee::create([
            'employee_code' => $company->company_code.'-'.str_pad((string) ($maxSeq + 1), 5, '0', STR_PAD_LEFT),
            'company_id' => 1,
            'position_id' => $this->position_id,
            'full_name' => $candidate->name,
            'birth_date' => $candidate->birth_date,
            'nik_ktp' => $candidate->national_id,
            'phone' => $candidate->phone,
            'email_personal' => $candidate->email,
            'address_detail_ktp' => $candidate->address,
            'join_date' => $offer->start_date,
            'work_status' => 'WORKING',
            'work_status_since' => $offer->start_date,
            'activate' => true,
            'created_by' => Auth::user()->login ?? 'system',
        ]);

        EmpContract::create([
            'employee_id' => $employee->employee_id,
            'contract_no' => 'CTR-'.$employee->employee_code,
            'employment_type' => $offer->employment_type,
            'start_date' => $offer->start_date,
            'salary_type' => 'PER_MONTH',
            'salary_amount' => $offer->proposed_salary,
            'activate' => true,
            'created_by' => Auth::user()->login ?? 'system',
        ]);

        // Dokumen kandidat disalin referensinya ke emp_document -- Tier2 §7
        // poin 7 (arsip rekrutmen tetap utuh, bukan dipindah).
        foreach (CndDocument::where('id_candidate', $candidate->id_candidate)->get() as $doc) {
            EmpDocument::create([
                'employee_id' => $employee->employee_id,
                'document_type' => $doc->doc_type,
                'file_path' => $doc->file_path,
                'uploaded_by_role' => 'RECRUITMENT_SYNC',
                'activate' => true,
                'created_by' => Auth::user()->login ?? 'system',
            ]);
        }

        $cred = app(EmployeeLoginProvisionService::class)->provisionForEmployee($employee, Auth::user()->login ?? 'system');

        $app->update([
            'status' => 'HIRED',
            'provisioned_employee_id' => $employee->employee_id,
            'provisioned_at' => now(),
        ]);

        $this->credentials = $cred;
        unset($this->readyToProvision, $this->provisionedRecently);
    }
};
?>

<div class="max-w-[1200px] mx-auto my-5 px-4">
    <div class="bg-white border border-[#d7dbe2] rounded-md overflow-hidden mb-4">
        <div class="px-4 py-2.5 bg-[#f4f6fa] border-b border-[#d7dbe2]">
            <h3 class="text-xs text-navy uppercase font-bold">{{ t('Ready to Provision (Offer Accepted)') }}</h3>
        </div>
        <table class="w-full text-xs">
            <thead class="text-[10px] text-muted uppercase border-b border-[#eef1f5]">
                <tr>
                    <th class="text-left px-3 py-2">{{ t('Candidate') }}</th>
                    <th class="text-left px-3 py-2">{{ t('Vacancy') }}</th>
                    <th class="px-3 py-2 text-right w-[160px]">{{ t('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#eef1f5]">
                @forelse ($this->readyToProvision as $app)
                    <tr wire:key="ready-{{ $app->id_application }}">
                        <td class="px-3 py-2 font-semibold">{{ $app->candidate->name ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $app->vacancy->title ?? '—' }}</td>
                        <td class="px-3 py-2 text-right">
                            <button type="button" wire:click="openProvision({{ $app->id_application }})" class="text-[11px] font-bold text-white bg-navy px-3 py-1.5 rounded">{{ t('Provision to ERP') }}</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-3 py-6 text-center text-muted text-xs">{{ t('No candidates are ready to be provisioned.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="bg-white border border-[#d7dbe2] rounded-md overflow-hidden">
        <div class="px-4 py-2.5 bg-[#f4f6fa] border-b border-[#d7dbe2]">
            <h3 class="text-xs text-navy uppercase font-bold">{{ t('Provisioning History') }}</h3>
        </div>
        <table class="w-full text-xs">
            <thead class="text-[10px] text-muted uppercase border-b border-[#eef1f5]">
                <tr><th class="text-left px-3 py-2">{{ t('Candidate') }}</th><th class="text-left px-3 py-2">{{ t('Provisioned At') }}</th></tr>
            </thead>
            <tbody class="divide-y divide-[#eef1f5]">
                @forelse ($this->provisionedRecently as $app)
                    <tr wire:key="prov-{{ $app->id_application }}">
                        <td class="px-3 py-2">{{ $app->candidate->name ?? '—' }}</td>
                        <td class="px-3 py-2 font-mono">{{ $app->provisioned_at?->translatedFormat('d M Y H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="px-3 py-6 text-center text-muted text-xs">{{ t('None yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($provisioningId)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-[60] p-4" wire:click.self="$set('provisioningId', null)">
            <div class="bg-white rounded-xl w-full max-w-[460px] p-5">
                @if (! $credentials)
                    <h3 class="text-sm font-bold text-navy mb-2">{{ t('Confirm Provisioning') }}</h3>
                    <p class="text-[11.5px] text-muted mb-3">{{ t('This will create a new login account for this candidate.') }} {{ t('This action') }} <b>{{ t('cannot be undone') }}</b>.</p>
                    @error('provision') <p class="text-[11px] text-[#a3241f] mb-2">⚠ {{ $message }}</p> @enderror
                    <x-form.select wire:model="position_id" :options="$this->positionsFor" :placeholder="t('— select position/job title —')">
                        <x-slot:label>{{ t('Position/Job Title') }}</x-slot:label>
                    </x-form.select>
                    <div class="flex justify-end gap-2 mt-4">
                        <button type="button" wire:click="$set('provisioningId', null)" class="border border-[#d7dbe2] text-xs font-bold px-4 py-2 rounded-md">{{ t('Cancel') }}</button>
                        <button type="button" wire:click="provision" wire:confirm="{{ t('Are you sure you want to provision this employee to the ERP?') }}" class="bg-navy text-white text-xs font-bold px-4 py-2 rounded-md">{{ t('Provision to ERP') }}</button>
                    </div>
                @else
                    <h3 class="text-sm font-bold text-[#1f8a4c] mb-2">✓ {{ t('Provisioned Successfully') }}</h3>
                    <p class="text-[11.5px] text-muted mb-3">{{ t('Give the following credentials to the new employee manually:') }}</p>
                    <div class="bg-[#f8fafc] border border-[#d7dbe2] rounded-md p-3 text-xs space-y-1 font-mono">
                        <p>{{ t('Login') }}: <b>{{ $credentials['login'] ?? '—' }}</b></p>
                        <p>{{ t('Password') }}: <b>{{ $credentials['password'] ?? '—' }}</b></p>
                    </div>
                    <div class="flex justify-end mt-4">
                        <button type="button" wire:click="$set('provisioningId', null)" class="bg-navy text-white text-xs font-bold px-4 py-2 rounded-md">{{ t('Close') }}</button>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
