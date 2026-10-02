<?php

namespace Database\Seeders;

use App\Models\Master\FlowLog;
use App\Models\Master\MatCategory;
use App\Models\Master\MatRaw;
use App\Models\Master\OrgDepartment;
use App\Models\Pur\PrLine;
use App\Models\Pur\PrLineAlloc;
use App\Models\Pur\PrRequisition;
use App\Models\Sys\SecUser;
use App\Support\V2\Data\PurchaseRequisition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// Dummy untuk menguji PR Approval Center (user 2026-10-02): PR berstatus SUBMITTED yang menunggu keputusan approver
// tahap berjalan (flow_config PR -> jabatan Operational Manager). Approve / Reject bersifat FINAL, jadi pengujian
// diulang lewat Reset data dummy -- simpan baseline sesudah seeder ini supaya antrian kembali setelah Reset.
// Diajukan lewat aturan layar (PurchaseRequisition::submit): nomor dokumen + flow_log lahir seperti Confirm sungguhan.
// Harga master (mat_raw.last_unitprice) kosong -> harga estimasi = angka yang sudah dipakai PR dummy lama.
// Hanya ddt*; diulang = PR seeder ini yang belum ditarik ke PO dihapus lalu dibuat lagi.
//   php artisan db:seed --class=UatPrApprovalSeeder --env=mockup --force
//   php artisan ddt:baseline save --env=mockup --no-interaction --force
class UatPrApprovalSeeder extends Seeder
{
    private const BY = 'uat_approval';

    // [departemen, login pengaju, tgl butuh (+hari), catatan, baris]. Baris master = [kode bahan, qty, harga estimasi];
    // baris teks bebas = [null, qty, harga estimasi, uraian, satuan, kode akun beban] (pr_line.i_coa_account_id wajib isi).
    private const PLAN = [
        ['Cetak', 'toni', 7, 'Paper and ink for next week print run (3 customer orders).',
            [['PYJ31', 50, 1185000], ['IOH12', 15, 150000]]],
        ['Gudang', 'mahmud', 10, 'Stock replenishment: duplex and HVS below minimum stock.',
            [['PZ00083', 30, 905000], ['P4454', 10, 455000]]],
        ['Folder Glue', 'firdianzah', 5, 'Glue for folder gluer line, stock left for 3 days.',
            [['GZ00726', 50, 100000]]],
        ['Umum', 'imam', 14, 'Office maintenance, routine.',
            [[null, 4, 350000, 'Air conditioner service, office area', 'Unit', '619000'], [null, 6, 275000, 'Fire extinguisher refill 6 kg', 'Unit', '619000']]],
        ['Plong + DieCut', 'wahyu', 4, 'Die cutting consumables, urgent: current rule is worn out.',
            [[null, 2, 1850000, 'Die cutting rule 2 pt', 'Box', '517000'], [null, 10, 165000, 'Creasing matrix 0.4 x 1.3 mm', 'Roll', '517000']]],
        ['Sales and Marketing', 'yuna', 6, 'Sample delivery and exhibition material.',
            [[null, 1, 750000, 'Product sample courier to Jakarta', 'Lot', '614000'], [null, 2, 450000, 'Exhibition banner print 60 x 160 cm', 'PCS', '614000']]],
    ];

    public function run(): void
    {
        $pur = DB::connection('tm2pur');
        if (! str_starts_with($pur->getDatabaseName(), 'ddt') || ! str_starts_with(DB::connection('tm2mst')->getDatabaseName(), 'ddt')) {
            throw new RuntimeException('UatPrApprovalSeeder hanya boleh di database dummy ddt* (--env=mockup).');
        }

        $kept = $this->reset();
        $made = array_map(fn ($d) => $this->submit(...$d), $this->drafts());

        $this->command?->info(count($made).' PR menunggu approval di '.$pur->getDatabaseName().': '.implode(', ', $made)
            .($kept ? ' (dipertahankan karena sudah ditarik ke PO: '.implode(', ', $kept).')' : '').'.');
    }

    // PR DRAFT (belum diajukan, belum bernomor) sesuai PLAN. Dipakai juga oleh pembangun set stage (stage 1 = Draft).
    /** @return list<array{PrRequisition, SecUser}> [PR, pengaju] */
    public function drafts(): array
    {
        $pur = DB::connection('tm2pur');
        $fallback = SecUser::findOrFail(1);
        $made = [];

        foreach (self::PLAN as [$deptName, $login, $needDays, $notes, $lines]) {
            $dept = OrgDepartment::where('department_name', $deptName)->value('department_id')
                ?? throw new RuntimeException("Departemen {$deptName} tidak ada di master.");

            $pr = PrRequisition::create(['i_doc_date' => now()->toDateString(), 'i_source' => 'MANUAL', 'i_department_id' => $dept,
                'i_need_date' => now()->addDays($needDays)->toDateString(), 'i_notes' => $notes, 'status' => 'DRAFT',
                'created_by' => self::BY, 'updated_by' => self::BY]);

            foreach ($lines as $i => $l) {
                $line = new PrLine(['pr_id' => $pr->pr_id, 'line_no' => $i + 1, 'i_qty' => $l[1], 'i_est_price' => $l[2],
                    'i_department_id' => $dept, 'created_by' => self::BY, 'updated_by' => self::BY] + $this->item($l));
                $line->recalcSubtotal();
                $line->save();
            }
            $pr->recalcEstTotal();
            $pr->save();
            $pur->table('pr_requisition')->where('pr_id', $pr->pr_id)->update(['data_origin' => 'DUMMY']);
            $pur->table('pr_line')->where('pr_id', $pr->pr_id)->update(['data_origin' => 'DUMMY']);
            $made[] = [$pr, SecUser::where('login', $login)->first() ?? $fallback];
        }

        return $made;
    }

    // Confirm oleh pengaju lewat aturan layar: nomor dokumen lahir + masuk antrian approval. Balikan = nomor dokumen.
    public function submit(PrRequisition $pr, SecUser $by): string
    {
        Auth::setUser($by);
        $blocked = PurchaseRequisition::submit($pr);
        if ($blocked !== '') {
            throw new RuntimeException($blocked);
        }

        return $pr->fresh()->doc_no;
    }

    private function item(array $l): array
    {
        if ($l[0] === null) {
            $coa = DB::connection('tm2acc')->table('fin_account')->where('account_code', $l[5])->value('account_id')
                ?? throw new RuntimeException("Akun {$l[5]} tidak ada di fin_account.");

            return ['i_item_source' => 'FREE_TEXT', 'i_description' => $l[3], 'i_uom' => $l[4], 'i_coa_account_id' => $coa, 'is_stock' => false];
        }
        $m = MatRaw::where('material_code', $l[0])->first() ?? throw new RuntimeException("Bahan {$l[0]} tidak ada di master.");
        $cat = MatCategory::level(1)->where('category_name', $m->category1)->first();

        return ['i_item_source' => 'MASTER_ITEM', 'item_id' => $m->material_id, 'c_item_code' => $m->material_code, 'i_description' => $m->material_name,
            'i_uom' => $m->uom_purchase ?: 'PCS', 'i_coa_account_id' => $cat?->default_coa_account_id, 'is_stock' => $cat?->stock_class !== 'SERVICE'];
    }

    // PR seeder ini dari jalan sebelumnya; yang barisnya sudah ditarik ke PO tidak dihapus (PO kehilangan sumber).
    private function reset(): array
    {
        $kept = [];
        foreach (PrRequisition::where('created_by', self::BY)->get() as $pr) {
            $lineIds = $pr->lines()->pluck('pr_line_id');
            if ($pr->lines()->where('c_qty_ordered', '>', 0)->exists() || PrLineAlloc::whereIn('pr_line_id', $lineIds)->where('c_is_converted', true)->exists()) {
                $kept[] = $pr->doc_no;

                continue;
            }
            FlowLog::where('module_code', 'PR')->where('doc_id', $pr->pr_id)->delete();
            PrLineAlloc::whereIn('pr_line_id', $lineIds)->delete();
            PrLine::whereIn('pr_line_id', $lineIds)->delete();
            $pr->delete();
        }

        return $kept;
    }
}
