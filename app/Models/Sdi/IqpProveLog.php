<?php

namespace App\Models\Sdi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IqpProveLog extends Model
{
    protected $connection = 'tm2sdi';

    protected $table = 'iqp_prove_log';

    protected $primaryKey = 'id_prove_log';

    // Tabel tidak punya kolom created_at/updated_at — audit pakai logged_at/logged_by sendiri.
    public $timestamps = false;

    protected $fillable = [
        'prove_id',
        'version_no',
        'separation',
        'pdf_file',
        'note',
        'logged_at',
        'logged_by',
    ];

    protected $casts = [
        'logged_at' => 'datetime',
    ];

    public function prove(): BelongsTo
    {
        return $this->belongsTo(IqpProve::class, 'prove_id', 'id_prove');
    }
}
