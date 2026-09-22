<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Aturan urutan antar-proses. Dibaca RoutingValidator; BLOCK menolak routing,
// WARN hanya memperingatkan (pola "engine suggests, officer confirms").
class ProdProcessPrecedence extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'prod_process_precedence';

    protected $primaryKey = 'precedence_id';

    public const RULE_TYPES = [
        'MUST_FOLLOW' => 'Harus SESUDAH proses pembanding',
        'MUST_PRECEDE' => 'Harus SEBELUM proses pembanding',
        'FORBIDDEN_AFTER' => 'TIDAK BOLEH sesudah proses pembanding',
        'REQUIRES' => 'Mewajibkan proses pembanding ada di routing',
    ];

    protected $fillable = [
        'process_id',
        'related_process_id',
        'rule_type',
        'severity',
        'reason',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = ['activate' => 'boolean'];

    public function process(): BelongsTo
    {
        return $this->belongsTo(ProdProcess::class, 'process_id', 'id_process');
    }

    public function relatedProcess(): BelongsTo
    {
        return $this->belongsTo(ProdProcess::class, 'related_process_id', 'id_process');
    }
}
