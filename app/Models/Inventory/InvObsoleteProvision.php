<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Satu posting provisi stok usang (jurnal hanya lahir atas aksi user). Barisnya = foto per item x bin saat diposting.
class InvObsoleteProvision extends Model
{
    protected $connection = 'tm2inv';

    protected $table = 'inv_obsolete_provision';

    protected $primaryKey = 'id_provision';

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = ['provision_date' => 'date', 'stock_value' => 'float', 'provision_total' => 'float', 'previous_total' => 'float', 'adjustment' => 'float'];

    public function lines(): HasMany
    {
        return $this->hasMany(InvObsoleteProvisionLine::class, 'id_provision', 'id_provision');
    }
}
