<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class PurPoDetail extends Model
{
    protected $connection = 'tm2old';

    protected $table = 'pur_po_detail';

    protected $primaryKey = 'po_detail_id';

    public $timestamps = false;
}
