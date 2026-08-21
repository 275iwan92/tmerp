<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

// Sumber baca-saja untuk App\Services\Migration\EmployeeMigrationService.
// tm2old.emp = roster ScriptCase V1 (485 baris). emp_history (subset penuh,
// 347 baris duplikat is_active=0) SENGAJA tidak dibaca -- lihat catatan
// service-nya.
class Emp extends Model
{
    protected $connection = 'tm2old';

    protected $table = 'emp';

    protected $primaryKey = 'employee_id';

    public $timestamps = false;

    protected $guarded = ['*'];
}
