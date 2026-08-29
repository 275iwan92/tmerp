<?php

namespace App\Models\Hrr;

use Illuminate\Database\Eloquent\Model;

class CndEducationNonformal extends Model
{
    public $timestamps = false;

    protected $connection = 'tm2hrr';

    protected $table = 'cnd_education_nonformal';

    protected $primaryKey = 'id_nonformal';

    protected $fillable = ['id_candidate', 'course_type', 'institution', 'period_start_year', 'period_end_year', 'certificate_no'];
}
