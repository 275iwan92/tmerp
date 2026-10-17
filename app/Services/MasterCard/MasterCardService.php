<?php

/*
 * REMARK
 * Tujuan       : Layanan Master Card dummy / impor: membuat inquiry, produk, dan kartu dari berkas; tarif PPN inquiry awal dibaca dari Tax Rate.
 * Sumber hulu  : Berkas Master Card (docs/attachments), CustCustomer, ProductMasterSync, TaxRate::ppn().
 * Input        : Data kartu, berkas gambar.
 * Output       : iqp_inquiry, prod_master_card, berkas.
 * Dipakai oleh : MasterCardDummyEngine, perintah dummy:master-card.
 * Status       : Aktif.
 */

namespace App\Services\MasterCard;

use App\Models\Master\CustCustomer;
use App\Models\Master\ProdMasterCard;
use App\Models\Master\ProdMasterCardFile;
use App\Models\Master\ProdProduct;
use App\Models\Sdi\IqpInquiry;
use App\Services\Sales\ProductMasterSync;
use App\Support\Finance\TaxRate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

// Master Card Collection (user 2026-10-01): upload kartu lama (Excel / foto) -> koleksi -> Make Master Product / Make Inquiry.
// Master Product TIDAK ditulis langsung: kartu -> Inquiry (sumber "MASTER CARD") -> ProductMasterSync::fromInquiry, jalur yang
// sama dengan Quotation WIN, supaya aturan Master Product tetap satu (docs/flow_product_master.md). Kartu per part (A/B) dengan
// group_key sama digabung jadi 1 produk multi-part. Setiap kartu menyimpan SVG dieline mockup (TmDieline) — wajib.
final class MasterCardService
{
    public const DISK = 'public';

    // [kata kunci di kartu, prod_process.process_name]; urutan penting (Laminating sebelum Laminasi, Plong sebelum Emboss).
    private const PROCESS = [['LAMINATING GLOSSY', 'LAMINATING GLOSSY'], ['LAMINATING DOF', 'LAMINATING DOFT'], ['LAMINASI', 'LAMINASI'],
        ['POTONG', 'POTONG'], ['CETAK', 'CETAK'], ['PLONG', 'PLONG'], ['KUPAS', 'KUPAS'], ['LEM', 'LEM MESIN'], ['HITUNG', 'HITUNG'],
        ['WRAPPING', 'WRAPPING'], ['STRIPPING', 'QC PACK'], ['PACK', 'QC PACK'], ['VARNISH', 'VARNISH'], ['HOT PRINT', 'HOT PRINT'],
        ['EMBOSS', 'EMBOSS'], ['FOIL', 'FOIL STAMPING'], ['UV', 'UV'], ['QC', 'QC']];

    // Langkah rakitan (Double Join) -> BOP Body, bukan per part.
    private const ASSEMBLY = ['LEM MESIN', 'HITUNG', 'QC PACK', 'WRAPPING'];

    private const FINISHING = [['LAMINATING DOF', 'Laminating Doff'], ['LAMINATING GLOSS', 'Laminating Glossy'], ['UV SPOT', 'UV Spot'],
        ['VARNISH', 'Varnish'], ['HOT PRINT', 'Hot Print'], ['EMBOSS', 'Emboss'], ['DEBOSS', 'Deboss'], ['FOIL', 'Foil Stamping']];

    public const ACC = ['email' => 'EMAIL', 'acc_digital' => 'DIGITAL', 'box_polos' => 'SAMPLE'];

    public function __construct(private string $login) {}

    // ---------------------------------------------------------------- import

    public function import(UploadedFile $file, string $origin = 'LIVE'): ProdMasterCard
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if (in_array($ext, ['xlsx', 'xlsm', 'xls'], true)) {
            $data = (new MasterCardReader)->read($file->getRealPath(), $file->getClientOriginalName());

            return $this->fromData($data, 'XLSX', $origin, $file);
        }
        if (in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            $name = trim(preg_replace('/^MC[-_ ]*/i', '', pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)));

            return $this->fromData(['product_name' => $name, 'part_label' => preg_match('/\s([AB])$/', $name, $m) ? $m[1] : null, 'images' => []], 'PHOTO', $origin, $file);
        }
        throw ValidationException::withMessages(['files' => tf(':name: unsupported format (Excel .xlsx or photo .jpg/.png).', ['name' => $file->getClientOriginalName()])]);
    }

    public function fromData(array $d, string $kind, string $origin = 'LIVE', ?UploadedFile $source = null): ProdMasterCard
    {
        return DB::connection('tm2mst')->transaction(function () use ($d, $kind, $origin, $source) {
            // Impor ulang kartu yang sama (kode item + no. desain + part) memperbarui baris lama selama belum dikonversi.
            $card = ! empty($d['card_code']) ? ProdMasterCard::where('card_code', $d['card_code'])->where('design_no', $d['design_no'] ?? null)
                ->where('part_label', $d['part_label'] ?? null)->whereNotIn('status', ['PRODUCT', 'INQUIRY'])->first() : null;
            $card ??= new ProdMasterCard(['created_by' => $this->login, 'data_origin' => $origin]);

            $processes = $this->processes($d['process'] ?? [], MasterCardShape::doubleJoin($d));
            $card->fill([
                'source_kind' => $kind,
                'card_code' => $d['card_code'] ?? null, 'design_no' => $d['design_no'] ?? null, 'product_name' => $d['product_name'] ?? null,
                'part_label' => $d['part_label'] ?? null, 'card_date' => $d['card_date'] ?? null, 'customer_text' => $d['customer_text'] ?? null,
                'customer_id' => $this->customerId($d['customer_text'] ?? null),
                'dim_l' => $d['dim_l'] ?? null, 'dim_w' => $d['dim_w'] ?? null, 'dim_h' => $d['dim_h'] ?? null,
                'knife_code' => $d['knife_code'] ?? null, 'knife_w' => $d['knife_w'] ?? null, 'knife_h' => $d['knife_h'] ?? null, 'up_text' => $d['up_text'] ?? null,
                'print_w' => $d['print_w'] ?? null, 'print_h' => $d['print_h'] ?? null,
                'join_text' => $d['join_text'] ?? null, 'join_type_id' => $this->joinTypeId($d['join_text'] ?? null),
                'materials_json' => array_map(fn ($m) => $m + $this->category($m['name'], $m['quality'] ?? null), $d['materials'] ?? []),
                'finishing_json' => $this->finishings(array_merge($d['finishing'] ?? [], $d['process'] ?? [])),
                'process_json' => $processes,
                'colors_json' => $d['colors'] ?? ['first' => [], 'second' => []],
                'acc_json' => $d['acc'] ?? [],
                'pack_qty' => $this->packQty($d['process'] ?? []),
                'notes' => $d['notes'] ?? null,
                'raw_json' => $d['raw'] ?? null,
                'updated_by' => $this->login,
            ]);
            $card->group_key = $this->groupKey($card);
            if ($card->dim_l && $card->dim_w && $card->dim_h) {
                $card->type_code = MasterCardShape::suggestType($card->toArray());
                $card->dims_json = MasterCardShape::fit($card->toArray(), $card->type_code);
            }
            $this->refresh($card);

            if ($source) {
                $card->files()->whereIn('file_kind', ['SOURCE', 'PHOTO', 'PROOF', 'CUT_PLAN'])->get()->each(fn ($f) => $this->dropFile($f));
                $this->storeFile($card, $kind === 'PHOTO' ? 'PHOTO' : 'SOURCE', file_get_contents($source->getRealPath()), $source->getClientOriginalName(), $source->getMimeType());
            }
            foreach ($d['images'] ?? [] as $i => $img) {
                $this->storeFile($card, $img['kind'], $img['bytes'], strtolower($img['kind']).'-'.($i + 1).'.'.$img['ext'], 'image/'.($img['ext'] === 'jpg' ? 'jpeg' : $img['ext']), $i + 1);
            }

            return $card->refresh();
        });
    }

    public function storeFile(ProdMasterCard $card, string $kind, string $bytes, string $name, ?string $mime, int $seq = 1, ?string $part = null): ProdMasterCardFile
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name);
        $path = "master-card/{$card->id_master_card}/".strtolower($kind).'/'.uniqid().'-'.$safe;
        Storage::disk(self::DISK)->put($path, $bytes);

        return $card->files()->create(['file_kind' => $kind, 'part_label' => $part, 'path' => $path, 'original_name' => $name, 'mime' => $mime,
            'size' => strlen($bytes), 'seq' => $seq, 'created_by' => $this->login]);
    }

    public function dropFile(ProdMasterCardFile $f): void
    {
        Storage::disk(self::DISK)->delete($f->path);
        $f->delete();
    }

    // Hitung ulang SVG dieline, bentangan & status — dipanggil setiap kartu berubah.
    public function refresh(ProdMasterCard $card): ProdMasterCard
    {
        if ($card->type_code && $card->dims_json) {
            $check = MasterCardShape::check($card->toArray(), $card->type_code, $card->dims_json);
            $card->dieline_svg = MasterCardShape::svg($card->type_code, $card->dims_json);
            $card->flat_w = $check['flat']['w'] ?: null;
            $card->flat_h = $check['flat']['h'] ?: null;
        } else {
            $card->dieline_svg = null;
        }
        if (! $card->converted()) {
            // Produk kartu ini sudah ada di Master Product (no. desain / kode item sama) -> ditautkan, tidak dibuat dobel.
            if ($existing = $this->existingProduct($card)) {
                $card->product_id = $existing;
                $card->status = 'PRODUCT';
            } else {
                $card->status = $this->missing($card) ? 'INCOMPLETE' : 'READY';
            }
        }
        $card->save();

        return $card;
    }

    // ---------------------------------------------------------------- edit

    public function save(ProdMasterCard $card, array $in, array $artworks = []): ProdMasterCard
    {
        abort_if($card->converted(), 422, t('The card is already converted — it cannot be changed.'));
        $num = fn ($v) => $v === null || $v === '' ? null : (float) str_replace(',', '.', (string) $v);
        $card->fill([
            'card_code' => $in['card_code'] ?? null, 'design_no' => $in['design_no'] ?? null, 'product_name' => $in['product_name'] ?? null,
            'card_date' => $in['card_date'] ?? null, 'customer_id' => $in['customer_id'] ?: null, 'part_label' => $in['part_label'] ?: null,
            'dim_l' => $num($in['dim_l'] ?? null), 'dim_w' => $num($in['dim_w'] ?? null), 'dim_h' => $num($in['dim_h'] ?? null),
            'knife_code' => $in['knife_code'] ?? null, 'knife_w' => $num($in['knife_w'] ?? null), 'knife_h' => $num($in['knife_h'] ?? null),
            'up_text' => $in['up_text'] ?? null, 'join_type_id' => $in['join_type_id'] ?: null, 'pack_qty' => ($in['pack_qty'] ?? '') !== '' ? (int) $in['pack_qty'] : null,
            'notes' => $in['notes'] ?? null, 'updated_by' => $this->login,
        ]);
        $card->join_text = $card->join_type_id ? DB::connection('tm2mst')->table('mat_join_type')->where('id_join_type', $card->join_type_id)->value('label') ?? $card->join_text : $card->join_text;
        if (isset($in['materials']) && is_array($in['materials'])) {
            $card->materials_json = collect($card->materials_json ?? [])->map(fn ($m, $i) => array_merge($m, array_filter(['category_id' => (int) ($in['materials'][$i] ?? 0) ?: null])))->all();
        }

        $type = $in['type_code'] ?? null;
        // Nilai bukan angka (mis. hole_type ROUND/RECT) disimpan apa adanya.
        $dims = array_map(fn ($v) => is_numeric(str_replace(',', '.', (string) $v)) ? $num($v) : $v, array_filter($in['dieline']['dims'] ?? [], fn ($v) => $v !== null && $v !== ''));
        if ($type && ($type !== $card->type_code || ! $dims)) {
            $dims = MasterCardShape::fit($card->toArray(), $type);
        }
        foreach (['product_length' => 'dim_l', 'product_width' => 'dim_w', 'product_height' => 'dim_h'] as $k => $col) {
            if ($card->$col) {
                $dims[$k] = $card->$col;
            }
        }
        $card->type_code = $type ?: null;
        $card->dims_json = $type ? $dims : null;
        $card->group_key = $this->groupKey($card);

        foreach ($artworks as $part => $file) {
            if ($file instanceof UploadedFile) {
                $card->files()->where('file_kind', 'ARTWORK')->where('part_label', $part === '_' ? null : $part)->get()->each(fn ($f) => $this->dropFile($f));
                $this->storeFile($card, 'ARTWORK', file_get_contents($file->getRealPath()), $file->getClientOriginalName(), $file->getMimeType(), 1, $part === '_' ? null : $part);
            }
        }

        return $this->refresh($card);
    }

    public function delete(ProdMasterCard $card): void
    {
        abort_if($card->converted(), 422, t('The card is already converted — it cannot be deleted.'));
        Storage::disk(self::DISK)->deleteDirectory("master-card/{$card->id_master_card}");
        $card->delete();
    }

    // Field kunci yang wajib ada sebelum konversi: [field => label]. Dialog Make ... hanya menanyakan ini.
    public function missing(ProdMasterCard $card, string $target = 'product'): array
    {
        $m = [];
        foreach (['product_name' => t('Item Name'), 'customer_id' => t('Customer (master)'), 'type_code' => t('Product Type (dieline)'),
            'dim_l' => t('Length (L)'), 'dim_w' => t('Width (W)'), 'dim_h' => t('Height (H)')] as $f => $label) {
            if (! $card->$f) {
                $m[$f] = $label;
            }
        }
        if ($target === 'product' && ! $card->design_no) {
            $m['design_no'] = t('Design No.');
        }
        foreach ($card->materials_json ?? [] as $i => $mat) {
            if (empty($mat['category_id'])) {
                $m['materials.'.$i] = tf('Material #:n (master category)', ['n' => $i + 1]);
            }
        }
        if (! $card->materials_json) {
            $m['materials'] = t('Material (at least 1)');
        }

        return $m;
    }

    public function existingProduct(ProdMasterCard $card): ?int
    {
        if (! $card->design_no && ! $card->card_code) {
            return null;
        }

        return ProdProduct::where('card_status', 'ACTIVE')->where(fn ($q) => $q
            ->when($card->design_no, fn ($x) => $x->orWhere('design_no', $card->design_no))
            ->when($card->card_code, fn ($x) => $x->orWhere('product_code', $card->card_code)))->value('id_product');
    }

    // Kartu satu produk (A/B dengan group_key sama) yang belum dikonversi.
    public function group(ProdMasterCard $card): Collection
    {
        if (! $card->group_key) {
            return collect([$card]);
        }

        return ProdMasterCard::where('group_key', $card->group_key)->whereNotIn('status', ['PRODUCT', 'INQUIRY'])->orderBy('part_label')->get()
            ->push($card)->unique('id_master_card')->values();
    }

    // ---------------------------------------------------------------- convert

    public function makeProduct(ProdMasterCard $card, array $in): ProdProduct
    {
        $this->applyDialog($card, $in, 'product');
        if ($card->status === 'PRODUCT') {
            throw ValidationException::withMessages(['dialog' => t('This product already exists in Master Product (same design no. / item code) — the card is linked, no duplicate is made.')]);
        }

        return DB::connection('tm2sdi')->transaction(function () use ($card) {
            $group = $this->group($card);
            $inq = $this->inquiry($group, ['state' => 'SUBMITTED', 'confirm' => true]);
            $product = (new ProductMasterSync($this->login))->fromInquiry($inq);
            $product->update(array_filter(['product_code' => $card->card_code, 'design_no' => $card->design_no, 'design_date' => $card->card_date,
                'updated_by' => $this->login]));
            foreach ($group as $c) {
                $this->accRows($product, $c);
                $c->update(['status' => 'PRODUCT', 'product_id' => $product->id_product, 'updated_by' => $this->login]);
            }
            $mst = DB::connection('tm2mst');
            // Each part points back to its card (PlanFacts reads the knife code of the card through prod_part.mastercard_code).
            $mst->table('prod_part')->where('product_id', $product->id_product)->update(['data_origin' => $card->data_origin, 'mastercard_code' => $card->card_code]);
            $mst->table('prod_product')->where('id_product', $product->id_product)->update(['data_origin' => $card->data_origin]);

            return $product->refresh();
        });
    }

    // Produk sudah ada -> Inquiry Rekalkulasi dari produk itu (layar v2), tanpa menulis apa pun di sini.
    public function makeInquiry(ProdMasterCard $card, array $in): array
    {
        if ($product = $this->group($card)->pluck('product_id')->filter()->first() ?? $card->product_id) {
            return ['url' => route('v2.screen', 'inquiry-recalc').'?product='.$product, 'message' => t('The product already exists in Master Product — continue as a Recalculation Inquiry.')];
        }
        $this->applyDialog($card, $in, 'inquiry');
        $inq = DB::connection('tm2sdi')->transaction(function () use ($card, $in) {
            $group = $this->group($card);
            $inq = $this->inquiry($group, ['state' => 'DRAFT', 'confirm' => false, 'qty' => (float) $in['qty'], 'deadline' => $in['deadline'], 'pic' => $in['pic_sales']]);
            foreach ($group as $c) {
                $c->update(['status' => 'INQUIRY', 'inquiry_id' => $inq->id_inquiry, 'updated_by' => $this->login]);
            }

            return $inq;
        });

        return ['url' => route('v2.screen', 'inquiry-new').'?id='.$inq->id_inquiry, 'message' => tf('Inquiry :no (DRAFT) created from the master card — complete it then Confirm.', ['no' => $inq->inquiry_no])];
    }

    private function applyDialog(ProdMasterCard $card, array $in, string $target): void
    {
        $rules = ['design_no' => 'nullable|string|max:30', 'customer_id' => 'nullable|integer', 'type_code' => 'nullable|string|max:40', 'materials' => 'array'];
        if ($target === 'inquiry') {
            $rules += ['qty' => 'required|numeric|min:1', 'deadline' => 'required|date', 'pic_sales' => 'required|string|max:60'];
        }
        validator($in, $rules, [], ['qty' => t('Qty order'), 'deadline' => t('Deadline quotation'), 'pic_sales' => t('PIC Sales')])->validate();
        $card->fill(array_filter(['design_no' => $in['design_no'] ?? null, 'customer_id' => $in['customer_id'] ?? null], fn ($v) => $v !== null && $v !== ''));
        if (! empty($in['type_code']) && $in['type_code'] !== $card->type_code) {
            $card->type_code = $in['type_code'];
            $card->dims_json = MasterCardShape::fit($card->toArray(), $in['type_code']);
        }
        if (! empty($in['materials'])) {
            $card->materials_json = collect($card->materials_json ?? [])->map(fn ($m, $i) => array_merge($m, array_filter(['category_id' => (int) ($in['materials'][$i] ?? 0) ?: null])))->all();
        }
        $this->refresh($card);
        foreach ($this->group($card) as $c) {
            if ($miss = $this->missing($c, $target)) {
                throw ValidationException::withMessages(['dialog' => $c->is($card)
                    ? tf('Still empty: :list.', ['list' => implode(', ', $miss)])
                    : tf('Still empty on the card of part :part: :list.', ['part' => $c->part_label, 'list' => implode(', ', $miss)])]);
            }
        }
    }

    // Kartu (1 atau beberapa part) -> iqp_inquiry + iqp_part (+ Body rakitan bila > 1 part) + bahan/tinta/finishing/BOP/artwork.
    private function inquiry(Collection $group, array $o): IqpInquiry
    {
        $first = $group->first();
        $now = now();
        $sdi = DB::connection('tm2sdi');
        $row = fn (array $x) => $x + ['created_at' => $now, 'updated_at' => $now, 'created_by' => $this->login, 'updated_by' => $this->login, 'data_origin' => $first->data_origin];
        $name = trim(preg_replace('/\s*\(([AB])\)\s*$/', '', (string) $first->product_name));

        $inqId = $sdi->table('iqp_inquiry')->insertGetId($row([
            'inquiry_no' => IqpInquiry::nextInquiryNo(), 'inquiry_date' => $now->toDateString(), 'source' => 'MASTER CARD', 'pic_sales' => $o['pic'] ?? null,
            'customer_id' => $first->customer_id, 'is_prospect' => 0, 'deadline_quotation' => $o['deadline'] ?? null, 'product_draft_name' => $name,
            'design_number' => $first->design_no, 'sales_note' => trim('From legacy master card '.($first->card_code ?? '#'.$first->id_master_card).'. '.($first->notes ?? '')),
            'inquiry_type' => 'NEW', 'revision_no' => 0, 'material_source' => 'OWN', 'job_nature' => 'PRODUCT', 'tax_scheme' => 'PPN', 'pph_applicable' => 0,
            'ppn_percent' => TaxRate::ppn(), 'pph_percent' => 2, 'sub_state' => $o['state'], 'stat_confirm_sales' => $o['confirm'] ? 1 : 0, 'stat_cancel' => 0, 'is_approved' => $o['confirm'] ? 1 : 0,
        ]));
        if (! empty($o['qty'])) {
            $sdi->table('iqp_tier')->insert($row(['inquiry_id' => $inqId, 'tier_seq' => 1, 'qty' => $o['qty'], 'is_primary' => 1]));
        }

        $parts = $group->flatMap(fn ($c) => array_map(fn ($l) => [$c, $l], MasterCardShape::parts(array_merge($c->toArray(), ['part_label' => $c->part_label]))))->values();
        $multi = $parts->count() > 1;
        $body = null;
        $seq = 0;
        if ($multi) {
            $body = $sdi->table('iqp_part')->insertGetId($row(['inquiry_id' => $inqId, 'part_seq' => ++$seq, 'part_code' => 'BODY', 'part_name' => 'Body',
                'part_type' => $this->partType($first), 'part_kind' => 'ASSEMBLY', 'join_type_id' => $first->join_type_id, 'qty_in_parent' => 1, 'qty_per_sku' => 1,
                'pack_qty' => $first->pack_qty, 'dieline_spec' => json_encode(['type_code' => $first->type_code, 'dims' => $first->dims_json])]));
            $this->bop($body, array_filter($first->process_json ?? [], fn ($p) => $p['scope'] === 'BODY'), $row);
        }
        foreach ($parts as [$c, $label]) {
            $id = $sdi->table('iqp_part')->insertGetId($row([
                'inquiry_id' => $inqId, 'parent_part_id' => $body, 'part_seq' => ++$seq, 'part_code' => $label !== '' ? 'PART '.$label : 'P1',
                'part_name' => $label !== '' ? 'Part '.$label : $name, 'part_type' => $this->partType($c), 'part_kind' => 'COMPONENT',
                'join_type_id' => $multi ? null : $c->join_type_id, 'qty_in_parent' => 1, 'qty_per_sku' => 1, 'pack_qty' => $multi ? null : $c->pack_qty,
                'cfg_color' => count($c->colors_json['first'] ?? []).'/'.count($c->colors_json['second'] ?? []), 'cfg_ink' => implode(', ', $c->colors_json['first'] ?? []) ?: null,
                'dieline_spec' => json_encode(['type_code' => $c->type_code, 'dims' => $c->dims_json]), 'flat_w' => $c->flat_w, 'flat_h' => $c->flat_h,
                'flat_area' => $c->flat_w && $c->flat_h ? round($c->flat_w * $c->flat_h, 2) : null,
            ]));
            foreach (array_values($c->materials_json ?? []) as $i => $m) {
                $sdi->table('iqp_part_material')->insert($row(['part_id' => $id, 'layer_seq' => $i + 1, 'category_id' => $m['category_id'] ?? null,
                    'rawmat_name' => $m['category_label'] ?? $m['name'], 'gram_weight' => $m['gsm'] ?? null, 'flute_type' => $m['flute'] ?? null,
                    'size_p' => $m['size_p'] ?? null, 'size_l' => $m['size_l'] ?? null, 'is_primary' => $i === 0 ? 1 : 0, 'material_source' => 'OWN', 'source' => 'MANUAL']));
            }
            foreach (array_merge($c->colors_json['first'] ?? [], $c->colors_json['second'] ?? []) as $u => $ink) {
                $sdi->table('iqp_part_ink')->insert($row(['part_id' => $id, 'pass_seq' => $u < count($c->colors_json['first'] ?? []) ? 1 : 2, 'unit_no' => $u + 1, 'ink_name' => $ink, 'source' => 'MANUAL']));
            }
            foreach (array_values(array_filter($c->finishing_json ?? [], fn ($f) => $f['finishing_type_id'])) as $i => $f) {
                $sdi->table('iqp_part_finishing')->insert(['part_id' => $id, 'seq' => ($i + 1) * 10, 'finishing_type_id' => $f['finishing_type_id'], 'finishing_name' => $f['finishing_name'],
                    'attribute' => $f['attribute'] ?: null, 'created_at' => $now, 'updated_at' => $now, 'created_by' => $this->login, 'updated_by' => $this->login, 'data_origin' => $c->data_origin]);
            }
            $this->bop($id, array_filter($c->process_json ?? [], fn ($p) => ! $multi || $p['scope'] === 'PART'), $row);

            // Foto kartu induk BUKAN artwork produk -> hanya file ARTWORK per part yang ikut.
            $art = $c->files->first(fn ($f) => $f->file_kind === 'ARTWORK' && (string) $f->part_label === $label);
            if ($art && Storage::disk(self::DISK)->exists($art->path)) {
                $path = "inquiry/{$id}/artwork/".basename($art->path);
                Storage::disk(self::DISK)->copy($art->path, $path);
                $sdi->table('iqp_part')->where('id_part', $id)->update(['artwork_path' => $path, 'artwork_original_filename' => $art->original_name]);
            }
        }

        return IqpInquiry::findOrFail($inqId);
    }

    private function bop(int $partId, array $steps, callable $row): void
    {
        foreach (array_values($steps) as $i => $s) {
            DB::connection('tm2sdi')->table('iqp_part_process')->insert($row(['part_id' => $partId, 'branch_code' => 'MAIN', 'step_seq' => ($i + 1) * 10, 'flow_type' => 'LINEAR',
                'process_id' => $s['process_id'], 'process_name' => mb_substr($s['process_name'] ?? $s['text'], 0, 80), 'qty_factor' => 1,
                'note' => $s['text'] !== ($s['process_name'] ?? null) ? mb_substr($s['text'], 0, 255) : null, 'source' => 'MANUAL']));
        }
    }

    private function accRows(ProdProduct $product, ProdMasterCard $c): void
    {
        $now = now();
        foreach (self::ACC as $key => $type) {
            $at = $c->acc_json[$key] ?? ($key === 'acc_digital' ? ($c->acc_json['acc_date'] ?? null) : null);
            if (! $at || ! strtotime($at) || DB::connection('tm2mst')->table('prod_product_acc')->where('product_id', $product->id_product)->where('acc_type', $type)->exists()) {
                continue;
            }
            DB::connection('tm2mst')->table('prod_product_acc')->insert(['product_id' => $product->id_product, 'acc_type' => $type, 'acc_at' => date('Y-m-d 09:00:00', strtotime($at)),
                'approved_by' => $c->acc_json['acc_by'] ?? null, 'channel' => $type === 'EMAIL' ? 'EMAIL' : 'LANGSUNG', 'note' => 'From master card '.($c->card_code ?? '#'.$c->id_master_card),
                'created_at' => $now, 'updated_at' => $now, 'created_by' => $this->login, 'updated_by' => $this->login, 'data_origin' => $c->data_origin]);
        }
    }

    // ---------------------------------------------------------------- mapping teks kartu -> master

    private function partType(ProdMasterCard $c): string
    {
        $names = strtoupper(implode(' ', array_column($c->materials_json ?? [], 'name')));

        return match (true) {
            str_contains($names, 'SINGLE FACE') => 'DC+SF',
            str_contains($names, 'FLUTE') || str_contains($names, 'SHEET') => 'DC+SHEET',
            str_contains($names, 'KRAFT') => 'KRAFT',
            default => 'DC',
        };
    }

    public function customerId(?string $text): ?int
    {
        if (! $text) {
            return null;
        }
        $norm = fn ($s) => preg_replace('/[^A-Z0-9]/', '', strtoupper(preg_replace('/^(PT|CV|UD)\.?\s+/i', '', trim($s))));
        $id = CustCustomer::whereRaw('UPPER(TRIM(customer_name)) = ?', [strtoupper(trim($text))])->orderBy('id_customer')->value('id_customer');

        return $id ?? CustCustomer::query()->select('id_customer', 'customer_name')->get()->first(fn ($c) => $norm($c->customer_name) === $norm($text))?->id_customer;
    }

    private function joinTypeId(?string $text): ?int
    {
        $code = match (true) {
            (bool) preg_match('/double/i', (string) $text) => 'GLUE_DOUBLE',
            (bool) preg_match('/single/i', (string) $text) => 'GLUE_SINGLE',
            default => null,
        };

        return $code ? DB::connection('tm2mst')->table('mat_join_type')->where('code', $code)->value('id_join_type') : null;
    }

    // Jenis & kualitas di kartu -> kategori bahan baku (Paper > Duplex > 310gr, Sheet > BCF > M125/M125X3/K125, ...). Hanya saran.
    public function category(string $name, ?string $quality): array
    {
        $n = strtoupper($name);
        $q = strtoupper(str_replace(' ', '', (string) $quality));
        $gsm = preg_match('/(\d{2,3})\s*G/i', (string) $quality, $m) ? (int) $m[1] : null;
        if (preg_match('/FLUTE|SHEET|SINGLE\s*FACE|\bSF\b|\bSH\b/', $n)) {
            $l2 = match (true) {
                str_contains($n, 'SINGLE') && str_contains($n, 'E') => 'SFEF',
                (bool) preg_match('/B\s*\/\s*C|\bBC\b/', $n) => 'BCF',
                (bool) preg_match('/DOUBLE|\bDF\b/', $n) => 'DF',
                (bool) preg_match('/\bE\b|E\/|EF/', $n) => 'SHEF',
                default => 'SHBF',
            };
            $path = ['Sheet', $l2, preg_replace('#/X(\d)#', 'X$1', $q)];
        } else {
            $l2 = collect(['DUPLEX' => 'Duplex', 'IVORY' => 'Ivory', 'ART CARTON' => 'Art carton', 'ART PAPER' => 'Art Papper', 'KRAFT' => 'Kraft', 'HVS' => 'HVS'])
                ->first(fn ($v, $k) => str_contains($n, $k)) ?? 'Duplex';
            $path = ['Paper', $l2, $gsm ? $gsm.'gr' : null];
        }
        $mst = DB::connection('tm2mst');
        $id = null;
        $label = [];
        foreach (array_filter($path) as $level => $cat) {
            $next = $mst->table('mat_category')->where('level', $level + 1)->whereRaw('UPPER(category_name) = ?', [strtoupper($cat)])
                ->when($id, fn ($x) => $x->where('parent_id', $id), fn ($x) => $x->whereNull('parent_id'))->orderBy('id_category')->value('id_category');
            if (! $next) {
                break;
            }
            $id = $next;
            $label[] = $cat;
        }

        return ['category_id' => count($label) === count(array_filter($path)) ? $id : null, 'category_label' => implode(' › ', $label) ?: null,
            'gsm' => $gsm, 'flute' => $path[0] === 'Sheet' ? $path[1] : null];
    }

    private function processes(array $steps, bool $double): array
    {
        $ids = DB::connection('tm2mst')->table('prod_process')->pluck('id_process', 'process_name');

        return array_map(function ($t) use ($ids, $double) {
            $u = strtoupper($t);
            $name = collect(self::PROCESS)->first(fn ($p) => str_contains($u, $p[0]))[1] ?? null;

            return ['text' => $t, 'process_name' => $name, 'process_id' => $name ? ($ids[$name] ?? null) : null,
                'scope' => $double && in_array($name, self::ASSEMBLY, true) ? 'BODY' : 'PART'];
        }, $steps);
    }

    private function finishings(array $texts): array
    {
        $types = DB::connection('tm2mst')->table('ref_finishing_type')->pluck('id_finishing_type', 'finishing_name');
        $out = [];
        foreach ($texts as $t) {
            $u = strtoupper($t);
            foreach (self::FINISHING as [$key, $name]) {
                if (str_contains($u, $key) && ! isset($out[$name])) {
                    $attr = trim(preg_replace('/^.*?'.preg_quote($key, '/').'\S*\s*[\/:-]?\s*/i', '', $t));
                    $out[$name] = ['text' => $t, 'finishing_name' => $name, 'finishing_type_id' => $types[$name] ?? null,
                        'attribute' => preg_match('/^\(?(plastik|mesin)/i', $attr) ? null : ($attr ?: null)];
                }
            }
        }
        foreach ($texts as $t) {
            if (strtoupper(trim($t)) === 'UV' && ! collect($out)->contains(fn ($f) => str_starts_with($f['finishing_name'], 'UV'))) {
                $out['UV'] = ['text' => $t, 'finishing_name' => 'UV (full)', 'finishing_type_id' => null, 'attribute' => null];
            }
        }

        return array_values($out);
    }

    private function packQty(array $steps): ?int
    {
        foreach ($steps as $t) {
            if (preg_match('/(\d+)\s*(?:pcs|biji)\s*\/\s*pack|\/\s*(\d+)\s*pcs/i', $t, $m)) {
                return (int) ($m[1] ?: $m[2]);
            }
        }

        return null;
    }

    private function groupKey(ProdMasterCard $c): ?string
    {
        $name = strtoupper(trim(preg_replace('/\s*\(?[AB]\)?\s*$/', '', (string) $c->product_name)));

        return ($c->design_no ?: $name) ? mb_substr(($c->design_no ? $c->design_no.'|' : '').$name, 0, 200) : null;
    }
}
