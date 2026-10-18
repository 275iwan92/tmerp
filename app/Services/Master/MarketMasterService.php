<?php

/*
 * REMARK
 * Tujuan       : Master Product Market dari layar v2 sales-market-form: simpan Kategori Produk (pengelompok) dan Detail Kategori (Tipe Produk, default Inquiry New, bahan #1 Paper dan #2 Sheet, foto), aktif / nonaktif, foto, dan Ukuran Standar per detail (dialog tambah, ubah, hapus).
 * Sumber hulu  : Aturan dipindah dari halaman v1 pages::lookup.product-market-entry (diarsipkan di tmerp_unused/lookup_product_market_entry_v1_2026-10-19.blade.php): kode otomatis dari nama, kategori hanya pengelompok (tanpa tipe, bahan, warna, jenjang qty, ukuran), detail wajib Tipe Produk, warna n/n, jenjang qty angka dipisah koma, dimensi mengikuti parameter Tipe Produk. Disetujui Jo 2026-10-19: Tipe Produk tidak boleh diganti selama ada ukuran, kategori yang punya detail tidak boleh jadi detail, detail yang punya ukuran tidak boleh jadi kategori.
 * Input        : Isian form (header), file foto, rantai bahan (paper, sheet = id kategori terdalam), isian dialog ukuran (label + dimensi per param_key), user.
 * Output       : tm2mst.prod_market, prod_type_size (market_id terisi), prod_type_size_val berubah; foto di disk public product-market/{id}; ValidationException bila aturan dilanggar.
 * Dipakai oleh : routes/v2_master.php (v2.sales.market.*), layar sales-market-form, tes MarketMasterServiceTest.
 * Status       : Aktif. Dibaca Inquiry New lewat Sales::productMarkets (tidak diubah).
 */

namespace App\Services\Master;

use App\Models\Master\MatCategory;
use App\Models\Master\ProdMarket;
use App\Models\Master\ProdType;
use App\Models\Master\ProdTypeParam;
use App\Models\Master\ProdTypeSize;
use App\Models\Master\ProdTypeSizeVal;
use App\Models\Sys\SecUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class MarketMasterService
{
    /** Dimensi yang disimpan per ukuran pasaran; parameter teknis (bleed, overlap, flap) tetap default Tipe Produk. */
    public const SIZE_KEYS = ['product_length', 'product_width', 'product_height', 'top_length', 'top_width', 'lid_height', 'diameter'];

    /** Akar rantai bahan: #1 selalu Paper, #2 selalu Sheet (user 2026-09-26). */
    public const CHAIN_ROOT = ['paper' => 'Paper', 'sheet' => 'Sheet'];

    public const COLOURS = ['1/0' => '1/0 (1 colour, 1 side)', '2/0' => '2/0', '4/0' => '4/0 (full colour, 1 side)', '4/1' => '4/1', '4/4' => '4/4 (full colour, 2 sides)'];

    public function __construct(private MaterialCategoryService $categories) {}

    public function save(?ProdMarket $m, array $in, SecUser $user, ?UploadedFile $photo = null): ProdMarket
    {
        $in['market_code'] = Str::upper(Str::slug((string) (($in['market_code'] ?? '') ?: ($in['market_label'] ?? '')), '_'));
        $in['parent_id'] = ($in['parent_id'] ?? '') === '' ? null : $in['parent_id'];
        $isDetail = $in['parent_id'] !== null;
        $in['photo'] = $photo;

        $d = Validator::make($in, [
            'market_code' => ['required', 'max:40', Rule::unique(ProdMarket::class, 'market_code')->ignore($m?->id_market, 'id_market')],
            'market_label' => ['required', 'max:100'],
            'parent_id' => ['nullable', 'integer', 'exists:tm2mst.prod_market,id_market'],
            'type_code' => [$isDetail ? 'required' : 'nullable', 'exists:tm2mst.prod_type,type_code'],
            'default_color' => ['nullable', 'regex:/^\d\/\d$/'],
            'default_tiers' => ['nullable', 'regex:/^\s*\d[\d.]*(\s*,\s*\d[\d.]*)*\s*$/'],
            'notes' => ['nullable', 'max:500'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ], [], ['market_label' => 'Name', 'market_code' => 'Code', 'type_code' => 'Product Type', 'default_color' => 'Print colour (e.g. 4/0)', 'default_tiers' => 'Qty tiers (e.g. 1000,3000,5000)'])->validate();

        $this->guardLevels($m, $isDetail ? (int) $d['parent_id'] : null, $isDetail ? $d['type_code'] : null);

        return DB::connection('tm2mst')->transaction(function () use ($m, $d, $in, $isDetail, $user) {
            $data = [
                // Kategori (induk) hanya pengelompok: tanpa Tipe Produk, bahan, warna, jenjang qty.
                'parent_id' => $isDetail ? (int) $d['parent_id'] : null, 'market_code' => $d['market_code'], 'market_label' => trim($d['market_label']),
                'type_code' => $isDetail ? $d['type_code'] : null,
                'paper_category_id' => $isDetail ? $this->deepest('paper', (int) ($in['paper'] ?? 0)) : null,
                'sheet_category_id' => $isDetail ? $this->deepest('sheet', (int) ($in['sheet'] ?? 0)) : null,
                'default_color' => $isDetail ? (($d['default_color'] ?? '') ?: null) : null,
                'default_tiers' => $isDetail && ! empty($d['default_tiers']) ? preg_replace('/\s+/', '', $d['default_tiers']) : null,
                'notes' => ($d['notes'] ?? '') !== '' ? $d['notes'] : null, 'sort_order' => (int) $d['sort_order'], 'updated_by' => $user->login,
            ];
            if ($m) {
                $m->update($data);
            } else {
                $m = ProdMarket::create($data + ['activate' => true, 'created_by' => $user->login]);
            }
            if ($in['photo']) {
                $old = $m->photo_path;
                $m->update(['photo_path' => $in['photo']->store("product-market/{$m->id_market}", 'public')]);
                if ($old) {
                    Storage::disk('public')->delete($old);
                }
            }

            return $m;
        });
    }

    /** Dua tingkat saja; Tipe Produk terkunci selama ada ukuran; kategori tidak jadi detail bila punya detail; detail tidak jadi kategori bila punya ukuran. */
    private function guardLevels(?ProdMarket $m, ?int $parentId, ?string $typeCode): void
    {
        if ($parentId !== null) {
            $parent = ProdMarket::find($parentId);
            if ($parent && ($parent->parent_id !== null || $m?->id_market === $parent->id_market)) {
                throw ValidationException::withMessages(['parent_id' => 'The parent must be a Product Category (a category without parent), and not the record itself.']);
            }
        }
        if (! $m) {
            return;
        }
        if ($parentId !== null && $m->children()->exists()) {
            throw ValidationException::withMessages(['parent_id' => 'This Product Category has details under it, so it cannot become a Category Detail (two levels only).']);
        }
        $sizes = $m->sizes()->count();
        if ($parentId === null && $sizes > 0) {
            throw ValidationException::withMessages(['parent_id' => 'This Category Detail has '.$sizes.' standard size(s). Remove them first before turning it into a Product Category.']);
        }
        if ($parentId !== null && $sizes > 0 && $m->type_code && $m->type_code !== $typeCode) {
            throw ValidationException::withMessages(['type_code' => 'This Category Detail has '.$sizes.' standard size(s) that follow its Product Type. Remove them first before changing the Product Type.']);
        }
    }

    /** Kategori bahan terdalam: Paper boleh berhenti di akar (kertas apa saja, bawaan); Sheet baru terisi bila jenisnya (tingkat 2) dipilih. */
    private function deepest(string $chain, int $id): ?int
    {
        $root = (int) MatCategory::roots()->where('category_name', self::CHAIN_ROOT[$chain])->value('id_category');
        $node = $id ? MatCategory::find($id) : null;
        if (! $node || ($this->categories->chain($node)[0]->id_category ?? null) !== $root) {
            return $chain === 'paper' ? $root : null;
        }

        return $chain === 'sheet' && (int) $node->level < 2 ? null : $node->id_category;
    }

    public function setActive(ProdMarket $m, bool $active, SecUser $user): void
    {
        $m->update(['activate' => $active, 'updated_by' => $user->login]);
    }

    public function removePhoto(ProdMarket $m, SecUser $user): void
    {
        if ($m->photo_path) {
            Storage::disk('public')->delete($m->photo_path);
        }
        $m->update(['photo_path' => null, 'updated_by' => $user->login]);
    }

    public function saveSize(ProdMarket $m, ?int $sizeId, array $in, SecUser $user): ProdTypeSize
    {
        if ($m->parent_id === null || ! $m->type_code) {
            throw ValidationException::withMessages(['size_label' => 'Standard sizes belong to a Category Detail with a Product Type. Save the Product Type first.']);
        }
        $params = $this->dimParams($m->type_code);
        $rules = ['size_label' => ['required', 'max:100']];
        foreach ($params as $p) {
            $rules['dim_'.$p->param_key] = ['nullable', 'numeric', 'min:0'];
        }
        $in = array_map(fn ($v) => is_string($v) ? str_replace(',', '.', trim($v)) : $v, $in);
        $d = Validator::make($in, $rules, [], ['size_label' => 'Size name'] + $params->mapWithKeys(fn ($p) => ['dim_'.$p->param_key => $p->param_label])->all())->validate();

        return DB::connection('tm2mst')->transaction(function () use ($m, $sizeId, $d, $params, $user) {
            $size = $sizeId ? ProdTypeSize::where('market_id', $m->id_market)->findOrFail($sizeId)
                : new ProdTypeSize(['market_id' => $m->id_market, 'created_by' => $user->login, 'sort_order' => (int) ProdTypeSize::where('market_id', $m->id_market)->max('sort_order') + 10]);
            $size->fill(['type_code' => $m->type_code, 'size_label' => trim($d['size_label']), 'activate' => true, 'updated_by' => $user->login,
                'size_code' => rtrim(Str::upper(Str::limit(Str::slug($m->market_code.' '.$d['size_label'], '_'), 40, '')), '_')])->save();

            $size->values()->delete();
            foreach ($params as $p) {
                $v = $d['dim_'.$p->param_key] ?? null;
                if ($v !== null && $v !== '') {
                    ProdTypeSizeVal::create(['type_size_id' => $size->id_type_size, 'param_key' => $p->param_key, 'value_num' => $v]);
                }
            }

            return $size;
        });
    }

    public function deleteSize(ProdMarket $m, int $sizeId): void
    {
        $size = ProdTypeSize::where('market_id', $m->id_market)->findOrFail($sizeId);
        DB::connection('tm2mst')->transaction(function () use ($size) {
            $size->values()->delete();
            $size->delete();
        });
    }

    /** Kolom dimensi ukuran mengikuti parameter Tipe Produk. */
    public function dimParams(?string $typeCode)
    {
        return ProdTypeParam::where('type_code', (string) $typeCode)->where('activate', true)->whereIn('param_key', self::SIZE_KEYS)->orderBy('seq')->get(['param_key', 'param_label', 'unit']);
    }

    /** Urutan tampil otomatis = urutan terakhir + 10 (record baru). */
    public function nextSortOrder(): int
    {
        return (int) ProdMarket::where('sort_order', '<', 999)->max('sort_order') + 10;
    }

    /** @return array<int, string> Kategori Produk (induk) selain diri sendiri */
    public function groupOptions(?int $exceptId): array
    {
        return ProdMarket::whereNull('parent_id')->where('activate', true)->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->orderBy('sort_order')->orderBy('market_label')->pluck('market_label', 'id_market')->all();
    }

    /** @return array<string, string> */
    public function typeOptions(): array
    {
        return ProdType::where('activate', true)->orderBy('type_name')->pluck('type_name', 'type_code')->all();
    }

    /** Bahan untuk x-dl::code-chain: hanya rantai di bawah akar Paper atau Sheet. */
    public function chainNodes(string $chain): array
    {
        $root = (int) MatCategory::roots()->where('category_name', self::CHAIN_ROOT[$chain])->value('id_category');

        return array_values(array_filter($this->categories->nodes(), fn ($n) => ($this->categories->chain($n[0])[0]->id_category ?? null) === $root));
    }

    /** Id akar rantai (nilai awal kotak bahan). */
    public function chainRoot(string $chain): ?int
    {
        return MatCategory::roots()->where('category_name', self::CHAIN_ROOT[$chain])->value('id_category');
    }

    /** Riwayat tab History: terbaru di atas. */
    public function history(ProdMarket $m): array
    {
        $rows = [['at' => $m->created_at?->toDateTimeString(), 'by' => $m->created_by, 'action' => 'Created', 'note' => '']];
        if ($m->updated_at && $m->updated_at != $m->created_at) {
            $rows[] = ['at' => $m->updated_at->toDateTimeString(), 'by' => $m->updated_by, 'action' => 'Last modified', 'note' => ''];
        }

        return array_reverse($rows);
    }
}
