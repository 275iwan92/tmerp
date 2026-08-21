<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

// Legacy job-title master (49 baris) -- TIDAK punya kolom department, cuma
// nama+skill_rate. Lihat catatan mapping di EmployeeMigrationService.
class EmpPosition extends Model
{
    protected $connection = 'tm2old';

    protected $table = 'emp_position';

    protected $primaryKey = 'position_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = ['*'];
}
