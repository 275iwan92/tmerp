<?php

namespace App\Support\Recruitment;

/*
 * REMARK
 * Tujuan       : Sumber tunggal pilihan dan label field "Data Calon Karyawan" (jenis kelamin, kendaraan, status nikah, hubungan keluarga, level kemampuan, ya/tidak, daftar kesediaan), supaya form lamar dan layar review HR selalu sinkron.
 * Sumber hulu  : Formulir LJM/HRD/P01/F02 (keputusan Jo 2026-09-05: ubah satu tempat berlaku ke keduanya).
 * Input        : -
 * Output       : Konstanta array kunci => label.
 * Dipakai oleh : resources/views/careers/apply.blade.php, App\Services\Portal\CandidateApplicationService, resources/views/v2/screens/hrm-rcr-review-form.blade.php.
 * Status       : Aktif.
 */
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
