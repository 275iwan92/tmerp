<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Landed cost yang datang SETELAH penerimaan (ongkos angkut, bea, asuransi): dibagi ke baris penerimaan menurut nilai atau qty (D17).
class InvLandedCost extends Model
{
    public const BASES = ['VALUE' => 'By value', 'QTY' => 'By quantity'];

    public const TYPES = ['FREIGHT' => 'Freight', 'DUTY' => 'Duty', 'INSURANCE' => 'Insurance', 'OTHER' => 'Other'];

    protected $connection = 'tm2inv';

    protected $table = 'inv_landed_cost';

    protected $primaryKey = 'id_landed_cost';

    protected $guarded = [];

    protected $casts = ['doc_date' => 'date', 'amount' => 'float', 'amount_to_stock' => 'float', 'amount_to_variance' => 'float', 'card_synced' => 'boolean',
        'stat_confirm' => 'boolean', 'stat_cancel' => 'boolean', 'confirmed_at' => 'datetime'];

    public function lines(): HasMany
    {
        return $this->hasMany(InvLandedCostLine::class, 'id_landed_cost', 'id_landed_cost')->orderBy('line_no');
    }

    // DRAFT | CONFIRMED | CANCELLED
    public function status(): string
    {
        return $this->stat_cancel ? 'CANCELLED' : ($this->stat_confirm ? 'CONFIRMED' : 'DRAFT');
    }
}
