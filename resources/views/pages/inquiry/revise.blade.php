<?php

use App\Models\Master\MatRaw;
use App\Models\Master\ProdType;
use App\Models\Master\RefFinishingType;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\IqpPart;
use App\Models\Sdi\IqpPartMaterial;
use App\Models\Sys\SysConstant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

// Layar REVISE (docs/mockups/inquiry_revise_v2.html) — ubah spec via copy-record
// + versi baru. Scope manual (R-REV-1, docs/inquiryproduct_delta_revise.md) menentukan
// blok edit apa yang tampil; field di luar scope tetap reuse verbatim dari baku.
new #[Title('TELASIH MAKMUR — REVISE')] class extends Component
{
    use WithFileUploads;

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

    // Temp upload artwork baru per index part -- hanya relevan/ditampilkan saat
    // scope ARTWORK aktif (permintaan user 2026-09-04: "REVISE juga harus ada
    // upload image jika terjadi perubahan image"). Pola sama persis dgn
    // inquiry/form.blade.php (temp upload Livewire, preview via temporaryUrl(),
    // dipindah ke storage permanen saat Simpan Draft).
    public array $artworkUploads = [];

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
            $this->readOnly = (bool) $this->inquiry->stat_confirm;

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

                // Artwork LAMA jangan langsung jadi artwork_path draft baru (2 versi
                // Inquiry akan berbagi 1 file fisik, resiko salah hapus) -- pindah ke
                // source_artwork_* dulu, persistPendingArtwork() yg urus salin fisik
                // saat disimpan kalau memang tidak diganti manual.
                $this->parts[$i]['source_artwork_path'] = $p['artwork_path'];
                $this->parts[$i]['source_artwork_original_filename'] = $p['artwork_original_filename'];
                $this->parts[$i]['artwork_path'] = null;
                $this->parts[$i]['artwork_original_filename'] = null;
            }

            $this->design_number = $this->source->design_number ?? '';

            return;
        }

        abort(404);
    }

    protected function fillPartsFrom(IqpInquiry $base): void
    {
        $parts = $base->parts()->with(['materials.rawMaterial', 'finishings'])->orderBy('part_seq')->get();

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
            'artwork_path' => $p->artwork_path,
            'artwork_original_filename' => $p->artwork_original_filename,
            // Dipakai HANYA sbg fallback saat draft baru (lihat mount() cabang
            // source_id) -- salin fisik ke path baru saat disimpan kalau sales
            // TIDAK upload artwork baru, drpd 2 versi Inquiry berbagi 1 file
            // (hapus/ganti di 1 versi bisa merusak versi lain).
            'source_artwork_path' => null,
            'source_artwork_original_filename' => null,
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
            'finishings' => $p->finishings->map(fn ($f) => [
                'finishing_type_id' => (string) $f->finishing_type_id,
                'attribute' => $f->attribute ?? '',
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

    // Matriks pemicu — persis Tier 2 (docs/inquiryproduct_delta_revise.md §TIER 2).
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
            $type = ProdType::with(['params' => fn ($q) => $q->where('activate', true)->orderBy('seq')])->find($typeCode);

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
            ->where('activate', true)
            ->where('stat_confirm', true)
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

        $this->parts[$partIndex]['materials'][$matIndex]['rawmat_id'] = $material->material_id;
        $this->parts[$partIndex]['materials'][$matIndex]['display_name'] = $material->material_name;
        $this->parts[$partIndex]['materials'][$matIndex]['gram_weight'] = $material->gram_weight;
        $this->parts[$partIndex]['materials'][$matIndex]['size_p'] = $material->cm_length;
        $this->parts[$partIndex]['materials'][$matIndex]['size_l'] = $material->cm_width;
        $this->parts[$partIndex]['materials'][$matIndex]['query'] = '';
    }

    // ================= ARTWORK BARU (scope ARTWORK) =================
    // Upload dipakai HANYA kalau sales mencentang scope Artwork (menandakan
    // desain memang berubah) -- lihat blok "@if (in_array('ARTWORK', $scope..."
    // di template. Pola upload sama persis inquiry/form.blade.php (temp upload
    // Livewire, preview via temporaryUrl(), analisis warna baca file temp) --
    // TIDAK porting suggestion engine bahan/finishing dari histori (di luar
    // scope revisi 2026-09-04, REVISE sudah punya alur substitusi bahan sendiri
    // di blok scope MATERIAL).

    #[Computed]
    public function artworkMaxMb(): float
    {
        return SysConstant::number('INQUIRY_ARTWORK_MAX_MB', 10);
    }

    public function updatedArtworkUploads($value, $key): void
    {
        $index = (int) $key;

        if ($this->readOnly || ! isset($this->parts[$index]) || ! $value) {
            return;
        }

        $this->validateOnly("artworkUploads.{$key}", [
            "artworkUploads.{$key}" => 'image|mimes:jpg,jpeg,png|max:'.($this->artworkMaxMb * 1024),
        ]);

        $this->parts[$index]['artwork_original_filename'] = $value->getClientOriginalName();
        $this->parts[$index]['artwork_color_suggestion'] = $this->analyzeImageColors($value->getRealPath());
    }

    public function removeArtwork(int $index): void
    {
        if ($this->readOnly || ! isset($this->parts[$index])) {
            return;
        }

        unset($this->artworkUploads[$index]);

        $path = $this->parts[$index]['artwork_path'] ?? null;

        if ($path) {
            Storage::disk('public')->delete($path);

            if ($this->parts[$index]['id']) {
                IqpPart::whereKey($this->parts[$index]['id'])->update([
                    'artwork_path' => null,
                    'artwork_original_filename' => null,
                ]);
            }
        }

        $this->parts[$index]['artwork_path'] = null;
        $this->parts[$index]['artwork_original_filename'] = null;
        $this->parts[$index]['artwork_color_suggestion'] = null;
    }

    // Sama persis logika analisis warna di inquiry/form.blade.php (GD, downsample
    // 40x40, kuantisasi /32) -- saran KASAR jumlah warna/grayscale, bukan deteksi
    // presisi cetak. Tidak diekstrak ke trait/service bersama (2 file kecil,
    // duplikasi < abstraksi prematur, lihat CLAUDE.md §7.2).
    protected function analyzeImageColors(?string $full): ?array
    {
        if (! $full || ! is_file($full)) {
            return null;
        }

        $info = @getimagesize($full);

        if (! $info) {
            return null;
        }

        $img = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($full),
            IMAGETYPE_PNG => @imagecreatefrompng($full),
            default => null,
        };

        if (! $img) {
            return null;
        }

        $sw = 40;
        $sh = 40;
        $sample = imagecreatetruecolor($sw, $sh);
        imagecopyresampled($sample, $img, 0, 0, 0, 0, $sw, $sh, imagesx($img), imagesy($img));
        imagedestroy($img);

        $colors = [];
        $isGrayscale = true;

        for ($y = 0; $y < $sh; $y++) {
            for ($x = 0; $x < $sw; $x++) {
                $rgb = imagecolorat($sample, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                if (abs($r - $g) > 12 || abs($g - $b) > 12 || abs($r - $b) > 12) {
                    $isGrayscale = false;
                }

                $colors[intdiv($r, 32).'-'.intdiv($g, 32).'-'.intdiv($b, 32)] = true;
            }
        }

        imagedestroy($sample);

        $distinct = count($colors);

        $code = match (true) {
            $isGrayscale && $distinct <= 3 => '1/0',
            ! $isGrayscale && $distinct <= 4 => '2/0',
            ! $isGrayscale && $distinct <= 10 => '4/0',
            default => '4/4',
        };

        return ['code' => $code, 'distinct_colors' => $distinct, 'is_grayscale' => $isGrayscale];
    }

    // ================= FINISHING (scope PROCESS) =================
    // Yang direvisi adalah FINISHING (input); routing dihitung ulang engine
    // BOP di ProDev dari finishing ini (BACKLOG §18).

    #[Computed]
    public function finishingTypeOptions()
    {
        return RefFinishingType::where('activate', true)->orderBy('finishing_name')->get();
    }

    public function addProcessRow(int $partIndex): void
    {
        if ($this->readOnly || ! isset($this->parts[$partIndex])) {
            return;
        }

        $this->parts[$partIndex]['finishings'][] = ['finishing_type_id' => '', 'attribute' => ''];
    }

    public function removeProcessRow(int $partIndex, int $rowIndex): void
    {
        if ($this->readOnly || ! isset($this->parts[$partIndex]['finishings'][$rowIndex])) {
            return;
        }

        unset($this->parts[$partIndex]['finishings'][$rowIndex]);
        $this->parts[$partIndex]['finishings'] = array_values($this->parts[$partIndex]['finishings']);
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

    protected function persistPartFinishings(IqpPart $part, int $partIndex): void
    {
        $part->finishings()->delete();
        $names = $this->finishingTypeOptions->pluck('finishing_name', 'id_finishing_type');

        foreach (array_values(array_filter(
            $this->parts[$partIndex]['finishings'] ?? [],
            fn ($r) => ! empty($r['finishing_type_id']) && isset($names[$r['finishing_type_id']])
        )) as $seq => $row) {
            $part->finishings()->create([
                'seq' => ($seq + 1) * 10,
                'finishing_type_id' => (int) $row['finishing_type_id'],
                'finishing_name' => $names[$row['finishing_type_id']],
                'attribute' => trim($row['attribute'] ?? '') ?: null,
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

            $this->persistPendingArtwork($part, $index);
            $this->persistPartMaterials($part, $index);
            $this->persistPartFinishings($part, $index);
        }

        $this->inquiry->parts()->whereNotIn('id_part', $keepIds)->delete();
    }

    // Prioritas: (1) upload baru sesi ini menang -- pindah dari temp ke storage
    // permanen. (2) kalau tidak ada upload baru TAPI part ini py source_artwork_path
    // (draft baru dari REVISE, artwork lama belum diganti), salin fisik file lama
    // ke path baru milik part INI -- SENGAJA disalin bukan direuse path yg sama,
    // supaya versi lama & baru independen (hapus/ganti artwork di 1 versi tidak
    // pernah merusak versi lain, lihat catatan di mount()/fillPartsFrom()).
    protected function persistPendingArtwork(IqpPart $part, int $index): void
    {
        $file = $this->artworkUploads[$index] ?? null;

        if ($file) {
            $oldPath = $part->artwork_path;
            $path = $file->store("inquiry/{$part->id_part}/artwork", 'public');

            if ($oldPath && $oldPath !== $path) {
                Storage::disk('public')->delete($oldPath);
            }

            $part->update([
                'artwork_path' => $path,
                'artwork_original_filename' => $file->getClientOriginalName(),
            ]);

            $this->parts[$index]['artwork_path'] = $path;
            unset($this->artworkUploads[$index]);

            return;
        }

        $sourcePath = $this->parts[$index]['source_artwork_path'] ?? null;

        if (! $part->artwork_path && $sourcePath && Storage::disk('public')->exists($sourcePath)) {
            $newPath = "inquiry/{$part->id_part}/artwork/".basename($sourcePath);
            Storage::disk('public')->copy($sourcePath, $newPath);

            $part->update([
                'artwork_path' => $newPath,
                'artwork_original_filename' => $this->parts[$index]['source_artwork_original_filename'] ?? null,
            ]);

            $this->parts[$index]['artwork_path'] = $newPath;
            $this->parts[$index]['artwork_original_filename'] = $this->parts[$index]['source_artwork_original_filename'] ?? null;
        }
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
                'stat_confirm' => true,
                'stat_cancel' => true,
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

        $this->inquiry->update(['stat_confirm' => true, 'updated_by' => Auth::user()->login]);
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
                <x-form.input legacy label="Alasan Revisi *" wire:model="revise_reason" placeholder="mis. Tambah logo Halal Indonesia (spot ink) — permintaan customer" :disabled="$readOnly" name="revise_reason" />
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
                <x-form.input legacy label="No. Design / Proof" wire:model="design_number" placeholder="mis. DS003480" :disabled="$readOnly" />

                @foreach ($parts as $i => $part)
                    <div class="sec-title">{{ $part['part_name'] ?: 'Part '.($i + 1) }}</div>
                    <div style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:10px" x-data="{ zoomOld: false, zoomNew: false }">
                        @if ($part['source_artwork_path'] ?? null)
                            <div style="width:150px">
                                <div class="hint" style="margin-bottom:4px">Artwork Lama (v{{ $source->revision_no }})</div>
                                <img src="{{ asset('storage/'.$part['source_artwork_path']) }}" @click="zoomOld = true"
                                     style="width:150px;height:150px;object-fit:cover;border-radius:6px;border:1px solid #c7cfda;cursor:zoom-in" alt="artwork lama">
                                <div x-show="zoomOld" x-cloak @click.self="zoomOld = false" @keydown.escape.window="zoomOld = false"
                                     style="position:fixed;inset:0;z-index:250;background:rgba(15,22,45,.72);display:flex;align-items:center;justify-content:center;padding:24px">
                                    <img src="{{ asset('storage/'.$part['source_artwork_path']) }}" style="max-width:90vw;max-height:88vh;border-radius:8px;box-shadow:0 12px 40px rgba(0,0,0,.4)" alt="artwork lama ukuran penuh">
                                </div>
                            </div>
                        @endif

                        <div style="width:150px">
                            <div class="hint" style="margin-bottom:4px">Artwork Baru</div>
                            @php($newPreviewUrl = isset($artworkUploads[$i]) ? $artworkUploads[$i]->temporaryUrl() : (($part['artwork_path'] ?? null) ? asset('storage/'.$part['artwork_path']) : null))
                            @if ($newPreviewUrl)
                                <img src="{{ $newPreviewUrl }}" @click="zoomNew = true"
                                     style="width:150px;height:150px;object-fit:cover;border-radius:6px;border:2px solid #22a06b;cursor:zoom-in" alt="artwork baru">
                                <div x-show="zoomNew" x-cloak @click.self="zoomNew = false" @keydown.escape.window="zoomNew = false"
                                     style="position:fixed;inset:0;z-index:250;background:rgba(15,22,45,.72);display:flex;align-items:center;justify-content:center;padding:24px">
                                    <img src="{{ $newPreviewUrl }}" style="max-width:90vw;max-height:88vh;border-radius:8px;box-shadow:0 12px 40px rgba(0,0,0,.4)" alt="artwork baru ukuran penuh">
                                </div>
                                @unless($readOnly)
                                    <button type="button" class="btn d xs" wire:click="removeArtwork({{ $i }})" style="margin-top:4px;width:150px">✕ hapus</button>
                                @endunless
                                @if ($part['artwork_color_suggestion'] ?? null)
                                    @php($sc = $part['artwork_color_suggestion'])
                                    <div class="hint" style="margin-top:4px">💡 Saran warna: <b>{{ $sc['code'] }}</b> ({{ $sc['distinct_colors'] }} warna{{ $sc['is_grayscale'] ? ', B/W' : '' }})</div>
                                @endif
                            @elseif(! $readOnly)
                                <input type="file" x-ref="artworkInput{{ $i }}" wire:model="artworkUploads.{{ $i }}" accept=".jpg,.jpeg,.png" style="display:none">
                                <button type="button" class="btn g xs" style="width:150px" @click="$refs['artworkInput{{ $i }}'].click()">📎 Upload Artwork Baru</button>
                                <div wire:loading wire:target="artworkUploads.{{ $i }}" class="hint" style="color:#1560a8">⏳ mengunggah…</div>
                                @error('artworkUploads.'.$i) <div style="font-size:10px;color:#c0392b">{{ $message }}</div> @enderror
                            @else
                                <div class="hint">Belum ada artwork baru.</div>
                            @endif
                        </div>
                    </div>
                @endforeach

                <div class="note b" style="margin-top:10px">Artwork baru akan melalui proof ulang (modul Proof/Prove) sebelum quotation — di luar layar ini.</div>
            </div>
        @endif

        @if (in_array('COLOR', $scope, true))
            <div class="eb">
                <div class="t">🖌️ Warna — Konfigurasi Cetak per Part</div>
                @foreach ($parts as $i => $part)
                    <div class="grid two" style="margin-bottom:8px">
                        <x-form.input legacy label="{{ $part['part_name'] ?: 'Part '.($i + 1) }} — Warna (mis. 4/4)" wire:model="parts.{{ $i }}.cfg_color" :disabled="$readOnly" />
                        <x-form.input legacy label="Catatan tinta/plate" wire:model="parts.{{ $i }}.cfg_ink" placeholder="mis. +1 spot Pantone 348C" :disabled="$readOnly" />
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
                            <x-form.input legacy label="{{ $param['label'] }} {{ $param['uom'] ? '('.$param['uom'].')' : '' }}" wire:model="parts.{{ $i }}.dims.{{ $param['key'] }}" :disabled="$readOnly" />
                        @empty
                            <x-form.input legacy label="Panjang (cm)" type="number" step="0.01" wire:model="parts.{{ $i }}.flat_w" :disabled="$readOnly" />
                            <x-form.input legacy label="Lebar (cm)" type="number" step="0.01" wire:model="parts.{{ $i }}.flat_h" :disabled="$readOnly" />
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
                                                <div class="ac-i" wire:click="selectMaterial({{ $i }}, {{ $j }}, {{ $opt->material_id }})">
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
                <div class="t">⚙️ Finishing (routing dihitung ulang di ProDev)</div>
                @foreach ($parts as $i => $part)
                    <div class="sec-title">{{ $part['part_name'] ?: 'Part '.($i + 1) }}</div>
                    @foreach ($part['finishings'] as $k => $fin)
                        <div class="inkrow" style="grid-template-columns:1fr 1fr 30px" wire:key="rvfin-{{ $i }}-{{ $k }}">
                            <select wire:model="parts.{{ $i }}.finishings.{{ $k }}.finishing_type_id" x-data x-effect="$el.value = @js((string) $fin['finishing_type_id'])" @if($readOnly) disabled @endif>
                                <option value="">— pilih jenis finishing —</option>
                                @foreach ($this->finishingTypeOptions as $ft)
                                    <option value="{{ $ft->id_finishing_type }}">{{ $ft->finishing_name }}</option>
                                @endforeach
                            </select>
                            <input type="text" maxlength="60" wire:model="parts.{{ $i }}.finishings.{{ $k }}.attribute" placeholder="atribut, mis. Gold Puyeh" @if($readOnly) disabled @endif>
                            <button type="button" class="btn d xs" wire:click="removeProcessRow({{ $i }}, {{ $k }})" @if($readOnly) disabled @endif>✕</button>
                        </div>
                    @endforeach
                    <button type="button" class="addln" wire:click="addProcessRow({{ $i }})" @if($readOnly) disabled @endif>+ finishing</button>
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
