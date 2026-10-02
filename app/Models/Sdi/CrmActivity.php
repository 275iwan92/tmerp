<?php

namespace App\Models\Sdi;

use App\Models\Master\CustCustomer;
use App\Models\Master\CustCustomerContact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Aktivitas Sales per customer (telepon, kunjungan, WhatsApp, ...) + tindak lanjutnya. Perluasan quo_activity (user
// 2026-10-02): customer wajib, quotation boleh kosong. Follow-up terbuka = next_action_date terisi dan next_done = 0.
class CrmActivity extends Model
{
    public const TYPES = ['CALL' => 'Call', 'VISIT' => 'Visit', 'WHATSAPP' => 'WhatsApp', 'EMAIL' => 'E-mail', 'MEETING' => 'Meeting', 'OTHER' => 'Other'];

    public const TOPICS = ['QUOTATION' => 'Quotation', 'ORDER' => 'Order / Delivery', 'PROSPECT' => 'Prospecting', 'COMPLAINT' => 'Complaint', 'PAYMENT' => 'Payment', 'RELATION' => 'Relationship'];

    public const RESULTS = ['POSITIVE' => 'Positive', 'NEUTRAL' => 'Neutral', 'NEGATIVE' => 'Negative', 'NO_ANSWER' => 'No answer'];

    protected $connection = 'tm2sdi';

    protected $table = 'crm_activity';

    protected $primaryKey = 'id_activity';

    protected $fillable = [
        'customer_id', 'contact_id', 'quotation_id', 'activity_type', 'topic', 'activity_date', 'contact_person', 'result',
        'notes', 'next_action_date', 'next_action', 'next_done', 'next_done_at', 'next_done_by', 'created_by', 'updated_by',
    ];

    protected $casts = ['activity_date' => 'date', 'next_action_date' => 'date', 'next_done' => 'boolean', 'next_done_at' => 'datetime'];

    // Layar lama (follow-up di form Quotation v1 / v2) hanya mengirim quotation_id: customer diambil dari quotation-nya.
    protected static function booted(): void
    {
        static::creating(function (CrmActivity $a) {
            if (! $a->customer_id && $a->quotation_id) {
                $a->customer_id = QuoQuotation::query()->whereKey($a->quotation_id)->value('customer_id');
            }
        });
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(QuoQuotation::class, 'quotation_id', 'id_quotation');
    }

    // Lintas-DB (tm2sdi -> tm2mst): query terpisah lewat relasi Eloquent, bukan join.
    public function customer(): BelongsTo
    {
        return $this->belongsTo(CustCustomer::class, 'customer_id', 'id_customer');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CustCustomerContact::class, 'contact_id', 'id_customer_contact');
    }

    public function followUpOpen(): bool
    {
        return $this->next_action_date !== null && ! $this->next_done;
    }
}
