<?php

namespace App\Models\Prd;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// 1 baris rinci reject/waste BER-TIPE dalam 1 entri output (F15) -- 1 entri
// boleh punya beberapa baris (mis. 3pcs Cacat Cetak + 2pcs Warna Meleset
// dalam 1x "Catat Output" yang sama).
class PrdWoOperationOutputDefect extends Model
{
    protected $connection = 'tm2prd';

    protected $table = 'prd_wo_operation_output_defect';

    protected $primaryKey = 'line_id';

    public $timestamps = false;

    protected $fillable = ['output_id', 'scrap_type_id', 'qty'];

    protected $casts = [
        'qty' => 'decimal:4',
    ];

    public function output(): BelongsTo
    {
        return $this->belongsTo(PrdWoOperationOutput::class, 'output_id', 'output_id');
    }

    public function scrapType(): BelongsTo
    {
        return $this->belongsTo(PrdScrapType::class, 'scrap_type_id', 'type_id');
    }
}
