<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpBadgePhotoRequest extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'emp_badge_photo_request';

    protected $primaryKey = 'request_id';

    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'photo_path',
        'width_px',
        'height_px',
        'status',
        'requested_at',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'width_px' => 'integer',
        'height_px' => 'integer',
        'requested_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmpEmployee::class, 'employee_id', 'employee_id');
    }
}
