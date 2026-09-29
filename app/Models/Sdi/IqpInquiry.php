<?php

namespace App\Models\Sdi;

use App\Models\Master\CustCustomer;
use App\Models\Master\ProdProduct;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class IqpInquiry extends Model
{
    protected $connection = 'tm2sdi';

    protected $table = 'iqp_inquiry';

    protected $primaryKey = 'id_inquiry';

    protected $fillable = [
        'inquiry_no',
        'inquiry_date',
        'source',
        'pic_sales',
        'customer_id',
        'is_prospect',
        'prospect_name',
        'deadline_quotation',
        'product_draft_name',
        'sales_note',
        'inquiry_type',
        'parent_inquiry_id',
        'product_id',
        'revision_no',
        'revise_scope',
        'revise_reason',
        'material_source',
        'job_nature',
        'cust_material_spec',
        'cust_material_eta',
        'tax_scheme',
        'pph_applicable',
        'ppn_percent',
        'pph_percent',
        'discount_doc_type',
        'discount_doc_value',
        'discount_qty_value',
        'discount_qty_min_ton',
        'prove_final_version',
        'prove_approved_date',
        'due_date',
        'design_number',
        'co_id',
        'sub_state',
        'stat_confirm',
        'stat_cancel',
        'cancel_reason',
        'is_approved',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'inquiry_date' => 'date',
        'deadline_quotation' => 'date',
        'cust_material_eta' => 'date',
        'prove_approved_date' => 'date',
        'due_date' => 'date',
        'approved_at' => 'datetime',
        'is_prospect' => 'boolean',
        'pph_applicable' => 'boolean',
        'stat_confirm' => 'boolean',
        'stat_cancel' => 'boolean',
        'is_approved' => 'boolean',
        'ppn_percent' => 'decimal:4',
        'pph_percent' => 'decimal:4',
        'revision_no' => 'integer',
    ];

    // FK dalam-DB (tm2sdi): inquiry_type -> iqp_type.type_code.
    public function type(): BelongsTo
    {
        return $this->belongsTo(IqpType::class, 'inquiry_type', 'type_code');
    }

    // FK lintas-DB (tm2sdi -> tm2mst): via Eloquent relationship, bukan join.
    public function customer(): BelongsTo
    {
        return $this->belongsTo(CustCustomer::class, 'customer_id', 'id_customer');
    }

    // FK lintas-DB (tm2sdi -> tm2mst): Master Product inquiry ini -- DRAFT sejak Kirim ke ProDev, resmi saat Quotation WIN.
    public function product(): BelongsTo
    {
        return $this->belongsTo(ProdProduct::class, 'product_id', 'id_product');
    }

    // Inquiry asal dokumen REVISE/RECALC.
    public function parentInquiry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_inquiry_id', 'id_inquiry');
    }

    // Product DRAFT untuk ProDev (dibuat sekali). REVISE = cabang baru dari product induknya
    // (docs/flow_product_master.md §4); RECALC memakai product yang sama dengan inquiry asalnya.
    public function ensureDraftProduct(string $login): int
    {
        if ($this->product_id) {
            return (int) $this->product_id;
        }

        $parentProduct = $this->inquiry_type === 'REVISE' ? $this->parentInquiry?->product_id : null;
        $product = ProdProduct::create([
            'product_code' => 'DRAFT-'.$this->id_inquiry,
            'product_name' => $this->product_draft_name ?: $this->inquiry_no,
            'customer_id' => $this->customer_id,
            'parent_product_id' => $parentProduct,
            'revision_no' => $parentProduct ? (int) ProdProduct::whereKey($parentProduct)->value('revision_no') + 1 : 0,
            'activate' => true,
            'created_by' => $login,
            'updated_by' => $login,
        ]);
        $this->update(['product_id' => $product->id_product]);

        return (int) $product->id_product;
    }

    // revise_scope disimpan CSV (lihat migration 2026_08_09_000006) — bukan cast
    // array Eloquent supaya tetap kompatibel dgn kolom string biasa.
    public function reviseScopeArray(): array
    {
        return $this->revise_scope ? explode(',', $this->revise_scope) : [];
    }

    // Penomoran LOCKED Tier1 §2.4: IQ+YY+NNNNN, counter reset tiap tahun. Sama
    // dengan logika form.blade.php persist() — dipakai juga oleh layar RECALC/REVISE.
    public static function nextInquiryNo(): string
    {
        $prefix = 'IQ'.now()->format('y');

        $last = static::where('inquiry_no', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->orderByDesc('inquiry_no')
            ->value('inquiry_no');

        $seq = $last ? ((int) substr($last, 4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    // RECALC: spec/layout 100% reuse dari baku (inquiryproduct_delta_revise.md R-RC-1)
    // — clone verbatim part+material+process, tak ada field yang diedit user.
    // $source harus sudah eager-load parts.materials & parts.processes.
    public function cloneStructureFrom(self $source): void
    {
        $map = [];
        foreach ($source->parts as $part) {
            $newPart = $this->parts()->create([
                'part_kind' => $part->part_kind,
                'join_type_id' => $part->join_type_id,
                'qty_in_parent' => $part->qty_in_parent,
                'part_seq' => $part->part_seq,
                'part_code' => $part->part_code,
                'part_name' => $part->part_name,
                'part_type' => $part->part_type,
                'qty_per_sku' => $part->qty_per_sku,
                'pack_qty' => $part->pack_qty,
                'cfg_color' => $part->cfg_color,
                'cfg_ink' => $part->cfg_ink,
                'dieline_spec' => $part->dieline_spec,
                'flat_w' => $part->flat_w,
                'flat_h' => $part->flat_h,
                'flat_area' => $part->flat_area,
                'created_by' => 'web',
                'updated_by' => 'web',
            ]);
            $map[$part->id_part] = $newPart;

            foreach ($part->materials as $mat) {
                $newPart->materials()->create([
                    'layer_seq' => $mat->layer_seq,
                    'rawmat_id' => $mat->rawmat_id,
                    'category_id' => $mat->category_id,
                    'rawmat_name' => $mat->rawmat_name,
                    'gram_weight' => $mat->gram_weight,
                    'flute_type' => $mat->flute_type,
                    'size_p' => $mat->size_p,
                    'size_l' => $mat->size_l,
                    'is_primary' => $mat->is_primary,
                    'material_source' => $mat->material_source,
                    'created_by' => 'web',
                    'updated_by' => 'web',
                ]);
            }

            foreach ($part->processes as $proc) {
                $newPart->processes()->create([
                    'branch_code' => $proc->branch_code,
                    'step_seq' => $proc->step_seq,
                    'flow_type' => $proc->flow_type,
                    'process_id' => $proc->process_id,
                    'process_name' => $proc->process_name,
                    'machine_id' => $proc->machine_id,
                    'qty_factor' => $proc->qty_factor,
                    'note' => $proc->note,
                    'source' => $proc->source,
                    'created_by' => 'web',
                    'updated_by' => 'web',
                ]);
            }

            foreach ($part->finishings as $fin) {
                $newPart->finishings()->create([
                    'seq' => $fin->seq,
                    'finishing_type_id' => $fin->finishing_type_id,
                    'finishing_name' => $fin->finishing_name,
                    'attribute' => $fin->attribute,
                    'notes' => $fin->notes,
                    'created_by' => 'web',
                    'updated_by' => 'web',
                ]);
            }

            foreach ($part->inks as $ink) {
                $newPart->inks()->create($ink->only([
                    'pass_seq', 'pass_label', 'unit_no', 'ink_name',
                    'coverage_pct', 'coverage_cm2', 'color_hex', 'source',
                ]) + ['created_by' => 'web', 'updated_by' => 'web']);
            }
        }

        // Struktur rakitan ikut tersalin: induk menunjuk ke part baru, bukan part sumber.
        foreach ($source->parts as $part) {
            if ($part->parent_part_id && isset($map[$part->parent_part_id])) {
                $map[$part->id_part]->update(['parent_part_id' => $map[$part->parent_part_id]->id_part]);
            }
        }
    }

    public function reviseChanges(): HasMany
    {
        return $this->hasMany(IqpReviseChange::class, 'inquiry_id', 'id_inquiry');
    }

    public function tiers(): HasMany
    {
        return $this->hasMany(IqpTier::class, 'inquiry_id', 'id_inquiry');
    }

    public function parts(): HasMany
    {
        return $this->hasMany(IqpPart::class, 'inquiry_id', 'id_inquiry');
    }

    public function toolings(): HasMany
    {
        return $this->hasMany(IqpTooling::class, 'inquiry_id', 'id_inquiry');
    }

    public function externalCosts(): HasMany
    {
        return $this->hasMany(IqpExternalCost::class, 'inquiry_id', 'id_inquiry');
    }

    public function prove(): HasOne
    {
        return $this->hasOne(IqpProve::class, 'inquiry_id', 'id_inquiry');
    }

    public function costing(): HasOne
    {
        return $this->hasOne(IqpCosting::class, 'inquiry_id', 'id_inquiry');
    }

    // Traceability ke Quotation sekarang lewat quo_quotation_item.inquiry_id
    // (1 inquiry bisa muncul di banyak baris quotation berbeda -- lihat migration
    // 2026_08_20_000007_redesign_quotation_multi_inquiry.php).
    public function quotationItems(): HasMany
    {
        return $this->hasMany(QuoQuotationItem::class, 'inquiry_id', 'id_inquiry');
    }

    // CO terakhir yang menyerap inquiry ini (convenience lookup) -- sumber kebenaran
    // baris per-CO tetap co_order_item.inquiry_id (1 inquiry bisa tersebar di banyak
    // co_order_item kalau di-split beberapa CO, jarang tapi mungkin).
    public function lastCo(): BelongsTo
    {
        return $this->belongsTo(CoOrder::class, 'co_id', 'id_order');
    }

    public function coOrderItems(): HasMany
    {
        return $this->hasMany(CoOrderItem::class, 'inquiry_id', 'id_inquiry');
    }
}
