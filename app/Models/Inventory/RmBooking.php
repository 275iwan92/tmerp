<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Booking bahan per Customer Order (1 header per CO), atau pre-booking tanpa CO (booking_type PRE, chain_key = inquiry_id, stage B0..B4,
// hold_until = batas Hold; lihat MaterialPlanningEngine::runPre / hold). Usulan engine = DRAFT (soft, tidak mengurangi Available);
// Confirm oleh PPIC = stat_confirm 1 -> v_rm_available mengurangi qty_book - qty_issued (hard). Logika: App\Services\Planning\MaterialPlanningEngine.
class RmBooking extends Model
{
    protected $connection = 'tm2inv';

    protected $table = 'rm_booking';

    protected $primaryKey = 'booking_id';

    const CREATED_AT = 'create_log';

    const UPDATED_AT = 'update_log';

    protected $guarded = ['booking_id'];

    protected $casts = ['booking_date' => 'datetime', 'confirm_date' => 'datetime', 'last_run_at' => 'datetime', 'hold_until' => 'date', 'stale_warned_on' => 'date'];

    public function lines(): HasMany
    {
        return $this->hasMany(RmBookingD::class, 'booking_id')->orderBy('seq_no');
    }
}
