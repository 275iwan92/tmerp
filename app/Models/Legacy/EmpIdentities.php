<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class EmpIdentities extends Model
{
    protected $connection = 'tm2old';

    protected $table = 'emp_identities';

    protected $primaryKey = 'identity_id';

    public $timestamps = false;

    protected $guarded = ['*'];
}
