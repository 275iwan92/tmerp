<?php

namespace App\Models\Sdi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoQuotation extends Model
{
    protected $connection = 'tm2sdi';

    protected $table = 'quo_quotation';

    protected $primaryKey = 'id_quotation';

    protected $fillable = [
        'quotation_no',
        'inquiry_id',
        'revision_no',
        'issued_date',
        'valid_until',
        'issued_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'revision_no' => 'integer',
        'issued_date' => 'date',
        'valid_until' => 'date',
    ];

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(IqpInquiry::class, 'inquiry_id', 'id_inquiry');
    }

    // Penomoran selaras pola IqpInquiry::nextInquiryNo(): QUO+YY+NNNNN, counter reset tiap tahun.
    public static function nextQuotationNo(): string
    {
        $prefix = 'QUO'.now()->format('y');

        $last = static::where('quotation_no', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->orderByDesc('quotation_no')
            ->value('quotation_no');

        $seq = $last ? ((int) substr($last, 5)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}
