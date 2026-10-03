<?php

namespace App\Support\Dummy;

use App\Models\Acc\FinInvoice;
use App\Models\Master\FlowLog;
use App\Models\Pur\PoOrder;
use App\Models\Pur\PrLineAlloc;
use App\Models\Pur\PrRequisition;
use App\Models\Sdi\CoOrder;
use App\Models\Sdi\CoOrderDelivery;
use App\Models\Sdi\DoDelivery;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\IqpPart;
use App\Models\Sdi\QuoQuotation;
use App\Models\Sys\SecUser;
use App\Services\Flow\FlowEngineService;
use App\Services\Sales\DeliveryPromiseService;
use App\Services\Sales\InquiryStage;
use App\Services\Sales\InquiryWriter;
use App\Support\V2\Data\ApprovalCenter;
use App\Support\V2\Data\Cpdel;
use App\Support\V2\Data\CustomerInvoice;
use App\Support\V2\Data\CustomerOrder;
use App\Support\V2\Data\DeliveryOrder;
use App\Support\V2\Data\Prodev;
use App\Support\V2\Data\PurchaseOrder;
use App\Support\V2\Data\Quotation;
use Database\Seeders\Concerns\PricedDummyChain;
use Database\Seeders\UatPrApprovalSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDOException;
use RuntimeException;

// Membangun set data dummy per stage lewat ATURAN APLIKASI (bukan mengetik baris ke tabel): stage N = stage N-1 yang
// dimajukan satu langkah oleh Confirm / Approve / Post yang sama dengan tombol di layar. Aturan yang gagal saat
// memajukan stage = bug alur yang ketemu di sini.
// Stage 1 = titik nol: SEMUA dokumen transaksi operasional dihapus (WIPE), yang tersisa hanya dokumen langkah pertama
// tiap alur (Inquiry New yang belum Confirm, PR Draft). Master, data legacy, HR / payroll / rekrutmen, maintenance dan
// aset tetap. Set "mixed" (semua status campur, dipakai dashboard) disimpan terpisah dan tidak diubah di sini.
final class StageBuilder
{
    use PricedDummyChain;

    // [judul, isi] tiap stage -> tampil di layar Data Dummy. Stage berikutnya ditambah per alur (docs/globaluserrules.md B.15).
    public const STAGES = [
        1 => ['Stage 1 — nothing confirmed yet',
            'Inquiry New: Draft, not confirmed by Sales. Purchase Requisition: Draft, not submitted. No quotation, order, work order, delivery, invoice, purchase order or stock document exists yet.'],
        2 => ['Stage 2 — in Pro-Dev, not confirmed',
            'Inquiry New: confirmed by Sales, waiting on the Pro-Dev desk (not started). Purchase Requisition: submitted, waiting for approval by the Operational Manager.'],
        3 => ['Stage 3 — in Pro-Cost, not confirmed',
            'Inquiry New: every part confirmed by Pro-Dev (layout, BOM, ink, process), waiting on the Pro-Cost desk; no costing yet. Purchase Requisition: approved, waiting for a Purchase Order.'],
        4 => ['Stage 4 — Quotation Draft',
            'Costing published by Pro-Cost for every inquiry; one Quotation per inquiry, still Draft (not confirmed, not sent). Purchase Order: Draft, one per approved requisition, not submitted.'],
        5 => ['Stage 5 — Customer Order Draft',
            'Quotations published and won; each has a Customer Order in Draft (qty not chosen, no due date) and a Master Product. Purchase Order: submitted, waiting for approval level 1 (Product Costing).'],
        6 => ['Stage 6 — Customer Order waiting for approval',
            'Customer Orders confirmed by Sales (one qty chosen, due date set), waiting for approval level 1 (F&A SPV). Purchase Order: waiting for approval level 2 (Operational Manager).'],
        7 => ['Stage 7 — CPDEL Draft',
            'Customer Orders approved (Confirmed); their Customer Plan Delivery lines are Planned and not posted to Production. No work order or delivery yet. Purchase Order: waiting for approval level 3 (President Director).'],
        8 => ['Stage 8 — Posted to Production, Work Orders not started',
            'Every Customer Plan Delivery line is posted to Production; its Work Orders exist and none has started. Purchase Order: approved, not sent to the supplier, nothing received.'],
        9 => ['Stage 9 — Delivery Order Draft',
            'One Delivery Order per Customer Order with its delivery line pulled in, still Draft. No production output was entered (delivery shortcut). Purchase Order: approved.'],
        10 => ['Stage 10 — Delivery Order Confirmed, waiting for proof of delivery',
            'Delivery Orders confirmed (delivery note issued); the customer receipt is not recorded yet, so nothing can be invoiced. Purchase Order: approved.'],
        11 => ['Stage 11 — Customer Invoice Draft',
            'Proof of delivery recorded (customer received everything, promise kept); one Customer Invoice per order, still Draft. Purchase Order: approved.'],
        12 => ['Stage 12 — Customer Invoice Confirmed, waiting for payment',
            'Customer Invoices confirmed and posted; no payment received yet. Purchase Order: approved.'],
    ];

    // Tanggal kirim CO dummy: hari kerja ke-14 dihitung kasar sebagai +21 hari kalender dari hari build.
    private const DUE_DAYS = 21;

    // Tepi lembar cetak yang tidak dipakai susunan (cm): gripper + margin, sama dengan konstanta layar Pro-Dev.
    private const EDGE_CM = ['gripper' => 1.2, 'margin' => 0.8];

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

    // Tabel di luar WIPE yang barisnya memang berkurang di stage 1 (dihapus sebagian), koneksi => tabel seperti WIPE.
    // Tabel lain yang berkurang = salah hapus.
    private const PARTIAL = ['tm2mst' => ['flow_log'], 'tm2acc' => ['fin_voucher', 'fin_voucher_line'], 'tm2sdi' => ['iqp_inquiry']];

    // Log approval dokumen yang ikut dihapus (tm2mst.flow_log).
    private const FLOW_MODULES = ['PR', 'PO', 'POC', 'CO', 'AD', 'OP', 'PRT', 'IN'];

    private const CONNECTIONS = ['tm2mst', 'tm2sdi', 'tm2pur', 'tm2inv', 'tm2prd', 'tm2acc'];

    /** @var list<array{PrRequisition, SecUser, string}> [PR, pengaju, kategori supplier] */
    private array $requisitions = [];

    /** @var list<string> */
    private array $knownOrphans = [];

    private string $startedAt = '';

    /** @var array<string, list<string>> koneksi => tabel yang punya data_origin + created_at */
    private array $originTables = [];

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
        $this->startedAt = now()->subSecond()->toDateTimeString();
        foreach (self::CONNECTIONS as $conn) {
            $db = DB::connection($conn);
            $this->originTables[$conn] = array_column($db->select("SELECT TABLE_NAME t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND COLUMN_NAME IN ('data_origin', 'created_at')
                GROUP BY TABLE_NAME HAVING COUNT(*) = 2", [$db->getDatabaseName()]), 't');
        }
        // Build sungguhan TANPA transaksi: tiap database punya koneksi sendiri, dan aturan yang menulis lintas database
        // (mis. Confirm invoice lewat koneksi keuangan mengubah do_line penjualan) harus melihat data yang sudah
        // tersimpan. Gagal di tengah jalan ditangani pemanggil (isi sebelum build dikembalikan). Dry run memakai
        // transaksi supaya bisa dibatalkan, jadi stage yang butuh data lintas-koneksi tidak bisa diuji kering:
        // berhenti di sana dengan keterangan.
        $dry = $save === null;
        if ($dry) {
            $this->begin();
        }
        try {
            foreach (array_keys(self::STAGES) as $n) {
                try {
                    $this->{'stage'.$n}();
                } catch (PDOException $e) {
                    if ($dry && str_contains($e->getMessage(), 'Lock wait timeout')) {
                        $this->out->warn("Stage $n cannot be dry-run (it writes across databases and needs the stage before it committed). It only runs in a real build.");
                        break;
                    }
                    throw $e;
                }
                $this->markDummy();
                $this->report($n);
                if ($save) {
                    $save(sprintf('stage-%02d', $n), ...self::STAGES[$n]);
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
        $allowed = array_map(fn ($c) => 'tm2sdi.'.$c[0], [...$this->children('tm2sdi', 'iqp_inquiry'), ...$this->children('tm2sdi', 'iqp_part')]);
        foreach ([self::WIPE, self::PARTIAL] as $list) {
            foreach ($list as $conn => $tables) {
                array_push($allowed, ...array_map(fn ($t) => "$conn.$t", $tables));
            }
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
        // Model yang sama dipakai lagi di stage 4 (nomor dokumen untuk pesan).
        array_walk($this->requisitions, fn ($r) => $r[0]->refresh());
        Auth::setUser(SecUser::findOrFail(1));
    }

    // Stage 3: Pro-Dev Confirm semua part -> inquiry pindah ke meja Pro-Cost. PR disetujui.
    // Di layar, susunan (layout) dibuat petugas di kanvas; di sini susunannya grid sederhana (gridLayout) yang
    // disimpan + di-Confirm lewat method yang sama dengan tombol Confirm (saveLayout komponen Pro-Dev), jadi BOM / tinta /
    // proses tetap hasil SpecEngine. Angkanya contoh, bukan hasil kerja Pro-Dev sungguhan.
    private function stage3(): void
    {
        $officer = $this->user('dedi');
        Auth::setUser($officer);
        foreach (IqpInquiry::withoutGlobalScopes()->where('stat_confirm_sales', true)->orderBy('id_inquiry')->get() as $inq) {
            InquiryStage::openInProdev($inq, $officer->login);
            foreach (IqpPart::where('inquiry_id', $inq->id_inquiry)->whereNotNull('prod_part_id')->orderBy('part_seq')->pluck('prod_part_id') as $partId) {
                $c = Prodev::component((int) $partId);
                $job = $c->jobs()[0] ?? throw new RuntimeException("Pro-Dev: part {$partId} of {$inq->inquiry_no} has no blank size, a layout cannot be made.");
                $out = $c->saveLayout($this->gridLayout($job, $c->machines()[0], $c->dieCutters()[0] ?? null, $c->planos()), true);
                if (! ($out['ok'] ?? false)) {
                    throw new RuntimeException("Pro-Dev Confirm {$inq->inquiry_no} part {$partId}: ".($out['message'] ?? 'failed'));
                }
            }
            InquiryStage::syncProdev((int) $inq->id_inquiry);
        }

        $this->approve('PR', fn (int $id) => ApprovalCenter::decide('PR', $id, 'approve', ''));
        Auth::setUser(SecUser::findOrFail(1));
    }

    // Stage 4: Pro-Cost publish (mode manual + CostingEngine, sama dengan dummy rantai order) -> Quotation Draft per inquiry.
    private function stage4(): void
    {
        $costing = $this->user('wiwik');
        Auth::setUser($costing);
        $ids = DB::connection('tm2sdi')->table('iqp_inquiry')->where('stat_confirm_prodev', 1)->orderBy('id_inquiry')->pluck('id_inquiry');
        foreach ($ids as $id) {
            $this->priceAndPublish((int) $id, $costing->login);
            InquiryStage::syncProcost((int) $id);
        }

        Auth::setUser($this->user('yuna'));
        foreach ($ids as $id) {
            Quotation::create([(int) $id]);
        }

        // PO Draft: satu PO per PR, ke supplier pertama (urut nama) dari kategori supplier di rencana PR; dua PR dengan
        // kategori sama mendapat supplier berbeda. Baris PO = alokasi PR yang disetujui (harga = estimasi PR).
        Auth::setUser(SecUser::findOrFail(1));
        $used = [];
        foreach ($this->requisitions as [$pr, , $category]) {
            $supplier = DB::connection('tm2mst')->table('sup_supplier')->where('category', $category)->where('activate', 1)->where('is_blacklisted', 0)
                ->whereNotIn('id_supplier', $used)->orderBy('supplier_name')->value('id_supplier')
                ?? throw new RuntimeException("No active supplier in category {$category} for {$pr->doc_no}.");
            $used[] = $supplier;
            $po = PurchaseOrder::create((int) $supplier);
            foreach (PrLineAlloc::whereIn('pr_line_id', $pr->lines()->pluck('pr_line_id'))->orderBy('alloc_id')->pluck('alloc_id') as $alloc) {
                PurchaseOrder::addAllocation($po, (int) $alloc);
            }
        }
        Auth::setUser(SecUser::findOrFail(1));
    }

    // Stage 5: Quotation Confirm (terbit) -> WIN -> CO Draft + Master Product. PO diajukan (menunggu approval level 1).
    private function stage5(): void
    {
        Auth::setUser($this->user('yuna'));
        foreach (QuoQuotation::orderBy('id_quotation')->pluck('id_quotation') as $id) {
            Quotation::confirm(QuoQuotation::findOrFail($id));
            Quotation::win(QuoQuotation::with('items.inquiry')->findOrFail($id)) ?? throw new RuntimeException("WIN of quotation {$id} did not create a Customer Order.");
        }

        Auth::setUser(SecUser::findOrFail(1));
        foreach (PoOrder::where('status', 'DRAFT')->orderBy('po_id')->get() as $po) {
            if ($blocked = PurchaseOrder::submit($po)) {
                throw new RuntimeException("Submit PO #{$po->po_id}: {$blocked}");
            }
        }
        Auth::setUser(SecUser::findOrFail(1));
    }

    // Satu level approval PO (dipanggil di stage 6, 7, 8: level 1, 2, 3).
    private function approvePurchaseOrders(): void
    {
        foreach (['PO', 'POC'] as $module) {
            $this->approve($module, fn (int $id) => PurchaseOrder::decide(PoOrder::findOrFail($id), 'approve', ''));
        }
    }

    // Stage 6: Sales memilih satu qty per product, mengisi due date, Confirm -> menunggu approval level 1.
    private function stage6(): void
    {
        Auth::setUser($this->user('yuna'));
        $due = now()->addDays(self::DUE_DAYS)->toDateString();
        foreach (CoOrder::orderBy('id_order')->get() as $co) {
            // Memilih satu baris menghapus pilihan qty lain product itu -> daftar dibaca ulang tiap putaran.
            while (($open = $co->refresh()->itemsWithUnpickedQty())->isNotEmpty()) {
                CustomerOrder::pickQty($co, (int) $open->first()->id_item);
            }
            $items = $co->refresh()->items->where('line_kind', 'PRODUCT')->mapWithKeys(fn ($i) => [$i->id_item => ['due_date' => $due]])->all();
            CustomerOrder::save($co, ['items' => $items]);
            if ($error = CustomerOrder::confirm($co->refresh())) {
                throw new RuntimeException("Confirm {$co->co_no}: {$error}");
            }
        }
        $this->approvePurchaseOrders();
        Auth::setUser(SecUser::findOrFail(1));
    }

    // Stage 7: semua level approval CO -> Confirmed, baris CPDEL lahir (Planned, belum di-Post).
    private function stage7(): void
    {
        for ($level = 1; $level <= 5 && CoOrder::where('doc_status', 'SUBMITTED')->exists(); $level++) {
            $this->approve('CO', fn (int $id) => CustomerOrder::decide(CoOrder::findOrFail($id), 'approve', ''));
        }
        if ($left = CoOrder::where('doc_status', '<>', 'CONFIRMED')->pluck('co_no')->all()) {
            throw new RuntimeException('Customer Orders not Confirmed after approval: '.implode(', ', $left));
        }
        $this->approvePurchaseOrders();
        Auth::setUser(SecUser::findOrFail(1));
    }

    // Stage 8: Post to Production -> Work Order lahir (belum dimulai). PO: level approval terakhir.
    private function stage8(): void
    {
        Auth::setUser($this->user('yuna'));
        foreach (CoOrderDelivery::whereNull('posted_at')->orderBy('id_delivery')->pluck('id_delivery') as $id) {
            if ($error = Cpdel::post((int) $id)) {
                throw new RuntimeException("Post to Production of CPDEL line {$id}: {$error}");
            }
        }
        $this->approvePurchaseOrders();
        if ($left = PoOrder::where('status', '<>', 'APPROVED')->pluck('doc_no')->all()) {
            throw new RuntimeException('Purchase Orders not Approved after three approval levels: '.implode(', ', $left));
        }
        Auth::setUser(SecUser::findOrFail(1));
    }

    // Stage 9: Delivery Order Draft per CO (jalan pintas kirim tanpa input hasil produksi, aturan A.3).
    private function stage9(): void
    {
        Auth::setUser($this->user('yuna'));
        foreach (CoOrder::orderBy('id_order')->get() as $co) {
            $do = DeliveryOrder::create((int) $co->customer_id);
            if ($error = DeliveryOrder::pull($do, CoOrderDelivery::where('order_id', $co->id_order)->pluck('id_delivery')->all())) {
                throw new RuntimeException("Delivery Order for {$co->co_no}: {$error}");
            }
            if (! $do->lines()->exists()) {
                throw new RuntimeException("Delivery Order for {$co->co_no}: no line was pulled.");
            }
        }
        Auth::setUser(SecUser::findOrFail(1));
    }

    // Stage 10: Delivery Order Confirm (Surat Jalan terbit).
    private function stage10(): void
    {
        Auth::setUser($this->user('yuna'));
        foreach (DoDelivery::where('status', 'DRAFT')->orderBy('id_delivery')->get() as $do) {
            [$ok, $message] = DeliveryOrder::act($do, 'confirm');
            if (! $ok) {
                throw new RuntimeException("Confirm Delivery Order #{$do->id_delivery}: {$message}");
            }
        }
        Auth::setUser(SecUser::findOrFail(1));
    }

    // Stage 11: bukti terima (customer menerima semua, tiba pada tanggal janji) -> Customer Invoice Draft per CO.
    private function stage11(): void
    {
        $sales = $this->user('yuna');
        Auth::setUser($sales);
        foreach (DoDelivery::where('status', 'CONFIRMED')->orderBy('id_delivery')->get() as $do) {
            DeliveryPromiseService::record($do, ['arrived_at' => DeliveryPromiseService::promise($do)['date'].' 09:00', 'result' => 'RECEIVED',
                'received_by' => 'Customer warehouse', 'miss_cause' => 'OTHER'], null, $sales->login);
        }

        Auth::setUser($this->user('mia'));
        foreach (array_keys(CustomerInvoice::orderOptions('INVOICE')) as $coId) {
            CustomerInvoice::create('INVOICE', (int) $coId, CustomerInvoice::service()->billable((int) $coId)->pluck('id_line')->all());
        }
        if (FinInvoice::count() !== CoOrder::count()) {
            throw new RuntimeException('Customer Invoice Draft: '.FinInvoice::count().' invoices for '.CoOrder::count().' orders.');
        }
        Auth::setUser(SecUser::findOrFail(1));
    }

    // Stage 12: Customer Invoice Confirm (jurnal piutang terposting), belum dibayar.
    private function stage12(): void
    {
        Auth::setUser($this->user('mia'));
        foreach (FinInvoice::orderBy('invoice_id')->get() as $invoice) {
            if ($error = CustomerInvoice::act($invoice, 'confirm')) {
                throw new RuntimeException("Confirm invoice #{$invoice->invoice_id}: {$error}");
            }
        }
        Auth::setUser(SecUser::findOrFail(1));
    }

    // Satu putaran approval: tiap dokumen yang menunggu disetujui oleh pemegang jabatan langkah berjalan.
    private function approve(string $module, callable $decide): void
    {
        $latest = FlowLog::where('module_code', $module)->selectRaw('MAX(flow_log_id) id')->groupBy('doc_id')->pluck('id');
        $users = SecUser::where('activate', true)->get()->groupBy(fn ($u) => (int) $u->currentPositionId());
        foreach (FlowLog::whereIn('flow_log_id', $latest)->whereIn('action', ['SUBMITTED', 'HOLD'])->orderBy('doc_id')->get() as $log) {
            $approver = $users->get((int) $log->actor_position_id)?->first()
                ?? throw new RuntimeException("$module #{$log->doc_id}: no active user holds the approver position {$log->actor_position_id}.");
            Auth::setUser($approver);
            if ($error = $decide((int) $log->doc_id)) {
                throw new RuntimeException("$module #{$log->doc_id} approval by {$approver->login}: {$error}");
            }
        }
    }

    // Dokumen yang lahir lewat aturan aplikasi tercatat data_origin LIVE (bawaan kolom), padahal inquiry asalnya DUMMY:
    // disamakan jadi DUMMY supaya saringan asal data (DataOriginScope) memperlakukan satu rantai dokumen sama.
    private function markDummy(): void
    {
        foreach ($this->originTables as $conn => $tables) {
            foreach ($tables as $t) {
                DB::connection($conn)->table($t)->where('created_at', '>=', $this->startedAt)->where('data_origin', 'LIVE')->update(['data_origin' => 'DUMMY']);
            }
        }
    }

    private function user(string $login): SecUser
    {
        return SecUser::where('login', $login)->where('activate', true)->first() ?? SecUser::findOrFail(1);
    }

    // State layar Pro-Dev (gatherState() workbench.js) untuk susunan grid: blank tidak diputar, disusun kolom x baris
    // pada plano yang muat di meja mesin cetak dan memberi pemakaian bahan terbaik. Blank yang lebih besar dari meja
    // = canvas "Custom" seukuran blank + tepi, isi 1.
    private function gridLayout(array $job, array $press, ?array $diecut, array $planos): array
    {
        [$l, $w, $gap] = [(float) $job['l'], (float) $job['w'], (float) $job['gap']];
        $fit = fn (float $span, float $size) => (int) floor(($span + $gap) / ($size + $gap));
        $best = null;
        foreach ($planos as $p) {
            if ($p['L'] > $press['L'] || $p['W'] > $press['W']) {
                continue;
            }
            $cols = $fit($p['L'] - 2 * self::EDGE_CM['margin'], $l);
            $rows = $fit($p['W'] - self::EDGE_CM['gripper'] - self::EDGE_CM['margin'], $w);
            $eff = $cols * $rows * $l * $w / ($p['L'] * $p['W']) * 100;
            if ($cols * $rows > 0 && (! $best || $eff > $best['eff'])) {
                $best = ['plano' => $p, 'L' => $p['L'], 'W' => $p['W'], 'cols' => $cols, 'rows' => $rows, 'eff' => $eff, 'limit' => 'PRESS'];
            }
        }
        if (! $best) {
            [$L, $W] = [$l + 2 * self::EDGE_CM['margin'], $w + self::EDGE_CM['gripper'] + self::EDGE_CM['margin']];
            $plano = collect($planos)->first(fn ($p) => $p['L'] >= $L && $p['W'] >= $W);
            $best = ['plano' => $plano, 'L' => round($L, 1), 'W' => round($W, 1), 'cols' => 1, 'rows' => 1, 'eff' => $l * $w / ($L * $W) * 100, 'limit' => 'CUSTOM'];
        }

        $objects = [];
        for ($r = 0; $r < $best['rows']; $r++) {
            for ($c = 0; $c < $best['cols']; $c++) {
                $objects[] = ['x' => round(self::EDGE_CM['margin'] + $c * ($l + $gap), 2), 'y' => round(self::EDGE_CM['gripper'] + $r * ($w + $gap), 2),
                    'w' => $l, 'h' => $w, 'r' => 0, 'fx' => false, 'fy' => false];
            }
        }
        $up = $best['cols'] * $best['rows'];

        return [
            'partId' => $job['partId'], 'canvasL' => $best['L'], 'canvasW' => $best['W'], 'canvasLimit' => $best['limit'], 'gapCm' => $gap,
            'upCount' => $up, 'method' => 'AUTO_GRID', 'planoId' => $best['plano']['id'] ?? null, 'efficiencyPct' => round($best['eff'], 2),
            'machine' => ['id' => $press['id'], 'name' => $press['name'], 'bedL' => $press['L'], 'bedW' => $press['W']],
            'knifeW' => round($best['cols'] * ($l + $gap) - $gap, 2), 'knifeH' => round($best['rows'] * ($w + $gap) - $gap, 2),
            'spec' => ['sheetL' => $best['L'], 'sheetW' => $best['W'], 'planoL' => $best['plano']['L'] ?? $best['L'], 'planoW' => $best['plano']['W'] ?? $best['W'],
                'planoName' => $best['plano']['name'] ?? '', 'sheetsPerPlano' => 1, 'printMachineId' => $press['id'], 'diecutMachineId' => $diecut['id'] ?? null,
                'upCount' => $up, 'orderQty' => (int) ($job['orderQty'] ?? 0), 'artworkColors' => $job['artColors'] ?? null],
            'specOverride' => [], 'specReset' => [], 'objects' => $objects,
        ];
    }

    // Inquiry yang sudah lewat langkah pertama (Confirm Sales / batal / bukan New) dihapus beserta anaknya; anak dicari
    // dari FOREIGN KEY ke iqp_inquiry dan iqp_part, jadi tabel anak baru ikut tanpa mengubah daftar di sini.
    private function wipeInquiries(): void
    {
        $sdi = DB::connection('tm2sdi');
        $ids = $sdi->table('iqp_inquiry')->where(fn ($q) => $q->where('inquiry_type', '<>', 'NEW')->orWhere('stat_confirm_sales', 1)->orWhere('stat_cancel', 1))->pluck('id_inquiry');
        // Draft yang tidak bisa menempuh alur (tanpa customer -> tidak bisa ditawarkan; part tanpa ukuran bentangan ->
        // Pro-Dev tidak bisa membuat layout; tanpa pilihan qty -> tidak bisa dihitung) tidak ikut set stage; tetap ada di set "mixed".
        $component = fn ($q) => $q->whereNull('part_kind')->orWhere('part_kind', '<>', 'ASSEMBLY');
        $incomplete = $sdi->table('iqp_inquiry')->whereNotIn('id_inquiry', $ids)->where(fn ($q) => $q
            ->whereNull('customer_id')
            ->orWhereNotIn('id_inquiry', $sdi->table('iqp_tier')->select('inquiry_id'))
            ->orWhereNotIn('id_inquiry', $sdi->table('iqp_part')->where($component)->select('inquiry_id'))
            ->orWhereIn('id_inquiry', $sdi->table('iqp_part')->where($component)->where(fn ($p) => $p->whereNull('flat_w')->orWhere('flat_w', '<=', 0)->orWhereNull('flat_h')->orWhere('flat_h', '<=', 0))->select('inquiry_id')))
            ->pluck('inquiry_no', 'id_inquiry');
        if ($incomplete->isNotEmpty()) {
            $this->out->warn('  Left out of the stage sets (Draft without customer, blank size or qty tier): '.$incomplete->implode(', '));
        }
        $ids = $ids->merge($incomplete->keys());
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
        $by = fn (string $table, string $column) => $sdi->table($table)->selectRaw("$column k, COUNT(*) n")->groupBy('k')->pluck('n', 'k')->map(fn ($v, $k) => "$k: $v")->implode(', ') ?: 'none';
        $this->out->line('  Quotation: '.$by('quo_quotation', 'doc_status').' | Customer Order: '.$by('co_order', 'doc_status').' | CPDEL lines: '.$sdi->table('co_order_delivery')->count()
            .' (posted '.$sdi->table('co_order_delivery')->whereNotNull('posted_at')->count().') | Master Product ACTIVE: '.DB::connection('tm2mst')->table('prod_product')->where('card_status', 'ACTIVE')->count());
        $count = fn (string $conn, string $table, string $column) => DB::connection($conn)->table($table)->selectRaw("$column k, COUNT(*) n")->groupBy('k')->pluck('n', 'k')
            ->map(fn ($v, $k) => "$k: $v")->implode(', ') ?: 'none';
        $this->out->line('  Purchase Order: '.$count('tm2pur', 'po_order', 'status').' | Work Order: '.$count('tm2prd', 'prd_wo', 'wo_status').' | Delivery Order: '.$count('tm2sdi', 'do_delivery', 'status')
            .' | Proof of delivery: '.$sdi->table('do_delivery_pod')->count().' | Customer Invoice: '.$count('tm2acc', 'fin_invoice', "CONCAT(doc_status, ' ', payment_status)"));
        $flow = app(FlowEngineService::class);
        $users = SecUser::where('activate', true)->get();
        foreach (['PR', 'CO', 'PO'] as $module) {
            $this->out->line("  $module waiting per approver: ".($users->map(fn ($u) => [$u->login, $flow->pendingForUser($module, $u->getKey())->count()])
                ->filter(fn ($r) => $r[1])->map(fn ($r) => "$r[0]: $r[1]")->implode(', ') ?: 'none'));
        }
        $downstream = collect(['tm2prd' => ['prd_wo'], 'tm2sdi' => ['do_delivery'], 'tm2acc' => ['fin_invoice'], 'tm2pur' => ['po_order'], 'tm2inv' => ['rm_stock', 'fg_stock']])
            ->flatMap(fn ($tables, $conn) => collect($tables)->mapWithKeys(fn ($t) => ["$t" => DB::connection($conn)->table($t)->count()]))->filter();
        $this->out->line('  Work order / delivery / invoice / PO / stock documents: '.($downstream->map(fn ($v, $k) => "$k: $v")->implode(', ') ?: 'none'));
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

    private function rollBack(): void
    {
        foreach (self::CONNECTIONS as $c) {
            while (DB::connection($c)->transactionLevel() > 0) {
                DB::connection($c)->rollBack();
            }
        }
    }
}
