<?php

namespace Database\Seeders;

use App\Models\Master\FlowLog;
use App\Models\Prd\PrdCrewAssignment;
use App\Models\Prd\PrdWo;
use App\Models\Prd\PrdWoOperation;
use App\Models\Sdi\CoOrder;
use App\Models\Sdi\CoOrderDelivery;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\QuoQuotation;
use App\Models\Sys\SecUser;
use App\Support\V2\Data\Cpdel;
use App\Support\V2\Data\CustomerOrder;
use App\Support\V2\Data\DeliveryOrder;
use App\Support\V2\Data\Quotation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

// Dummy rantai order yang BENAR (user 2026-10-01: "data dummy salah selalu hapus dan create baru").
// Menghapus dummy salah: CO2600001 (CO uji manual + quotation manual, melanggar aturan A.1) beserta WO/DO-nya, CO INSTANT
// CO2600004/005 (Master Product tanpa inquiry ber-harga -> tidak bisa di-Post), dan CO lama yang masih memuat lebih dari
// satu pilihan qty per product (aturan: customer memilih SATU qty di CO). Lalu membangun ulang LEWAT ALUR RESMI:
// Inquiry (costing published + routing) -> Quotation -> WIN -> pilih satu qty -> CO Confirmed (2 approval) -> CPDEL ->
// Post to Production -> WO (+ contoh status produksi) -> DO jalan pintas (laporan Shipped Without Production Output).
// Boleh diulang (rantai [UAT-CHAIN] sebelumnya dihapus dulu):
//   php artisan db:seed --class=UatOrderChainSeeder --env=mockup --force   lalu   php artisan ddt:baseline save --env=mockup
class UatOrderChainSeeder extends Seeder
{
    public const MARK = '[UAT-CHAIN] dummy rantai Inquiry-Quotation-CO-Produksi 2026-10-01.';

    // Routing dummy (iqp_part_process) untuk inquiry dummy siap tawar yang belum punya langkah proses:
    // [step, process_id, nama, kode mesin (prod_process_machine rank 1) atau null = manual].
    private const ROUTING = [
        [10, 1, 'CETAK', 'OFF-02'],
        [20, 2, 'LAMINASI', 'LAM-04'],
        [30, 3, 'PLONG', 'DIE-04'],
        [40, 4, 'LEM MESIN', 'FGL-04'],
        [50, 11, 'QC PACK', null],
    ];

    private const ROUTED = ['IQ99DUM001', 'IQ99DUM002', 'IQ99DUM003', 'IQ99DUM004', 'IQ99DUM005', 'IQ99DUM006', 'IQ99UAT012'];

    private SecUser $admin;

    // true = jalankan lalu batalkan semua (uji coba tanpa mengubah data).
    public bool $dryRun = false;

    public function run(): void
    {
        // HANYA ddt*. Dummy CO2600001 + WO2600225..262 di tm2* adalah FIXTURE test regresi produksi/CO (21 test menunjuk
        // order_id 2, inquiry 5, nomor WO itu): menghapusnya di tm2 mematahkan test (terjadi & dipulihkan 2026-10-01).
        if (! str_starts_with(DB::connection('tm2sdi')->getDatabaseName(), 'ddt')) {
            throw new RuntimeException('UatOrderChainSeeder hanya untuk database dummy ddt* (--env=mockup); dummy tm2* = fixture test.');
        }
        $this->admin = SecUser::where('login', 'johiwan')->firstOrFail();
        Auth::login($this->admin);
        // Semua koneksi dalam satu transaksi: gagal di tengah = tidak ada data setengah jadi.
        $conns = array_values(array_filter(array_keys(config('database.connections')), fn ($c) => str_starts_with($c, 'tm2') && $c !== 'tm2crp'));
        foreach ($conns as $c) {
            DB::connection($c)->beginTransaction();
        }
        try {
            $this->build();
        } catch (\Throwable $e) {
            foreach ($conns as $c) {
                while (DB::connection($c)->transactionLevel() > 0) {
                    DB::connection($c)->rollBack();
                }
            }
            throw $e;
        }
        foreach ($conns as $c) {
            while (DB::connection($c)->transactionLevel() > 0) {
                $this->dryRun ? DB::connection($c)->rollBack() : DB::connection($c)->commit();
            }
        }
        Auth::logout();
    }

    private function build(): void
    {
        // 1. Hapus rantai seeder ini sebelumnya (inquiry kembali siap tawar, Master Product hasil WIN ikut dihapus).
        foreach (CoOrder::where('notes', 'like', '[UAT-CHAIN]%')->orderByDesc('id_order')->pluck('co_no') as $no) {
            $this->dropChain($no, 'PRICE_CALCULATED', true);
        }
        // 2. Hapus dummy salah.
        $this->dropChain('CO2600001', 'SUBMITTED', false);
        foreach (['CO2600004', 'CO2600005'] as $no) {
            $this->dropChain($no, 'SUBMITTED', false);
        }
        // CO lama dari quotation yang masih memuat >1 pilihan qty per product dan sudah lewat DRAFT (mis. [SEED-WIN] di tm2).
        foreach (CoOrder::with('items')->where('doc_status', '<>', 'DRAFT')->get()->filter(fn ($o) => $o->itemsWithUnpickedQty()->isNotEmpty())->pluck('co_no') as $no) {
            $this->dropChain($no, 'PRICE_CALCULATED', true, keepQuotation: true);
        }
        $this->addRouting();

        // Rantai 1: Folding Box Kosmetik (IQ99DUM001) -> 1 jadwal kirim, di-Post, WO sedang berjalan.
        [$co1, $d1] = $this->integrated('IQ99DUM001', '2026-10-15');
        $this->post($d1);
        // Rantai 2: Karton Box Susu (IQ99DUM003) -> jadwal kirim dipecah 2 tanggal; baris 1 di-Post (WO breakdown),
        // baris 2 dibiarkan PLANNED supaya tombol Post to Production bisa dicoba sendiri.
        [$co2, $d2] = $this->integrated('IQ99DUM003', '2026-10-22');
        $this->check(Cpdel::split($d2->id_delivery, (string) floor($d2->ship_qty / 2), '2026-10-20'), 'split CPDEL');
        $this->post($d2->refresh());
        // Rantai 3: CO INSTANT (direct, aturan A.2) dari Master Product hasil WIN rantai 1 -> Post -> DO jalan pintas.
        $co3 = $this->instant($co1, '2026-10-08', 500, 'Pesanan kilat Direksi (CO direct, tanpa Quotation).');
        $d3 = $this->confirm($co3);
        $this->post($d3);
        $do = DeliveryOrder::create((int) $co3->customer_id);
        $this->check(DeliveryOrder::pull($do, [$d3->id_delivery]), 'tarik DO');
        [$ok, $msg] = DeliveryOrder::act($do->refresh(), 'confirm');
        if (! $ok && $do->refresh()->status !== 'CONFIRMED') {
            throw new RuntimeException('DO confirm: '.$msg);
        }
        $do->update(['notes' => self::MARK]);
        // Rantai 4: CO INSTANT menunggu approval (contoh untuk Approval Center).
        $co4 = $this->instant($co2, '2026-10-27', 1000, 'Order ulang lewat telepon, menunggu approval.');
        $this->check(CustomerOrder::confirm($co4->refresh()), "confirm {$co4->co_no}");

        $this->productionSamples($d1, $d2);

        $this->command?->info(DB::connection('tm2sdi')->getDatabaseName().": {$co1->co_no} (Posted, WO berjalan), {$co2->co_no} (1 Posted + 1 PLANNED, WO breakdown), "
            ."{$co3->co_no} INSTANT (Posted, DO {$do->do_no} tanpa hasil produksi), {$co4->co_no} INSTANT (menunggu approval).");
    }

    // Hapus satu CO beserta DO, WO + riwayat produksi, log approval, (quotation), dan kembalikan inquiry-nya.
    // $dropProduct = Master Product hasil WIN ikut dihapus (inquiry kembali belum punya product).
    private function dropChain(string $coNo, string $inquiryState, bool $dropProduct, bool $keepQuotation = false): void
    {
        $sdi = DB::connection('tm2sdi');
        $prd = DB::connection('tm2prd');
        $o = $sdi->table('co_order')->where('co_no', $coNo)->first();
        if (! $o) {
            return;
        }
        $deliveries = $sdi->table('co_order_delivery')->where('order_id', $o->id_order)->pluck('id_delivery');
        $wos = $prd->table('prd_wo')->where('source_co_id', $o->id_order)->orWhereIn('source_delivery_id', $deliveries)->pluck('wo_id');
        $ops = $prd->table('prd_wo_operation')->whereIn('wo_id', $wos)->pluck('operation_id');
        $events = $prd->table('prd_breakdown_event')->whereIn('operation_id', $ops)->pluck('event_id');
        $prd->table('prd_breakdown_event')->whereIn('event_id', $events)->delete();
        $prd->table('prd_crew_assignment')->whereIn('operation_id', $ops)->delete();
        $outputs = $prd->table('prd_wo_operation_output')->whereIn('operation_id', $ops)->pluck('output_id');
        $prd->table('prd_wo_operation_output_defect')->whereIn('output_id', $outputs)->delete();
        $prd->table('prd_wo_operation_output')->whereIn('output_id', $outputs)->delete();
        $prd->table('prd_wo_operation')->whereIn('batched_with_operation_id', $ops)->update(['batched_with_operation_id' => null]);
        $prd->table('prd_wo_operation')->whereIn('operation_id', $ops)->delete();
        $prd->table('prd_wo_material_substitution')->whereIn('wo_id', $wos)->delete();
        $prd->table('prd_wo_material')->whereIn('wo_id', $wos)->delete();
        $prd->table('prd_wo')->whereIn('wo_id', $wos)->delete();

        $dos = $sdi->table('do_line')->where('co_order_id', $o->id_order)->pluck('delivery_id')->unique();
        $sdi->table('do_line')->where('co_order_id', $o->id_order)->delete();
        $emptyDos = $sdi->table('do_delivery')->whereIn('id_delivery', $dos)
            ->whereNotExists(fn ($q) => $q->from('do_line')->whereColumn('do_line.delivery_id', 'do_delivery.id_delivery'))->pluck('id_delivery');
        if (Schema::connection('tm2sdi')->hasTable('do_delivery_pod')) {
            $sdi->table('do_delivery_pod')->whereIn('delivery_id', $emptyDos)->delete();
        }
        $sdi->table('do_delivery')->whereIn('id_delivery', $emptyDos)->delete();

        FlowLog::where('module_code', 'CO')->where('doc_id', $o->id_order)->delete();
        $inquiries = $sdi->table('iqp_inquiry')->where('co_id', $o->id_order)->get(['id_inquiry', 'product_id']);
        $sdi->table('co_order_delivery')->where('order_id', $o->id_order)->delete();
        $sdi->table('co_order_external_cost')->where('order_id', $o->id_order)->delete();
        $sdi->table('co_order_item')->where('order_id', $o->id_order)->delete();
        $sdi->table('co_order')->where('id_order', $o->id_order)->delete();

        foreach ($inquiries as $inq) {
            $sdi->table('iqp_inquiry')->where('id_inquiry', $inq->id_inquiry)->update(['co_id' => null, 'sub_state' => $inquiryState, 'updated_by' => 'system']
                + ($dropProduct ? ['product_id' => null] : []));
            if ($dropProduct && $inq->product_id) {
                $this->dropProduct((int) $inq->product_id, (int) $inq->id_inquiry);
            }
        }
        if (! $keepQuotation && $o->quotation_id && ! $sdi->table('co_order')->where('quotation_id', $o->quotation_id)->exists()) {
            foreach (['quo_quotation_item', 'quo_quotation_cc', 'quo_activity'] as $t) {
                $sdi->table($t)->where('quotation_id', $o->quotation_id)->delete();
            }
            $sdi->table('quo_quotation')->where('id_quotation', $o->quotation_id)->delete();
        }
        $this->command?->info("{$coNo} dihapus: ".count($wos).' WO, '.count($ops).' operasi, '.count($events).' breakdown, '.count($dos).' DO.');
    }

    // Master Product hasil WIN (ProductMasterSync) -- hanya bila tidak dipakai CO lain.
    private function dropProduct(int $productId, int $inquiryId): void
    {
        $sdi = DB::connection('tm2sdi');
        $mst = DB::connection('tm2mst');
        if ($sdi->table('co_order_item')->where('product_id', $productId)->exists() || $sdi->table('iqp_inquiry')->where('product_id', $productId)->exists()) {
            return;
        }
        $parts = $mst->table('prod_part')->where('product_id', $productId)->pluck('id_part');
        $sdi->table('iqp_part')->where('inquiry_id', $inquiryId)->update(['prod_part_id' => null]);
        $mst->table('prod_product_acc')->where('product_id', $productId)->delete();
        foreach (['prod_part_material', 'prod_part_ink', 'prod_part_process', 'prod_part_finishing', 'prod_part_artwork', 'prod_part_attachment'] as $t) {
            if (Schema::connection('tm2mst')->hasTable($t)) {
                $mst->table($t)->whereIn('part_id', $parts)->delete();
            }
        }
        $mst->table('prod_part')->whereIn('id_part', $parts)->update(['parent_part_id' => null]);
        $mst->table('prod_part')->whereIn('id_part', $parts)->delete();
        $mst->table('prod_product')->where('id_product', $productId)->delete();
    }

    private function addRouting(): void
    {
        $sdi = DB::connection('tm2sdi');
        $machines = DB::connection('tm2mst')->table('prod_machine')->whereIn('machine_code', array_filter(array_column(self::ROUTING, 3)))->pluck('machine_id', 'machine_code');
        $parts = $sdi->table('iqp_part as p')->join('iqp_inquiry as i', 'i.id_inquiry', '=', 'p.inquiry_id')->whereIn('i.inquiry_no', self::ROUTED)
            ->whereNotExists(fn ($q) => $q->from('iqp_part_process')->whereColumn('iqp_part_process.part_id', 'p.id_part'))->pluck('p.id_part');
        $now = now();
        foreach ($parts as $part) {
            foreach (self::ROUTING as [$seq, $process, $name, $machine]) {
                $sdi->table('iqp_part_process')->insert(['part_id' => $part, 'branch_code' => 'MAIN', 'step_seq' => $seq, 'flow_type' => 'LINEAR',
                    'process_id' => $process, 'process_name' => $name, 'machine_id' => $machine ? ($machines[$machine] ?? null) : null, 'qty_factor' => 1,
                    'source' => 'MANUAL', 'note' => self::MARK, 'created_at' => $now, 'updated_at' => $now, 'created_by' => 'uat', 'updated_by' => 'uat', 'data_origin' => 'DUMMY']);
            }
        }
        $this->command?->info('Routing dummy ditambahkan ke '.count($parts).' part inquiry.');
    }

    // Inquiry siap tawar -> Quotation -> Confirm -> WIN -> pilih SATU qty -> due date -> Confirm -> approval.
    // Balikan [CO, jadwal kirim pertama]. Quotation terbit yang sudah ada (tanpa CO) untuk inquiry itu dipakai ulang.
    private function integrated(string $inquiryNo, string $due): array
    {
        $inq = IqpInquiry::where('inquiry_no', $inquiryNo)->firstOrFail();
        $q = QuoQuotation::where('doc_status', 'PUBLISHED')->whereHas('items', fn ($x) => $x->where('inquiry_id', $inq->id_inquiry))
            ->whereNotIn('id_quotation', CoOrder::whereNotNull('quotation_id')->select('quotation_id'))->first();
        if (! $q) {
            $q = Quotation::create([$inq->id_inquiry]);
            $q->update(['notes' => self::MARK]);
            Quotation::confirm($q->refresh());
        }
        $coId = Quotation::win(QuoQuotation::with('items.inquiry')->findOrFail($q->id_quotation)) ?? throw new RuntimeException("WIN {$inquiryNo} gagal");
        $co = CoOrder::with('items')->findOrFail($coId);
        $co->update(['notes' => self::MARK, 'customer_po_no' => 'PO-'.substr($inquiryNo, -3).'-UAT']);
        // Customer memilih satu qty (pilihan pertama); pilihan qty lain dari quotation dihapus dari CO.
        $keep = $co->items->where('line_kind', 'PRODUCT')->first();
        CustomerOrder::pickQty($co->refresh(), (int) $keep->id_item);
        CustomerOrder::save($co->refresh(), ['items' => [$keep->id_item => ['due_date' => $due]]]);

        return [$co, $this->confirm($co)];
    }

    // CO INSTANT (DRAFT, siap Confirm) dari Master Product CO lain (pesanan langsung, aturan A.2).
    private function instant(CoOrder $from, string $due, int $qty, string $note): CoOrder
    {
        $product = $from->items()->whereNotNull('product_id')->value('product_id') ?? throw new RuntimeException("Master Product {$from->co_no} tidak ditemukan");
        $co = CustomerOrder::createInstant((int) $from->customer_id);
        $co->update(['notes' => self::MARK.' '.$note]);
        CustomerOrder::addProduct($co, (int) $product);
        $item = $co->items()->firstOrFail();
        CustomerOrder::save($co->refresh(), ['items' => [$item->id_item => ['qty' => (string) $qty, 'unit_price' => (string) ((float) $from->items()->value('unit_price') ?: 1500), 'due_date' => $due]]]);

        return $co;
    }

    private function confirm(CoOrder $co): CoOrderDelivery
    {
        $this->check(CustomerOrder::confirm($co->refresh()), "confirm {$co->co_no}");
        for ($level = 1; $level <= 4 && $co->refresh()->doc_status === 'SUBMITTED'; $level++) {
            $log = FlowLog::where('module_code', 'CO')->where('doc_id', $co->id_order)->orderByDesc('flow_log_id')->first();
            $approver = SecUser::where('activate', true)->get()->first(fn ($u) => $log && $u->currentPositionId() == $log->actor_position_id)
                ?? throw new RuntimeException("Approver level {$level} {$co->co_no} tidak ditemukan");
            Auth::login($approver);
            $this->check(CustomerOrder::decide($co->refresh(), 'approve', '') ?: null, "approval {$co->co_no}");
            Auth::login($this->admin);
        }
        if ($co->refresh()->doc_status !== 'CONFIRMED') {
            throw new RuntimeException("{$co->co_no} belum CONFIRMED ({$co->doc_status})");
        }

        return CoOrderDelivery::where('order_id', $co->id_order)->orderBy('id_delivery')->firstOrFail();
    }

    private function post(CoOrderDelivery $d): void
    {
        $this->check(Cpdel::post($d->id_delivery), "post #{$d->id_delivery}");
    }

    // Contoh status produksi untuk Scheduling Board / Kanban / Monitoring (pengganti WO2600225..231 lama).
    private function productionSamples(CoOrderDelivery $d1, CoOrderDelivery $d2): void
    {
        $day = Carbon::parse('2026-10-01');
        $ops = fn (CoOrderDelivery $d) => PrdWoOperation::whereIn('wo_id', PrdWo::where('source_delivery_id', $d->id_delivery)->pluck('wo_id'))->orderBy('seq_no')->get();
        $slot = function (PrdWoOperation $op, int $from, int $to, string $status, ?float $cr = null) use ($day) {
            $op->update(['target_start' => $day->copy()->setTime($from, 0), 'target_finish' => $day->copy()->setTime($to, 0), 'task_status' => $status,
                'critical_ratio' => $cr, 'updated_by' => 'uat']);
        };

        // Rantai 1: Cetak selesai (QC lolos, hasil 100%), Laminasi sedang berjalan dengan crew, sisanya terjadwal besok.
        $a = $ops($d1);
        if ($a->count() >= 3) {
            $slot($a[0], 8, 11, 'QC_PASSED', 2.1);
            DB::connection('tm2prd')->table('prd_wo_operation_output')->insert(['operation_id' => $a[0]->operation_id, 'qty_good' => $a[0]->qty,
                'recorded_by' => 'uat', 'recorded_at' => $day->copy()->setTime(11, 0), 'created_at' => now(), 'notes' => self::MARK, 'data_origin' => 'DUMMY']);
            $slot($a[1], 11, 14, 'RUNNING', 1.8);
            PrdCrewAssignment::create(['operation_id' => $a[1]->operation_id, 'employee_id' => 363, 'crew_role' => 'OPERATOR_UTAMA', 'created_by' => 'uat']);
            PrdCrewAssignment::create(['operation_id' => $a[1]->operation_id, 'employee_id' => 159, 'crew_role' => 'CO_OPERATOR', 'created_by' => 'uat']);
            $day2 = $day->copy()->addDay();
            foreach ($a->slice(2)->values() as $i => $op) {
                $op->update(['target_start' => $day2->copy()->setTime(8 + $i * 2, 0), 'target_finish' => $day2->copy()->setTime(10 + $i * 2, 0), 'task_status' => 'ASSIGNED', 'updated_by' => 'uat']);
            }
            PrdWo::where('source_delivery_id', $d1->id_delivery)->update(['wo_status' => 'IN_PROGRESS', 'schedule_layer' => 'COMMITTED', 'updated_by' => 'uat']);
        }

        // Rantai 2: Cetak lolos QC, Plong rusak di tengah jalan -> WO ditahan (breakdown menunggu keputusan reroute/subkon).
        $b = $ops($d2);
        if ($b->count() >= 3) {
            $slot($b[0], 8, 10, 'QC_PASSED', 2.0);
            DB::connection('tm2prd')->table('prd_wo_operation_output')->insert(['operation_id' => $b[0]->operation_id, 'qty_good' => $b[0]->qty,
                'recorded_by' => 'uat', 'recorded_at' => $day->copy()->setTime(10, 0), 'created_at' => now(), 'notes' => self::MARK, 'data_origin' => 'DUMMY']);
            $slot($b[2], 10, 13, 'BREAKDOWN', 0.6);
            PrdCrewAssignment::create(['operation_id' => $b[2]->operation_id, 'employee_id' => 137, 'crew_role' => 'OPERATOR_UTAMA', 'created_by' => 'uat']);
            DB::connection('tm2prd')->table('prd_breakdown_event')->insert(['operation_id' => $b[2]->operation_id, 'machine_id' => $b[2]->machine_id,
                'reported_by' => 'uat', 'reported_at' => $day->copy()->setTime(10, 30), 'downtime_estimate_minutes' => 180, 'created_at' => now(), 'data_origin' => 'DUMMY']);
            PrdWo::where('source_delivery_id', $d2->id_delivery)->update(['wo_status' => 'ON_HOLD', 'schedule_layer' => 'COMMITTED', 'updated_by' => 'uat']);
        }
    }

    private function check(?string $error, string $what): void
    {
        if ($error) {
            throw new RuntimeException("{$what}: {$error}");
        }
    }
}
