<?php

use App\Models\Sdi\IqpInquiry;
use App\Support\Localization\Translator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('TELASIH MAKMUR — Sistem Inquiry')] class extends Component
{
    #[Url]
    public string $fq = '';

    #[Url]
    public string $fst = '';

    #[Url]
    public string $ftype = '';

    public ?int $selectedId = null;

    // Pemilih produk existing (RECALC/REVISE "+ Buat data baru") — docs/mockups/inquiry_hub.html §prodsel.
    // Belum ada modul product/costing terpisah (Tier 1 CLAUDE.md gap), jadi sumbernya = inkuiri
    // tersimpan yang sudah punya no. dokumen (proksi terbaik utk "produk existing" saat ini).
    public ?string $pickerMode = null;

    public string $pickerQuery = '';

    public ?int $pickerSelectedId = null;

    private const TYPE_LABELS = [
        'NEW' => 'sales_hub_type_new',
        'RECALC' => 'sales_hub_type_recalc',
        'REVISE' => 'sales_hub_type_revise',
    ];

    public function pickSaved(string $type): void
    {
        $this->ftype = $type;
        $this->selectedId = null;
    }

    public function updatedFq(): void
    {
        $this->selectedId = null;
    }

    public function updatedFst(): void
    {
        $this->selectedId = null;
    }

    public function updatedFtype(): void
    {
        $this->selectedId = null;
    }

    public function selectRow(int $id): void
    {
        $this->selectedId = $id;
    }

    public function openSelected()
    {
        if (! $this->selectedId) {
            return;
        }

        return $this->openRow($this->selectedId);
    }

    // Klik 2x baris / ikon Aksi -- buka langsung tanpa lewat select+tombol
    // footer dulu (GRID_UI_STANDARD.md: klik 2x buka detail).
    public function openRow(int $id)
    {
        $type = IqpInquiry::whereKey($id)->value('inquiry_type');

        return match ($type) {
            'RECALC' => redirect()->route('inquiry.recalc', ['id' => $id]),
            'REVISE' => redirect()->route('inquiry.revise', ['id' => $id]),
            default => redirect()->route('inquiry.form', ['id' => $id]),
        };
    }

    private const TYPE_LABEL_DEFAULTS = [
        'NEW' => 'Produk Baru',
        'RECALC' => 'Hitung Ulang Harga',
        'REVISE' => 'Ubah Spesifikasi',
    ];

    public function typeLabel(string $type): string
    {
        $key = self::TYPE_LABELS[$type] ?? null;

        return $key ? Translator::t($key, self::TYPE_LABEL_DEFAULTS[$type]) : $type;
    }

    // ============== Stat box (griduistandard.md §2) ==============
    // Baris 1: backlog "belum confirm" per tipe (stat_confirm=0, CANCELLED/LOST
    // dikecualikan -- itu sudah selesai/mati, bukan lagi tunggakan) + total
    // tahun berjalan (by inquiry_date). Baris 2: backlog "sudah diberi harga
    // tapi belum ke customer" -- sub_state PRICE_APPROVED persis (Direksi sudah
    // publish, TAPI belum QUOTED) per tipe + total sudah di-approve Direksi
    // tahun ini (is_approved=1, beda dari sub_state krn is_approved tetap 1
    // walau sub_state sudah lanjut ke QUOTED/CONVERTED -- ini KPI kumulatif,
    // bukan backlog aktif spt 3 kotak sebelahnya).
    public function statUnconfirmedByType(string $type): int
    {
        return IqpInquiry::where('inquiry_type', $type)
            ->where('stat_confirm', false)
            ->whereNotIn('sub_state', ['CANCELLED', 'LOST'])
            ->count();
    }

    #[Computed]
    public function statTotalThisYear(): int
    {
        return IqpInquiry::whereYear('inquiry_date', now()->year)->count();
    }

    public function statPricedNotQuotedByType(string $type): int
    {
        return IqpInquiry::where('inquiry_type', $type)
            ->where('sub_state', 'PRICE_APPROVED')
            ->count();
    }

    #[Computed]
    public function statApprovedThisYear(): int
    {
        return IqpInquiry::whereYear('inquiry_date', now()->year)
            ->where('is_approved', true)
            ->count();
    }

    #[Computed]
    public function inquiries()
    {
        return IqpInquiry::query()
            ->with(['customer', 'tiers' => fn ($q) => $q->where('is_primary', true), 'parts' => fn ($q) => $q->orderBy('part_seq')->limit(1)])
            ->when($this->fst, fn ($q) => $q->where('sub_state', $this->fst))
            ->when($this->ftype, fn ($q) => $q->where('inquiry_type', $this->ftype))
            ->when($this->fq, function ($q) {
                $q->where(function ($q) {
                    $q->where('inquiry_no', 'like', "%{$this->fq}%")
                        ->orWhere('product_draft_name', 'like', "%{$this->fq}%")
                        ->orWhere('prospect_name', 'like', "%{$this->fq}%");
                });
            })
            ->orderByDesc('id_inquiry')
            ->limit(200)
            ->get();
    }

    public function statusPillClass(string $state): string
    {
        return match ($state) {
            'DRAFT' => 'draft',
            'WAITING_COST', 'SUBMITTED', 'PRICE_CALCULATED' => 'submit',
            'PRICE_APPROVED' => 'priceok',
            'QUOTED' => 'quoted',
            'PROVING', 'PROVE_APPROVED' => 'proving',
            'CONVERTED' => 'ok',
            'CANCELLED', 'LOST' => 'cancelled',
            default => 'draft',
        };
    }

    // ============== Pemilih produk existing (RECALC/REVISE) ==============

    public function openPicker(string $mode): void
    {
        $this->pickerMode = $mode;
        $this->pickerQuery = '';
        $this->pickerSelectedId = null;
    }

    public function closePicker(): void
    {
        $this->pickerMode = null;
        $this->pickerSelectedId = null;
    }

    public function selectPickerRow(int $id): void
    {
        $this->pickerSelectedId = $id;
    }

    public function confirmPicker()
    {
        if (! $this->pickerMode || ! $this->pickerSelectedId) {
            return;
        }

        $routeName = $this->pickerMode === 'RECALC' ? 'inquiry.recalc' : 'inquiry.revise';

        return redirect()->route($routeName, ['source_id' => $this->pickerSelectedId]);
    }

    #[Computed]
    public function pickerCandidates()
    {
        $q = trim($this->pickerQuery);

        return IqpInquiry::query()
            ->whereNotNull('inquiry_no')
            ->with(['customer', 'tiers' => fn ($q) => $q->where('is_primary', true), 'parts' => fn ($q) => $q->orderBy('part_seq')->limit(1)])
            ->when($q, function ($query) use ($q) {
                $query->where(function ($query) use ($q) {
                    $query->where('inquiry_no', 'like', "%{$q}%")
                        ->orWhere('product_draft_name', 'like', "%{$q}%")
                        ->orWhere('prospect_name', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('id_inquiry')
            ->limit(50)
            ->get();
    }
};
?>

<div>
    <div class="hub">
        {{-- shrink-0 -- .hub flex-col 100dvh (kompak 1-layar, 2026-08-24): cuma
        .qwrap yang boleh scroll/menyusut. 8 kotak statistik (2 baris) makan
        banyak tempat di layar sekompak ini -- dibuat bisa diciutkan (feedback
        user: "terlihat sempit", minta bisa di-minimize spy grid lebih penuh).
        Ingat pilihan user via localStorage (per-browser, bukan per-akun),
        default terbuka supaya tidak ada yang berubah kalau belum pernah disentuh. --}}
        <div class="shrink-0" x-data="{
                open: true,
                init() {
                    try {
                        const saved = localStorage.getItem('tmerp.inquiryHub.statOpen');
                        if (saved !== null) this.open = saved === '1';
                    } catch (e) {}
                },
                toggle() {
                    this.open = !this.open;
                    try { localStorage.setItem('tmerp.inquiryHub.statOpen', this.open ? '1' : '0'); } catch (e) {}
                },
            }">
            <button type="button" @click="toggle()"
                class="w-full flex items-center justify-between px-3 py-1.5 mb-1.5 rounded-md bg-[#f4f6fa] border border-[#e2e7ee] text-[11px] font-bold text-navy cursor-pointer">
                <span>📊 {{ Translator::t('sales_hub_stat_summary', 'Ringkasan') }}</span>
                <span class="text-muted font-semibold" x-text="open ? '▾ {{ Translator::t('sales_hub_stat_collapse', 'Ciutkan') }}' : '▸ {{ Translator::t('sales_hub_stat_expand', 'Tampilkan') }}'"></span>
            </button>
            <div x-show="open" x-cloak>
                <x-grid.stat-row :count="4">
                    <x-grid.stat-box tone="warn" label="{{ Translator::t('sales_hub_stat_new_unconfirmed', 'NEW belum confirm') }}" :value="$this->statUnconfirmedByType('NEW')" />
                    <x-grid.stat-box tone="warn" label="{{ Translator::t('sales_hub_stat_revise_unconfirmed', 'REVISE belum confirm') }}" :value="$this->statUnconfirmedByType('REVISE')" />
                    <x-grid.stat-box tone="warn" label="{{ Translator::t('sales_hub_stat_recalc_unconfirmed', 'RECALC belum confirm') }}" :value="$this->statUnconfirmedByType('RECALC')" />
                    <x-grid.stat-box tone="total" label="{{ Translator::t('sales_hub_stat_total_year', 'Total inquiry tahun ini') }}" :value="$this->statTotalThisYear" />
                </x-grid.stat-row>
                <x-grid.stat-row :count="4">
                    <x-grid.stat-box tone="extra" label="{{ Translator::t('sales_hub_stat_new_priced', 'NEW sudah harga, belum ke customer') }}" :value="$this->statPricedNotQuotedByType('NEW')" />
                    <x-grid.stat-box tone="extra" label="{{ Translator::t('sales_hub_stat_revise_priced', 'REVISE sudah harga, belum ke customer') }}" :value="$this->statPricedNotQuotedByType('REVISE')" />
                    <x-grid.stat-box tone="extra" label="{{ Translator::t('sales_hub_stat_recalc_priced', 'RECALC sudah harga, belum ke customer') }}" :value="$this->statPricedNotQuotedByType('RECALC')" />
                    <x-grid.stat-box tone="active" label="{{ Translator::t('sales_hub_stat_approved_year', 'Sudah di-approve tahun ini') }}" :value="$this->statApprovedThisYear" />
                </x-grid.stat-row>
            </div>
        </div>

        <div class="pick">
            <div class="opt n">
                <div class="ico">✨</div><span class="tag">NEW</span>
                <h4>{{ Translator::t('sales_hub_card_new_title', 'Produk Baru') }}</h4>
                <p>{{ Translator::t('sales_hub_card_new_desc', 'Belum pernah dibuat. Sales menentukan tipe produk, dimensi, bahan, dan finishing dari nol.') }}</p>
                <div class="req">{!! Translator::t('sales_hub_card_new_req', 'Perlu: <b>tipe produk + dimensi + bahan</b><br>Alur: Sales → Design → Costing') !!}</div>
                <div class="optbtns">
                    <button type="button" class="bsaved" wire:click="pickSaved('NEW')" onclick="document.getElementById('saved-box')?.scrollIntoView({behavior:'smooth',block:'start'})">📂 {{ Translator::t('sales_hub_btn_pick_saved', 'Pilih data tersimpan') }}</button>
                    <a class="bnew bn-n" href="{{ route('inquiry.form', ['mode' => 'NEW']) }}">＋ {{ Translator::t('sales_hub_btn_create_new', 'Buat data baru') }}</a>
                </div>
            </div>

            <div class="opt r">
                <div class="ico">🔁</div><span class="tag">RECALC</span>
                <h4>{{ Translator::t('sales_hub_card_recalc_title', 'Hitung Ulang Harga') }}</h4>
                <p>{{ Translator::t('sales_hub_card_recalc_desc', 'Produk & layout sudah ada, tidak ada yang berubah. Hanya perlu harga baru.') }}</p>
                <div class="req">{!! Translator::t('sales_hub_card_recalc_req', 'Perlu: <b>pilih produk existing + qty</b><br>Alur: Sales → Costing <span style="color:#177a4b">(lewati Design)</span>') !!}</div>
                <div class="optbtns">
                    <button type="button" class="bsaved" wire:click="pickSaved('RECALC')" onclick="document.getElementById('saved-box')?.scrollIntoView({behavior:'smooth',block:'start'})">📂 {{ Translator::t('sales_hub_btn_pick_saved', 'Pilih data tersimpan') }}</button>
                    <button type="button" class="bnew bn-r" wire:click="openPicker('RECALC')">＋ {{ Translator::t('sales_hub_btn_create_new', 'Buat data baru') }}</button>
                </div>
            </div>

            <div class="opt v">
                <div class="ico">✏️</div><span class="tag">REVISE</span>
                <h4>{{ Translator::t('sales_hub_card_revise_title', 'Ubah Spesifikasi') }}</h4>
                <p>{{ Translator::t('sales_hub_card_revise_desc', 'Produk sudah ada tapi ada yang berubah: dimensi, bahan, warna, atau finishing.') }}</p>
                <div class="req">{!! Translator::t('sales_hub_card_revise_req', 'Perlu: <b>pilih produk existing + apa yang berubah</b><br>Alur: Sales → Design → Costing') !!}</div>
                <div class="optbtns">
                    <button type="button" class="bsaved" wire:click="pickSaved('REVISE')" onclick="document.getElementById('saved-box')?.scrollIntoView({behavior:'smooth',block:'start'})">📂 {{ Translator::t('sales_hub_btn_pick_saved', 'Pilih data tersimpan') }}</button>
                    <button type="button" class="bnew bn-v" wire:click="openPicker('REVISE')">＋ {{ Translator::t('sales_hub_btn_create_new', 'Buat data baru') }}</button>
                </div>
            </div>
        </div>

        @if ($pickerMode)
            <div class="prodsel on" wire:key="prodsel">
                <div class="hd">
                    <span>{{ $pickerMode === 'RECALC' ? '🔁' : '✏️' }}</span>
                    <span>{{ Translator::t('sales_hub_picker_title', 'Pilih produk existing —') }} <b>{{ $pickerMode }}</b></span>
                    <span class="x" wire:click="closePicker">×</span>
                </div>
                <div class="bd">
                    <div class="note {{ $pickerMode === 'RECALC' ? 'b' : 'o' }}">
                        @if ($pickerMode === 'RECALC')
                            {!! Translator::t('sales_hub_picker_note_recalc', 'Semua spesifikasi akan disalin dari inkuiri terpilih dan <b>dikunci</b>. Anda hanya mengisi qty & deadline.') !!}
                        @else
                            {!! Translator::t('sales_hub_picker_note_revise', 'Spesifikasi disalin lalu <b>bisa diubah</b>. Tipe produk tetap terkunci — kalau tipe berubah, gunakan <b>NEW</b>.') !!}
                        @endif
                    </div>
                    <div class="srch">
                        <input type="text" wire:model.live.debounce.400ms="pickerQuery" placeholder="{{ Translator::t('sales_hub_picker_search_placeholder', 'Cari no. inkuiri / customer / produk…') }}">
                    </div>
                    <div class="plist">
                        @forelse ($this->pickerCandidates as $row)
                            <div class="prow {{ $pickerSelectedId === $row->id_inquiry ? 'sel' : '' }}" wire:key="prow-{{ $row->id_inquiry }}" wire:click="selectPickerRow({{ $row->id_inquiry }})">
                                <div class="th">{{ $row->parts->first()?->part_type ? mb_substr($row->parts->first()->part_type, 0, 9) : '—' }}</div>
                                <div class="mn">
                                    <b>{{ $row->product_draft_name ?: Translator::t('sales_hub_no_product_name', '(tanpa nama produk)') }}</b>
                                    <span>{{ $row->is_prospect ? $row->prospect_name : ($row->customer?->customer_name ?? '—') }} · {{ $row->parts->first()?->part_type }} · {{ Translator::t('sales_hub_qty_label', 'qty') }} {{ $row->tiers->first()?->qty ? number_format((float) $row->tiers->first()->qty, 0, ',', '.') : '—' }}</span>
                                </div>
                                <div class="mt"><code>{{ $row->inquiry_no }}</code><br>{{ $row->inquiry_date?->format('Y-m-d') }} · <span class="pill {{ $this->statusPillClass($row->sub_state) }}">{{ $row->sub_state }}</span></div>
                            </div>
                        @empty
                            <div class="qempty">{{ Translator::t('sales_hub_picker_empty', 'Tidak ada produk/inkuiri yang cocok.') }}</div>
                        @endforelse
                    </div>
                    <div class="act">
                        <div class="hint">
                            @php($pickerRow = $pickerSelectedId ? $this->pickerCandidates->firstWhere('id_inquiry', $pickerSelectedId) : null)
                            @if ($pickerRow)
                                {{ Translator::t('sales_hub_picker_selected', 'Terpilih') }} <b>{{ $pickerRow->inquiry_no }}</b> — {{ $pickerRow->product_draft_name }}
                            @else
                                {{ Translator::t('sales_hub_picker_pick_hint', 'Pilih satu baris untuk melanjutkan.') }}
                            @endif
                        </div>
                        <div class="sp"></div>
                        <button type="button" class="btn g" wire:click="closePicker">{{ Translator::t('shared_btn_cancel', 'Batal') }}</button>
                        <button type="button" class="btn p" wire:click="confirmPicker" @disabled(! $pickerRow)>{{ $pickerRow ? Translator::t('sales_hub_picker_continue', 'Lanjut →').' '.$pickerRow->inquiry_no : Translator::t('sales_hub_picker_pick_first', 'Pilih produk dulu') }}</button>
                    </div>
                </div>
            </div>
        @endif

        <div class="openbox" id="saved-box" wire:key="saved-box">
            <div class="hd">📂 <span>{{ Translator::t('sales_hub_saved_title', 'Inkuiri Tersimpan') }}{{ $ftype ? ' — '.$this->typeLabel($ftype) : '' }}</span>
                <span class="cnt">{{ $this->inquiries->count() }} {{ Translator::t('sales_hub_saved_count_suffix', 'inkuiri') }}</span></div>
            <div class="bd">
                <div class="filters">
                    <input type="text" wire:model.live.debounce.400ms="fq" placeholder="{{ Translator::t('sales_hub_saved_search_placeholder', 'Cari no. inkuiri / nama produk / prospek…') }}">
                    <div class="w-[170px] shrink-0">
                        <select wire:model.live="fst">
                            <option value="">{{ Translator::t('sales_hub_filter_all_status', 'Semua status') }}</option>
                            <option value="DRAFT">DRAFT</option>
                            <option value="WAITING_COST">WAITING_COST</option>
                            <option value="SUBMITTED">SUBMITTED</option>
                            <option value="PRICE_CALCULATED">PRICE_CALCULATED</option>
                            <option value="PRICE_APPROVED">PRICE_APPROVED</option>
                            <option value="QUOTED">QUOTED</option>
                            <option value="PROVING">PROVING</option>
                            <option value="PROVE_APPROVED">PROVE_APPROVED</option>
                            <option value="CONVERTED">CONVERTED</option>
                            <option value="CANCELLED">CANCELLED</option>
                            <option value="LOST">LOST</option>
                        </select>
                    </div>
                    <div class="w-[140px] shrink-0">
                        <select wire:model.live="ftype">
                            <option value="">{{ Translator::t('sales_hub_filter_all_type', 'Semua tipe') }}</option>
                            <option value="NEW">NEW</option>
                            <option value="RECALC">RECALC</option>
                            <option value="REVISE">REVISE</option>
                        </select>
                    </div>
                </div>

                <div class="qwrap">
                    <table class="qtbl">
                        <thead>
                            <tr>
                                <th>{{ Translator::t('sales_hub_col_product_customer', 'Produk / Customer') }}</th>
                                <th>{{ Translator::t('sales_hub_col_inquiry_no', 'No. Inkuiri') }}</th><th>{{ Translator::t('sales_hub_col_date', 'Tgl') }}</th>
                                <th class="hide-sm">{{ Translator::t('sales_hub_col_product_type', 'Tipe Produk') }}</th>
                                <th style="text-align:right">{{ Translator::t('sales_hub_col_qty_tier', 'Qty tier utama') }}</th>
                                <th>{{ Translator::t('sales_hub_col_status', 'Status') }}</th><th>{{ Translator::t('sales_hub_col_type', 'Jenis') }}</th>
                                <th style="text-align:center; width:1%">{{ Translator::t('sales_hub_col_action', 'Aksi') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->inquiries as $row)
                                @php($custName = $row->is_prospect ? $row->prospect_name : ($row->customer?->customer_name ?? '—'))
                                <tr wire:key="inq-{{ $row->id_inquiry }}" class="{{ $selectedId === $row->id_inquiry ? 'sel' : '' }}"
                                    wire:click="selectRow({{ $row->id_inquiry }})" wire:dblclick="openRow({{ $row->id_inquiry }})" title="Klik 2x untuk buka">
                                    <td>
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-8 h-8 rounded-md bg-gradient-to-br {{ \App\Support\Grid\Avatar::gradient($row->id_inquiry) }} text-white text-[11px] font-bold flex items-center justify-center shrink-0">{{ \App\Support\Grid\Avatar::initials($row->product_draft_name ?: $custName) }}</div>
                                            <div>
                                                <div class="font-semibold text-[13px] text-navy">{{ $row->product_draft_name ?: Translator::t('sales_hub_no_product_name', '(tanpa nama produk)') }}</div>
                                                <div class="text-[11px] text-muted">
                                                    {{ $custName }}
                                                    @if ($row->is_prospect)
                                                        <span class="pill prospect">{{ Translator::t('sales_hub_prospect_pill', 'prospek') }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="qdoc">{{ $row->inquiry_no ?: Translator::t('sales_hub_no_doc_number', '(draft, belum ada no.)') }}</span></td>
                                    <td>{{ $row->inquiry_date?->format('Y-m-d') }}</td>
                                    <td class="hide-sm">{{ $row->parts->first()?->part_type ?? '—' }}</td>
                                    <td style="text-align:right">
                                        {{ $row->tiers->first()?->qty ? number_format((float) $row->tiers->first()->qty, 0, ',', '.') : '—' }}
                                    </td>
                                    <td><span class="pill {{ $this->statusPillClass($row->sub_state) }}">{{ $row->sub_state }}</span></td>
                                    <td><span class="pill {{ strtolower($row->inquiry_type) }}">{{ $row->inquiry_type }}</span></td>
                                    <td style="text-align:center">
                                        <button type="button" wire:click.stop="openRow({{ $row->id_inquiry }})"
                                            title="{{ $row->sub_state === 'DRAFT' ? Translator::t('sales_hub_row_open_edit', 'Buka untuk diedit') : Translator::t('sales_hub_row_open_view', 'Buka (lihat-saja)') }}"
                                            class="inline-flex items-center justify-center w-7 h-7 rounded-md text-accent hover:bg-[#eef3fb] cursor-pointer">
                                            {{ $row->sub_state === 'DRAFT' ? '✎' : '↗' }}
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8"><div class="qempty">{{ Translator::t('sales_hub_no_matching_inquiry', 'Belum ada inkuiri yang cocok.') }}</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @php($selectedRow = $selectedId ? $this->inquiries->firstWhere('id_inquiry', $selectedId) : null)
                <div class="openact">
                    <div class="hint">
                        @if ($selectedRow)
                            {{ Translator::t('sales_hub_picker_selected', 'Terpilih') }} <b>{{ $selectedRow->inquiry_no ?: 'id '.$selectedRow->id_inquiry }}</b> —
                            {{ $selectedRow->sub_state === 'DRAFT' ? Translator::t('sales_hub_will_open_edit', 'akan dibuka untuk diedit') : Translator::t('sales_hub_will_open_view', 'dibuka lihat-saja (bukan DRAFT)') }}
                        @else
                            {{ Translator::t('sales_hub_click_row_hint', 'Klik satu baris untuk memilih.') }}
                        @endif
                    </div>
                    <div class="sp"></div>
                    <button type="button" class="btn p" wire:click="openSelected" @disabled(! $selectedRow)>
                        {{ $selectedRow && $selectedRow->sub_state !== 'DRAFT' ? Translator::t('sales_hub_btn_open_view', 'Buka (lihat-saja) →') : Translator::t('sales_hub_btn_open_inquiry', 'Buka inkuiri →') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
