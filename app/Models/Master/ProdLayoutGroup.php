<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Hasil ENGINE imposition (Tool #1, §4.3a) -- 1 baris = 1 Master Card imposition,
// REUSABLE lintas Inquiry, bisa menaungi >1 prod_part (§4.2b, gabung/pisah =
// keputusan engine). Menggantikan pola lama iqp_layout (1:1 ke 1 iqp_part, tm2sdi).
class ProdLayoutGroup extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'prod_layout_group';

    protected $primaryKey = 'id_layout_group';

    protected $fillable = [
        'product_id',
        'mastercard_code',
        'plano_size_id',
        'plano_w_mm',
        'plano_h_mm',
        'method',
        'up_count',
        'knife_code',
        'knife_w_cm',
        'knife_h_cm',
        'efficiency_pct',
        'waste_pct',
        'die_rule_meter',
        'layout_detail',
        'status',
        'confirmed_at',
        'confirmed_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'layout_detail' => 'array',
        'confirmed_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProdProduct::class, 'product_id', 'id_product');
    }

    public function planoSize(): BelongsTo
    {
        return $this->belongsTo(MatPlanoSize::class, 'plano_size_id', 'id_plano_size');
    }

    public function parts(): BelongsToMany
    {
        return $this->belongsToMany(ProdPart::class, 'prod_layout_group_part', 'id_layout_group', 'part_id')
            ->withPivot('qty_up_in_group');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ProdPartAttachment::class, 'id_layout_group', 'id_layout_group');
    }

    // Alokasi biaya proporsional (BACKLOG.md §0, tier1 §4.2b) -- kalau group
    // ini menaungi >1 part (Tool #10), biaya kertas/plate 1 lembar plano
    // TIDAK BOLEH dibebankan penuh ke tiap part (melipatgandakan biaya keliru)
    // -- harus dibagi proporsional by luas area terpakai. Trik: kembalikan
    // "cut efektif" (bukan qty_up_in_group mentah) supaya formula existing
    // CostingEngine (paperCostPc = ... / cut / isi, TIDAK diubah sama sekali)
    // otomatis menghasilkan alokasi proporsional yang benar -- derivasi:
    // biaya/pc = (biayaPlanoPenuh * shareLuasA) / upA, shareLuasA = (luasA*upA)/totalLuas
    // => biaya/pc = biayaPlanoPenuh / (totalLuas/luasA) => "cut efektif" = totalLuas/luasA
    // (up_count TIDAK muncul di hasil akhir -- proporsional murni by luas, bukan by up count).
    // Group SOLO (1 part) balik ke up_count apa adanya (fallback identik perilaku lama).
    public function effectiveCutFor(ProdPart $part): float
    {
        $parts = $this->parts;

        if ($parts->count() <= 1) {
            return (float) $this->up_count;
        }

        $engine = new \App\Services\Imposition\ImpositionEngine;
        $totalUsedAreaMm2 = 0.0;
        $ownAreaMm2 = null;

        foreach ($parts as $p) {
            $bodyW = (float) ($p->cm_width ?? 0) * 10;
            $bodyH = (float) ($p->cm_length ?? 0) * 10;
            [$flatW, $flatH] = $engine->applyFlap($bodyW, $bodyH, $p->flap_side ?? 'TOP', (float) ($p->flap_length_mm ?? 0));
            $areaMm2 = $flatW * $flatH;
            $totalUsedAreaMm2 += $areaMm2 * (float) $p->pivot->qty_up_in_group;

            if ($p->id_part === $part->id_part) {
                $ownAreaMm2 = $areaMm2;
            }
        }

        if (! $ownAreaMm2 || $ownAreaMm2 <= 0 || $totalUsedAreaMm2 <= 0) {
            return (float) $this->up_count; // fallback aman, tak pernah divide-by-zero
        }

        return $totalUsedAreaMm2 / $ownAreaMm2;
    }
}
