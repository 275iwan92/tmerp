<?php

use App\Models\Master\MatRaw;
use App\Models\Master\ProdType;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\IqpPart;
use App\Models\Sdi\IqpPartMaterial;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

// Layar REVISE (docs/mockups/inquiry_revise_v2.html) — ubah spec via copy-record
// + versi baru. Scope manual (R-REV-1, docs/DELTA_TIER_revise_scope_CO.md) menentukan
// blok edit apa yang tampil; field di luar scope tetap reuse verbatim dari baku.
new #[Title('TELASIH MAKMUR — REVISE')] class extends Component
{
    #[Url]
    public ?int $id = null;

    #[Url]
    public ?int $source_id = null;

    public ?IqpInquiry $inquiry = null;

    public ?IqpInquiry $source = null;

    public bool $readOnly = false;

    /** @var array<int, string> */
    public array $scope = [];

    public string $revise_reason = '';

    public string $design_number = '';

    /** @var array<int, array> */
    public array $parts = [];

    public ?string $flash = null;

    protected array $dimParamsCache = [];

    public function mount(): void
    {
        if ($this->id) {
            $this->inquiry = IqpInquiry::with('customer')->where('inquiry_type', 'REVISE')->findOrFail($this->id);
            $this->source = $this->inquiry->parentInquiry()->with('customer')->first() ?? $this->inquiry;
            $this->fillPartsFrom($this->inquiry);
            $this->scope = $this->inquiry->reviseScopeArray();
            $this->revise_reason = $this->inquiry->revise_reason ?? '';
            $this->design_number = $this->inquiry->design_number ?? '';
            $this->readOnly = (bool) $this->inquiry->is_confirmed;

            return;
        }

        if ($this->source_id) {
            $this->source = IqpInquiry::with('customer')->findOrFail($this->source_id);
            $this->fillPartsFrom($this->source);

            // Clone segar dari baku: id part/material milik SOURCE, bukan boleh dipakai
            // sebagai target update — null-kan supaya persistParts() men-create baris baru.
            foreach ($this->parts as $i => $p) {
                $this->parts[$i]['id'] = null;
                foreach ($this->parts[$i]['materials'] as $j => $m) {
                    $this->parts[$i]['materials'][$j]['id'] = null;
                }
            }

            $this->design_number = $this->source->design_number ?? '';

            return;
        }

        abort(404);
    }

    protected function fillPartsFrom(IqpInquiry $base): void
    {
        $parts = $base->parts()->with(['materials.rawMaterial', 'processes'])->orderBy('part_seq')->get();

        $this->parts = $parts->map(fn (IqpPart $p) => [
            'id' => $p->id_part,
            'part_name' => $p->part_name,
            'part_type' => $p->part_type,
            'type_code' => $p->dieline_spec['type_code'] ?? '',
            'qty_per_sku' => $p->qty_per_sku !== null ? rtrim(rtrim((string) $p->qty_per_sku, '0'), '.') : '1',
            'dims' => $p->dieline_spec['dims'] ?? [],
            'flat_w' => $p->flat_w !== null ? rtrim(rtrim((string) $p->flat_w, '0'), '.') : '',
            'flat_h' => $p->flat_h !== null ? rtrim(rtrim((string) $p->flat_h, '0'), '.') : '',
            'flat_area' => $p->flat_area !== null ? rtrim(rtrim((string) $p->flat_area, '0'), '.') : '',
            'cfg_color' => $p->cfg_color ?? '',
            'cfg_ink' => $p->cfg_ink ?? '',
            'materials' => $p->materials->map(fn (IqpPartMaterial $m) => [
                'id' => $m->id_part_material,
                'rawmat_id' => $m->rawmat_id,
                'display_name' => $m->rawmat_name ?? '',
                'gram_weight' => $m->gram_weight,
                'size_p' => $m->size_p,
                'size_l' => $m->size_l,
                'is_primary' => (bool) $m->is_primary,
                'query' => '',
            ])->values()->all(),
            'processes' => $p->processes->map(fn ($pr) => [
                'process_name' => $pr->process_name,
            ])->values()->all(),
        ])->values()->all();
    }

    public function toggleScope(string $key): void
    {
        if ($this->readOnly) {
            return;
        }

        if (in_array($key, $this->scope, true)) {
            $this->scope = array_values(array_diff($this->scope, [$key]));
        } else {
            $this->scope[] = $key;
        }
    }

    // Matriks pemicu — persis Tier 2 (docs/DELTA_TIER_revise_scope_CO.md §TIER 2).
    #[Computed]
    public function triggers(): array
    {
        $has = fn (string $k) => in_array($k, $this->scope, true);

        return [
            'imposisi' => $has('DIMENSION'),
            'proof' => $has('ARTWORK'),
            'costing' => $has('MATERIAL') || $has('DIMENSION') || $has('COLOR') || $has('PROCESS'),
            'due' => $has('ARTWORK'),
        ];
    }

    public function dimParams(string $typeCode): array
    {
        if ($typeCode === '') {
            return [];
        }

        if (! array_key_exists($typeCode, $this->dimParamsCache)) {
            $type = ProdType::with(['params' => fn ($q) => $q->where('is_active', true)->orderBy('seq')])->find($typeCode);

            $this->dimParamsCache[$typeCode] = $type?->params->map(fn ($p) => [
                'key' => $p->param_key,
                'label' => $p->param_label,
                'uom' => $p->unit,
            ])->values()->all() ?? [];
        }

        return $this->dimParamsCache[$typeCode];
    }

    // ================= MATERIAL (scope MATERIAL) =================

    protected function blankMaterialRow(): array
    {
        return ['id' => null, 'rawmat_id' => null, 'display_name' => '', 'gram_weight' => null, 'size_p' => null, 'size_l' => null, 'is_primary' => false, 'query' => ''];
    }

    public function addMaterialRow(int $partIndex): void
    {
        if ($this->readOnly || ! isset($this->parts[$partIndex])) {
            return;
        }

        $this->parts[$partIndex]['materials'][] = $this->blankMaterialRow();
    }

    public function removeMaterialRow(int $partIndex, int $matIndex): void
    {
        if ($this->readOnly || ! isset($this->parts[$partIndex]['materials'][$matIndex]) || count($this->parts[$partIndex]['materials']) <= 1) {
            return;
        }

        unset($this->parts[$partIndex]['materials'][$matIndex]);
        $this->parts[$partIndex]['materials'] = array_values($this->parts[$partIndex]['materials']);
    }

    public function materialResults(int $partIndex, int $matIndex)
    {
        $row = $this->parts[$partIndex]['materials'][$matIndex] ?? null;

        if (! $row || $row['rawmat_id'] || mb_strlen($row['query']) < 2) {
            return collect();
        }

        return MatRaw::query()
            ->where('is_active', true)
            ->where(function ($q) use ($row) {
                $q->where('material_name', 'like', "%{$row['query']}%")->orWhere('material_code', 'like', "%{$row['query']}%");
            })
            ->orderBy('material_name')
            ->limit(8)
            ->get();
    }

    public function selectMaterial(int $partIndex, int $matIndex, int $rawmatId): void
    {
        if ($this->readOnly || ! isset($this->parts[$partIndex]['materials'][$matIndex])) {
            return;
        }

        $material = MatRaw::find($rawmatId);

        if (! $material) {
            return;
        }

        $this->parts[$partIndex]['materials'][$matIndex]['rawmat_id'] = $material->id_material;
        $this->parts[$partIndex]['materials'][$matIndex]['display_name'] = $material->material_name;
        $this->parts[$partIndex]['materials'][$matIndex]['gram_weight'] = $material->gram_weight;
        $this->parts[$partIndex]['materials'][$matIndex]['size_p'] = $material->cm_length;
        $this->parts[$partIndex]['materials'][$matIndex]['size_l'] = $material->cm_width;
        $this->parts[$partIndex]['materials'][$matIndex]['query'] = '';
    }

    // ================= PROSES (scope PROCESS) =================

    public function addProcessRow(int $partIndex): void
    {
        if ($this->readOnly || ! isset($this->parts[$partIndex])) {
            return;
        }

        $this->parts[$partIndex]['processes'][] = ['process_name' => ''];
    }

    public function removeProcessRow(int $partIndex, int $procIndex): void
    {
        if ($this->readOnly || ! isset($this->parts[$partIndex]['processes'][$procIndex])) {
            return;
        }

        unset($this->parts[$partIndex]['processes'][$procIndex]);
        $this->parts[$partIndex]['processes'] = array_values($this->parts[$partIndex]['processes']);
    }

    // ================= SIMPAN =================

    protected function persistPartMaterials(IqpPart $part, int $partIndex): void
    {
        $keepIds = [];

        foreach ($this->parts[$partIndex]['materials'] as $matIndex => $row) {
            if (! $row['rawmat_id'] && $row['display_name'] === '') {
                continue;
            }

            $data = [
                'layer_seq' => $matIndex + 1,
                'rawmat_id' => $row['rawmat_id'] ?: null,
                'rawmat_name' => $row['display_name'] !== '' ? $row['display_name'] : null,
                'gram_weight' => $row['gram_weight'] ?: null,
                'size_p' => $row['size_p'] ?: null,
                'size_l' => $row['size_l'] ?: null,
                'is_primary' => $row['is_primary'],
                'material_source' => $this->source->material_source,
                'updated_by' => Auth::user()->login,
            ];

            if ($row['id']) {
                IqpPartMaterial::whereKey($row['id'])->update($data);
                $keepIds[] = $row['id'];
            } else {
                $data['created_by'] = Auth::user()->login;
                $material = $part->materials()->create($data);
                $this->parts[$partIndex]['materials'][$matIndex]['id'] = $material->id_part_material;
                $keepIds[] = $material->id_part_material;
            }
        }

        $part->materials()->whereNotIn('id_part_material', $keepIds)->delete();
    }

    protected function persistPartProcesses(IqpPart $part, int $partIndex): void
    {
        // Tak ada id ter-track di array (routing branch/flow di luar scope layar ini,
        // lihat IqpPartProcess) — replace-all per save, cukup utk catatan proses REVISE.
        $part->processes()->delete();

        foreach ($this->parts[$partIndex]['processes'] as $seq => $row) {
            if (trim($row['process_name'] ?? '') === '') {
                continue;
            }

            $part->processes()->create([
                'branch_code' => 'MAIN',
                'step_seq' => $seq + 1,
                'flow_type' => 'LINEAR',
                'process_name' => $row['process_name'],
                'created_by' => Auth::user()->login,
                'updated_by' => Auth::user()->login,
            ]);
        }
    }

    protected function persistParts(): void
    {
        $keepIds = [];

        foreach ($this->parts as $index => $row) {
            $dielineSpec = null;

            if (! empty($row['type_code'])) {
                $type = ProdType::find($row['type_code']);

                if ($type && $type->dieline_spec) {
                    $dielineSpec = $type->dieline_spec;
                    $dielineSpec['dims'] = $row['dims'] ?? [];
                    $dielineSpec['type_code'] = $row['type_code'];
                }
            }

            $data = [
                'part_seq' => $index + 1,
                'part_name' => $row['part_name'] !== '' ? $row['part_name'] : 'Part '.($index + 1),
                'part_type' => $row['part_type'] ?: null,
                'qty_per_sku' => $row['qty_per_sku'] !== '' ? $row['qty_per_sku'] : 1,
                'cfg_color' => $row['cfg_color'] !== '' ? $row['cfg_color'] : null,
                'cfg_ink' => $row['cfg_ink'] !== '' ? $row['cfg_ink'] : null,
                'dieline_spec' => $dielineSpec,
                'flat_w' => $row['flat_w'] !== '' ? $row['flat_w'] : null,
                'flat_h' => $row['flat_h'] !== '' ? $row['flat_h'] : null,
                'flat_area' => $row['flat_area'] !== '' ? $row['flat_area'] : null,
                'updated_by' => Auth::user()->login,
            ];

            if ($row['id']) {
                $part = IqpPart::findOrFail($row['id']);
                $part->update($data);
                $keepIds[] = $row['id'];
            } else {
                $data['created_by'] = Auth::user()->login;
                $part = $this->inquiry->parts()->create($data);
                $this->parts[$index]['id'] = $part->id_part;
                $keepIds[] = $part->id_part;
            }

            $this->persistPartMaterials($part, $index);
            $this->persistPartProcesses($part, $index);
        }

        $this->inquiry->parts()->whereNotIn('id_part', $keepIds)->delete();
    }

    protected function persist(string $subState): void
    {
        $this->validate([
            'scope' => ['required', 'array', 'min:1'],
            'revise_reason' => ['required', 'string', 'max:255'],
            'design_number' => ['nullable', 'string', 'max:30'],
        ], [], ['scope' => 'Scope revisi', 'revise_reason' => 'Alasan revisi']);

        DB::connection('tm2sdi')->transaction(function () use ($subState) {
            if ($this->inquiry) {
                $this->inquiry->update([
                    'revise_scope' => implode(',', $this->scope),
                    'revise_reason' => $this->revise_reason,
                    'design_number' => $this->design_number ?: null,
                    'sub_state' => $subState,
                    'updated_by' => Auth::user()->login,
                ]);

                $this->persistParts();

                return;
            }

            $newRevisionNo = ($this->source->revision_no ?? 0) + 1;

            $this->inquiry = IqpInquiry::create([
                'inquiry_no' => IqpInquiry::nextInquiryNo(),
                'inquiry_date' => now()->toDateString(),
                'source' => $this->source->source,
                'pic_sales' => $this->source->pic_sales,
                'customer_id' => $this->source->customer_id,
                'is_prospect' => $this->source->is_prospect,
                'prospect_name' => $this->source->prospect_name,
                'deadline_quotation' => $this->source->deadline_quotation,
                'product_draft_name' => $this->source->product_draft_name,
                'sales_note' => 'REVISE dari '.$this->source->inquiry_no.' — '.$this->revise_reason,
                'inquiry_type' => 'REVISE',
                'parent_product_id' => $this->source->parent_product_id ?? $this->source->id_inquiry,
                'revision_no' => $newRevisionNo,
                'revise_scope' => implode(',', $this->scope),
                'revise_reason' => $this->revise_reason,
                'material_source' => $this->source->material_source,
                'job_nature' => $this->source->job_nature,
                'cust_material_spec' => $this->source->cust_material_spec,
                'cust_material_eta' => $this->source->cust_material_eta,
                'tax_scheme' => $this->source->tax_scheme,
                'pph_applicable' => $this->source->pph_applicable,
                'design_number' => $this->design_number ?: null,
                'sub_state' => $subState,
                'created_by' => Auth::user()->login,
                'updated_by' => Auth::user()->login,
            ])->refresh();

            $this->persistParts();

            foreach ($this->source->tiers()->orderBy('tier_seq')->get() as $t) {
                $this->inquiry->tiers()->create([
                    'tier_seq' => $t->tier_seq,
                    'qty' => $t->qty,
                    'is_primary' => $t->is_primary,
                    'discount_item_type' => $t->discount_item_type,
                    'discount_item_value' => $t->discount_item_value,
                    'created_by' => Auth::user()->login,
                    'updated_by' => Auth::user()->login,
                ]);
            }

            $this->id = $this->inquiry->id_inquiry;

            // R-CO-1/R-REV lineage: versi lama dikunci & ditandai superseded, CO/quotation
            // yang sudah pakai versi lama tetap aman (snapshot beku, tak tersentuh).
            $this->source->update([
                'is_confirmed' => true,
                'is_cancelled' => true,
                'cancel_reason' => 'Digantikan oleh revisi v'.$newRevisionNo.' ('.$this->inquiry->inquiry_no.')',
                'sub_state' => 'CANCELLED',
                'updated_by' => Auth::user()->login,
            ]);
        });
    }

    public function saveDraft(): void
    {
        if ($this->readOnly) {
            return;
        }

        $this->persist('DRAFT');
        $this->flash = 'Tersimpan sebagai '.$this->inquiry->inquiry_no.' (DRAFT).';
    }

    public function submitToCosting(): void
    {
        if ($this->readOnly) {
            return;
        }

        $this->persist('SUBMITTED');

        $this->inquiry->update(['is_confirmed' => true, 'updated_by' => Auth::user()->login]);
        $this->readOnly = true;
        $this->flash = 'Inkuiri '.$this->inquiry->inquiry_no.' dikirim ke Costing — status SUBMITTED, form terkunci.';
    }
};
?>

<div>
    <header class="app-header">
        <b>✏️ Ubah Spesifikasi</b>
        <span class="pill revise">REVISE</span>
        <span class="sub">{{ $inquiry?->inquiry_no ?: 'belum tersimpan' }} · {{ $inquiry?->sub_state ?? 'DRAFT' }} · v{{ ($source->revision_no ?? 0) + ($inquiry ? 0 : 1) }}</span>
        <a class="back" href="{{ route('inquiry.hub') }}">← Menu Utama</a>
    </header>

    <div class="wrap">

        @if ($flash)
            <div class="note g">✅ {{ $flash }}</div>
        @endif

        @if ($readOnly)
            <div class="note o">🔒 Inkuiri ini sudah dikirim ke Costing — dibuka lihat-saja, tidak bisa diedit.</div>
        @endif

        <div class="card">
            <h3>1️⃣ Produk Asal &amp; Jejak Relasi</h3>
            <div class="body">
                <div class="trace">
                    <span class="node"><b>Inkuiri asal:</b> {{ $source->inquiry_no }} (v{{ $source->revision_no }})</span>
                    <span class="ar">→</span>
                    <span class="node co"><b>Versi baru:</b> {{ $inquiry?->inquiry_no ?? '(belum tersimpan)' }} (v{{ $source->revision_no + 1 }})</span>
                </div>
                <div class="note b">🔗 REVISE membuat <b>versi baru</b>. Dokumen hilir (quotation/CO) yang sudah pakai versi lama tetap memakai snapshotnya — tak terpengaruh. Versi lama akan ditandai <b>CANCELLED · SUPERSEDED</b> begitu versi baru ini disimpan.</div>
                <div class="grid three">
                    <div class="f">
                        <label>Customer</label>
                        <input type="text" value="{{ $source->is_prospect ? $source->prospect_name : ($source->customer?->customer_name ?? '—') }}" readonly>
                    </div>
                    <div class="f">
                        <label>Produk</label>
                        <input type="text" value="{{ $source->product_draft_name }}" readonly>
                    </div>
                    <div class="f">
                        <label>Versi baru</label>
                        <input type="text" value="v{{ $source->revision_no + 1 }}" readonly>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <h3>2️⃣ Scope Revisi <span style="font-weight:400;color:#8a94a6;font-size:11px;margin-left:auto">pilih yang berubah (boleh &gt;1)</span></h3>
            <div class="body">
                <div class="note o">Scope menentukan <b>apa yang wajib diproses ulang</b>. Pilih manual — sistem tak menebak, agar tak memicu proof/imposisi mahal yang tak perlu.</div>
                @error('scope') <div class="note" style="background:#fdecea;border:1px solid #f5b7b1;color:#943126">{{ $message }}</div> @enderror
                <div class="scope">
                    <label class="scopeopt {{ in_array('MATERIAL', $scope, true) ? 'on' : '' }}" wire:click="toggleScope('MATERIAL')">
                        <input type="checkbox" @checked(in_array('MATERIAL', $scope, true)) @if($readOnly) disabled @endif>
                        <span class="tx"><b>🧱 Material</b><span>Bahan/gramasi berubah. → costing ulang.</span></span>
                    </label>
                    <label class="scopeopt {{ in_array('DIMENSION', $scope, true) ? 'on' : '' }}" wire:click="toggleScope('DIMENSION')">
                        <input type="checkbox" @checked(in_array('DIMENSION', $scope, true)) @if($readOnly) disabled @endif>
                        <span class="tx"><b>📐 Dimensi</b><span>Ukuran P×L×T berubah. → imposisi ulang + costing.</span></span>
                    </label>
                    <label class="scopeopt {{ in_array('ARTWORK', $scope, true) ? 'on' : '' }}" wire:click="toggleScope('ARTWORK')">
                        <input type="checkbox" @checked(in_array('ARTWORK', $scope, true)) @if($readOnly) disabled @endif>
                        <span class="tx"><b>🎨 Artwork</b><span>Desain berubah. → proof warna ulang.</span></span>
                    </label>
                    <label class="scopeopt {{ in_array('COLOR', $scope, true) ? 'on' : '' }}" wire:click="toggleScope('COLOR')">
                        <input type="checkbox" @checked(in_array('COLOR', $scope, true)) @if($readOnly) disabled @endif>
                        <span class="tx"><b>🖌️ Warna</b><span>Tinta/plate berubah. → costing ulang.</span></span>
                    </label>
                    <label class="scopeopt {{ in_array('PROCESS', $scope, true) ? 'on' : '' }}" wire:click="toggleScope('PROCESS')">
                        <input type="checkbox" @checked(in_array('PROCESS', $scope, true)) @if($readOnly) disabled @endif>
                        <span class="tx"><b>⚙️ Proses</b><span>Urutan/finishing berubah. → costing ulang.</span></span>
                    </label>
                </div>
                <div class="f">
                    <label>Alasan Revisi *</label>
                    <input type="text" wire:model="revise_reason" placeholder="mis. Tambah logo Halal Indonesia (spot ink) — permintaan customer" @if($readOnly) disabled @endif>
                    @error('revise_reason') <span class="hint" style="color:#e55353">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        <div class="card">
            <h3>3️⃣ Konsekuensi (otomatis dari scope)</h3>
            <div class="body">
                <div class="matrix">
                    <div class="mh">Yang wajib diproses ulang</div>
                    <div class="mrow"><span class="lb">📐 Imposisi ulang</span><span class="flag {{ $this->triggers['imposisi'] ? 'on' : 'off' }}">{{ $this->triggers['imposisi'] ? 'YA' : 'TIDAK' }}</span><span class="why">{{ $this->triggers['imposisi'] ? 'ukuran berubah → up/plano dihitung ulang' : 'dieline & ukuran tetap → layout reuse' }}</span></div>
                    <div class="mrow"><span class="lb">🎨 Proof warna ulang</span><span class="flag {{ $this->triggers['proof'] ? 'on' : 'off' }}">{{ $this->triggers['proof'] ? 'YA' : 'TIDAK' }}</span><span class="why">{{ $this->triggers['proof'] ? 'artwork berubah → verifikasi visual' : 'artwork tetap' }}</span></div>
                    <div class="mrow"><span class="lb">💰 Costing / HPP ulang</span><span class="flag {{ $this->triggers['costing'] ? 'on' : 'off' }}">{{ $this->triggers['costing'] ? 'YA' : 'TIDAK' }}</span><span class="why">{{ $this->triggers['costing'] ? 'material/plate/proses berubah → biaya berubah' : 'tak ada perubahan biaya' }}</span></div>
                    <div class="mrow"><span class="lb">📅 Due-date reset</span><span class="flag {{ $this->triggers['due'] ? 'on' : 'off' }}">{{ $this->triggers['due'] ? 'YA' : 'TIDAK' }}</span><span class="why">14 hari kerja dari approval proof baru</span></div>
                </div>
            </div>
        </div>

        @if (in_array('ARTWORK', $scope, true))
            <div class="eb">
                <div class="t">🎨 Artwork — Identitas Desain Baru</div>
                <div class="f">
                    <label>No. Design / Proof</label>
                    <input type="text" wire:model="design_number" placeholder="mis. DS003480" @if($readOnly) disabled @endif>
                </div>
                <div class="note b" style="margin-top:10px">Artwork baru akan melalui proof ulang (modul Proof/Prove) sebelum quotation — di luar layar ini.</div>
            </div>
        @endif

        @if (in_array('COLOR', $scope, true))
            <div class="eb">
                <div class="t">🖌️ Warna — Konfigurasi Cetak per Part</div>
                @foreach ($parts as $i => $part)
                    <div class="grid two" style="margin-bottom:8px">
                        <div class="f">
                            <label>{{ $part['part_name'] ?: 'Part '.($i + 1) }} — Warna (mis. 4/4)</label>
                            <input type="text" wire:model="parts.{{ $i }}.cfg_color" @if($readOnly) disabled @endif>
                        </div>
                        <div class="f">
                            <label>Catatan tinta/plate</label>
                            <input type="text" wire:model="parts.{{ $i }}.cfg_ink" placeholder="mis. +1 spot Pantone 348C" @if($readOnly) disabled @endif>
                        </div>
                    </div>
                @endforeach
                <div class="hppimpact">⚠️ Penambahan spot/plate menaikkan HPP walau ukuran &amp; bahan tetap — dihitung ulang oleh Costing.</div>
            </div>
        @endif

        @if (in_array('DIMENSION', $scope, true))
            <div class="eb">
                <div class="t">📐 Dimensi — Ukuran Baru</div>
                @foreach ($parts as $i => $part)
                    <div class="sec-title">{{ $part['part_name'] ?: 'Part '.($i + 1) }}</div>
                    <div class="grid three" style="margin-bottom:10px">
                        @forelse ($this->dimParams($part['type_code']) as $param)
                            <div class="f">
                                <label>{{ $param['label'] }} @if($param['uom'])({{ $param['uom'] }})@endif</label>
                                <input type="text" wire:model="parts.{{ $i }}.dims.{{ $param['key'] }}" @if($readOnly) disabled @endif>
                            </div>
                        @empty
                            <div class="f"><label>Panjang (cm)</label><input type="number" step="0.01" wire:model="parts.{{ $i }}.flat_w" @if($readOnly) disabled @endif></div>
                            <div class="f"><label>Lebar (cm)</label><input type="number" step="0.01" wire:model="parts.{{ $i }}.flat_h" @if($readOnly) disabled @endif></div>
                        @endforelse
                    </div>
                @endforeach
                <div class="note o">Ukuran berubah → up per plano bisa berubah → imposisi WAJIB diulang (di luar layar ini).</div>
            </div>
        @endif

        @if (in_array('MATERIAL', $scope, true))
            <div class="eb">
                <div class="t">🧱 Material — Substitusi</div>
                @foreach ($parts as $i => $part)
                    <div class="sec-title">{{ $part['part_name'] ?: 'Part '.($i + 1) }}</div>
                    @foreach ($part['materials'] as $j => $mat)
                        <div class="inkrow" style="grid-template-columns:1fr 90px 90px 30px" wire:key="rvmat-{{ $i }}-{{ $j }}">
                            <div class="ac-wrap">
                                @if ($mat['rawmat_id'])
                                    <input type="text" value="{{ $mat['display_name'] }}" readonly>
                                @else
                                    <input type="text" wire:model.live.debounce.300ms="parts.{{ $i }}.materials.{{ $j }}.query" placeholder="Cari bahan…" @if($readOnly) disabled @endif>
                                    @if (! empty($mat['query']) && mb_strlen($mat['query']) >= 2)
                                        <div class="ac-box on">
                                            @forelse ($this->materialResults($i, $j) as $opt)
                                                <div class="ac-i" wire:click="selectMaterial({{ $i }}, {{ $j }}, {{ $opt->id_material }})">
                                                    <b>{{ $opt->material_name }}</b> <span class="cd">{{ $opt->material_code }}</span>
                                                </div>
                                            @empty
                                                <div class="ac-none">Tak ada bahan cocok.</div>
                                            @endforelse
                                        </div>
                                    @endif
                                @endif
                            </div>
                            <input type="number" step="0.0001" wire:model="parts.{{ $i }}.materials.{{ $j }}.gram_weight" placeholder="gramasi" @if($readOnly) disabled @endif>
                            <input type="text" value="{{ $mat['rawmat_id'] ? 'terpilih' : '—' }}" readonly>
                            <button type="button" class="btn d xs" wire:click="removeMaterialRow({{ $i }}, {{ $j }})" @if($readOnly) disabled @endif>✕</button>
                        </div>
                    @endforeach
                    <button type="button" class="addln" wire:click="addMaterialRow({{ $i }})" @if($readOnly) disabled @endif>+ baris bahan</button>
                @endforeach
                <div class="note b" style="margin-top:10px">Gramasi/bahan naik → harga bahan berubah → costing ulang. Imposisi tetap (ukuran sama) kecuali scope Dimensi juga dipilih.</div>
            </div>
        @endif

        @if (in_array('PROCESS', $scope, true))
            <div class="eb">
                <div class="t">⚙️ Proses — Urutan/Finishing</div>
                @foreach ($parts as $i => $part)
                    <div class="sec-title">{{ $part['part_name'] ?: 'Part '.($i + 1) }}</div>
                    @foreach ($part['processes'] as $k => $proc)
                        <div class="inkrow" style="grid-template-columns:1fr 30px" wire:key="rvproc-{{ $i }}-{{ $k }}">
                            <input type="text" wire:model="parts.{{ $i }}.processes.{{ $k }}.process_name" placeholder="mis. Laminasi Doff" @if($readOnly) disabled @endif>
                            <button type="button" class="btn d xs" wire:click="removeProcessRow({{ $i }}, {{ $k }})" @if($readOnly) disabled @endif>✕</button>
                        </div>
                    @endforeach
                    <button type="button" class="addln" wire:click="addProcessRow({{ $i }})" @if($readOnly) disabled @endif>+ langkah proses</button>
                @endforeach
                <div class="note b" style="margin-top:10px">Urutan/finishing berubah → waktu &amp; biaya proses berubah → costing ulang.</div>
            </div>
        @endif

    </div>

    <div class="actionbar">
        <button type="button" class="btn g" wire:click="saveDraft" @if($readOnly) disabled @endif wire:loading.attr="disabled">💾 Simpan Draft</button>
        <span style="font-size:11px;color:#8a94a6;margin-left:6px">Scope: <b>{{ $scope ? implode(', ', $scope) : '(belum ada)' }}</b> · Doc <b>{{ $inquiry?->inquiry_no ?? '(draft baru)' }}</b></span>
        <button type="button" class="btn s" style="margin-left:auto" wire:click="submitToCosting" @if($readOnly) disabled @endif wire:loading.attr="disabled">➜ Submit ke Costing (v{{ $source->revision_no + 1 }})</button>
    </div>
</div>
