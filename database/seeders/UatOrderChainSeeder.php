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
use RuntimeException;

// Pengganti dummy CO2600001 (user 2026-10-01, pilihan A: "data dummy salah selalu hapus dan create baru").
// CO2600001 = CO uji manual (quotation manual tanpa inquiry -> melanggar aturan A.1) yang menjadi jangkar seluruh dummy
// produksi (WO berjalan/selesai/breakdown). Seeder ini menghapusnya lalu membangun rantai baru LEWAT ALUR RESMI:
// Inquiry (costing published + routing) -> Quotation -> WIN -> CO Confirmed (2 approval) -> CPDEL -> Post to Production
// -> WO (+ contoh status produksi) -> DO jalan pintas (laporan Shipped Without Production Output).
// Hanya ddt*: php artisan db:seed --class=UatOrderChainSeeder --env=mockup --force, lalu ddt:baseline save.
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
        $sdi = DB::connection('tm2sdi');
        if (! str_starts_with($sdi->getDatabaseName(), 'ddt')) {
            throw new RuntimeException('UatOrderChainSeeder hanya untuk database dummy ddt* (--env=mockup).');
        }
        if (CoOrder::where('notes', self::MARK)->exists()) {
            $this->command?->warn('Rantai [UAT-CHAIN] sudah ada -- tidak dibuat dobel.');

            return;
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

        $this->dropOldChain('CO2600001');
        $this->addRouting();

        // Rantai 1: Folding Box Kosmetik (IQ99DUM001) -> 1 jadwal kirim, di-Post, WO sedang berjalan.
        [$co1, $d1] = $this->integrated('IQ99DUM001', '2026-10-15');
        $this->post($d1);
        // Rantai 2: Karton Box Susu (IQ99DUM003) -> jadwal kirim dipecah 2 tanggal; baris 1 di-Post (WO breakdown),
        // baris 2 dibiarkan PLANNED supaya tombol Post to Production bisa dicoba sendiri.
        [$co2, $d2] = $this->integrated('IQ99DUM003', '2026-10-22');
        $this->check(Cpdel::split($d2->id_delivery, (string) floor($d2->ship_qty / 2), '2026-10-29'), 'split CPDEL');
        $this->post($d2->refresh());
        // Rantai 3: CO INSTANT (direct, aturan A.2) dari Master Product hasil WIN rantai 1 -> Post -> DO jalan pintas.
        [$co3, $d3] = $this->instant($co1, '2026-10-08');
        $this->post($d3);
        $do = DeliveryOrder::create((int) $co3->customer_id);
        $this->check(DeliveryOrder::pull($do, [$d3->id_delivery]), 'tarik DO');
        [$ok, $msg] = DeliveryOrder::act($do->refresh(), 'confirm');
        if (! $ok && $do->refresh()->status !== 'CONFIRMED') {
            throw new RuntimeException('DO confirm: '.$msg);
        }
        $do->update(['notes' => self::MARK]);

        $this->productionSamples($d1, $d2);

        $this->command?->info("Rantai baru: {$co1->co_no} (Posted, WO berjalan), {$co2->co_no} (1 Posted + 1 PLANNED, WO breakdown), "
            ."{$co3->co_no} INSTANT (Posted, DO {$do->do_no} tanpa hasil produksi).");
    }

    // Hapus CO uji beserta quotation manual, DO, WO + riwayat produksinya (dummy lama tanpa alur resmi).
    private function dropOldChain(string $coNo): void
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
        $prd->table('mnt_request')->whereIn('breakdown_event_id', $events)->update(['breakdown_event_id' => null]);
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
        $sdi->table('do_delivery')->whereIn('id_delivery', $dos)->whereNotExists(fn ($q) => $q->from('do_line')->whereColumn('do_line.delivery_id', 'do_delivery.id_delivery'))->delete();

        FlowLog::where('module_code', 'CO')->where('doc_id', $o->id_order)->delete();
        $sdi->table('iqp_inquiry')->where('co_id', $o->id_order)->update(['co_id' => null, 'sub_state' => 'SUBMITTED', 'updated_by' => 'system']);
        $sdi->table('co_order_delivery')->where('order_id', $o->id_order)->delete();
        $sdi->table('co_order_external_cost')->where('order_id', $o->id_order)->delete();
        $sdi->table('co_order_item')->where('order_id', $o->id_order)->delete();
        $sdi->table('co_order')->where('id_order', $o->id_order)->delete();
        if ($o->quotation_id && ! $sdi->table('co_order')->where('quotation_id', $o->quotation_id)->exists()) {
            foreach (['quo_quotation_item', 'quo_quotation_cc', 'quo_activity'] as $t) {
                $sdi->table($t)->where('quotation_id', $o->quotation_id)->delete();
            }
            $sdi->table('quo_quotation')->where('id_quotation', $o->quotation_id)->delete();
        }
        $this->command?->info("{$coNo} dihapus: ".count($wos).' WO, '.count($ops).' operasi, '.count($events).' breakdown, '.count($dos).' DO.');
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

    // Inquiry siap tawar -> Quotation -> Confirm -> WIN -> CO -> due date -> Confirm -> approval. Balikan [CO, jadwal kirim pertama].
    private function integrated(string $inquiryNo, string $due): array
    {
        $inq = IqpInquiry::where('inquiry_no', $inquiryNo)->firstOrFail();
        $q = Quotation::create([$inq->id_inquiry]);
        $q->update(['notes' => self::MARK]);
        Quotation::confirm($q->refresh());
        $coId = Quotation::win(QuoQuotation::with('items.inquiry')->findOrFail($q->id_quotation)) ?? throw new RuntimeException("WIN {$inquiryNo} gagal");
        $co = CoOrder::with('items')->findOrFail($coId);
        $co->update(['notes' => self::MARK, 'customer_po_no' => 'PO-'.substr($inquiryNo, -3).'-UAT']);
        CustomerOrder::save($co->refresh(), ['items' => $co->items->where('line_kind', 'PRODUCT')->mapWithKeys(fn ($i) => [$i->id_item => ['due_date' => $due]])->all()]);

        return [$co, $this->confirm($co)];
    }

    // CO INSTANT dari Master Product rantai sebelumnya (pesanan kilat Direksi, aturan A.2).
    private function instant(CoOrder $from, string $due): array
    {
        $product = $from->items()->whereNotNull('product_id')->value('product_id') ?? IqpInquiry::where('co_id', $from->id_order)->value('product_id')
            ?? throw new RuntimeException('Master Product rantai 1 tidak ditemukan');
        $co = CustomerOrder::createInstant((int) $from->customer_id);
        $co->update(['notes' => self::MARK.' Pesanan kilat Direksi (CO direct, tanpa Quotation).']);
        CustomerOrder::addProduct($co, (int) $product);
        $item = $co->items()->firstOrFail();
        CustomerOrder::save($co->refresh(), ['items' => [$item->id_item => ['qty' => '500', 'unit_price' => (string) ($from->items()->value('unit_price') ?: 1500), 'due_date' => $due]]]);

        return [$co, $this->confirm($co)];
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
