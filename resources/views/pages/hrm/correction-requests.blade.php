<?php

use App\Services\Hrm\EmployeeBankService;
use App\Models\Master\EmpCorrectionRequest;
use App\Models\Sys\SecUser;
use App\Models\Sys\SysNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Layar review EmpCorrectionRequest (keputusan user 2026-08-27, dipicu temuan
// field readonly di security-setup): tabel ini SUDAH ADA sejak self-service
// "Ajukan Koreksi" dibangun, tapi TIDAK PERNAH punya layar approve/reject --
// request numpuk PENDING selamanya. Sekarang dipakai juga oleh
// SecurityRecoveryService::syncIdentityToEmployee() (isi data kosong lewat
// Setup Keamanan Akun). Bukan "form asli + approval" spt PR/PO
// (griduistandard.md §8) -- correction request tak punya dokumen induk,
// jadi Approve/Reject langsung di grid ini.
new #[Title('Koreksi Data Karyawan — TELASIH MAKMUR ERP')] class extends Component
{
    // Field yang bisa diterapkan otomatis ke emp_employee saat Approve --
    // BPJS/dst (tabel relasi terpisah) TIDAK masuk di sini -- approve utk field
    // itu cuma tutup request (HR update manual), lihat autoApplyColumn().
    // Rekening Bank Payroll DITERAPKAN otomatis lewat EmployeeBankService
    // (keputusan user 2026-09-23 -- dulu cuma ditutup, rekening tak berubah).
    private const FIELD_COLUMN_MAP = [
        'Tanggal Lahir' => 'birth_date',
        'NIK KTP' => 'nik_ktp',
        'Telepon / WA' => 'phone',
        'Alamat KTP' => 'address_detail_ktp',
        'Alamat Domisili' => 'address_detail_domisili',
        'Kontak Darurat' => 'emergency_contact_name',
    ];

    public bool $showRejectModal = false;

    public ?int $rejectingId = null;

    public string $rejectReason = '';

    #[Computed]
    public function requests()
    {
        return EmpCorrectionRequest::with('employee')
            ->orderByRaw("FIELD(status, 'PENDING', 'APPROVED', 'REJECTED')")
            ->orderByDesc('requested_at')
            ->get();
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'pending' => EmpCorrectionRequest::where('status', 'PENDING')->count(),
            'approved' => EmpCorrectionRequest::where('status', 'APPROVED')->count(),
            'rejected' => EmpCorrectionRequest::where('status', 'REJECTED')->count(),
        ];
    }

    protected function autoApplyColumn(string $fieldName): ?string
    {
        return self::FIELD_COLUMN_MAP[$fieldName] ?? null;
    }

    public function approve(int $requestId): void
    {
        $req = EmpCorrectionRequest::findOrFail($requestId);

        if ($req->status !== 'PENDING') {
            return;
        }

        $column = $this->autoApplyColumn($req->field_name);

        if ($column && $req->employee) {
            $req->employee->update([$column => $req->new_value, 'updated_by' => Auth::user()->login]);
        }

        if ($bank = $req->bankPayload()) {
            EmployeeBankService::setPrimary($req->employee_id, $bank['bank_supplier_id'], $bank['account_no'] ?? null,
                $bank['account_holder_name'] ?? null, Auth::user()->login);
        }

        $req->update([
            'status' => 'APPROVED',
            'reviewed_by' => Auth::user()->login,
            'reviewed_at' => now(),
        ]);

        unset($this->requests, $this->stats);
    }

    public function openReject(int $requestId): void
    {
        $this->rejectingId = $requestId;
        $this->rejectReason = '';
        $this->showRejectModal = true;
    }

    public function reject(): void
    {
        $data = $this->validate(['rejectReason' => ['required', 'string', 'max:255']]);

        $req = EmpCorrectionRequest::findOrFail($this->rejectingId);

        if ($req->status === 'PENDING') {
            $req->update([
                'status' => 'REJECTED',
                'reviewed_by' => Auth::user()->login,
                'review_note' => $data['rejectReason'],
                'reviewed_at' => now(),
            ]);

            $employeeUser = SecUser::where('employee_id', $req->employee_id)->first();

            if ($employeeUser) {
                SysNotification::create([
                    'user_id' => $employeeUser->login,
                    'title' => 'Pengajuan koreksi data ditolak',
                    'message' => "Koreksi \"{$req->field_name}\" ditolak HR: {$data['rejectReason']}. Silakan ajukan ulang dengan data yang benar.",
                    'type' => 'warning',
                    'is_read' => false,
                ]);
            }
        }

        $this->showRejectModal = false;
        unset($this->requests, $this->stats);
    }

    // Componentization grid (2026-08-27): avatarInitials()/avatarGradient()
    // dipindah ke App\Support\Grid\Avatar (dipakai <x-grid.identity-cell>).
};
?>

<div>
    <div class="max-w-[1300px] mx-auto my-5 px-4">

        <x-grid.stat-row :count="3">
            <x-grid.stat-box tone="warn" label="Menunggu Review" :value="number_format($this->stats['pending'])" />
            <x-grid.stat-box tone="active" label="Disetujui" :value="number_format($this->stats['approved'])" />
            <x-grid.stat-box tone="critical" label="Ditolak" :value="number_format($this->stats['rejected'])" />
        </x-grid.stat-row>

        <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 overflow-hidden">
            <x-grid.table-shell>
                <table class="w-full text-xs border-collapse">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Karyawan</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Field</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Lama → Baru</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Alasan</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Diajukan</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Status</th>
                            <th class="px-3 py-2 bg-[#fafbfd] border-b border-[#eef1f5] sticky top-0 z-[1]"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->requests as $r)
                            <tr class="border-b border-[#eef1f5]" wire:key="corr-{{ $r->request_id }}">
                                <x-grid.identity-cell :id="$r->employee_id" :name="$r->employee?->full_name ?? '—'" :code="$r->employee?->employee_code" />
                                <td class="px-3 py-2 font-semibold">{{ $r->field_name }}</td>
                                <td class="px-3 py-2 text-[11px]">
                                    <span class="text-muted line-through">{{ $r->old_value ?: '(kosong)' }}</span>
                                    →
                                    <span class="font-semibold">{{ $r->newValueLabel() }}</span>
                                </td>
                                <td class="px-3 py-2 text-[11px] text-muted max-w-[220px] truncate" title="{{ $r->reason }}">{{ $r->reason ?: '—' }}</td>
                                <td class="px-3 py-2 text-[11px]">{{ $r->requested_at?->format('d M Y H:i') }}</td>
                                <td class="px-3 py-2">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ match ($r->status) { 'APPROVED' => 'bg-[#dcfce7] text-[#166534]', 'REJECTED' => 'bg-[#fee2e2] text-[#991b1b]', default => 'bg-[#fef3c7] text-[#92400e]' } }}">{{ $r->status }}</span>
                                </td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                    @if ($r->status === 'PENDING')
                                        <x-grid.action-icon icon="fa-check" title="Setujui" action="approve({{ $r->request_id }})"
                                            confirm="Setujui koreksi {{ $r->field_name }} jadi \"{{ $r->newValueLabel() }}\"?" variant="success" />
                                        <x-grid.action-icon icon="fa-xmark" title="Tolak" action="openReject({{ $r->request_id }})" variant="danger" />
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="7" message="Tidak ada pengajuan koreksi." />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>
    </div>

    @if ($showRejectModal)
        <div class="fixed inset-0 bg-[rgba(20,26,40,.45)] z-[100] flex items-center justify-center p-5" wire:click.self="$set('showRejectModal', false)">
            <div class="bg-white rounded-xl w-[420px] max-w-[92vw] shadow-2xl">
                <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold">Tolak Pengajuan Koreksi</div>
                <form wire:submit="reject" class="p-4 flex flex-col gap-3">
                    <x-form.textarea label="Alasan Penolakan *" wire:model="rejectReason" rows="3" placeholder="Jelaskan kenapa ditolak — karyawan akan menerima pesan ini." name="rejectReason" />
                    <div class="flex gap-2 justify-end pt-2 border-t border-[#eef1f5] mt-1">
                        <button type="button" wire:click="$set('showRejectModal', false)" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">Batal</button>
                        <button type="submit" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#c0392b] text-white cursor-pointer">Tolak</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
