<?php

use App\Models\Acc\FinAccount;
use App\Models\Acc\FinVoucher;
use App\Models\Acc\FinVoucherLine;
use App\Services\Acc\JournalPostingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Jurnal Kas & Bank (BKM/BKK/BBM/BBK) -- dari mockup HTML user 2026-08-22
// (gemini-code-1787379102109.html), disesuaikan ke griduistandard.md &
// pola grid<->form panel-switching satu route (bukan modal spt DP Invoice --
// kontennya terlalu banyak: tab rincian+pajak+audit, sama spt Stock In/Out).
// fin_voucher.status ENUM(DRAFT,POSTED,CANCELLED) sudah ada sejak awal tapi
// baru di sini benar2 dipakai DRAFT-nya -- DP Invoice dkk langsung POSTED,
// tak pernah draft (lihat migration 2026_08_22_000023). Posting manual pakai
// JournalPostingService::postDraft() (baru), BUKAN post() yg utk sistem
// generate header+baris sekaligus.
// "Smart button" e-Faktur/Rekonsiliasi Bank di mockup SENGAJA tidak dibuat --
// itu placeholder statis tanpa logika nyata di balik nilainya, tak ada
// validasi e-Faktur atau rekonsiliasi bank yang diminta/dibangun di modul ini.
new #[Title('Jurnal Kas & Bank — TELASIH MAKMUR ERP')] class extends Component
{
    private const TYPE_JOURNAL = ['BKM' => 'CSH', 'BKK' => 'CSH', 'BBM' => 'BNK', 'BBK' => 'BNK'];

    private const TYPE_LABEL = [
        'BKM' => 'Bukti Kas Masuk', 'BKK' => 'Bukti Kas Keluar',
        'BBM' => 'Bukti Bank Masuk', 'BBK' => 'Bukti Bank Keluar',
    ];

    public string $panel = 'grid';

    public string $search = '';

    public ?int $editingId = null;

    public bool $readOnly = false;

    // ---- header form ----
    public string $doc_no = '';

    public string $voucher_type = 'BKM';

    public string $entry_date = '';

    public ?int $main_account_id = null;

    public string $partner_name = '';

    public string $reference_no = '';

    public string $ppn_type = 'NONE';

    public string $efaktur_no = '';

    public string $pph_type = 'NONE';

    public string $bupot_no = '';

    public string $status = 'DRAFT';

    public string $activeTab = 'items';

    // ---- baris jurnal ----
    public array $lines = [];

    public string $errorMessage = '';

    #[Computed]
    public function cashBankAccounts()
    {
        // Konvensi COA yang sudah diseed: 111x = Kas & Setara Kas (FinanceMasterSeeder).
        return FinAccount::where('account_code', 'like', '111%')->where('is_postable', true)->orderBy('account_code')->get();
    }

    #[Computed]
    public function postableAccounts()
    {
        return FinAccount::where('is_postable', true)->orderBy('account_code')->get();
    }

    #[Computed]
    public function vouchers()
    {
        return FinVoucher::with(['lines', 'mainAccount'])
            ->whereNotNull('voucher_type')
            ->when($this->search !== '', function ($q) {
                $term = $this->search;
                $q->where(fn ($qq) => $qq->where('voucher_number', 'like', "%{$term}%")
                    ->orWhere('partner_name', 'like', "%{$term}%")
                    ->orWhere('voucher_type', 'like', "%{$term}%"));
            })
            ->orderByDesc('voucher_id')
            ->limit(200)
            ->get();
    }

    #[Computed]
    public function statTotal(): int
    {
        return $this->vouchers->count();
    }

    #[Computed]
    public function statDraft(): int
    {
        return $this->vouchers->where('status', 'DRAFT')->count();
    }

    #[Computed]
    public function statPosted(): int
    {
        return $this->vouchers->where('status', 'POSTED')->count();
    }

    #[Computed]
    public function totalDebit(): float
    {
        return array_sum(array_map(fn ($l) => (float) ($l['debit'] ?: 0), $this->lines));
    }

    #[Computed]
    public function totalCredit(): float
    {
        return array_sum(array_map(fn ($l) => (float) ($l['credit'] ?: 0), $this->lines));
    }

    #[Computed]
    public function editingVoucher(): ?FinVoucher
    {
        return $this->editingId ? FinVoucher::find($this->editingId) : null;
    }

    public function openNew(): void
    {
        $this->reset(['editingId', 'doc_no', 'main_account_id', 'partner_name', 'reference_no', 'efaktur_no', 'bupot_no', 'lines', 'errorMessage']);
        $this->voucher_type = 'BKM';
        $this->entry_date = now()->toDateString();
        $this->ppn_type = 'NONE';
        $this->pph_type = 'NONE';
        $this->status = 'DRAFT';
        $this->readOnly = false;
        $this->activeTab = 'items';
        $this->seedFirstLine();
        $this->panel = 'form';
    }

    // Baris pertama otomatis mengikuti Akun Kas/Bank Utama + arah normal
    // vokasi-nya (BKM/BBM = uang MASUK = Debit; BKK/BBK = uang KELUAR =
    // Kredit) -- kemudahan awal, tetap bisa diedit bebas sesudahnya.
    public function seedFirstLine(): void
    {
        if (! $this->main_account_id || $this->status !== 'DRAFT') {
            return;
        }

        $isInflow = in_array($this->voucher_type, ['BKM', 'BBM'], true);
        $this->lines[0] = [
            'account_id' => $this->main_account_id,
            'desc' => self::TYPE_LABEL[$this->voucher_type] ?? '',
            'debit' => $isInflow ? '' : '0',
            'credit' => $isInflow ? '0' : '',
        ];
    }

    public function updatedVoucherType(): void
    {
        if (! empty($this->lines)) {
            $this->seedFirstLine();
        }
    }

    public function updatedMainAccountId(): void
    {
        $this->seedFirstLine();
    }

    public function addLine(): void
    {
        $this->lines[] = ['account_id' => null, 'desc' => '', 'debit' => '', 'credit' => ''];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function openEdit(int $id): void
    {
        $voucher = FinVoucher::with('lines')->findOrFail($id);

        $this->editingId = $voucher->voucher_id;
        $this->doc_no = (string) $voucher->voucher_number;
        $this->voucher_type = (string) $voucher->voucher_type;
        $this->entry_date = $voucher->entry_date?->format('Y-m-d') ?? now()->toDateString();
        $this->main_account_id = $voucher->main_account_id;
        $this->partner_name = (string) $voucher->partner_name;
        $this->reference_no = (string) $voucher->reference_no;
        $this->ppn_type = $voucher->ppn_type ?: 'NONE';
        $this->efaktur_no = (string) $voucher->efaktur_no;
        $this->pph_type = $voucher->pph_type ?: 'NONE';
        $this->bupot_no = (string) $voucher->bupot_no;
        $this->status = (string) $voucher->status;
        $this->readOnly = $voucher->status !== 'DRAFT';
        $this->errorMessage = '';
        $this->activeTab = 'items';

        $this->lines = $voucher->lines->map(fn ($l) => [
            'account_id' => $l->account_id,
            'desc' => (string) $l->narration,
            'debit' => (float) $l->debit > 0 ? (string) (float) $l->debit : '',
            'credit' => (float) $l->credit > 0 ? (string) (float) $l->credit : '',
        ])->all();

        $this->panel = 'form';
    }

    public function backToGrid(): void
    {
        $this->panel = 'grid';
    }

    public function saveDraft(): void
    {
        $this->errorMessage = '';

        $validLines = array_values(array_filter($this->lines, fn ($l) => ! empty($l['account_id']) && ((float) ($l['debit'] ?: 0) > 0 || (float) ($l['credit'] ?: 0) > 0)));

        if (! $this->main_account_id) {
            $this->errorMessage = 'Akun Kas/Bank Utama wajib dipilih.';

            return;
        }
        if (empty($validLines)) {
            $this->errorMessage = 'Minimal 1 baris jurnal dgn akun + nilai debit/kredit.';

            return;
        }

        $journalCode = self::TYPE_JOURNAL[$this->voucher_type];
        $journalId = DB::connection('tm2acc')->table('fin_journal')->where('journal_code', $journalCode)->value('journal_id');
        $login = Auth::user()->login;

        DB::connection('tm2acc')->transaction(function () use ($validLines, $journalId, $login) {
            $data = [
                'voucher_type' => $this->voucher_type,
                'journal_id' => $journalId,
                'main_account_id' => $this->main_account_id,
                'entry_date' => $this->entry_date,
                'posting_date' => $this->entry_date,
                'reference_no' => $this->reference_no ?: null,
                'partner_name' => $this->partner_name ?: null,
                'ppn_type' => $this->ppn_type,
                'efaktur_no' => $this->efaktur_no ?: null,
                'pph_type' => $this->pph_type,
                'bupot_no' => $this->bupot_no ?: null,
                'description' => self::TYPE_LABEL[$this->voucher_type].($this->partner_name ? " — {$this->partner_name}" : ''),
                'updated_by' => $login,
            ];

            if ($this->editingId) {
                $voucher = FinVoucher::findOrFail($this->editingId);
                abort_if($voucher->status !== 'DRAFT', 422, 'Voucher sudah diposting, tidak bisa diubah.');
                $voucher->update($data);
                $voucher->lines()->delete();
            } else {
                $voucher = FinVoucher::create([
                    ...$data,
                    'voucher_number' => $this->nextVoucherNumber($this->voucher_type),
                    'status' => 'DRAFT',
                    'created_by' => $login,
                ]);
                $this->editingId = $voucher->voucher_id;
            }

            foreach ($validLines as $line) {
                FinVoucherLine::create([
                    'voucher_id' => $voucher->voucher_id,
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'] ?: 0,
                    'credit' => $line['credit'] ?: 0,
                    'narration' => $line['desc'] ?: null,
                ]);
            }

            $this->doc_no = $voucher->voucher_number;
        });

        unset($this->vouchers, $this->statTotal, $this->statDraft, $this->statPosted);
        session()->flash('flash', "Draft {$this->doc_no} tersimpan.");
        $this->panel = 'grid';
    }

    private function nextVoucherNumber(string $type): string
    {
        $prefix = $type.'/'.now()->format('Y').'/'.now()->format('m').'/';
        $last = FinVoucher::where('voucher_number', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('voucher_number')->value('voucher_number');
        $seq = $last ? ((int) substr($last, -3)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

    public function postDoc(?int $docId, JournalPostingService $svc): void
    {
        $id = $docId ?? $this->editingId;
        if (! $id) {
            return;
        }

        try {
            $svc->postDraft(FinVoucher::findOrFail($id), Auth::user()->login);
        } catch (\Throwable $e) {
            $message = method_exists($e, 'getMessage') ? $e->getMessage() : 'Gagal posting.';
            if ($this->panel === 'form') {
                $this->errorMessage = $message;
            } else {
                session()->flash('error', $message);
            }

            return;
        }

        unset($this->vouchers, $this->statTotal, $this->statDraft, $this->statPosted);
        session()->flash('flash', 'Jurnal berhasil di-posting.');

        if ($this->panel === 'form') {
            $this->openEdit($id);
        }
    }

    public function cancelDoc(): void
    {
        if (! $this->editingId) {
            return;
        }

        $voucher = FinVoucher::findOrFail($this->editingId);
        if ($voucher->status !== 'DRAFT') {
            return;
        }

        $voucher->update(['status' => 'CANCELLED', 'is_cancelled' => true, 'updated_by' => Auth::user()->login]);
        unset($this->vouchers, $this->statTotal, $this->statDraft, $this->statPosted);
        session()->flash('flash', 'Draft dibatalkan.');
        $this->panel = 'grid';
    }

    public function typeBadgeClass(string $type): string
    {
        return match ($type) {
            'BKM' => 'bg-[#e9f9ef] text-[#16a34a]',
            'BKK' => 'bg-[#fdecec] text-[#c0392b]',
            'BBM' => 'bg-[#eaf2ff] text-[#1d4ed8]',
            'BBK' => 'bg-[#f1ecfc] text-[#6d28d9]',
            default => 'bg-[#eef0f3] text-[#8a8f98]',
        };
    }

    public function statusBadgeClass(string $status): string
    {
        return match ($status) {
            'DRAFT' => 'bg-[#eef0f3] text-[#8a8f98]',
            'POSTED' => 'bg-[#e9f9ef] text-[#16a34a]',
            'CANCELLED' => 'bg-[#fdecec] text-[#c0392b]',
            default => 'bg-[#eef0f3] text-[#8a8f98]',
        };
    }

    public function avatarInitials(string $name): string
    {
        $name = trim($name) ?: 'K B';
        $words = array_values(array_filter(explode(' ', $name)));
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
};
?>

<div>
    <div class="max-w-[1400px] mx-auto my-5 px-4">

        @if (session('flash'))
            <div class="text-xs px-3.5 py-2.5 rounded-md mb-3.5 bg-[#e9f9ef] border border-[#c8ecd6] text-[#1f8a4c]">✅ {{ session('flash') }}</div>
        @endif
        @if (session('error'))
            <div class="text-xs px-3.5 py-2.5 rounded-md mb-3.5 bg-[#fdecec] border border-[#f5c6c6] text-[#a3453f]">⚠️ {{ session('error') }}</div>
        @endif

        @if ($panel === 'grid')
            <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 mb-3.5">
                <div class="rounded-lg p-3.5 shadow-sm bg-[#eaf2ff] border border-[#c8ddfb]">
                    <p class="text-[10.5px] font-bold uppercase text-[#3b5b99]">Total Voucher</p>
                    <p class="text-xl font-extrabold text-[#1d4ed8] mt-0.5">{{ $this->statTotal }}</p>
                </div>
                <div class="rounded-lg p-3.5 shadow-sm bg-[#fef6e7] border border-[#f5e0b3]">
                    <p class="text-[10.5px] font-bold uppercase text-[#8a6d00]">Draft</p>
                    <p class="text-xl font-extrabold text-[#b45309] mt-0.5">{{ $this->statDraft }}</p>
                </div>
                <div class="rounded-lg p-3.5 shadow-sm bg-[#e9f9ef] border border-[#c8ecd6]">
                    <p class="text-[10.5px] font-bold uppercase text-[#2f7a52]">Sudah Posting</p>
                    <p class="text-xl font-extrabold text-[#16a34a] mt-0.5">{{ $this->statPosted }}</p>
                </div>
            </div>

            <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
                <div class="flex items-center gap-2 bg-white border-b border-[#e2e7ee] px-2.5 py-2 overflow-x-auto">
                    <div class="flex items-center gap-1.5 shrink-0">
                        <span class="text-sm">🔍</span>
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari no. bukti / tipe / mitra…"
                            class="w-64 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                    </div>
                    <span class="flex-1"></span>
                    <button type="button" wire:click="openNew"
                        class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
                </div>

                <div class="max-h-[65vh] overflow-auto">
                    <table class="w-full border-collapse text-[12.5px]">
                        <thead>
                            <tr>
                                <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Mitra / No. Bukti</th>
                                <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">Tanggal</th>
                                <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[80px]">Tipe</th>
                                <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[180px]">Akun Kas/Bank</th>
                                <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[140px]">Total (IDR)</th>
                                <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[100px]">Status</th>
                                <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[70px]">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->vouchers as $v)
                                <tr wire:key="vch-{{ $v->voucher_id }}" wire:dblclick="openEdit({{ $v->voucher_id }})" class="hover:bg-[#f7faff] cursor-pointer" title="Klik 2x untuk buka">
                                    <td class="px-3 py-2 border-b border-[#eef1f5]">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-8 h-8 rounded-md bg-gradient-to-br {{ $this->avatarGradient($v->voucher_id) }} text-white text-[11px] font-bold flex items-center justify-center shrink-0">{{ $this->avatarInitials($v->partner_name ?: $v->voucher_type) }}</div>
                                            <div>
                                                <div class="font-semibold text-[13px]">{{ $v->partner_name ?: '—' }}</div>
                                                <div class="text-[11px] text-muted font-mono">{{ $v->voucher_number }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $v->entry_date?->translatedFormat('d M Y') }}</td>
                                    <td class="px-3 py-2 border-b border-[#eef1f5]">
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded {{ $this->typeBadgeClass($v->voucher_type) }}">{{ $v->voucher_type }}</span>
                                    </td>
                                    <td class="px-3 py-2 border-b border-[#eef1f5] text-[11px]">{{ $v->mainAccount?->account_code }} — {{ $v->mainAccount?->account_name }}</td>
                                    <td class="px-3 py-2 border-b border-[#eef1f5] text-right font-semibold">{{ number_format((float) $v->lines->sum('debit'), 0, ',', '.') }}</td>
                                    <td class="px-3 py-2 border-b border-[#eef1f5]">
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $this->statusBadgeClass($v->status) }}">{{ $v->status }}</span>
                                    </td>
                                    <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                        <button type="button" wire:click.stop="openEdit({{ $v->voucher_id }})" title="Buka" class="inline-flex items-center justify-center w-7 h-7 rounded-md text-accent hover:bg-[#eef3fb] cursor-pointer">↗</button>
                                        @if ($v->status === 'DRAFT')
                                            <button type="button" wire:click.stop="postDoc({{ $v->voucher_id }})" wire:confirm="Posting {{ $v->voucher_number }}? Setelah posting tidak bisa diedit lagi." title="Post Jurnal" class="inline-flex items-center justify-center w-7 h-7 rounded-md text-[#16a34a] hover:bg-[#e9f9ef] cursor-pointer">✓</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center py-8 text-muted text-sm">Belum ada jurnal Kas &amp; Bank.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            {{-- ============ FORM ============ --}}
            <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
                <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold flex items-center gap-2">
                    <span>{{ $editingId ? 'Edit Jurnal · '.$doc_no : 'Jurnal Kas & Bank Baru' }}</span>
                    @if ($editingId)
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $this->statusBadgeClass($status) }}">{{ $status }}</span>
                    @endif
                    <button type="button" wire:click="backToGrid" class="ml-auto bg-[#43508a] text-[#cfd6ef] text-xs px-3.5 py-1.5 rounded-md cursor-pointer hover:brightness-110">← Daftar</button>
                </div>

                <div class="p-4 flex flex-col gap-3">
                    @if ($readOnly)
                        <div class="text-xs px-3 py-2.5 rounded-md bg-[#fff4e5] border border-[#ffd9a0] text-[#8a4b00]">🔒 Jurnal sudah {{ $status === 'POSTED' ? 'diposting' : 'dibatalkan' }} — lihat-saja, tidak bisa diedit.</div>
                    @endif
                    @if ($errorMessage)
                        <div class="text-xs px-3 py-2.5 rounded-md bg-[#fdecec] border border-[#f5c6c6] text-[#a3453f]">⚠ {{ $errorMessage }}</div>
                    @endif

                    <div class="text-xs font-bold text-navy uppercase tracking-wide">Header</div>
                    <div class="grid grid-cols-4 gap-3">
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">No. Dokumen</label>
                            <input type="text" value="{{ $doc_no ?: '(otomatis saat simpan)' }}" disabled class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px] font-mono bg-[#f4f6fa] text-muted">
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Tipe Transaksi *</label>
                            <select wire:model.live="voucher_type" @disabled($readOnly) class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px] disabled:bg-[#f4f6fa]">
                                <option value="BKM">Bukti Kas Masuk (BKM)</option>
                                <option value="BKK">Bukti Kas Keluar (BKK)</option>
                                <option value="BBM">Bukti Bank Masuk (BBM)</option>
                                <option value="BBK">Bukti Bank Keluar (BBK)</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Tanggal Pembukuan *</label>
                            <input type="date" wire:model="entry_date" @disabled($readOnly) class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px] disabled:bg-[#f4f6fa]">
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Akun Kas/Bank Utama *</label>
                            <select wire:model.live="main_account_id" @disabled($readOnly) class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px] disabled:bg-[#f4f6fa]">
                                <option value="">— pilih —</option>
                                @foreach ($this->cashBankAccounts as $a)
                                    <option value="{{ $a->account_id }}">{{ $a->account_code }} — {{ $a->account_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Mitra / Penerima</label>
                            <input type="text" wire:model="partner_name" @disabled($readOnly) placeholder="Nama perusahaan / individu" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px] disabled:bg-[#f4f6fa]">
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Referensi / No. Cek / Giro</label>
                            <input type="text" wire:model="reference_no" @disabled($readOnly) class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px] disabled:bg-[#f4f6fa]">
                        </div>
                    </div>
                </div>

                <div class="px-4">
                    <div class="flex items-center gap-1 border-b border-[#eef1f5]">
                        <button type="button" wire:click="$set('activeTab', 'items')" class="text-xs font-semibold px-3.5 py-2.5 border-b-2 {{ $activeTab === 'items' ? 'border-accent text-accent' : 'border-transparent text-muted' }}">Rincian Jurnal</button>
                        <button type="button" wire:click="$set('activeTab', 'tax')" class="text-xs font-semibold px-3.5 py-2.5 border-b-2 {{ $activeTab === 'tax' ? 'border-accent text-accent' : 'border-transparent text-muted' }}">Informasi Perpajakan</button>
                        <button type="button" wire:click="$set('activeTab', 'audit')" class="text-xs font-semibold px-3.5 py-2.5 border-b-2 {{ $activeTab === 'audit' ? 'border-accent text-accent' : 'border-transparent text-muted' }}">Audit Trail</button>
                    </div>
                </div>

                <div class="p-4">
                    @if ($activeTab === 'items')
                        <div class="overflow-x-auto border border-[#eef1f5] rounded-md">
                            <table class="w-full text-[12.5px] border-collapse">
                                <thead>
                                    <tr class="bg-[#fafbfd]">
                                        <th class="text-left px-2 py-1.5 text-[10.5px] text-muted uppercase border-b border-[#eef1f5] w-[26%]">Kode &amp; Nama Akun (COA)</th>
                                        <th class="text-left px-2 py-1.5 text-[10.5px] text-muted uppercase border-b border-[#eef1f5]">Keterangan</th>
                                        <th class="text-right px-2 py-1.5 text-[10.5px] text-muted uppercase border-b border-[#eef1f5] w-[130px]">Debit (IDR)</th>
                                        <th class="text-right px-2 py-1.5 text-[10.5px] text-muted uppercase border-b border-[#eef1f5] w-[130px]">Kredit (IDR)</th>
                                        @if (! $readOnly)
                                            <th class="w-[40px] border-b border-[#eef1f5]"></th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($lines as $i => $line)
                                        <tr wire:key="line-{{ $i }}" class="border-b border-[#eef1f5]">
                                            <td class="px-2 py-1.5">
                                                <select wire:model="lines.{{ $i }}.account_id" @disabled($readOnly) class="w-full px-1.5 py-1 border border-[#d7dbe2] rounded text-xs disabled:bg-transparent disabled:border-transparent">
                                                    <option value="">— pilih akun —</option>
                                                    @foreach ($this->postableAccounts as $a)
                                                        <option value="{{ $a->account_id }}" @selected((int) ($line['account_id'] ?? 0) === $a->account_id)>{{ $a->account_code }} — {{ $a->account_name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td class="px-2 py-1.5"><input type="text" wire:model="lines.{{ $i }}.desc" @disabled($readOnly) class="w-full px-1.5 py-1 border border-[#d7dbe2] rounded text-xs disabled:bg-transparent disabled:border-transparent"></td>
                                            <td class="px-2 py-1.5"><input type="number" step="0.01" wire:model.live="lines.{{ $i }}.debit" @disabled($readOnly) class="w-full px-1.5 py-1 border border-[#d7dbe2] rounded text-xs text-right disabled:bg-transparent disabled:border-transparent"></td>
                                            <td class="px-2 py-1.5"><input type="number" step="0.01" wire:model.live="lines.{{ $i }}.credit" @disabled($readOnly) class="w-full px-1.5 py-1 border border-[#d7dbe2] rounded text-xs text-right disabled:bg-transparent disabled:border-transparent"></td>
                                            @if (! $readOnly)
                                                <td class="px-2 py-1.5 text-center"><button type="button" wire:click="removeLine({{ $i }})" class="text-[#c0392b] cursor-pointer" title="Hapus baris">✕</button></td>
                                            @endif
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center py-6 text-muted text-xs">Belum ada baris jurnal.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if (! $readOnly)
                            <button type="button" wire:click="addLine" class="mt-2 text-[11px] font-bold text-white bg-accent rounded px-3 py-1.5">+ Tambah Baris Jurnal</button>
                        @endif

                        <div class="flex justify-end gap-6 bg-[#fafbfd] rounded-md px-4 py-2.5 mt-3 text-sm font-semibold">
                            <div><span class="text-muted font-normal mr-1.5">Total Debit:</span>{{ number_format($this->totalDebit, 0, ',', '.') }}</div>
                            <div><span class="text-muted font-normal mr-1.5">Total Kredit:</span>{{ number_format($this->totalCredit, 0, ',', '.') }}</div>
                            <div class="{{ abs($this->totalDebit - $this->totalCredit) < 0.01 ? 'text-[#16a34a]' : 'text-[#c0392b]' }}"><span class="text-muted font-normal mr-1.5">Selisih:</span>{{ number_format(abs($this->totalDebit - $this->totalCredit), 0, ',', '.') }}</div>
                        </div>
                    @elseif ($activeTab === 'tax')
                        <div class="grid grid-cols-4 gap-3">
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold text-[#556]">Perlakuan PPN</label>
                                <select wire:model="ppn_type" @disabled($readOnly) class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px] disabled:bg-[#f4f6fa]">
                                    <option value="NONE">Non-PPN</option>
                                    <option value="MASUKAN_11">PPN Masukan 11%</option>
                                    <option value="KELUARAN_11">PPN Keluaran 11%</option>
                                </select>
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold text-[#556]">Nomor Faktur Pajak (e-Faktur)</label>
                                <input type="text" wire:model="efaktur_no" @disabled($readOnly) placeholder="010.003-25.00000000" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px] disabled:bg-[#f4f6fa]">
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold text-[#556]">Jenis Pemotongan PPh</label>
                                <select wire:model="pph_type" @disabled($readOnly) class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px] disabled:bg-[#f4f6fa]">
                                    <option value="NONE">Tanpa PPh</option>
                                    <option value="PPH23_2">PPh Pasal 23 (2% - Jasa)</option>
                                    <option value="PPH4_2_10">PPh Pasal 4(2) (10% - Sewa/Bangunan)</option>
                                    <option value="PPH21">PPh Pasal 21 (Bukan Pegawai)</option>
                                </select>
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold text-[#556]">Nomor Bukti Potong PPh</label>
                                <input type="text" wire:model="bupot_no" @disabled($readOnly) placeholder="BP-2025-0001928" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px] disabled:bg-[#f4f6fa]">
                            </div>
                        </div>
                    @else
                        <div class="grid grid-cols-3 gap-3">
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold text-[#556]">Dibuat Oleh</label>
                                <input type="text" readonly value="{{ $this->editingVoucher?->created_by ?? Auth::user()->login }}" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px] bg-[#f4f6fa] text-muted">
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold text-[#556]">Diposting Oleh</label>
                                <input type="text" readonly value="{{ $this->editingVoucher?->posted_by ?? '—' }}" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px] bg-[#f4f6fa] text-muted">
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold text-[#556]">Tgl. Posting</label>
                                <input type="text" readonly value="{{ $this->editingVoucher?->posted_at?->translatedFormat('d M Y, H:i') ?? '—' }}" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px] bg-[#f4f6fa] text-muted">
                            </div>
                        </div>
                    @endif
                </div>

                @if (! $readOnly)
                    <div class="flex gap-2 justify-end px-4 pb-4 pt-2 border-t border-[#eef1f5]">
                        @if ($editingId)
                            <button type="button" wire:click="cancelDoc" wire:confirm="Batalkan draft ini?" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#fdecec] text-[#c0392b] cursor-pointer">Batalkan</button>
                        @endif
                        <button type="button" wire:click="saveDraft" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">💾 Simpan Draft</button>
                        @if ($editingId)
                            <button type="button" wire:click="postDoc(null)" wire:confirm="Post jurnal ini? Setelah posting tidak bisa diedit lagi." class="px-4 py-2 rounded-md text-xs font-semibold bg-accent text-white cursor-pointer">✓ Post Jurnal</button>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
