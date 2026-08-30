<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Data lowongan REAL dari akun Glints TapLoker for Employers perusahaan
// (screenshot user 2026-08-30, employers.glints.id/dashboard) -- BUKAN dummy.
// Status published/closed di sini MENCERMINKAN status asli di Glints saat
// screenshot diambil (3 dari 4 sudah Nonaktif di sana, cuma "Performance
// Marketing Specialist" yang Aktif) -- portal kita ikut status itu, bukan
// mem-publish semuanya asal ada datanya. Deskripsi lengkap TIDAK terlihat
// di screenshot (cuma judul/tipe/lokasi/status) -- placeholder ringkas,
// lengkapi manual via layar internal (hrm.recruitment.vacancy) kalau JD
// lengkap sudah tersedia.
return new class extends Migration
{
    public function up(): void
    {
        $hrr = DB::connection('tm2hrr');
        $now = now();

        $rows = [
            [
                'requisition_no' => 'RCR2600007',
                'vacancy_code' => 'VAC2600006',
                'department_id' => 7, // Staff/Admin -- tidak ada dept Pengadaan/Procurement khusus di org_department, lihat catatan migrasi
                'title' => 'Staff Pengadaan',
                'qualification' => 'Min. SMA/SMK, teliti, mampu bernegosiasi dengan supplier.',
                'is_published' => false,
                'published_at' => null,
                'closed_at' => '2026-08-24 00:00:00',
            ],
            [
                'requisition_no' => 'RCR2600008',
                'vacancy_code' => 'VAC2600007',
                'department_id' => 12, // Sales and Marketing
                'title' => 'Online Sales Executive - Printing & Packaging',
                'qualification' => 'Pengalaman sales online, memahami produk percetakan & packaging.',
                'is_published' => false,
                'published_at' => null,
                'closed_at' => '2026-08-20 00:00:00',
            ],
            [
                'requisition_no' => 'RCR2600009',
                'vacancy_code' => 'VAC2600008',
                'department_id' => 7, // Staff/Admin -- tidak ada dept Finance khusus di org_department, lihat catatan migrasi
                'title' => 'Finance & Billing Admin',
                'qualification' => 'Min. D3 Akuntansi/Keuangan, teliti, terbiasa invoicing & billing.',
                'is_published' => false,
                'published_at' => null,
                'closed_at' => '2026-08-19 00:00:00',
            ],
            [
                'requisition_no' => 'RCR2600010',
                'vacancy_code' => 'VAC2600009',
                'department_id' => 12, // Sales and Marketing
                'title' => 'Performance Marketing Specialist (Meta Ads & SEO)',
                'qualification' => 'Pengalaman Meta Ads & SEO, analitis, terbiasa optimasi campaign digital.',
                'is_published' => true,
                'published_at' => $now,
                'closed_at' => null, // Aktif di Glints hingga 2026-09-15 -- disimpan di description, bukan closed_at (masih terbuka)
            ],
        ];

        foreach ($rows as $r) {
            if ($hrr->table('vac_vacancy')->where('vacancy_code', $r['vacancy_code'])->exists()) {
                continue;
            }

            $idRequisition = $hrr->table('rcr_requisition')->insertGetId([
                'requisition_no' => $r['requisition_no'],
                'id_track' => 1, // STAFF
                'department_id' => $r['department_id'],
                'position_title' => $r['title'],
                'headcount' => 1,
                'qualification' => $r['qualification'],
                'reason' => 'Rekrutmen via kanal Glints TapLoker (employers.glints.id) -- data diinput dari dashboard Glints existing, 2026-08-30.',
                'requested_by' => 1,
                'status' => 'APPROVED',
                'created_at' => $now,
                'updated_at' => $now,
            ], 'id_requisition');

            $hrr->table('vac_vacancy')->insert([
                'vacancy_code' => $r['vacancy_code'],
                'id_requisition' => $idRequisition,
                'channel' => 'GLINTS',
                'title' => $r['title'],
                'description' => $r['qualification']."\n\n(Deskripsi lengkap belum tersedia -- sumber cuma screenshot dashboard Glints, lengkapi manual kalau JD penuh sudah ada. Ref: employers.glints.id)".
                    ($r['title'] === 'Performance Marketing Specialist (Meta Ads & SEO)' ? "\n\nAktif di Glints hingga 15 Sep 2026." : ''),
                'is_published' => $r['is_published'],
                'published_at' => $r['published_at'],
                'closed_at' => $r['closed_at'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $hrr = DB::connection('tm2hrr');
        $codes = ['VAC2600006', 'VAC2600007', 'VAC2600008', 'VAC2600009'];
        $reqNos = ['RCR2600007', 'RCR2600008', 'RCR2600009', 'RCR2600010'];

        $hrr->table('vac_vacancy')->whereIn('vacancy_code', $codes)->delete();
        $hrr->table('rcr_requisition')->whereIn('requisition_no', $reqNos)->delete();
    }
};
