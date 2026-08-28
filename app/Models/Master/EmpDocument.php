<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpDocument extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'emp_document';

    protected $primaryKey = 'document_id';

    protected $fillable = [
        'employee_id',
        'document_type',
        'document_no',
        'file_path',
        'expiry_date',
        'uploaded_by_role',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'activate' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmpEmployee::class, 'employee_id', 'employee_id');
    }
}
