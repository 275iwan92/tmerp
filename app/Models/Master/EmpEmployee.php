<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EmpEmployee extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'emp_employee';

    protected $primaryKey = 'employee_id';

    protected $fillable = [
        'legacy_employee_id',
        'employee_code',
        'company_id',
        'position_id',
        'full_name',
        'nickname',
        'gender',
        'birth_place',
        'birth_date',
        'nik_ktp',
        'no_kk',
        'npwp_number',
        'religion',
        'marital_status',
        'phone',
        'email_personal',
        'village_id_ktp',
        'address_detail_ktp',
        'village_id_domisili',
        'address_detail_domisili',
        'join_date',
        'resign_date',
        'resign_reason',
        'photo_path',
        'work_status',
        'work_status_since',
        'emergency_contact_name',
        'emergency_contact_relation',
        'emergency_contact_phone',
        'default_machine_id',
        'default_shift_id',
        'payment_frequency',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'join_date' => 'date',
        'resign_date' => 'date',
        'work_status_since' => 'date',
        'activate' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(OrgCompany::class, 'company_id', 'company_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(OrgPosition::class, 'position_id', 'position_id');
    }

    public function defaultMachine(): BelongsTo
    {
        return $this->belongsTo(ProdMachine::class, 'default_machine_id', 'machine_id');
    }

    public function defaultShift(): BelongsTo
    {
        return $this->belongsTo(EmpShift::class, 'default_shift_id', 'shift_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(EmpContract::class, 'employee_id', 'employee_id')->orderByDesc('start_date');
    }

    public function activeContract(): HasOne
    {
        return $this->hasOne(EmpContract::class, 'employee_id', 'employee_id')->where('activate', true);
    }

    public function families(): HasMany
    {
        return $this->hasMany(EmpFamily::class, 'employee_id', 'employee_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmpDocument::class, 'employee_id', 'employee_id');
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(EmpBankAccount::class, 'employee_id', 'employee_id');
    }

    public function educations(): HasMany
    {
        return $this->hasMany(EmpEducation::class, 'employee_id', 'employee_id')->orderByDesc('activity_date');
    }

    public function disciplinaries(): HasMany
    {
        return $this->hasMany(EmpDisciplinary::class, 'employee_id', 'employee_id')->orderByDesc('incident_date');
    }

    public function bpjsKesehatan(): HasOne
    {
        return $this->hasOne(EmpBpjsKesehatan::class, 'employee_id', 'employee_id');
    }

    public function bpjsKetenagakerjaan(): HasOne
    {
        return $this->hasOne(EmpBpjsKetenagakerjaan::class, 'employee_id', 'employee_id');
    }

    public function verbalWarningCount(): int
    {
        return $this->disciplinaries->where('type', 'TEGURAN_VERBAL')->count();
    }

    public function activeSpType(): ?string
    {
        return $this->disciplinaries->whereIn('type', ['SP1', 'SP2', 'SP3'])->first()?->type;
    }
}
