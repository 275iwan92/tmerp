<?php

namespace App\Models\Hrr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CndCandidate extends Model
{
    protected $connection = 'tm2hrr';

    protected $table = 'cnd_candidate';

    protected $primaryKey = 'id_candidate';

    protected $fillable = [
        'national_id', 'passport_no', 'name', 'nickname', 'gender', 'religion', 'email', 'phone', 'birth_date',
        'address', 'address_domisili', 'vehicle_type', 'sim_types', 'marital_status', 'marital_date',
        'company_acquaintance', 'hobby', 'leisure_activity', 'reading_topics', 'went_abroad',
        'why_join_us', 'what_you_know_about_us', 'contribution_plan', 'role_plan_if_hired',
        'job_type_preference', 'job_nature_preference', 'work_environment_preference', 'hindering_environment',
        'preferred_colleague_type', 'difficult_decision_topic', 'strengths_weaknesses', 'who_you_ask_when_stuck',
        'height_cm', 'weight_kg', 'blood_type', 'illness_history', 'hospitalized_history', 'is_smoker',
        'physical_disability', 'family_health_ok', 'still_receiving_aid', 'other_dependents', 'source_of_info',
        'expected_salary', 'expected_facility', 'availability_date', 'willingness_checklist', 'emergency_contacts',
        'professional_references', 'language_skills', 'computer_skills', 'agreement_signed', 'signature_name',
        'agreement_signed_at', 'activate', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'marital_date' => 'date',
        'availability_date' => 'date',
        'agreement_signed_at' => 'datetime',
        'activate' => 'boolean',
        'is_smoker' => 'boolean',
        'family_health_ok' => 'boolean',
        'agreement_signed' => 'boolean',
        'sim_types' => 'array',
        'willingness_checklist' => 'array',
        'emergency_contacts' => 'array',
        'professional_references' => 'array',
        'language_skills' => 'array',
        'computer_skills' => 'array',
    ];

    public function familyMembers(): HasMany
    {
        return $this->hasMany(CndFamilyMember::class, 'id_candidate', 'id_candidate');
    }

    public function education(): HasMany
    {
        return $this->hasMany(CndEducation::class, 'id_candidate', 'id_candidate');
    }

    public function workExperience(): HasMany
    {
        return $this->hasMany(CndWorkExperience::class, 'id_candidate', 'id_candidate');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CndDocument::class, 'id_candidate', 'id_candidate');
    }

    public function educationNonformal(): HasMany
    {
        return $this->hasMany(CndEducationNonformal::class, 'id_candidate', 'id_candidate');
    }

    public function organizationExperience(): HasMany
    {
        return $this->hasMany(CndOrganizationExperience::class, 'id_candidate', 'id_candidate');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(AppApplication::class, 'id_candidate', 'id_candidate');
    }
}
