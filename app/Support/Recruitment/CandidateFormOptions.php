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
    public const GENDER = ['LAKI_LAKI' => 'Laki-laki', 'PEREMPUAN' => 'Perempuan'];

    public const VEHICLE_TYPE = ['MOTOR' => 'Motor', 'MOBIL' => 'Mobil', 'TIDAK_ADA' => 'Tidak Ada'];

    public const MARITAL_STATUS = ['LAJANG' => 'Lajang', 'MENIKAH' => 'Menikah', 'DUDA' => 'Duda', 'JANDA' => 'Janda'];

    public const FAMILY_RELATION_EDITABLE = [
        'SPOUSE' => 'Suami/Istri', 'CHILD' => 'Anak', 'SIBLING' => 'Saudara Kandung',
        'FATHER_IN_LAW' => 'Mertua Ayah', 'MOTHER_IN_LAW' => 'Mertua Ibu',
    ];

    public const FAMILY_RELATION_ALL = ['FATHER' => 'Ayah', 'MOTHER' => 'Ibu'] + self::FAMILY_RELATION_EDITABLE;

    public const SKILL_LEVEL = ['S' => 'Sedang', 'CB' => 'Cukup Baik', 'B' => 'Baik'];

    public const YES_NO = [1 => 'Ya', 0 => 'Tidak'];

    public const FAMILY_HEALTH = [1 => 'Ya, baik-baik saja', 0 => 'Ada masalah'];

    public const WILLING_ITEMS = [
        'probation' => 'Mengikuti masa percobaan',
        'training' => 'Mengikuti training sesuai ketentuan Perusahaan',
        'overtime' => 'Bekerja lembur (termasuk Sabtu/Minggu/hari besar)',
        'guarantee_docs' => 'Menyerahkan jaminan Ijazah/BPKB jika dibutuhkan',
        'resign_notice' => 'Mengajukan permohonan tertulis 1 (satu) bulan sebelumnya apabila hendak mengundurkan diri',
        'obey_rules' => 'Mematuhi segala peraturan perusahaan yang berlaku',
        'resign_if_married_pregnant' => 'Bersedia mengundurkan diri jika menikah dengan rekan sekerja atau hamil sebelum masa kerja 1 (satu) tahun',
        'relocation' => 'Bersedia setiap saat dipindah/ditugaskan ke bagian/cabang lain',
    ];
}
