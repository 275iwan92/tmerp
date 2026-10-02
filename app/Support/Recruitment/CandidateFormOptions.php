<?php

namespace App\Support\Recruitment;

// Sumber TUNGGAL definisi pilihan/label field "Data Calon Karyawan" -- dipakai
// BERSAMA oleh form isian portal (careers/apply.blade.php lewat partial
// _apply-form-steps.blade.php) dan layar review HR
// (recruitment/candidate-review/detail.blade.php) lewat partial yang SAMA,
// supaya keduanya selalu sinkron dari 1 tempat (keputusan user 2026-09-05:
// ubah salah satu harus otomatis berlaku ke yang lain).
class CandidateFormOptions
{
    // Nilai = label English (kunci = kode yang disimpan); tampil lewat labels() -> kamus t().
    public const GENDER = ['LAKI_LAKI' => 'Male', 'PEREMPUAN' => 'Female'];

    public const VEHICLE_TYPE = ['MOTOR' => 'Motorcycle', 'MOBIL' => 'Car', 'TIDAK_ADA' => 'No Vehicle'];

    public const MARITAL_STATUS = ['LAJANG' => 'Single', 'MENIKAH' => 'Married', 'DUDA' => 'Widower/Divorced Man', 'JANDA' => 'Widow/Divorced Woman'];

    public const FAMILY_RELATION_EDITABLE = [
        'SPOUSE' => 'Husband/Wife', 'CHILD' => 'Child', 'SIBLING' => 'Sibling',
        'FATHER_IN_LAW' => 'Father-in-law', 'MOTHER_IN_LAW' => 'Mother-in-law',
    ];

    public const FAMILY_RELATION_ALL = ['FATHER' => 'Father', 'MOTHER' => 'Mother'] + self::FAMILY_RELATION_EDITABLE;

    public const SKILL_LEVEL = ['S' => 'Fair', 'CB' => 'Fairly Good', 'B' => 'Good'];

    public const YES_NO = [1 => 'Yes', 0 => 'No'];

    public const FAMILY_HEALTH = [1 => 'Yes, all fine', 0 => 'There are problems'];

    public const WILLING_ITEMS = [
        'probation' => 'Undergo a probation period',
        'training' => 'Attend training according to Company rules',
        'overtime' => 'Work overtime (including Saturday/Sunday/public holidays)',
        'guarantee_docs' => 'Hand over a Diploma/BPKB as guarantee if required',
        'resign_notice' => 'Submit a written request 1 (one) month in advance when intending to resign',
        'obey_rules' => 'Comply with all applicable company regulations',
        'resign_if_married_pregnant' => 'Willing to resign if marrying a coworker or becoming pregnant before 1 (one) year of service',
        'relocation' => 'Willing to be transferred/assigned to another department/branch at any time',
    ];

    public static function labels(array $options): array
    {
        return array_map('t', $options);
    }
}
