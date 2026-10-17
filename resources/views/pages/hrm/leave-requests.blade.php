<?php

use App\Models\Master\EmpLeaveBalance;
use App\Models\Master\EmpLeaveRequest;
use App\Models\Sys\SecUser;
use App\Models\Sys\SysNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use App\Livewire\Concerns\OpensFromQuery;
use Livewire\Component;

// Layar approval Cuti/Sakit/Izin/Cuti Khusus (masteremployee_state.yaml ->
// sop_perizinan_resmi). Self-service (v2: App\Support\V2\Data\SelfService)
// sudah bisa submit sejak awal & sudah menegakkan aturan SOP (H+7/H-7, maks 3
// hari, dokumen wajib, lock Izin selama saldo cuti > 0, dst) -- tapi TIDAK
// PERNAH punya sisi approval, jadi request numpuk PENDING & used_days tidak
// pernah bertambah (pola sama seperti gap EmpCorrectionRequest, lihat
// hrm/correction-requests.blade.php). Approve di sini yang menambah
// EmpLeaveBalance.used_days -- HANYA utk request yang deducts_leave_balance
// (CUTI biasa; Sakit/Izin/Cuti Khusus tidak potong saldo, sesuai submitLeave()).
new #[Title('Approval Cuti/Sakit/Izin — TELASIH MAKMUR ERP')] class extends Component
{
    use OpensFromQuery;

    private const EVENT_LABELS = [
        'MENIKAH' => 'Pernikahan Karyawan',
        'MENIKAHKAN_KHITAN_ANAK' => 'Menikahkan / Mengkhitankan Anak',
        'ISTRI_MELAHIRKAN' => 'Istri Melahirkan / Keguguran',
        'KELUARGA_MENINGGAL' => 'Keluarga Inti Meninggal Dunia',
        'IBADAH' => 'Ibadah',
    ];

    private const IZIN_CATEGORY_LABELS = [
        'MUSIBAH' => 'Musibah',
        'INSTANSI_RESMI' => 'Instansi Resmi',
        'KEPENGURUSAN_MUSIBAH' => 'Kepengurusan Musibah',
    ];

    public bool $showRejectModal = false;

    public ?int $rejectingId = null;

    public string $rejectReason = '';

    #[Computed]
    public function requests()
    {
        return EmpLeaveRequest::with('employee')
            ->when($this->focusId, fn ($q) => $q->whereKey($this->focusId))
            ->orderByRaw("FIELD(status, 'PENDING', 'APPROVED', 'REJECTED')")
            ->orderByDesc('requested_at')
            ->get();
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'pending' => EmpLeaveRequest::where('status', 'PENDING')->count(),
            'approved' => EmpLeaveRequest::where('status', 'APPROVED')->count(),
            'rejected' => EmpLeaveRequest::where('status', 'REJECTED')->count(),
        ];
    }

    public function eventLabel(EmpLeaveRequest $r): ?string
    {
        return match (true) {
            (bool) $r->khusus_event_type => self::EVENT_LABELS[$r->khusus_event_type] ?? $r->khusus_event_type,
            (bool) $r->izin_category => self::IZIN_CATEGORY_LABELS[$r->izin_category] ?? $r->izin_category,
            default => null,
        };
    }

    public function attachments(EmpLeaveRequest $r): array
    {
        return array_filter([
            'Surat Cuti' => $r->attachment_surat_cuti,
            'Surat Dokter' => $r->attachment_surat_dokter,
            'Kwitansi/Resep' => $r->attachment_kwitansi_resep,
            'Screenshot JKN' => $r->attachment_jkn_screenshot,
            'Lampiran' => $r->attachment_path,
        ]);
    }

    public function approve(int $requestId): void
    {
        $req = EmpLeaveRequest::findOrFail($requestId);

        if ($req->status !== 'PENDING') {
            return;
        }

        if ($req->deducts_leave_balance) {
            $balance = EmpLeaveBalance::firstOrCreate(
                ['employee_id' => $req->employee_id, 'year' => $req->date_from->year],
                ['entitlement_days' => 12, 'used_days' => 0, 'carried_over_days' => 0],
            );
            $balance->increment('used_days', $req->total_days);
        }

        $req->update([
            'status' => 'APPROVED',
            'reviewed_by' => Auth::user()->login,
            'reviewed_at' => now(),
        ]);

        $this->notifyEmployee($req, 'Pengajuan disetujui', "Pengajuan {$req->leave_type} tanggal {$req->date_from->format('d M Y')} s/d {$req->date_to->format('d M Y')} disetujui HRD.", 'success');

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

        $req = EmpLeaveRequest::findOrFail($this->rejectingId);

        if ($req->status === 'PENDING') {
            $req->update([
                'status' => 'REJECTED',
                'reviewed_by' => Auth::user()->login,
                'review_note' => $data['rejectReason'],
                'reviewed_at' => now(),
            ]);

            $this->notifyEmployee($req, 'Pengajuan ditolak', "Pengajuan {$req->leave_type} ditolak HRD: {$data['rejectReason']}.", 'warning');
        }

        $this->showRejectModal = false;
        unset($this->requests, $this->stats);
    }

    private function notifyEmployee(EmpLeaveRequest $req, string $title, string $message, string $type): void
    {
        $employeeUser = SecUser::where('employee_id', $req->employee_id)->first();

        if ($employeeUser) {
            SysNotification::create([
                'user_id' => $employeeUser->login,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'is_read' => false,
            ]);
        }
    }
};
?>

<div>
    <div class="max-w-[1400px] mx-auto my-5 px-4">

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
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Jenis</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Periode</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Lampiran</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Alasan</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Diajukan</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Status</th>
                            <th class="px-3 py-2 bg-[#fafbfd] border-b border-[#eef1f5] sticky top-0 z-[1]"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->requests as $r)
                            <tr class="border-b border-[#eef1f5] align-top" wire:key="lr-{{ $r->request_id }}">
                                <x-grid.identity-cell :id="$r->employee_id" :name="$r->employee?->full_name ?? '—'" :code="$r->employee?->employee_code" />
                                <td class="px-3 py-2">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ match ($r->leave_type) { 'CUTI' => 'bg-[#dbeafe] text-[#1e40af]', 'SAKIT' => 'bg-[#fef3c7] text-[#92400e]', 'IZIN' => 'bg-[#f3e8ff] text-[#6b21a8]', default => 'bg-[#e2e8f0] text-[#334155]' } }}">{{ $r->leave_type }}</span>
                                    @if ($this->eventLabel($r))
                                        <div class="text-[10px] text-muted mt-0.5">{{ $this->eventLabel($r) }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-[11px] whitespace-nowrap">
                                    {{ $r->date_from->format('d M Y') }} — {{ $r->date_to->format('d M Y') }}
                                    <div class="text-muted">{{ $r->total_days }} hari{{ $r->deducts_leave_balance ? ' (potong saldo)' : '' }}</div>
                                </td>
                                <td class="px-3 py-2 text-[11px]">
                                    @forelse ($this->attachments($r) as $label => $path)
                                        <a href="{{ asset('storage/'.$path) }}" target="_blank" class="text-accent underline block">{{ $label }}</a>
                                    @empty
                                        <span class="text-muted">—</span>
                                    @endforelse
                                </td>
                                <td class="px-3 py-2 text-[11px] text-muted max-w-[200px] truncate" title="{{ $r->reason }}">{{ $r->reason ?: '—' }}</td>
                                <td class="px-3 py-2 text-[11px] whitespace-nowrap">{{ $r->requested_at?->format('d M Y H:i') }}</td>
                                <td class="px-3 py-2">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ match ($r->status) { 'APPROVED' => 'bg-[#dcfce7] text-[#166534]', 'REJECTED' => 'bg-[#fee2e2] text-[#991b1b]', default => 'bg-[#fef3c7] text-[#92400e]' } }}">{{ $r->status }}</span>
                                </td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                    @if ($r->status === 'PENDING')
                                        <x-grid.action-icon icon="fa-check" title="Setujui" action="approve({{ $r->request_id }})"
                                            confirm="Setujui pengajuan {{ $r->leave_type }} ini?" variant="success" />
                                        <x-grid.action-icon icon="fa-xmark" title="Tolak" action="openReject({{ $r->request_id }})" variant="danger" />
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="8" message="Tidak ada pengajuan Cuti/Sakit/Izin." />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>
    </div>

    @if ($showRejectModal)
        <div class="fixed inset-0 bg-[rgba(20,26,40,.45)] z-[100] flex items-center justify-center p-5" wire:click.self="$set('showRejectModal', false)">
            <div class="bg-white rounded-xl w-[420px] max-w-[92vw] shadow-2xl">
                <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold">Tolak Pengajuan</div>
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
