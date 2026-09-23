<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpCorrectionRequest extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'emp_correction_request';

    protected $primaryKey = 'request_id';

    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'field_name',
        'old_value',
        'new_value',
        'reason',
        'attachment_path',
        'requires_hr_approval',
        'status',
        'requested_at',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected $casts = [
        'requires_hr_approval' => 'boolean',
        'requested_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    // Pengajuan ganti rekening (2026-09-23) menyimpan new_value sebagai JSON
    // {bank_supplier_id, account_no, account_holder_name} supaya bisa diterapkan
    // otomatis saat Approve; field lain tetap teks biasa.
    public const FIELD_BANK = 'Rekening Bank Payroll';

    public function bankPayload(): ?array
    {
        if ($this->field_name !== self::FIELD_BANK) {
            return null;
        }
        $data = json_decode((string) $this->new_value, true);

        return is_array($data) && isset($data['bank_supplier_id']) ? $data : null;
    }

    public function newValueLabel(): string
    {
        $bank = $this->bankPayload();
        if (! $bank) {
            return (string) $this->new_value;
        }
        $name = SupSupplier::whereKey($bank['bank_supplier_id'])->value('supplier_name') ?? '?';

        return trim($name).' · '.($bank['account_no'] ?? '').' · a.n. '.($bank['account_holder_name'] ?? '');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmpEmployee::class, 'employee_id', 'employee_id');
    }
}
