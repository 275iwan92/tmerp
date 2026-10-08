<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

// Klaim ke supplier atas batch yang ditolak.
class InvSupplierClaim extends Model
{
    protected $connection = 'tm2inv';

    protected $table = 'inv_supplier_claim';

    protected $primaryKey = 'id_claim';

    protected $guarded = ['id_claim'];
}
