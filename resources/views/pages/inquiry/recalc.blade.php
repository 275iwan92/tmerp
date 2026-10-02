<?php

use App\Models\Master\MatRaw;
use App\Models\Sdi\IqpInquiry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

// Layar RECALC (docs/mockups/inquiry_recalc_v2.html) — repeat order harga baru.
// Spec/layout 100% reuse dari inkuiri "baku" (R-RC-1, docs/inquiryproduct_delta_revise.md):
// hanya qty & harga bahan yang di-refresh, part/material/process di-clone verbatim
// (IqpInquiry::cloneStructureFrom), tak ada satupun field spec yang bisa diedit di sini.
new #[Title('TELASIH MAKMUR — RECALC')] class extends Component
{
    #[Url]
    public ?int $id = null;

    #[Url]
    public ?int $source_id = null;

    public ?IqpInquiry $inquiry = null;

    public ?IqpInquiry $source = null;

    public bool $readOnly = false;

    public string $qty = '';

    public ?string $deadline_quotation = null;

    public ?string $flash = null;

    public function mount(): void
    {
        if ($this->id) {
            $this->inquiry = IqpInquiry::with([
                'customer',
                'tiers' => fn ($q) => $q->where('is_primary', true),
            ])->where('inquiry_type', 'RECALC')->findOrFail($this->id);

            $this->source = $this->inquiry->parentInquiry()->with([
                'customer',
                'parts' => fn ($q) => $q->orderBy('part_seq'),
                'parts.materials.rawMaterial',
                'parts.processes',
            ])->first() ?? $this->inquiry;

            $tier = $this->inquiry->tiers->first();
            $this->qty = $tier?->qty !== null ? rtrim(rtrim((string) $tier->qty, '0'), '.') : '';
            $this->deadline_quotation = optional($this->inquiry->deadline_quotation)->toDateString();
            $this->readOnly = (bool) $this->inquiry->stat_confirm_sales;

            return;
        }

        if ($this->source_id) {
            $this->source = IqpInquiry::with([
                'customer',
                'parts' => fn ($q) => $q->orderBy('part_seq'),
                'parts.materials.rawMaterial',
                'parts.processes',
                'tiers' => fn ($q) => $q->where('is_primary', true),
            ])->findOrFail($this->source_id);

            $primaryTier = $this->source->tiers->first();
            $this->qty = $primaryTier?->qty !== null ? rtrim(rtrim((string) $primaryTier->qty, '0'), '.') : '';
            $this->deadline_quotation = optional($this->source->deadline_quotation)->toDateString();

            return;
        }

        abort(404);
    }

    // Spec yang ditampilkan (read-only): kalau sudah pernah disimpan, pakai clone
    // beku milik $inquiry sendiri; kalau baru mau dibuat, pakai punya $source.
    #[Computed]
    public function reuseParts()
    {
        return $this->inquiry
            ? $this->inquiry->parts()->with(['materials.rawMaterial', 'processes'])->orderBy('part_seq')->get()
            : $this->source->parts;
    }

    // Perbandingan harga bahan baku → kini (mat_raw.avg_unitprice vs last_unitprice —
    // dua kolom real, bukan angka karangan; belum ada snapshot harga per-inquiry
    // karena costing engine bertarif versioned belum digarap, lihat CLAUDE.md §5).
    #[Computed]
    public function materialPriceRows()
    {
        $ids = $this->reuseParts->flatMap(fn ($p) => $p->materials->pluck('rawmat_id'))->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return MatRaw::whereIn('material_id', $ids)->orderBy('material_name')->get()->map(function (MatRaw $m) {
            $baku = $m->avg_unitprice !== null ? (float) $m->avg_unitprice : null;
            $kini = $m->last_unitprice !== null ? (float) $m->last_unitprice : $baku;
            $delta = ($baku !== null && $baku > 0 && $kini !== null) ? (($kini - $baku) / $baku) * 100 : null;

            return (object) [
                'name' => $m->material_name,
                'uom' => $m->uom_purchase,
                'baku' => $baku,
                'kini' => $kini,
                'delta' => $delta,
            ];
        });
    }

    public function statusPillClass(): string
    {
        return match ($this->inquiry?->sub_state) {
            'DRAFT', null => 'draft',
            'WAITING_COST', 'SUBMITTED', 'PRICE_CALCULATED' => 'submit',
            'PRICE_APPROVED' => 'priceok',
            'QUOTED' => 'quoted',
            'PROVING', 'PROVE_APPROVED' => 'proving',
            'CONVERTED' => 'ok',
            'CANCELLED', 'LOST' => 'cancelled',
            default => 'draft',
        };
    }

    protected function persistDraft(): void
    {
        $this->validate([
            'qty' => ['required', 'numeric', 'min:0.0001'],
            'deadline_quotation' => ['nullable', 'date'],
        ], [], ['qty' => t('Qty order')]);

        DB::connection('tm2sdi')->transaction(function () {
            if ($this->inquiry) {
                $this->inquiry->update([
                    'deadline_quotation' => $this->deadline_quotation ?: null,
                    'updated_by' => Auth::user()->login,
                ]);

                $tier = $this->inquiry->tiers()->where('is_primary', true)->first();

                if ($tier) {
                    $tier->update(['qty' => $this->qty, 'updated_by' => Auth::user()->login]);
                } else {
                    $this->inquiry->tiers()->create([
                        'tier_seq' => 1, 'qty' => $this->qty, 'is_primary' => true,
                        'created_by' => Auth::user()->login, 'updated_by' => Auth::user()->login,
                    ]);
                }

                return;
            }

            $this->inquiry = IqpInquiry::create([
                'inquiry_no' => IqpInquiry::nextInquiryNo(),
                'inquiry_date' => now()->toDateString(),
                'source' => $this->source->source,
                'pic_sales' => $this->source->pic_sales,
                'customer_id' => $this->source->customer_id,
                'is_prospect' => $this->source->is_prospect,
                'prospect_name' => $this->source->prospect_name,
                'deadline_quotation' => $this->deadline_quotation ?: null,
                'product_draft_name' => $this->source->product_draft_name,
                'sales_note' => 'RECALC dari '.$this->source->inquiry_no,
                'inquiry_type' => 'RECALC',
                'parent_inquiry_id' => $this->source->id_inquiry,
                'product_id' => $this->source->product_id,
                'revision_no' => 0,
                'material_source' => $this->source->material_source,
                'job_nature' => $this->source->job_nature,
                'cust_material_spec' => $this->source->cust_material_spec,
                'cust_material_eta' => $this->source->cust_material_eta,
                'tax_scheme' => $this->source->tax_scheme,
                'pph_applicable' => $this->source->pph_applicable,
                'sub_state' => 'DRAFT',
                'created_by' => Auth::user()->login,
                'updated_by' => Auth::user()->login,
            ])->refresh();

            $this->inquiry->cloneStructureFrom($this->source);

            $this->inquiry->tiers()->create([
                'tier_seq' => 1, 'qty' => $this->qty, 'is_primary' => true,
                'created_by' => Auth::user()->login, 'updated_by' => Auth::user()->login,
            ]);

            $this->id = $this->inquiry->id_inquiry;
            unset($this->reuseParts);
        });
    }

    public function saveDraft(): void
    {
        if ($this->readOnly) {
            return;
        }

        $this->persistDraft();
        $this->flash = str_replace(':no', $this->inquiry->inquiry_no, t('Saved as :no (DRAFT).'));
    }

    public function submitToCosting(): void
    {
        if ($this->readOnly) {
            return;
        }

        $this->persistDraft();

        $this->inquiry->update([
            'stat_confirm_sales' => true,
            'sub_state' => 'SUBMITTED',
            'updated_by' => Auth::user()->login,
        ]);

        $this->readOnly = true;
        $this->flash = str_replace(':no', $this->inquiry->inquiry_no, t('Inquiry :no sent to Costing — status SUBMITTED, form locked.'));
    }
};
?>

<div>
    <header class="app-header">
        <b>🔁 {{ t('Recalculate Price') }}</b>
        <span class="pill recalc">RECALC</span>
        <span class="sub">{{ $inquiry?->inquiry_no ?: t('not saved yet') }} · {{ $inquiry?->sub_state ?? 'DRAFT' }}</span>
        <a class="back" href="{{ route('inquiry.hub') }}">← {{ t('Main Menu') }}</a>
    </header>

    <div class="wrap">

        @if ($flash)
            <div class="note g">✅ {{ $flash }}</div>
        @endif

        @if ($readOnly)
            <div class="note o">🔒 {{ t('This inquiry has been sent to Costing — opened view-only, cannot be edited.') }}</div>
        @endif

        <div class="card">
            <h3>1️⃣ {{ t('Source Product & Relation Trace') }}</h3>
            <div class="body">
                <div class="trace">
                    <span class="node"><b>{{ t('Base inquiry') }}:</b> {{ $source->inquiry_no }}</span>
                    <span class="ar">→</span>
                    <span class="node co"><b>RECALC:</b> {{ $inquiry?->inquiry_no ?? t('(not saved yet)') }}</span>
                </div>
                <div class="note o">RECALC = <b>{{ t('repeat order with new price') }}</b>. {{ t('Spec, dimensions, artwork, color, and process') }} <b>{{ t('do not change') }}</b> — {{ t('all reused from the base inquiry. Only refreshed:') }} <b>{{ t('latest material prices') }}</b>.</div>
                <div class="grid three">
                    <x-form.input legacy :label="t('Customer')" value="{{ $source->is_prospect ? $source->prospect_name : ($source->customer?->customer_name ?? '—') }}" readonly />
                    <x-form.input legacy :label="t('Product')" value="{{ $source->product_draft_name }}" readonly />
                    <x-form.input legacy :label="t('Qty Order (PCS) *')" type="number" step="0.0001" min="0" wire:model="qty" :disabled="$readOnly" name="qty" />
                </div>
                <div class="grid three" style="margin-top:10px">
                    <x-form.date-picker legacy :label="t('Quotation Deadline')" wire:model="deadline_quotation" :disabled="$readOnly" />
                </div>
            </div>
        </div>

        <div class="card">
            <h3>2️⃣ {{ t('Spec & Layout (reused from base)') }} <span style="font-weight:400;color:#8a94a6;font-size:11px;margin-left:auto">{{ t('read-only') }}</span></h3>
            <div class="body">
                <div class="lockbar"><span class="lockbadge">🔒 {{ t('LOCKED') }}</span> {{ t('To change any of these, use') }} <b>REVISE</b>, {{ t('not RECALC.') }}</div>

                @foreach ($this->reuseParts as $part)
                    <div class="sec-title">{{ $part->part_name }} @if($part->part_type)({{ $part->part_type }})@endif</div>

                    {{-- Preview artwork + diecut (permintaan user 2026-09-04: "RECALC harus
                    ada preview image dan die cut nya juga agar tidak salah" -- RECALC 100%
                    reuse spec dari inquiry baku, jadi officer perlu cek visual cepat sebelum
                    submit, bukan cuma baca teks "Shallow Box" dari kolom Dieline/Tipe di
                    bawah yg gampang salah baca kalau ada banyak tipe mirip). --}}
                    <div style="display:flex;gap:14px;margin-bottom:10px" x-data="{ zoomArt: false }">
                        @if ($part->artwork_path)
                            <div style="width:110px;flex-shrink:0">
                                <div class="hint" style="margin-bottom:4px">{{ t('Artwork') }}</div>
                                <img src="{{ asset('storage/'.$part->artwork_path) }}" @click="zoomArt = true"
                                     style="width:110px;height:110px;object-fit:cover;border-radius:6px;border:1px solid #c7cfda;cursor:zoom-in" alt="{{ t('artwork') }}">
                                <div x-show="zoomArt" x-cloak @click.self="zoomArt = false" @keydown.escape.window="zoomArt = false"
                                     style="position:fixed;inset:0;z-index:250;background:rgba(15,22,45,.72);display:flex;align-items:center;justify-content:center;padding:24px">
                                    <img src="{{ asset('storage/'.$part->artwork_path) }}" style="max-width:90vw;max-height:88vh;border-radius:8px;box-shadow:0 12px 40px rgba(0,0,0,.4)" alt="{{ t('full-size artwork') }}">
                                </div>
                            </div>
                        @else
                            <div style="width:110px;flex-shrink:0">
                                <div class="hint" style="margin-bottom:4px">{{ t('Artwork') }}</div>
                                <div style="width:110px;height:110px;background:#f4f6fa;border:1px solid #c7cfda;border-radius:6px;display:flex;align-items:center;justify-content:center">
                                    <span class="hint">{{ t('No image') }}</span>
                                </div>
                            </div>
                        @endif

                        @php($hasDieline = ! empty($part->dieline_spec['elements'] ?? null))
                        <div style="width:150px;flex-shrink:0">
                            <div class="hint" style="margin-bottom:4px">{{ t('Diecut') }}</div>
                            @if ($hasDieline)
                                <div style="width:150px;height:150px;background:#fff;border:1px solid #c7cfda;border-radius:6px;display:flex;align-items:center;justify-content:center;padding:6px"
                                     x-data="{ svg: null }"
                                     x-init="svg = window.TMDieline ? window.TMDieline.svg(@js($part->dieline_spec), @js($part->dieline_spec['dims'] ?? (object) [])) : null">
                                    <div x-show="svg" x-html="svg" style="width:100%;height:100%"></div>
                                    <div x-show="!svg" class="hint">{{ t('Failed to load.') }}</div>
                                </div>
                            @else
                                <div style="width:150px;height:150px;background:#f4f6fa;border:1px solid #c7cfda;border-radius:6px;display:flex;align-items:center;justify-content:center">
                                    <span class="hint">{{ t('No diecut') }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="reuse">
                        <div class="k"><label>{{ t('Dimension') }}</label><div class="v mono">{{ $part->flat_w ?? '—' }} × {{ $part->flat_h ?? '—' }} cm</div><div class="rz">✓ {{ t('reuse') }}</div></div>
                        <div class="k"><label>{{ t('Dieline / Type') }}</label><div class="v mono">{{ $part->dieline_spec['type_code'] ?? '—' }}</div><div class="rz">✓ {{ t('reuse') }}</div></div>
                        <div class="k"><label>{{ t('Color') }}</label><div class="v mono">{{ $part->cfg_color ?? '—' }}{{ $part->cfg_ink ? ' · '.$part->cfg_ink : '' }}</div><div class="rz">✓ {{ t('reuse') }}</div></div>
                        <div class="k"><label>{{ t('Qty/SKU') }}</label><div class="v mono">{{ $part->qty_per_sku }}</div><div class="rz">✓ {{ t('reuse') }}</div></div>
                    </div>
                    <div class="reuse" style="margin-top:0 0 12px">
                        <div class="k" style="grid-column:1/3">
                            <label>{{ t('Material') }}</label>
                            <div class="v" style="font-size:11px">{{ $part->materials->pluck('rawmat_name')->filter()->implode(', ') ?: '—' }}</div>
                            <div class="rz">✓ {{ t('reuse') }}</div>
                        </div>
                        <div class="k" style="grid-column:3/5">
                            <label>{{ t('Process') }}</label>
                            <div class="v" style="font-size:11px">{{ $part->processes->pluck('process_name')->filter()->implode(' → ') ?: t('no data yet (process routing module not built yet)') }}</div>
                            <div class="rz">✓ {{ t('reuse') }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card">
            <h3>3️⃣ {{ t('Material Price Refresh') }} <span style="font-weight:400;color:#8a94a6;font-size:11px;margin-left:auto">{{ t('only this changes') }}</span></h3>
            <div class="body">
                <div class="note b">{{ t('Material prices are pulled from') }} <code>mat_raw</code> — {{ t('column') }} <code>avg_unitprice</code> ({{ t('base') }}) {{ t('compared with') }} <code>last_unitprice</code> ({{ t('current') }}). {{ t('Material structure & quantities are identical, only the unit price is updated.') }}</div>
                <div class="pt">
                    <div class="h">{{ t('Material price comparison (base → now)') }}</div>
                    <table>
                        <thead><tr><th>{{ t('Material') }}</th><th>{{ t('Base Price') }}</th><th>{{ t('Current Price') }}</th><th>Δ</th></tr></thead>
                        <tbody>
                            @forelse ($this->materialPriceRows as $row)
                                <tr>
                                    <td>{{ $row->name }}</td>
                                    <td>{{ $row->baku !== null ? 'Rp '.number_format($row->baku, 0, ',', '.').'/'.$row->uom : '—' }}</td>
                                    <td>{{ $row->kini !== null ? 'Rp '.number_format($row->kini, 0, ',', '.').'/'.$row->uom : '—' }}</td>
                                    <td>
                                        @if ($row->delta === null)
                                            <span style="color:#8a94a6">—</span>
                                        @elseif (abs($row->delta) < 0.05)
                                            <span style="color:#8a94a6">0%</span>
                                        @elseif ($row->delta > 0)
                                            <span class="up">▲ +{{ number_format($row->delta, 1) }}%</span>
                                        @else
                                            <span class="dn">▼ {{ number_format($row->delta, 1) }}%</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" style="text-align:center;color:#8a94a6">{{ t('No materials recorded in the base inquiry yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="note y">💰 {{ t('RECALC COGS & selling price are calculated by') }} <b>{{ t('Costing') }}</b> {{ t('after submit — not available on this Sales screen yet (Side A, see CLAUDE.md §4).') }}</div>
            </div>
        </div>

    </div>

    <div class="actionbar">
        <button type="button" class="btn g" wire:click="saveDraft" @if($readOnly) disabled @endif wire:loading.attr="disabled">💾 {{ t('Save Draft') }}</button>
        <span style="font-size:11px;color:#8a94a6;margin-left:6px">{{ t('Layout reuse') }} · {{ t('Doc') }} <b>{{ $inquiry?->inquiry_no ?? t('(new draft)') }}</b></span>
        <button type="button" class="btn s" style="margin-left:auto" wire:click="submitToCosting" @if($readOnly) disabled @endif wire:loading.attr="disabled">➜ {{ t('Submit to Costing') }}</button>
    </div>
</div>
