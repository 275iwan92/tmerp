<?php

namespace App\Support\Dummy;

use App\Models\Pur\PrRequisition;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sys\SecUser;
use App\Services\Flow\FlowEngineService;
use App\Services\Sales\InquiryWriter;
use Database\Seeders\UatPrApprovalSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

// Membangun set data dummy per stage lewat ATURAN APLIKASI (bukan mengetik baris ke tabel): stage N = stage N-1 yang
// dimajukan satu langkah oleh Confirm / Approve / Post yang sama dengan tombol di layar. Aturan yang gagal saat
// memajukan stage = bug alur yang ketemu di sini.
// Stage 1 = titik nol: SEMUA dokumen transaksi operasional dihapus (WIPE), yang tersisa hanya dokumen langkah pertama
// tiap alur (Inquiry New yang belum Confirm, PR Draft). Master, data legacy, HR / payroll / rekrutmen, maintenance dan
// aset tetap. Set "mixed" (semua status campur, dipakai dashboard) disimpan terpisah dan tidak diubah di sini.
final class StageBuilder
{
    // [judul, isi] tiap stage -> tampil di layar Data Dummy. Stage berikutnya ditambah per alur (docs/globaluserrules.md B.15).
    public const STAGES = [
        1 => ['Stage 1 — nothing confirmed yet',
            'Inquiry New: Draft, not confirmed by Sales. Purchase Requisition: Draft, not submitted. No quotation, order, work order, delivery, invoice, purchase order or stock document exists yet.'],
        2 => ['Stage 2 — first Confirm done, waiting for the next desk',
            'Inquiry New: confirmed by Sales, waiting on the Pro-Dev desk (not started). Purchase Requisition: submitted, waiting for approval by the Operational Manager.'],
    ];

    // Tabel transaksi operasional yang dikosongkan di stage 1 (koneksi => tabel). Inquiry tidak di sini: yang masih
    // Draft dipertahankan (wipeInquiries). Diperiksa ulang oleh orphans(): baris anak tanpa induk = daftar ini kurang.
    private const WIPE = [
        'tm2sdi' => ['quo_quotation_item', 'quo_quotation_cc', 'quo_quotation', 'co_delivery_negotiation', 'co_order_external_cost', 'co_order_delivery',
            'co_order_item', 'co_order', 'do_delivery_pod', 'do_line', 'do_delivery', 'crm_activity', 'crm_complaint_item', 'crm_complaint',
            'iqp_costing_state_log', 'iqp_costing_part_coating', 'iqp_costing_part', 'iqp_costing_price_tier', 'iqp_costing', 'iqp_layout',
            'iqp_prove_log', 'iqp_prove', 'iqp_revise_change'],
        'tm2pur' => ['po_import_doc', 'po_line', 'po_order', 'pq_line', 'pq_quotation', 'pr_line_alloc', 'pr_line', 'pr_requisition', 'prt_line',
            'prt_return', 'pv_line', 'pv_receipt'],
        'tm2inv' => ['fg_stock_d', 'fg_stock', 'fg_stockcard', 'inv_label_print', 'inv_layer_consumption', 'inv_stock_layer', 'inv_transfer_line',
            'inv_transfer', 'rm_booking_alloc', 'rm_booking_d', 'rm_booking', 'rm_stock_d', 'rm_stock', 'rm_stockcard', 'sfg_stock_d', 'sfg_stock', 'sfg_stockcard'],
        'tm2prd' => ['prd_breakdown_event', 'prd_crew_assignment', 'prd_decision_feedback_log', 'prd_wo_operation_output_defect', 'prd_wo_operation_output',
            'prd_wo_operation', 'prd_wo_material_substitution', 'prd_wo_material', 'prd_wo', 'prd_mrp_requirement', 'prd_mrp_run', 'qc_complaint_review'],
        // Keuangan: hanya dokumen piutang / hutang. Master (fin_journal = jenis jurnal, fin_petty_cash = dana kas kecil),
        // register pajak, rekening koran dan voucher di luar penjualan / pembelian TIDAK di sini (lihat wipeFinance).
        'tm2acc' => ['fin_dp_allocation', 'fin_payment_alloc', 'fin_invoice_receipt_line', 'fin_invoice_receipt', 'fin_invoice_line', 'fin_invoice', 'fin_petty_cash_tx'],
    ];

    // Voucher yang lahir dari dokumen penjualan / pembelian / stok (fin_voucher.source_type). Voucher lain (aset,
    // penyusutan, gaji, pinjaman, pajak, overhead, manual, saldo awal) bukan bagian alur ini dan tetap ada.
    private const VOUCHER_SOURCES = ['CUSTOMER_INVOICE', 'AR_RECEIPT', 'CUSTOMER_DP', 'CUSTOMER_DP_PAYMENT', 'VENDOR_BILL', 'AP_PAYMENT', 'PV', 'MATERIAL_USAGE'];

    // Tabel di luar WIPE yang barisnya memang berkurang di stage 1 (dihapus sebagian). Tabel lain yang berkurang = salah hapus.
    private const PARTIAL = ['tm2mst.flow_log', 'tm2acc.fin_voucher', 'tm2acc.fin_voucher_line', 'tm2sdi.iqp_inquiry'];

    // Log approval dokumen yang ikut dihapus (tm2mst.flow_log).
    private const FLOW_MODULES = ['PR', 'PO', 'POC', 'CO', 'AD', 'OP', 'PRT', 'IN'];

    private const CONNECTIONS = ['tm2mst', 'tm2sdi', 'tm2pur', 'tm2inv', 'tm2prd', 'tm2acc'];

    /** @var list<array{PrRequisition, SecUser}> */
    private array $requisitions = [];

    /** @var list<string> */
    private array $knownOrphans = [];

    public function __construct(private Command $out) {}

    // $save(key, label, note) menyimpan isi ddt* sekarang sebagai set; null = dry run: semua langkah jalan di dalam
    // transaksi yang dibatalkan, tidak ada yang disimpan.
    public function run(?callable $save): void
    {
        foreach (self::CONNECTIONS as $c) {
            if (! str_starts_with(DB::connection($c)->getDatabaseName(), 'ddt')) {
                throw new RuntimeException('Stage sets are built on the dummy databases only (ddt*, --env=mockup).');
            }
        }
        // Yatim yang sudah ada sebelum build (mis. master wilayah) bukan akibat build ini: hanya yang baru yang menggagalkan.
        $this->knownOrphans = $this->orphans();
        foreach ($this->knownOrphans as $o) {
            $this->out->warn("  Already there before the build: $o");
        }
        $this->begin();
        try {
            foreach (array_keys(self::STAGES) as $n) {
                $this->{'stage'.$n}();
                $this->report($n);
                if ($save) {
                    // mysqldump hanya melihat data yang sudah di-commit.
                    $this->commit();
                    $save(sprintf('stage-%02d', $n), ...self::STAGES[$n]);
                    $this->begin();
                }
            }
        } finally {
            $this->rollBack();
        }
    }

    // Stage 1: titik nol + dokumen langkah pertama.
    private function stage1(): void
    {
        // Inquiry Draft diambil dari isi data dummy sekarang: tanpa itu stage 1 kosong (mis. build dijalankan saat set
        // stage 2 sedang terpasang, semua inquiry sudah Confirm).
        if (! DB::connection('tm2sdi')->table('iqp_inquiry')->where('inquiry_type', 'NEW')->where('stat_confirm_sales', 0)->where('stat_cancel', 0)->exists()) {
            throw new RuntimeException('No Draft inquiry in the current dummy data. Restore the set "mixed" or "stage-01" first, then build.');
        }
        $before = $this->counts();

        foreach (self::WIPE as $conn => $tables) {
            $db = DB::connection($conn);
            $db->statement('SET FOREIGN_KEY_CHECKS=0');
            foreach ($tables as $t) {
                if (Schema::connection($conn)->hasTable($t)) {
                    $db->table($t)->delete();
                }
            }
        }
        $this->wipeInquiries();
        $this->wipeFinance();
        foreach (self::WIPE as $conn => $tables) {
            DB::connection($conn)->statement('SET FOREIGN_KEY_CHECKS=1');
        }
        DB::connection('tm2mst')->table('flow_log')->whereIn('module_code', self::FLOW_MODULES)->delete();
        // Permintaan maintenance tetap ada; kaitannya ke kejadian breakdown produksi (ikut terhapus) dilepas.
        if (Schema::connection('tm2prd')->hasColumn('mnt_request', 'breakdown_event_id')) {
            DB::connection('tm2prd')->table('mnt_request')->update(['breakdown_event_id' => null]);
        }

        // Pengaman salah hapus: di luar daftar WIPE / PARTIAL / anak inquiry tidak boleh ada tabel yang barisnya berkurang.
        $allowed = array_merge(self::PARTIAL, array_map(fn ($c) => 'tm2sdi.'.$c[0], [...$this->children('tm2sdi', 'iqp_inquiry'), ...$this->children('tm2sdi', 'iqp_part')]));
        foreach (self::WIPE as $conn => $tables) {
            array_push($allowed, ...array_map(fn ($t) => "$conn.$t", $tables));
        }
        $lost = array_filter($this->counts(), fn ($n, $t) => $n < $before[$t] && ! in_array($t, $allowed, true), ARRAY_FILTER_USE_BOTH);
        if ($lost) {
            throw new RuntimeException('Stage 1 removed rows from tables that are not transactions: '.implode(', ', array_keys($lost)));
        }

        $this->requisitions = (new UatPrApprovalSeeder)->drafts();
        Auth::setUser(SecUser::findOrFail(1));
    }

    // Voucher dari dokumen penjualan / pembelian / stok dihapus; baris rekening koran yang dicocokkan ke voucher itu
    // kembali belum-cocok (uang masuk / keluarnya tetap tercatat di bank, dokumennya yang belum ada).
    private function wipeFinance(): void
    {
        $acc = DB::connection('tm2acc');
        $ids = $acc->table('fin_voucher')->whereIn('source_type', self::VOUCHER_SOURCES)->pluck('voucher_id');
        $ids = $ids->merge($acc->table('fin_voucher')->whereIn('reversal_of_id', $ids)->pluck('voucher_id'))->unique();
        $acc->table('fin_bank_statement')->whereIn('matched_voucher_id', $ids)->update(['matched_voucher_id' => null, 'match_status' => 'UNMATCHED']);
        $acc->table('fin_voucher')->whereIn('reversed_by_id', $ids)->update(['reversed_by_id' => null]);
        $acc->table('fin_voucher_line')->whereIn('voucher_id', $ids)->delete();
        $acc->table('fin_voucher')->whereIn('voucher_id', $ids)->delete();
    }

    /** @return array<string, int> ["koneksi.tabel" => jumlah baris] semua tabel database transaksi + master */
    private function counts(): array
    {
        $out = [];
        foreach (self::CONNECTIONS as $conn) {
            $db = DB::connection($conn);
            foreach ($db->select("SELECT TABLE_NAME t FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'", [$db->getDatabaseName()]) as $r) {
                $out["$conn.$r->t"] = $db->table($r->t)->count();
            }
        }

        return $out;
    }

    // Stage 2: Confirm pertama tiap alur.
    private function stage2(): void
    {
        $sales = SecUser::where('login', 'yuna')->first() ?? SecUser::findOrFail(1);
        Auth::setUser($sales);
        $writer = new InquiryWriter($sales->login);
        foreach (IqpInquiry::withoutGlobalScopes()->where('stat_confirm_sales', false)->orderBy('id_inquiry')->get() as $inq) {
            $writer->confirm($inq);
        }

        $seeder = new UatPrApprovalSeeder;
        foreach ($this->requisitions as [$pr, $requester]) {
            $seeder->submit($pr->refresh(), $requester);
        }
        Auth::setUser(SecUser::findOrFail(1));
    }

    // Inquiry yang sudah lewat langkah pertama (Confirm Sales / batal / bukan New) dihapus beserta anaknya; anak dicari
    // dari FOREIGN KEY ke iqp_inquiry dan iqp_part, jadi tabel anak baru ikut tanpa mengubah daftar di sini.
    private function wipeInquiries(): void
    {
        $sdi = DB::connection('tm2sdi');
        $ids = $sdi->table('iqp_inquiry')->where(fn ($q) => $q->where('inquiry_type', '<>', 'NEW')->orWhere('stat_confirm_sales', 1)->orWhere('stat_cancel', 1))->pluck('id_inquiry');
        $parts = $sdi->table('iqp_part')->whereIn('inquiry_id', $ids)->pluck('id_part');
        foreach ($this->children('tm2sdi', 'iqp_part') as [$table, $column]) {
            $sdi->table($table)->whereIn($column, $parts)->delete();
        }
        foreach ($this->children('tm2sdi', 'iqp_inquiry') as [$table, $column]) {
            $sdi->table($table)->whereIn($column, $ids)->delete();
        }
        $sdi->table('iqp_inquiry')->whereIn('id_inquiry', $ids)->delete();

        // Master Product / part / master card yang lahir dari inquiry itu tetap ada sebagai master, tetapi penunjuk ke
        // inquiry asalnya dilepas: id yang sudah dihapus bisa terpakai lagi oleh inquiry baru dan menunjuk dokumen yang salah.
        $mst = DB::connection('tm2mst');
        $mst->table('prod_product')->whereIn('source_inquiry_id', $ids)->update(['source_inquiry_id' => null]);
        $mst->table('prod_part')->whereIn('source_iqp_part_id', $parts)->update(['source_iqp_part_id' => null]);
        $mst->table('prod_master_card')->whereIn('inquiry_id', $ids)->update(['inquiry_id' => null]);
    }

    /** @return list<array{string, string}> [tabel anak, kolom FK] yang menunjuk $table (di database yang sama) */
    private function children(string $conn, string $table): array
    {
        $db = DB::connection($conn);

        return array_map(fn ($r) => [$r->t, $r->c], $db->select('SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME = ?', [$db->getDatabaseName(), $db->getDatabaseName(), $table]));
    }

    // Baris anak yang induknya tidak ada (semua FOREIGN KEY di database transaksi). Kosong = bersih.
    /** @return list<string> */
    public function orphans(): array
    {
        $found = [];
        foreach (self::CONNECTIONS as $conn) {
            $db = DB::connection($conn);
            $name = $db->getDatabaseName();
            foreach ($db->select('SELECT TABLE_NAME t, COLUMN_NAME c, REFERENCED_TABLE_NAME rt, REFERENCED_COLUMN_NAME rc FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_SCHEMA = ?', [$name, $name]) as $fk) {
                $n = $db->table("$fk->t as c")->leftJoin("$fk->rt as p", "c.$fk->c", '=', "p.$fk->rc")->whereNotNull("c.$fk->c")->whereNull("p.$fk->rc")->count();
                if ($n) {
                    $found[] = "$name.$fk->t.$fk->c -> $fk->rt: $n row(s) without parent";
                }
            }
        }

        return $found;
    }

    private function report(int $n): void
    {
        $sdi = DB::connection('tm2sdi');
        $this->out->info(self::STAGES[$n][0]);
        $this->out->line('  Inquiry: '.$sdi->table('iqp_inquiry')->selectRaw("CONCAT(inquiry_type, ' sales=', stat_confirm_sales, ' prodev=', stat_confirm_prodev, ' procost=', stat_confirm_procost) k, COUNT(*) n")
            ->groupBy('k')->pluck('n', 'k')->map(fn ($v, $k) => "$k: $v")->implode(', '));
        $this->out->line('  Purchase Requisition: '.DB::connection('tm2pur')->table('pr_requisition')->selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status')
            ->map(fn ($v, $k) => "$k: $v")->implode(', '));
        $flow = app(FlowEngineService::class);
        $this->out->line('  PR waiting per approver: '.(SecUser::where('activate', true)->get()->map(fn ($u) => [$u->login, $flow->pendingForUser('PR', $u->getKey())->count()])
            ->filter(fn ($r) => $r[1])->map(fn ($r) => "$r[0]: $r[1]")->implode(', ') ?: 'none'));
        $leftovers = collect(self::WIPE)->flatMap(fn ($tables, $conn) => collect($tables)->filter(fn ($t) => ! str_starts_with($t, 'pr_') && Schema::connection($conn)->hasTable($t))
            ->mapWithKeys(fn ($t) => ["$conn.$t" => DB::connection($conn)->table($t)->count()]))->filter();
        $this->out->line('  Other transaction rows: '.($leftovers->map(fn ($v, $k) => "$k: $v")->implode(', ') ?: 'none'));
        if ($orphans = array_diff($this->orphans(), $this->knownOrphans)) {
            throw new RuntimeException("Stage $n leaves rows without a parent:\n  ".implode("\n  ", $orphans));
        }
    }

    private function begin(): void
    {
        foreach (self::CONNECTIONS as $c) {
            DB::connection($c)->beginTransaction();
        }
    }

    private function commit(): void
    {
        foreach (self::CONNECTIONS as $c) {
            DB::connection($c)->commit();
        }
    }

    private function rollBack(): void
    {
        foreach (self::CONNECTIONS as $c) {
            while (DB::connection($c)->transactionLevel() > 0) {
                DB::connection($c)->rollBack();
            }
        }
    }
}
