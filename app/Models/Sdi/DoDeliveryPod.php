<?php

namespace App\Models\Sdi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Bukti terima (proof of delivery): satu baris = satu kali truk datang ke customer. Dasar KPI janji kirim semua departemen.
class DoDeliveryPod extends Model
{
    protected $connection = 'tm2sdi';

    protected $table = 'do_delivery_pod';

    protected $primaryKey = 'id_pod';

    protected $fillable = ['delivery_id', 'attempt_no', 'promise_date', 'promise_time', 'arrived_at', 'result', 'received_by', 'miss_cause',
        'promise_kept', 'photo_path', 'gps_lat', 'gps_lng', 'notes', 'created_by', 'updated_by'];

    protected $casts = ['promise_date' => 'date', 'arrived_at' => 'datetime', 'promise_kept' => 'boolean'];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(DoDelivery::class, 'delivery_id', 'id_delivery');
    }
}
