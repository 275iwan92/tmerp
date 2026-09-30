<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RmBookingD extends Model
{
    protected $connection = 'tm2inv';

    protected $table = 'rm_booking_d';

    protected $primaryKey = 'booking_detail_id';

    const CREATED_AT = 'create_log';

    const UPDATED_AT = 'update_log';

    protected $guarded = ['booking_detail_id'];

    protected $casts = ['need_date' => 'date'];

    public function allocs(): HasMany
    {
        return $this->hasMany(RmBookingAlloc::class, 'booking_detail_id');
    }
}
