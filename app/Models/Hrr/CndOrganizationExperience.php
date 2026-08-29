<?php

namespace App\Models\Hrr;

use Illuminate\Database\Eloquent\Model;

class CndOrganizationExperience extends Model
{
    public $timestamps = false;

    protected $connection = 'tm2hrr';

    protected $table = 'cnd_organization_experience';

    protected $primaryKey = 'id_org_exp';

    protected $fillable = ['id_candidate', 'organization_name', 'position', 'period', 'task_description'];
}
