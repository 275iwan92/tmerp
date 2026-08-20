<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

// Sumber baca-saja untuk engine migrasi. Lihat MstVendor.
class MstVendorBank extends Model
{
    protected $connection = 'tm2old';

    protected $table = 'mst_vendor_bank';

    protected $primaryKey = 'vendor_bank_id';

    public $timestamps = false;

    protected $guarded = ['*'];
}
