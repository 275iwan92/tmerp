<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class SdiCoDetail extends Model
{
    protected $connection = 'tm2old';

    protected $table = 'sdi_co_detail';

    protected $primaryKey = 'co_detail_id';

    public $timestamps = false;
}
