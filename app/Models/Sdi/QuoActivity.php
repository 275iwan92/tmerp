<?php

namespace App\Models\Sdi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoActivity extends Model
{
    public const TYPES = [
        'CALL' => ['📞', 'Telepon'],
        'VISIT' => ['🚗', 'Kunjungan'],
        'WHATSAPP' => ['💬', 'WhatsApp'],
        'EMAIL' => ['✉️', 'Email'],
        'MEETING' => ['🤝', 'Meeting'],
        'OTHER' => ['📝', 'Lainnya'],
    ];

    public const RESULTS = [
        'POSITIVE' => 'Positif',
        'NEUTRAL' => 'Netral',
        'NEGATIVE' => 'Negatif',
        'NO_ANSWER' => 'Tidak terhubung',
    ];

    protected $connection = 'tm2sdi';

    protected $table = 'quo_activity';

    protected $primaryKey = 'id_activity';

    protected $fillable = [
        'quotation_id', 'activity_type', 'activity_date', 'contact_person', 'result',
        'notes', 'next_action_date', 'next_action', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'activity_date' => 'date',
        'next_action_date' => 'date',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(QuoQuotation::class, 'quotation_id', 'id_quotation');
    }
}
