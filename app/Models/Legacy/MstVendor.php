<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

// Sumber baca-saja untuk engine migrasi (App\Services\Migration\VendorMigrationService).
// tm2old = staging dump mentah ScriptCase V1, di luar kendali skema Laravel —
// JANGAN pernah write ke sini dari aplikasi.
class MstVendor extends Model
{
    protected $connection = 'tm2old';

    protected $table = 'mst_vendor';

    protected $primaryKey = 'vendor_code';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = ['*'];
}
