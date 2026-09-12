<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class SdiCo extends Model
{
    protected $connection = 'tm2old';

    protected $table = 'sdi_co';

    protected $primaryKey = 'co_no';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;
}
