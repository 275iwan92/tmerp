<?php

use App\Models\Acc\FinAccount;
use App\Models\Acc\FinVoucher;
use App\Models\Acc\FinVoucherLine;
use App\Services\Acc\JournalPostingService;
use App\Support\Localization\Translator;
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

        $voucher->update(['status' => 'CANCELLED', 'stat_cancel' => true, 'updated_by' => Auth::user()->login]);
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

    // Componentization grid+form (2026-08-27): avatarInitials()/avatarGradient()
    // dipindah ke App\Support\Grid\Avatar (dipakai <x-grid.identity-cell>).
};
?>

<div>
    <div class="max-w-[1400px] mx-auto my-5 px-4">

        <x-alert.banner type="success" :message="session('flash')" />
        <x-alert.banner type="danger" :message="session('error')" />

        @if ($panel === 'grid')
            <x-grid.stat-row :count="3">
                <x-grid.stat-box tone="total" label="{{ Translator::t('fin_cb_stat_total', 'Total Voucher') }}" :value="$this->statTotal" />
                <x-grid.stat-box tone="warn" label="{{ Translator::t('sales_quo_stat_draft', 'Draft') }}" :value="$this->statDraft" />
                <x-grid.stat-box tone="active" label="{{ Translator::t('inv_stat_posted', 'Sudah Posting') }}" :value="$this->statPosted" />
            </x-grid.stat-row>

            <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
                <x-grid.toolbar>
                    <div class="flex items-center gap-1.5 shrink-0">
                        <span class="text-sm">🔍</span>
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ Translator::t('fin_cb_search_placeholder', 'Cari no. bukti / tipe / mitra…') }}"
                            class="w-64 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                    </div>
                    <x-slot:newRecord>
                        <button type="button" wire:click="openNew"
                            class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
                    </x-slot:newRecord>
                </x-grid.toolbar>

                <x-grid.table-shell>
                    <table class="w-full border-collapse text-[12.5px]">
                        <thead>
                            <tr>
                                <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ Translator::t('fin_cb_col_partner_no', 'Mitra / No. Bukti') }}</th>
                                <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">{{ Translator::t('proc_col_date', 'Tanggal') }}</th>
                                <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[80px]">{{ Translator::t('proc_po_col_type', 'Tipe') }}</th>
                                <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[180px]">{{ Translator::t('fin_cb_col_cash_bank_account', 'Akun Kas/Bank') }}</th>
                                <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[140px]">{{ Translator::t('proc_pq_col_total_idr', 'Total (IDR)') }}</th>
                                <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[100px]">{{ Translator::t('proc_col_status', 'Status') }}</th>
                                <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[70px]">{{ Translator::t('proc_col_action', 'Aksi') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->vouchers as $v)
                                <tr wire:key="vch-{{ $v->voucher_id }}" wire:dblclick="openEdit({{ $v->voucher_id }})" class="hover:bg-[#f7faff] cursor-pointer" title="{{ Translator::t('shared_grid_dblclick_hint', 'Klik 2x untuk buka') }}">
                                    <x-grid.identity-cell :id="$v->voucher_id" :name="$v->partner_name ?: '—'" :code="$v->voucher_number" />
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
                                        <x-grid.action-icon icon="fa-up-right-from-square" title="{{ Translator::t('shared_grid_action_open', 'Buka') }}" action="openEdit({{ $v->voucher_id }})" />
                                        @if ($v->status === 'DRAFT')
                                            <x-grid.action-icon icon="fa-check" title="{{ Translator::t('fin_cb_action_post', 'Post Jurnal') }}" action="postDoc({{ $v->voucher_id }})"
                                                confirm="{{ Translator::t('inv_confirm_posting_prefix', 'Posting') }} {{ $v->voucher_number }}? {{ Translator::t('fin_cb_confirm_posting_suffix', 'Setelah posting tidak bisa diedit lagi.') }}" variant="success" />
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <x-grid.empty-row :colspan="7" message="{{ Translator::t('fin_cb_empty', 'Belum ada jurnal Kas & Bank.') }}" />
                            @endforelse
                        </tbody>
                    </table>
                </x-grid.table-shell>
            </div>
        @else
            {{-- ============ FORM ============ --}}
            <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
                <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold flex items-center gap-2">
                    <span>{{ $editingId ? Translator::t('fin_cb_edit_prefix', 'Edit Jurnal ·').' '.$doc_no : Translator::t('fin_cb_new_title', 'Jurnal Kas & Bank Baru') }}</span>
                    @if ($editingId)
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $this->statusBadgeClass($status) }}">{{ $status }}</span>
                    @endif
                    <button type="button" wire:click="backToGrid" data-dirty-guard title="{{ Translator::t('inv_back_to_list_title', 'Kembali ke Daftar') }}"
                        class="ml-auto inline-flex items-center gap-2 pl-3.5 pr-1 py-1 bg-[#43508a] rounded-full cursor-pointer hover:brightness-110">
                        <span class="text-[11px] font-bold uppercase tracking-wide text-white">Back</span>
                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-white text-[#43508a] text-[13px]"><i class="fa-solid fa-arrow-left"></i></span>
                    </button>
                </div>

                <div class="p-4 flex flex-col gap-3">
                    @if ($readOnly)
                        <div class="text-xs px-3 py-2.5 rounded-md bg-[#fff4e5] border border-[#ffd9a0] text-[#8a4b00]">🔒 {{ Translator::t('fin_cb_readonly_prefix', 'Jurnal sudah') }} {{ $status === 'POSTED' ? Translator::t('inv_readonly_posted', 'diposting') : Translator::t('inv_readonly_cancelled', 'dibatalkan') }} {{ Translator::t('fin_cb_readonly_suffix', '— lihat-saja, tidak bisa diedit.') }}</div>
                    @endif
                    @if ($errorMessage)
                        <div class="text-xs px-3 py-2.5 rounded-md bg-[#fdecec] border border-[#f5c6c6] text-[#a3453f]">⚠ {{ $errorMessage }}</div>
                    @endif

                    <div class="text-xs font-bold text-navy uppercase tracking-wide">{{ Translator::t('inv_section_header', 'Header') }}</div>
                    <div class="grid">
                        <x-form.input label="{{ Translator::t('inv_field_doc_no', 'No. Dokumen') }}" value="{{ $doc_no ?: Translator::t('inv_field_doc_no_auto', '(otomatis saat simpan)') }}" disabled class="font-mono" />
                        <x-form.select label="{{ Translator::t('fin_cb_field_voucher_type', 'Tipe Transaksi *') }}" wire:model.live="voucher_type" :disabled="$readOnly" :placeholder="false"
                            :options="['BKM' => Translator::t('fin_cb_type_bkm', 'Bukti Kas Masuk (BKM)'), 'BKK' => Translator::t('fin_cb_type_bkk', 'Bukti Kas Keluar (BKK)'), 'BBM' => Translator::t('fin_cb_type_bbm', 'Bukti Bank Masuk (BBM)'), 'BBK' => Translator::t('fin_cb_type_bbk', 'Bukti Bank Keluar (BBK)')]" />
                        <x-form.date-picker label="{{ Translator::t('fin_cb_field_entry_date', 'Tanggal Pembukuan *') }}" wire:model="entry_date" :disabled="$readOnly" />
                        <x-form.select label="{{ Translator::t('fin_cb_field_main_account', 'Akun Kas/Bank Utama *') }}" wire:model.live="main_account_id" :disabled="$readOnly"
                            :options="$this->cashBankAccounts->mapWithKeys(fn ($a) => [$a->account_id => $a->account_code.' — '.$a->account_name])" :selected="$main_account_id" />
                        <x-form.input label="{{ Translator::t('fin_cb_field_partner', 'Mitra / Penerima') }}" wire:model="partner_name" :disabled="$readOnly" placeholder="{{ Translator::t('fin_cb_field_partner_placeholder', 'Nama perusahaan / individu') }}" />
                        <x-form.input label="{{ Translator::t('fin_cb_field_reference', 'Referensi / No. Cek / Giro') }}" wire:model="reference_no" :disabled="$readOnly" />
                    </div>
                </div>

                <div class="px-4">
                    <div class="flex items-center gap-1 border-b border-[#eef1f5]">
                        <button type="button" wire:click="$set('activeTab', 'items')" class="text-xs font-semibold px-3.5 py-2.5 border-b-2 {{ $activeTab === 'items' ? 'border-accent text-accent' : 'border-transparent text-muted' }}">{{ Translator::t('fin_cb_tab_items', 'Rincian Jurnal') }}</button>
                        <button type="button" wire:click="$set('activeTab', 'tax')" class="text-xs font-semibold px-3.5 py-2.5 border-b-2 {{ $activeTab === 'tax' ? 'border-accent text-accent' : 'border-transparent text-muted' }}">{{ Translator::t('fin_cb_tab_tax', 'Informasi Perpajakan') }}</button>
                        <button type="button" wire:click="$set('activeTab', 'audit')" class="text-xs font-semibold px-3.5 py-2.5 border-b-2 {{ $activeTab === 'audit' ? 'border-accent text-accent' : 'border-transparent text-muted' }}">{{ Translator::t('fin_cb_tab_audit', 'Audit Trail') }}</button>
                    </div>
                </div>

                <div class="p-4">
                    @if ($activeTab === 'items')
                        <div class="overflow-x-auto border border-[#eef1f5] rounded-md">
                            <table class="w-full text-[12.5px] border-collapse">
                                <thead>
                                    <tr class="bg-[#fafbfd]">
                                        <th class="text-left px-2 py-1.5 text-[10.5px] text-muted uppercase border-b border-[#eef1f5] w-[26%]">{{ Translator::t('fin_cb_col_account_coa', 'Kode & Nama Akun (COA)') }}</th>
                                        <th class="text-left px-2 py-1.5 text-[10.5px] text-muted uppercase border-b border-[#eef1f5]">{{ Translator::t('fin_cb_col_description', 'Keterangan') }}</th>
                                        <th class="text-right px-2 py-1.5 text-[10.5px] text-muted uppercase border-b border-[#eef1f5] w-[130px]">{{ Translator::t('fin_cb_col_debit', 'Debit (IDR)') }}</th>
                                        <th class="text-right px-2 py-1.5 text-[10.5px] text-muted uppercase border-b border-[#eef1f5] w-[130px]">{{ Translator::t('fin_cb_col_credit', 'Kredit (IDR)') }}</th>
                                        @if (! $readOnly)
                                            <th class="w-[40px] border-b border-[#eef1f5]"></th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($lines as $i => $line)
                                        <tr wire:key="line-{{ $i }}" class="border-b border-[#eef1f5]">
                                            <td class="px-2 py-1.5">
                                                <x-form.select wire:model="lines.{{ $i }}.account_id" :disabled="$readOnly" placeholder="{{ Translator::t('fin_cb_select_account_placeholder', '— pilih akun —') }}" class="!py-1"
                                                    :options="$this->postableAccounts->mapWithKeys(fn ($a) => [$a->account_id => $a->account_code.' — '.$a->account_name])" :selected="$line['account_id'] ?? null" />
                                            </td>
                                            <td class="px-2 py-1.5"><x-form.input wire:model="lines.{{ $i }}.desc" :disabled="$readOnly" class="!py-1" /></td>
                                            <td class="px-2 py-1.5"><x-form.input type="number" step="0.01" wire:model.live="lines.{{ $i }}.debit" :disabled="$readOnly" class="!py-1 text-right" /></td>
                                            <td class="px-2 py-1.5"><x-form.input type="number" step="0.01" wire:model.live="lines.{{ $i }}.credit" :disabled="$readOnly" class="!py-1 text-right" /></td>
                                            @if (! $readOnly)
                                                <td class="px-2 py-1.5 text-center"><button type="button" wire:click="removeLine({{ $i }})" class="text-[#c0392b] cursor-pointer" title="{{ Translator::t('inv_line_remove_title', 'Hapus baris') }}">✕</button></td>
                                            @endif
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center py-6 text-muted text-xs">{{ Translator::t('fin_cb_no_lines', 'Belum ada baris jurnal.') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if (! $readOnly)
                            <button type="button" wire:click="addLine" class="mt-2 text-[11px] font-bold text-white bg-accent rounded px-3 py-1.5">+ {{ Translator::t('fin_cb_btn_add_line', 'Tambah Baris Jurnal') }}</button>
                        @endif

                        <div class="flex justify-end gap-6 bg-[#fafbfd] rounded-md px-4 py-2.5 mt-3 text-sm font-semibold">
                            <div><span class="text-muted font-normal mr-1.5">{{ Translator::t('fin_cb_total_debit_label', 'Total Debit:') }}</span>{{ number_format($this->totalDebit, 0, ',', '.') }}</div>
                            <div><span class="text-muted font-normal mr-1.5">{{ Translator::t('fin_cb_total_credit_label', 'Total Kredit:') }}</span>{{ number_format($this->totalCredit, 0, ',', '.') }}</div>
                            <div class="{{ abs($this->totalDebit - $this->totalCredit) < 0.01 ? 'text-[#16a34a]' : 'text-[#c0392b]' }}"><span class="text-muted font-normal mr-1.5">{{ Translator::t('fin_cb_variance_label', 'Selisih:') }}</span>{{ number_format(abs($this->totalDebit - $this->totalCredit), 0, ',', '.') }}</div>
                        </div>
                    @elseif ($activeTab === 'tax')
                        <div class="grid">
                            <x-form.select label="{{ Translator::t('fin_cb_field_ppn_treatment', 'Perlakuan PPN') }}" wire:model="ppn_type" :disabled="$readOnly" :placeholder="false"
                                :options="['NONE' => Translator::t('fin_cb_ppn_none', 'Non-PPN'), 'MASUKAN_11' => Translator::t('fin_cb_ppn_in', 'PPN Masukan 11%'), 'KELUARAN_11' => Translator::t('fin_cb_ppn_out', 'PPN Keluaran 11%')]" />
                            <x-form.input label="{{ Translator::t('fin_cb_field_efaktur', 'Nomor Faktur Pajak (e-Faktur)') }}" wire:model="efaktur_no" :disabled="$readOnly" placeholder="010.003-25.00000000" />
                            <x-form.select label="{{ Translator::t('fin_cb_field_pph_type', 'Jenis Pemotongan PPh') }}" wire:model="pph_type" :disabled="$readOnly" :placeholder="false"
                                :options="['NONE' => Translator::t('fin_cb_pph_none', 'Tanpa PPh'), 'PPH23_2' => Translator::t('fin_cb_pph_23', 'PPh Pasal 23 (2% - Jasa)'), 'PPH4_2_10' => Translator::t('fin_cb_pph_4_2', 'PPh Pasal 4(2) (10% - Sewa/Bangunan)'), 'PPH21' => Translator::t('fin_cb_pph_21', 'PPh Pasal 21 (Bukan Pegawai)')]" />
                            <x-form.input label="{{ Translator::t('fin_cb_field_bupot', 'Nomor Bukti Potong PPh') }}" wire:model="bupot_no" :disabled="$readOnly" placeholder="BP-2025-0001928" />
                        </div>
                    @else
                        <div class="grid three">
                            <x-form.input label="{{ Translator::t('fin_cb_field_created_by', 'Dibuat Oleh') }}" readonly value="{{ $this->editingVoucher?->created_by ?? Auth::user()->login }}" />
                            <x-form.input label="{{ Translator::t('fin_cb_field_posted_by', 'Diposting Oleh') }}" readonly value="{{ $this->editingVoucher?->posted_by ?? '—' }}" />
                            <x-form.input label="{{ Translator::t('fin_cb_field_posted_at', 'Tgl. Posting') }}" readonly value="{{ $this->editingVoucher?->posted_at?->translatedFormat('d M Y, H:i') ?? '—' }}" />
                        </div>
                    @endif
                </div>

                @if (! $readOnly)
                    <div class="flex gap-2 justify-end px-4 pb-4 pt-2 border-t border-[#eef1f5]">
                        @if ($editingId)
                            <button type="button" wire:click="cancelDoc" wire:confirm="{{ Translator::t('fin_cb_confirm_cancel_draft', 'Batalkan draft ini?') }}" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#fdecec] text-[#c0392b] cursor-pointer">{{ Translator::t('sales_form_btn_cancel_doc', 'Batalkan') }}</button>
                        @endif
                        <button type="button" wire:click="saveDraft" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">💾 {{ Translator::t('inv_btn_save_draft', 'Simpan Draft') }}</button>
                        @if ($editingId)
                            <button type="button" wire:click="postDoc(null)" wire:confirm="{{ Translator::t('fin_cb_confirm_post', 'Post jurnal ini? Setelah posting tidak bisa diedit lagi.') }}" class="px-4 py-2 rounded-md text-xs font-semibold bg-accent text-white cursor-pointer">✓ {{ Translator::t('fin_cb_action_post', 'Post Jurnal') }}</button>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
