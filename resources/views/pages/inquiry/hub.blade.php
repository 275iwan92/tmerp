<?php

use App\Models\Sdi\IqpInquiry;
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
        'NEW' => 'Produk Baru',
        'RECALC' => 'Hitung Ulang Harga',
        'REVISE' => 'Ubah Spesifikasi',
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

    public function typeLabel(string $type): string
    {
        return self::TYPE_LABELS[$type] ?? $type;
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
        <div class="pick">
            <div class="opt n">
                <div class="ico">✨</div><span class="tag">NEW</span>
                <h4>Produk Baru</h4>
                <p>Belum pernah dibuat. Sales menentukan tipe produk, dimensi, bahan, dan finishing dari nol.</p>
                <div class="req">Perlu: <b>tipe produk + dimensi + bahan</b><br>Alur: Sales → Design → Costing</div>
                <div class="optbtns">
                    <button type="button" class="bsaved" wire:click="pickSaved('NEW')" onclick="document.getElementById('saved-box')?.scrollIntoView({behavior:'smooth',block:'start'})">📂 Pilih data tersimpan</button>
                    <a class="bnew bn-n" href="{{ route('inquiry.form', ['mode' => 'NEW']) }}">＋ Buat data baru</a>
                </div>
            </div>

            <div class="opt r">
                <div class="ico">🔁</div><span class="tag">RECALC</span>
                <h4>Hitung Ulang Harga</h4>
                <p>Produk &amp; layout sudah ada, tidak ada yang berubah. Hanya perlu harga baru.</p>
                <div class="req">Perlu: <b>pilih produk existing + qty</b><br>Alur: Sales → Costing <span style="color:#177a4b">(lewati Design)</span></div>
                <div class="optbtns">
                    <button type="button" class="bsaved" wire:click="pickSaved('RECALC')" onclick="document.getElementById('saved-box')?.scrollIntoView({behavior:'smooth',block:'start'})">📂 Pilih data tersimpan</button>
                    <button type="button" class="bnew bn-r" wire:click="openPicker('RECALC')">＋ Buat data baru</button>
                </div>
            </div>

            <div class="opt v">
                <div class="ico">✏️</div><span class="tag">REVISE</span>
                <h4>Ubah Spesifikasi</h4>
                <p>Produk sudah ada tapi ada yang berubah: dimensi, bahan, warna, atau finishing.</p>
                <div class="req">Perlu: <b>pilih produk existing + apa yang berubah</b><br>Alur: Sales → Design → Costing</div>
                <div class="optbtns">
                    <button type="button" class="bsaved" wire:click="pickSaved('REVISE')" onclick="document.getElementById('saved-box')?.scrollIntoView({behavior:'smooth',block:'start'})">📂 Pilih data tersimpan</button>
                    <button type="button" class="bnew bn-v" wire:click="openPicker('REVISE')">＋ Buat data baru</button>
                </div>
            </div>
        </div>

        @if ($pickerMode)
            <div class="prodsel on" wire:key="prodsel">
                <div class="hd">
                    <span>{{ $pickerMode === 'RECALC' ? '🔁' : '✏️' }}</span>
                    <span>Pilih produk existing — <b>{{ $pickerMode }}</b></span>
                    <span class="x" wire:click="closePicker">×</span>
                </div>
                <div class="bd">
                    <div class="note {{ $pickerMode === 'RECALC' ? 'b' : 'o' }}">
                        @if ($pickerMode === 'RECALC')
                            Semua spesifikasi akan disalin dari inkuiri terpilih dan <b>dikunci</b>. Anda hanya mengisi qty &amp; deadline.
                        @else
                            Spesifikasi disalin lalu <b>bisa diubah</b>. Tipe produk tetap terkunci — kalau tipe berubah, gunakan <b>NEW</b>.
                        @endif
                    </div>
                    <div class="srch">
                        <input type="text" wire:model.live.debounce.400ms="pickerQuery" placeholder="Cari no. inkuiri / customer / produk…">
                    </div>
                    <div class="plist">
                        @forelse ($this->pickerCandidates as $row)
                            <div class="prow {{ $pickerSelectedId === $row->id_inquiry ? 'sel' : '' }}" wire:key="prow-{{ $row->id_inquiry }}" wire:click="selectPickerRow({{ $row->id_inquiry }})">
                                <div class="th">{{ $row->parts->first()?->part_type ? mb_substr($row->parts->first()->part_type, 0, 9) : '—' }}</div>
                                <div class="mn">
                                    <b>{{ $row->product_draft_name ?: '(tanpa nama produk)' }}</b>
                                    <span>{{ $row->is_prospect ? $row->prospect_name : ($row->customer?->customer_name ?? '—') }} · {{ $row->parts->first()?->part_type }} · qty {{ $row->tiers->first()?->qty ? number_format((float) $row->tiers->first()->qty, 0, ',', '.') : '—' }}</span>
                                </div>
                                <div class="mt"><code>{{ $row->inquiry_no }}</code><br>{{ $row->inquiry_date?->format('Y-m-d') }} · <span class="pill {{ $this->statusPillClass($row->sub_state) }}">{{ $row->sub_state }}</span></div>
                            </div>
                        @empty
                            <div class="qempty">Tidak ada produk/inkuiri yang cocok.</div>
                        @endforelse
                    </div>
                    <div class="act">
                        <div class="hint">
                            @php($pickerRow = $pickerSelectedId ? $this->pickerCandidates->firstWhere('id_inquiry', $pickerSelectedId) : null)
                            @if ($pickerRow)
                                Terpilih <b>{{ $pickerRow->inquiry_no }}</b> — {{ $pickerRow->product_draft_name }}
                            @else
                                Pilih satu baris untuk melanjutkan.
                            @endif
                        </div>
                        <div class="sp"></div>
                        <button type="button" class="btn g" wire:click="closePicker">Batal</button>
                        <button type="button" class="btn p" wire:click="confirmPicker" @disabled(! $pickerRow)>{{ $pickerRow ? 'Lanjut → '.$pickerRow->inquiry_no : 'Pilih produk dulu' }}</button>
                    </div>
                </div>
            </div>
        @endif

        <div class="openbox" id="saved-box" wire:key="saved-box">
            <div class="hd">📂 <span>Inkuiri Tersimpan{{ $ftype ? ' — '.$this->typeLabel($ftype) : '' }}</span>
                <span class="cnt">{{ $this->inquiries->count() }} inkuiri</span></div>
            <div class="bd">
                <div class="filters">
                    <input type="text" wire:model.live.debounce.400ms="fq" placeholder="Cari no. inkuiri / nama produk / prospek…">
                    <div class="w-[170px] shrink-0">
                        <select wire:model.live="fst">
                            <option value="">Semua status</option>
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
                            <option value="">Semua tipe</option>
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
                                <th>Produk / Customer</th>
                                <th>No. Inkuiri</th><th>Tgl</th>
                                <th class="hide-sm">Tipe Produk</th>
                                <th style="text-align:right">Qty tier utama</th>
                                <th>Status</th><th>Jenis</th>
                                <th style="text-align:center; width:1%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->inquiries as $row)
                                @php($custName = $row->is_prospect ? $row->prospect_name : ($row->customer?->customer_name ?? '—'))
                                <tr wire:key="inq-{{ $row->id_inquiry }}" class="{{ $selectedId === $row->id_inquiry ? 'sel' : '' }}"
                                    wire:click="selectRow({{ $row->id_inquiry }})" wire:dblclick="openRow({{ $row->id_inquiry }})" title="Klik 2x untuk buka">
                                    <td>
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-8 h-8 rounded-md bg-gradient-to-br {{ $this->avatarGradient($row->id_inquiry) }} text-white text-[11px] font-bold flex items-center justify-center shrink-0">{{ $this->avatarInitials($row->product_draft_name ?: $custName) }}</div>
                                            <div>
                                                <div class="font-semibold text-[13px] text-navy">{{ $row->product_draft_name ?: '(tanpa nama produk)' }}</div>
                                                <div class="text-[11px] text-muted">
                                                    {{ $custName }}
                                                    @if ($row->is_prospect)
                                                        <span class="pill prospect">prospek</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="qdoc">{{ $row->inquiry_no ?: '(draft, belum ada no.)' }}</span></td>
                                    <td>{{ $row->inquiry_date?->format('Y-m-d') }}</td>
                                    <td class="hide-sm">{{ $row->parts->first()?->part_type ?? '—' }}</td>
                                    <td style="text-align:right">
                                        {{ $row->tiers->first()?->qty ? number_format((float) $row->tiers->first()->qty, 0, ',', '.') : '—' }}
                                    </td>
                                    <td><span class="pill {{ $this->statusPillClass($row->sub_state) }}">{{ $row->sub_state }}</span></td>
                                    <td><span class="pill {{ strtolower($row->inquiry_type) }}">{{ $row->inquiry_type }}</span></td>
                                    <td style="text-align:center">
                                        <button type="button" wire:click.stop="openRow({{ $row->id_inquiry }})"
                                            title="{{ $row->sub_state === 'DRAFT' ? 'Buka untuk diedit' : 'Buka (lihat-saja)' }}"
                                            class="inline-flex items-center justify-center w-7 h-7 rounded-md text-accent hover:bg-[#eef3fb] cursor-pointer">
                                            {{ $row->sub_state === 'DRAFT' ? '✎' : '↗' }}
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8"><div class="qempty">Belum ada inkuiri yang cocok.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @php($selectedRow = $selectedId ? $this->inquiries->firstWhere('id_inquiry', $selectedId) : null)
                <div class="openact">
                    <div class="hint">
                        @if ($selectedRow)
                            Terpilih <b>{{ $selectedRow->inquiry_no ?: 'id '.$selectedRow->id_inquiry }}</b> —
                            {{ $selectedRow->sub_state === 'DRAFT' ? 'akan dibuka untuk diedit' : 'dibuka lihat-saja (bukan DRAFT)' }}
                        @else
                            Klik satu baris untuk memilih.
                        @endif
                    </div>
                    <div class="sp"></div>
                    <button type="button" class="btn p" wire:click="openSelected" @disabled(! $selectedRow)>
                        {{ $selectedRow && $selectedRow->sub_state !== 'DRAFT' ? 'Buka (lihat-saja) →' : 'Buka inkuiri →' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
