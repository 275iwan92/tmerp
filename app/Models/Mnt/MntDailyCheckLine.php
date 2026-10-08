<?php

namespace App\Models\Mnt;

use Illuminate\Database\Eloquent\Model;

class MntDailyCheckLine extends Model
{
    protected $connection = 'tm2mnt';

    protected $table = 'mnt_daily_check_line';

    protected $primaryKey = 'id_daily_check_line';

    protected $guarded = ['id_daily_check_line'];

    protected $casts = ['normal_min' => 'float', 'normal_max' => 'float', 'measured_value' => 'float'];

    // true bila ada batas normal dan nilai ukur di luarnya. Tanpa batas tidak pernah dinilai sistem.
    public function outOfLimit(): bool
    {
        if ($this->measured_value === null || ($this->normal_min === null && $this->normal_max === null)) {
            return false;
        }

        return ($this->normal_min !== null && $this->measured_value < $this->normal_min) || ($this->normal_max !== null && $this->measured_value > $this->normal_max);
    }
}
