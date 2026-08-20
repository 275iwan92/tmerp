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
        'parent_product_id',
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
        'is_confirmed',
        'is_cancelled',
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
        'is_confirmed' => 'boolean',
        'is_cancelled' => 'boolean',
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

    // FK lintas-DB (tm2sdi -> tm2mst): produk asal untuk RECALC/REVISE.
    public function parentProduct(): BelongsTo
    {
        return $this->belongsTo(ProdProduct::class, 'parent_product_id', 'id_product');
    }

    // Sumber "produk existing" untuk RECALC/REVISE saat ini = inkuiri tersimpan lain
    // (belum ada modul prod_product terpisah, lihat komentar hub.blade.php).
    public function parentInquiry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_product_id', 'id_inquiry');
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

    // RECALC: spec/layout 100% reuse dari baku (DELTA_TIER_revise_scope_CO.md R-RC-1)
    // — clone verbatim part+material+process, tak ada field yang diedit user.
    // $source harus sudah eager-load parts.materials & parts.processes.
    public function cloneStructureFrom(self $source): void
    {
        foreach ($source->parts as $part) {
            $newPart = $this->parts()->create([
                'part_seq' => $part->part_seq,
                'part_code' => $part->part_code,
                'part_name' => $part->part_name,
                'part_type' => $part->part_type,
                'qty_per_sku' => $part->qty_per_sku,
                'cfg_color' => $part->cfg_color,
                'cfg_ink' => $part->cfg_ink,
                'dieline_spec' => $part->dieline_spec,
                'flat_w' => $part->flat_w,
                'flat_h' => $part->flat_h,
                'flat_area' => $part->flat_area,
                'created_by' => 'web',
                'updated_by' => 'web',
            ]);

            foreach ($part->materials as $mat) {
                $newPart->materials()->create([
                    'layer_seq' => $mat->layer_seq,
                    'rawmat_id' => $mat->rawmat_id,
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
                    'created_by' => 'web',
                    'updated_by' => 'web',
                ]);
            }
        }
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
