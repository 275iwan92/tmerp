<?php

namespace App\Models\Sdi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Perbedaan REVISI sebelum -> sesudah per field; dibaca ProDev & ProCost supaya tahu apa yang wajib diulang.
class IqpReviseChange extends Model
{
    protected $connection = 'tm2sdi';

    protected $table = 'iqp_revise_change';

    protected $primaryKey = 'id_change';

    protected $fillable = ['inquiry_id', 'part_id', 'scope', 'field_label', 'old_value', 'new_value', 'impact', 'created_by', 'updated_by'];

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(IqpInquiry::class, 'inquiry_id', 'id_inquiry');
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(IqpPart::class, 'part_id', 'id_part');
    }
}
