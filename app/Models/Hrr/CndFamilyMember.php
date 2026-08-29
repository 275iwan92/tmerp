<?php

namespace App\Models\Hrr;

use Illuminate\Database\Eloquent\Model;

class CndFamilyMember extends Model
{
    public $timestamps = false;

    protected $connection = 'tm2hrr';

    protected $table = 'cnd_family_member';

    protected $primaryKey = 'id_family';

    protected $fillable = ['id_candidate', 'relation_type', 'name', 'gender', 'age', 'last_education', 'current_job', 'current_employer'];
}
