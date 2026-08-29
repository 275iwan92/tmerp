<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Struktur organisasi RIIL PT. Telasih Makmur, sumber: file "STRUKTUR.pdf"
// yang diberikan user 2026-08-29 (5 halaman: org chart utama + detail Sales/
// Marketing, Technical Support/GA/Human Capital, Supply Chain, Offset
// Production). 49 posisi + hierarki reports_to_position_id.
//
// KEPUTUSAN SADAR soal siapa yang ditaut ke emp_employee nyata (occupant):
// - HANYA Annisa Kusumaningtyas (employee_id=144, posisi PDF "Operational
//   Manager") & Sania Maura (employee_id=151, posisi "Human Capital Dept.")
//   di-set position_id-nya -- identitas keduanya SUDAH dikonfirmasi user
//   eksplisit sesi ini (recruitment approval matrix). Selain itu TIDAK
//   di-auto-link ke emp_employee manapun (chart tampil "Vacant") -- 484
//   karyawan legacy namanya rawan salah cocok (ada duplikat nama persis:
//   "Annisa Ika" muncul 2x di PDF utk 2 posisi beda -- Purchasing Staff &
//   Plong/Kupas SPV -- sengaja TIDAK ditaut krn ambigu). HR isi manual lewat
//   Master Employee (position_id) kalau mau tampilkan foto+nama di chart.
// - department_id per posisi = pencocokan TERBAIK ke org_department yang
//   sudah ada (bukan semua match sempurna, terutama posisi level
//   direksi/manager yang PDF tidak punya departemen produksi spesifik --
//   dipakaikan ADMIN sbg default, sama pola Direksi/Manajer Operasional yang
//   sudah ada). Bisa dikoreksi manual lewat Master Posisi kalau HR mau lebih presisi.
// - "Product Costing" (Wiwik) sengaja reports_to Presiden Direktur LANGSUNG
//   (bukan lewat Operational Manager) -- garis putus-putus di PDF + selaras
//   CLAUDE.md §4 "Costing rahasia (Sisi A): HPP/margin hanya Costing+Direksi",
//   Costing memang harus bypass Sisi B (Sales/Operasional).
// - Kotak yang di PDF tanpa nama (mis. "PPIC Admin", "Deskprint", "Cashier",
//   "Online Sales", "IT Support", dst) TETAP dibuat sbg posisi (vacant) --
//   itu representasi struktur yg valid, bukan placeholder ngasal.
// - Sel PDF berisi multi-nama ("Tia/Suro/Fardan", "Trio/Agung/Dwi/Puji") =
//   1 posisi (tim), TIDAK dipecah jadi beberapa baris posisi duplikat.
return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('tm2mst');

        if ($db->table('org_position')->where('position_name', 'Presiden Direktur')->exists()) {
            return;
        }

        $deptId = fn (string $code) => $db->table('org_department')->where('department_code', $code)->value('department_id');
        $ADMIN = $deptId('ADMIN');
        $SALES = $deptId('SALES-AND-MARKETI');
        $DESIGN = $deptId('DESIGN');
        $DIGITAL = $deptId('DIGITAL');
        $SECURITY = $deptId('SECURITY');
        $UMUM = $deptId('UMUM');
        $PPIC = $deptId('PPIC');
        $GUDANG = $deptId('GUDANG');
        $EKSPEDISI = $deptId('EKSPEDISI');
        $PRESS = $deptId('PRESS');
        $DIGITALPROD = $deptId('DIGITALPROD');
        $PRACETAK = $deptId('PRA-CETAK');
        $POTONG = $deptId('POTONG');
        $LAMINASI = $deptId('LAMINASI');
        $PLONGDIECUT = $deptId('PLONG-DIECUT');
        $FOLDERGLUE = $deptId('FOLDER-GLUE');
        $QC = $deptId('QC');

        $LVL_STAFF = $db->table('org_level')->where('level_code', 'STAFF')->value('level_id');
        $LVL_SPV = $db->table('org_level')->where('level_code', 'SPV')->value('level_id');
        $LVL_MGR = $db->table('org_level')->where('level_code', 'MGR')->value('level_id');
        $LVL_DIR = $db->table('org_level')->where('level_code', 'DIR')->value('level_id');

        $ids = []; // name-key -> position_id, dipakai resolve reports_to berantai
        $usedCodes = [];

        $slugCode = function (string $name) use (&$usedCodes) {
            $base = mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $name));
            $base = mb_substr($base ?: 'POS', 0, 15);
            $code = $base;
            $n = 1;
            while (isset($usedCodes[$code])) {
                $n++;
                $code = mb_substr($base, 0, 13).$n;
            }
            $usedCodes[$code] = true;

            return $code;
        };

        $make = function (string $key, string $name, int $deptId, int $levelId, ?string $reportsToKey) use (&$ids, $db, $slugCode) {
            $ids[$key] = $db->table('org_position')->insertGetId([
                'department_id' => $deptId,
                'level_id' => $levelId,
                'reports_to_position_id' => $reportsToKey ? $ids[$reportsToKey] : null,
                'position_code' => $slugCode($name),
                'position_name' => $name,
                'activate' => true,
                'created_by' => 'claude_code',
                'updated_by' => 'claude_code',
            ]);
        };

        // ── Puncak (PDF hal.1) ──
        $make('PRESDIR', 'Presiden Direktur', $ADMIN, $LVL_DIR, null);
        $make('DIRFA', 'Direktur F&A', $ADMIN, $LVL_DIR, 'PRESDIR');
        $make('GM', 'General Manager', $ADMIN, $LVL_DIR, 'PRESDIR');
        $make('FASPV', 'F&A SPV', $ADMIN, $LVL_SPV, 'DIRFA');
        $make('OPSMGR', 'Operational Manager', $ADMIN, $LVL_MGR, 'GM');
        $make('SALESMGR', 'Sales & Marketing Manager', $SALES, $LVL_MGR, 'OPSMGR');
        $make('TECHSUPPORT', 'Technical Support & Maintenance', $ADMIN, $LVL_SPV, 'OPSMGR');
        $make('GADEPT', 'General Affair Dept.', $UMUM, $LVL_SPV, 'OPSMGR');
        $make('HCDEPT', 'Human Capital Dept.', $ADMIN, $LVL_SPV, 'OPSMGR');
        $make('SCMDEPT', 'Supply Chain Dept.', $ADMIN, $LVL_SPV, 'OPSMGR');
        $make('OFFSETDEPT', 'Offset Production Dept.', $PRESS, $LVL_SPV, 'OPSMGR');
        $make('DIGITALDEPT', 'Digital Production Dept.', $DIGITALPROD, $LVL_SPV, 'OPSMGR');
        // Bypass Operational Manager -- Costing rahasia, langsung Direksi (CLAUDE.md §4).
        $make('PRODCOSTING', 'Product Costing', $ADMIN, $LVL_SPV, 'PRESDIR');

        // ── Sales & Marketing detail (PDF hal.2) ──
        $make('MKTDIV', 'Marketing Div.', $SALES, $LVL_SPV, 'SALESMGR');
        $make('OFFSETSALESDIV', 'Offset Div. (Sales)', $SALES, $LVL_SPV, 'SALESMGR');
        $make('DIGITALSALESDIV', 'Digital Div. (Sales)', $SALES, $LVL_SPV, 'SALESMGR');
        $make('ADVERTISER', 'Advertiser', $SALES, $LVL_STAFF, 'MKTDIV');
        $make('NPD', 'NPD', $SALES, $LVL_STAFF, 'MKTDIV');
        $make('CONTENTCREATOR', 'Content Creator', $DESIGN, $LVL_STAFF, 'MKTDIV');
        $make('B2BSALESADMIN', 'B2B Sales Admin', $SALES, $LVL_STAFF, 'OFFSETSALESDIV');
        $make('B2BSALES', 'B2B Sales', $SALES, $LVL_STAFF, 'OFFSETSALESDIV');
        $make('DESKPRINT', 'Deskprint', $DIGITAL, $LVL_STAFF, 'DIGITALSALESDIV');
        $make('CASHIERSALES', 'Cashier', $DIGITAL, $LVL_STAFF, 'DIGITALSALESDIV');
        $make('ONLINESALES', 'Online Sales', $DIGITAL, $LVL_STAFF, 'DIGITALSALESDIV');

        // ── Technical Support / GA / Human Capital detail (PDF hal.3) ──
        $make('ITSUPPORT', 'IT Support', $ADMIN, $LVL_STAFF, 'TECHSUPPORT');
        $make('MECHANICAL', 'Mechanical', $ADMIN, $LVL_STAFF, 'TECHSUPPORT');
        $make('SECURITYPOS', 'Security', $SECURITY, $LVL_STAFF, 'GADEPT');
        $make('OFFICEBOY', 'Office Boy', $UMUM, $LVL_STAFF, 'GADEPT');
        $make('TALENTACQ', 'Talent Acquisition', $ADMIN, $LVL_STAFF, 'HCDEPT');
        $make('PERSONALIA', 'Personalia', $ADMIN, $LVL_STAFF, 'HCDEPT');

        // ── Supply Chain detail (PDF hal.4) ──
        $make('PPICDIV', 'PPIC Div.', $PPIC, $LVL_SPV, 'SCMDEPT');
        $make('PROCUREMENTDIV', 'Procurement Div.', $ADMIN, $LVL_SPV, 'SCMDEPT');
        $make('LOGISTICDIV', 'Logistic Div.', $EKSPEDISI, $LVL_SPV, 'SCMDEPT');
        $make('PPICPLANNER', 'PPIC Planner', $PPIC, $LVL_STAFF, 'PPICDIV');
        $make('PPICADMIN', 'PPIC Admin', $PPIC, $LVL_STAFF, 'PPICDIV');
        $make('WHADMIN', 'Warehouse Admin', $GUDANG, $LVL_STAFF, 'PPICDIV');
        $make('FORKLIFTOPS', 'Forklift Ops.', $GUDANG, $LVL_STAFF, 'WHADMIN');
        $make('WHSTAFF', 'Warehouse Staff', $GUDANG, $LVL_STAFF, 'WHADMIN');
        $make('PURCHASINGSTAFF', 'Purchasing Staff', $ADMIN, $LVL_STAFF, 'PROCUREMENTDIV');
        $make('LOGISTICADMIN', 'Logistic Admin', $EKSPEDISI, $LVL_STAFF, 'LOGISTICDIV');
        $make('LOGISTICSTAFF', 'Logistic Staff', $EKSPEDISI, $LVL_STAFF, 'LOGISTICDIV');
        $make('FLEETSTAFF', 'Fleet Staff', $EKSPEDISI, $LVL_STAFF, 'LOGISTICDIV');

        // ── Offset Production detail (PDF hal.5) ──
        $make('PREPRESSSPV', 'Pre Press SPV', $PRACETAK, $LVL_SPV, 'OFFSETDEPT');
        $make('POTONGSPV', 'Potong SPV', $POTONG, $LVL_SPV, 'OFFSETDEPT');
        $make('OFFSETSPV', 'Offset SPV', $PRESS, $LVL_SPV, 'OFFSETDEPT');
        $make('COATINGSPV', 'Coating & Laminasi SPV', $LAMINASI, $LVL_SPV, 'OFFSETDEPT');
        $make('PLONGKUPASSPV', 'Plong & Kupas SPV', $PLONGDIECUT, $LVL_SPV, 'OFFSETDEPT');
        $make('JOININGSPV', 'Joining SPV', $FOLDERGLUE, $LVL_SPV, 'OFFSETDEPT');
        $make('QCSPV', 'QC SPV', $QC, $LVL_SPV, 'OFFSETDEPT');

        // ── Taut occupant yang IDENTITASNYA SUDAH DIKONFIRMASI user sesi ini ──
        $db->table('emp_employee')->where('employee_id', 144)->update(['position_id' => $ids['OPSMGR'], 'updated_by' => 'claude_code']);
        $db->table('emp_employee')->where('employee_id', 151)->update(['position_id' => $ids['HCDEPT'], 'updated_by' => 'claude_code']);
    }

    public function down(): void
    {
        // Kembalikan 2 occupant yg ditaut ke posisi legacy-unmapped semula (id=9).
        DB::connection('tm2mst')->table('emp_employee')->whereIn('employee_id', [144, 151])->update(['position_id' => 9]);

        DB::connection('tm2mst')->table('org_position')->whereIn('position_name', [
                'Presiden Direktur', 'Direktur F&A', 'General Manager', 'F&A SPV', 'Operational Manager',
                'Sales & Marketing Manager', 'Technical Support & Maintenance', 'General Affair Dept.',
                'Human Capital Dept.', 'Supply Chain Dept.', 'Offset Production Dept.', 'Digital Production Dept.',
                'Product Costing', 'Marketing Div.', 'Offset Div. (Sales)', 'Digital Div. (Sales)', 'Advertiser',
                'NPD', 'Content Creator', 'B2B Sales Admin', 'B2B Sales', 'Deskprint', 'Cashier', 'Online Sales',
                'IT Support', 'Mechanical', 'Security', 'Office Boy', 'Talent Acquisition', 'Personalia',
                'PPIC Div.', 'Procurement Div.', 'Logistic Div.', 'PPIC Planner', 'PPIC Admin', 'Warehouse Admin',
                'Forklift Ops.', 'Warehouse Staff', 'Purchasing Staff', 'Logistic Admin', 'Logistic Staff',
                'Fleet Staff', 'Pre Press SPV', 'Potong SPV', 'Offset SPV', 'Coating & Laminasi SPV',
                'Plong & Kupas SPV', 'Joining SPV', 'QC SPV',
            ])->delete();
    }
};
