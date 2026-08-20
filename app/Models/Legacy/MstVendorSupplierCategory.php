<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

// Sumber baca-saja untuk engine migrasi. Lihat MstVendor.
class MstVendorSupplierCategory extends Model
{
    protected $connection = 'tm2old';

    protected $table = 'mst_vendor_supplier_category';

    protected $primaryKey = 'supplier_category';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = ['*'];
}
