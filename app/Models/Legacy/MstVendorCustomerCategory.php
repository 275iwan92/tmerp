<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

// Sumber baca-saja untuk engine migrasi. Lihat MstVendor.
class MstVendorCustomerCategory extends Model
{
    protected $connection = 'tm2old';

    protected $table = 'mst_vendor_customer_category';

    protected $primaryKey = 'customer_category';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = ['*'];
}
