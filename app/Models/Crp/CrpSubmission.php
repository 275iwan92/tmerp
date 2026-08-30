<?php

namespace App\Models\Crp;

use Illuminate\Database\Eloquent\Model;

class CrpSubmission extends Model
{
    protected $connection = 'tm2crp';

    protected $table = 'crp_submission';

    protected $primaryKey = 'submission_uuid';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    // TIDAK guard submission_uuid -- model ini cuma diisi dari kode adapter
    // sendiri (bukan langsung dari request user), dan submission_uuid harus
    // bisa di-mass-assign lewat create() karena $incrementing=false (PK di-
    // generate Str::uuid() di pemanggil, bukan auto_increment DB). $guarded
    // berisi PK di sini SALAH -- itu justru memblokir create() mengisinya
    // (bug nyata ditemukan via tinker: "Field 'submission_uuid' doesn't have
    // a default value" walau uuid sudah dikirim di payload create()).
    protected $guarded = [];

    protected $casts = [
        'sim_types' => 'array',
        'emergency_contacts' => 'array',
        'professional_references' => 'array',
        'language_skills' => 'array',
        'computer_skills' => 'array',
        'willingness_checklist' => 'array',
        'family_members' => 'array',
        'education' => 'array',
        'education_nonformal' => 'array',
        'org_experience' => 'array',
        'work_experience' => 'array',
        'agreement_signed' => 'boolean',
        'is_smoker' => 'boolean',
        'family_health_ok' => 'boolean',
    ];

    // Sengaja TIDAK cast birth_date/marital_date/availability_date/submitted_at/
    // agreement_signed_at/synced_at ke Carbon -- adapter (findLatestByNationalId/
    // fetchPendingSubmissions) pakai toArray() apa adanya sbg payload lintas
    // backend (harus sama bentuknya dgn hasil decode Firestore, yg juga string
    // polos), jadi biarkan lolos sebagai string 'Y-m-d'/'Y-m-d H:i:s' asli dari
    // driver DB -- lebih simpel & konsisten drpd override serializeDate().

    public function documents()
    {
        return $this->hasMany(CrpDocument::class, 'submission_uuid', 'submission_uuid');
    }
}
