<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class PurPo extends Model
{
    protected $connection = 'tm2old';

    protected $table = 'pur_po';

    protected $primaryKey = 'po_no';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;
}
