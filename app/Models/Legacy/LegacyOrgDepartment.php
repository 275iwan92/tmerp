<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

// Nama diberi awalan Legacy* supaya tak bentrok App\Models\Master\OrgDepartment
// (target V2). tm2old.org_department = 20 departemen produksi riil.
class LegacyOrgDepartment extends Model
{
    protected $connection = 'tm2old';

    protected $table = 'org_department';

    protected $primaryKey = 'department_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = ['*'];
}
