<?php

namespace App\Models\Sdi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IqpType extends Model
{
    protected $connection = 'tm2sdi';

    protected $table = 'iqp_type';

    protected $primaryKey = 'type_code';

    protected $keyType = 'string';

    public $incrementing = false;

    // Tabel tidak punya kolom created_at/updated_at (lookup statis, lihat DDL).
    public $timestamps = false;

    protected $fillable = [
        'type_code',
        'type_label',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function inquiries(): HasMany
    {
        return $this->hasMany(IqpInquiry::class, 'inquiry_type', 'type_code');
    }
}
