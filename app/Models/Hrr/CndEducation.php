<?php

namespace App\Models\Hrr;

use Illuminate\Database\Eloquent\Model;

class CndEducation extends Model
{
    public $timestamps = false;

    protected $connection = 'tm2hrr';

    protected $table = 'cnd_education';

    protected $primaryKey = 'id_edu';

    protected $fillable = ['id_candidate', 'level', 'institution', 'major', 'period_start_year', 'graduation_year', 'final_score'];
}
