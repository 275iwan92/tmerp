<?php

use App\Models\Master\ProdMachine;
use App\Models\Master\ProdMachineDowntime;
use App\Models\Prd\MntLog;
use App\Models\Prd\MntMeterReading;
use App\Models\Prd\MntPlan;
use App\Models\Prd\MntRequest;
use App\Models\Prd\MntWo;
use App\Services\Maintenance\MaintenanceService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Contoh data Maintenance untuk UAT (mockup maintenance_v2.jpg) -- HANYA di database dummy ddt*. Di tm2* migration ini tidak
// menulis apa pun: plan & checklist nyata diisi kepala maintenance lewat layar Preventive Plan (keputusan K9).
// Isi plan = praktik umum pabrikan (servis bulanan mesin offset, filter kompresor per jam jalan, servis forklift, inspeksi K3 tahunan).
return new class extends Migration
{
    private function isDummy(): bool
    {
        return str_starts_with(DB::connection('tm2prd')->getDatabaseName(), 'ddt');
    }

    private function machine(string $code): ?int
    {
        return ProdMachine::where('machine_code', $code)->value('machine_id');
    }

    public function up(): void
    {
        if (! $this->isDummy()) {
            return;
        }
        $svc = new MaintenanceService('uat');
        $now = CarbonImmutable::now();
        $tech = 114; // DINUL ROCHMAN — Technical Support & Maintenance (satu-satunya teknisi di data pegawai)

        $plans = [
            ['PM-OFF01-01', 'OFF-01', 'Monthly service — dampening, inking, gripper', 'PREVENTIVE', 'TIME_OR_METER', 1, 'MONTH', 'RUN_HOURS', 250, 'FLOATING', 240, true,
                [['Clean & inspect dampening rollers, check glazing', 'CLEAN'], ['Check ink roller settings (stripe test)', 'MEASURE', 'mm', 3, 4], ['Clean & lubricate grippers', 'LUBRICATE'],
                    ['Check chain & belt tension', 'ADJUST'], ['Replace vacuum pump filter', 'REPLACE'], ['Air pressure at feeder', 'MEASURE', 'bar', 0.6, 0.8, false]],
                [[147, 1], [708, 0.2]], '-28 days', 3412],
            ['PM-OFF02-01', 'OFF-02', 'Monthly service — feeder, inking, gripper', 'PREVENTIVE', 'TIME_OR_METER', 1, 'MONTH', 'RUN_HOURS', 250, 'FLOATING', 240, true,
                [['Clean feeder suckers & check vacuum', 'CLEAN'], ['Check double-sheet detector', 'INSPECT'], ['Clean & lubricate grippers', 'LUBRICATE'], ['Check ink roller settings (stripe test)', 'MEASURE', 'mm', 3, 4]],
                [[55, 1], [708, 0.2]], '-12 days', 5120],
            ['PM-DIE04-01', 'DIE-04', 'Weekly lubrication', 'PREVENTIVE', 'TIME', 1, 'WEEK', null, null, 'FLOATING', 60, true,
                [['Grease main drive points', 'LUBRICATE'], ['Check gripper bars & chains', 'INSPECT'], ['Clean stripping unit', 'CLEAN']],
                [[708, 0.1]], '-6 days', null],
            ['PM-CMP01-01', 'CMP-01', 'Oil + air filter every 2000 run hours', 'PREVENTIVE', 'METER', null, null, 'RUN_HOURS', 2000, 'FLOATING', 90, true,
                [['Drain condensate', 'CLEAN'], ['Replace air filter element', 'REPLACE'], ['Replace compressor oil', 'REPLACE'], ['Outlet pressure', 'MEASURE', 'bar', 7, 8]],
                [[6, 1]], '-150 days', 10050],
            ['PM-CMP01-02', 'CMP-01', 'Annual pressure vessel inspection (K3)', 'INSPECTION', 'TIME', 1, 'YEAR', null, null, 'FIXED', 180, true,
                [['Inspector checks tank, safety valve & gauge', 'INSPECT'], ['File the inspection certificate', 'INSPECT']], [], '-340 days', null],
            ['PM-FKL01-01', 'FKL-01', 'Service every 250 hour-meter', 'PREVENTIVE', 'METER', null, null, 'RUN_HOURS', 250, 'FLOATING', 120, true,
                [['Change engine oil & filter', 'REPLACE'], ['Check hydraulic oil level & leaks', 'INSPECT'], ['Check brakes, tires, chains & forks', 'INSPECT'], ['Grease mast & steering', 'LUBRICATE']],
                [[708, 0.1]], '-40 days', 2210],
            ['PM-FKL01-02', 'FKL-01', 'Annual lifting-equipment inspection (K3)', 'INSPECTION', 'TIME', 1, 'YEAR', null, null, 'FIXED', 240, true,
                [['Inspector load test & checks', 'INSPECT'], ['File the inspection certificate', 'INSPECT']], [], '-351 days', null],
        ];
        $ids = [];
        foreach ($plans as [$code, $mc, $name, $type, $trig, $iv, $iu, $mt, $mi, $mode, $mins, $stop, $tasks, $parts, $last, $meter]) {
            $mid = $this->machine($mc);
            if (! $mid || MntPlan::where('plan_code', $code)->exists()) {
                continue;
            }
            $lastDone = $now->modify($last);
            $p = MntPlan::create(['plan_code' => $code, 'machine_id' => $mid, 'plan_name' => $name, 'wo_type' => $type, 'trigger_mode' => $trig,
                'interval_value' => $iv, 'interval_unit' => $iu, 'meter_type' => $mt, 'meter_interval' => $mi, 'schedule_mode' => $mode, 'lead_days' => 3,
                'est_minutes' => $mins, 'needs_machine_stop' => $stop, 'last_done_date' => $lastDone->toDateString(), 'last_done_meter' => $meter,
                'certificate_valid_until' => $type === 'INSPECTION' ? $lastDone->addYear()->toDateString() : null,
                'activate' => true, 'created_by' => 'uat', 'updated_by' => 'uat']);
            foreach ($tasks as $i => $t) {
                $p->tasks()->create(['seq_no' => $i + 1, 'task_text' => $t[0], 'task_kind' => $t[1], 'measure_uom' => $t[2] ?? null, 'min_value' => $t[3] ?? null,
                    'max_value' => $t[4] ?? null, 'is_required' => $t[5] ?? true, 'created_by' => 'uat']);
            }
            foreach ($parts as [$mat, $qty]) {
                $p->parts()->create(['material_id' => $mat, 'qty' => $qty, 'uom_code' => DB::connection('tm2mst')->table('mat_raw')->where('material_id', $mat)->value('uom_stock'), 'created_by' => 'uat']);
            }
            if ($type === 'INSPECTION') {
                // FIXED: tanggal pasti = ulang tahun inspeksi terakhir.
                $p->update(['next_due_date' => $lastDone->addYear()->toDateString()]);
            } else {
                $p->update($svc->nextDue($p, $lastDone, $meter));
            }
            MntLog::add('PLAN', $p->id_plan, 'Created', 'UAT sample');
            $ids[$code] = $p;
        }

        // Counter meter (hour-meter kompresor/forklift & jam jalan mesin offset) -- CMP-01 sudah lewat 2000 jam -> WO muncul.
        foreach ([['OFF-01', 3662], ['OFF-02', 5301], ['CMP-01', 12060], ['FKL-01', 2395]] as [$mc, $v]) {
            if ($mid = $this->machine($mc)) {
                MntMeterReading::create(['machine_id' => $mid, 'meter_type' => 'RUN_HOURS', 'reading_at' => $now->subDay(), 'reading_value' => $v, 'source' => 'MANUAL', 'created_by' => 'uat']);
            }
        }

        // Riwayat Closed (bahan MTBF/MTTR & kepatuhan PM): servis bulanan OFF-01 tiga bulan terakhir + 2 breakdown OFF-02.
        foreach ([118, 88, 58, 28] as $i => $ago) {
            if (! isset($ids['PM-OFF01-01'])) {
                break;
            }
            $w = $svc->generateFromPlan($ids['PM-OFF01-01']->load(['tasks', 'parts']), true);
            $this->finish($svc, $w, $now->subDays($ago)->setTime(7, 0), $i === 2 ? 3 * 24 * 60 : 0, $tech);
        }
        // Close di atas mencatat "selesai hari ini"; kembalikan ke servis terakhir yang sebenarnya (28 hari lalu, 3.412 jam).
        if (isset($ids['PM-OFF01-01'])) {
            $p = $ids['PM-OFF01-01']->refresh();
            $p->update(['last_done_date' => $now->subDays(28)->toDateString(), 'last_done_meter' => 3412]);
            $p->update($svc->nextDue($p->refresh(), $now->subDays(28), 3412));
        }
        $offTwo = $this->machine('OFF-02');
        foreach ([[70, 'FEEDER', 'MISFEED', 'WEAR', 'REPLACE', 190], [33, 'INKING', 'COLOR_VARIATION', 'MISADJUSTMENT', 'ADJUST', 95]] as [$ago, $comp, $prob, $cause, $act, $mins]) {
            if (! $offTwo) {
                break;
            }
            $req = $svc->createRequest(['machine_id' => $offTwo, 'request_kind' => 'BREAKDOWN', 'machine_stopped' => true, 'symptom' => $prob === 'MISFEED' ? 'Feeder misfeeds, sheets skewed' : 'Colour drifting on unit 2', 'reported_by' => null]);
            $w = $req->wo;
            $w->update(['assigned_to' => $tech, 'component_code' => $comp, 'problem_code' => $prob, 'cause_code' => $cause, 'action_code' => $act,
                'findings' => $act === 'REPLACE' ? 'Suction rubbers worn, replaced' : 'Ink key zero reset and roller pressure adjusted']);
            $svc->start($w->refresh());
            $svc->addLabor($w, $tech, $mins, $now->subDays($ago)->toDateString());
            $svc->done($w->refresh());
            $svc->close($w->refresh());
            $start = $now->subDays($ago)->setTime(9, 15);
            $w->refresh()->update(['planned_start' => $start, 'actual_start' => $start->addMinutes(10), 'actual_end' => $start->addMinutes($mins + 10), 'closed_at' => $start->addMinutes($mins + 60)]);
            $req->update(['request_date' => $start, 'stopped_at' => $start]);
            ProdMachineDowntime::whereKey($w->downtime_id)->update(['start_at' => $start, 'end_at' => $start->addMinutes($mins + 10), 'duration_minutes' => $mins + 10]);
        }

        // Keadaan "hari ini" seperti mockup:
        // 1) OFF-02 breakdown P1 dari laporan operator, sedang dikerjakan (downtime berjalan 2 j 35 m).
        if ($offTwo) {
            $req = $svc->createRequest(['machine_id' => $offTwo, 'request_kind' => 'BREAKDOWN', 'machine_stopped' => true,
                'symptom' => 'Feeder pulls 2 sheets, double-sheet sensor error', 'stopped_at' => $now->subMinutes(155)]);
            $w = $req->wo;
            $w->update(['assigned_to' => $tech, 'component_code' => 'FEEDER', 'description' => 'Feeder double-sheet, stop']);
            $svc->start($w->refresh());
            $w->refresh()->update(['actual_start' => $now->subMinutes(140)]);
            $svc->addPart($w, 55, 1);
            $svc->addLabor($w, $tech, 150);
            $w->parts()->update(['unit_cost' => 1250000]);
            $svc->recost($w->refresh());
            $req->update(['request_date' => $now->subMinutes(155)]);
        }
        // 2) OFF-01 servis bulanan terjadwal (dari plan) -- Draft lalu Confirm.
        if (isset($ids['PM-OFF01-01']) && ($w = $svc->generateFromPlan($ids['PM-OFF01-01']->refresh()->load(['tasks', 'parts']), true))) {
            $w->update(['assigned_to' => $tech, 'planned_start' => $now->addDays(3)->setTime(7, 0), 'planned_end' => $now->addDays(3)->setTime(11, 0)]);
            $svc->confirm($w->refresh());
        }
        // 3) CMP-01 ganti filter & oli (meter lewat 2000 jam) -- menunggu spare part.
        // DIE-04 pelumasan mingguan sengaja belum dibuat: jatuh tempo besok -> tombol Generate PM yang membuatnya saat UAT.
        if (isset($ids['PM-CMP01-01']) && ($w = $svc->generateFromPlan($ids['PM-CMP01-01']->refresh()->load(['tasks', 'parts'])))) {
            $w->update(['assigned_to' => $tech, 'planned_start' => $now->addDay()->setTime(16, 0)]);
            $svc->confirm($w->refresh());
            $svc->start($w->refresh());
            $w->parts()->update(['unit_cost' => 385000]);
            $svc->hold($w->refresh(), 'Air filter element not in stock');
        }
        // 4) FKL-01 inspeksi K3 tahunan -- vendor, terjadwal.
        if (isset($ids['PM-FKL01-02']) && ($w = $svc->generateFromPlan($ids['PM-FKL01-02']->refresh()->load(['tasks', 'parts']), true))) {
            $w->update(['planned_start' => $now->addDays(14)->setTime(8, 0), 'cost_vendor' => 1500000]);
            $svc->confirm($w->refresh());
        }
        // 5) CUT-01 corrective (pisau tumpul) -- Done, menunggu Close kepala maintenance.
        if ($cut = $this->machine('CUT-01')) {
            $req = $svc->createRequest(['machine_id' => $cut, 'request_kind' => 'CORRECTIVE', 'machine_stopped' => false, 'symptom' => 'Blade dull, uneven cut']);
            $w = $svc->acceptRequest($req, $tech, $now->subDays(2)->setTime(13, 0)->toDateTimeString());
            $w->update(['component_code' => 'BLADE_KNIFE', 'problem_code' => 'DULL', 'cause_code' => 'WEAR', 'action_code' => 'SHARPEN', 'findings' => 'Blade sharpened, cutting stick turned']);
            $svc->addTask($w, 'Sharpen blade and turn cutting stick');
            $w->tasks()->update(['result' => 'OK', 'done_by' => 'uat', 'done_at' => $now->subDays(2)]);
            $svc->start($w->refresh());
            $svc->addLabor($w, $tech, 70, $now->subDays(2)->toDateString());
            $svc->done($w->refresh());
            $w->refresh()->update(['actual_start' => $now->subDays(2)->setTime(13, 5), 'actual_end' => $now->subDays(2)->setTime(14, 15)]);
        }
        // 6) Laporan terbuka (menunggu keputusan) + satu yang ditolak.
        if ($die = $this->machine('DIE-04')) {
            $svc->createRequest(['machine_id' => $die, 'request_kind' => 'CORRECTIVE', 'machine_stopped' => false, 'symptom' => 'Squeaking noise from main drive when running above 5000 sheets/h']);
        }
        if ($lam = $this->machine('LAM-03')) {
            $r = $svc->createRequest(['machine_id' => $lam, 'request_kind' => 'IMPROVEMENT', 'machine_stopped' => false, 'symptom' => 'Add a light above the feed table']);
            $svc->rejectRequest($r, 'Covered by the factory lighting project');
        }
    }

    private function finish(MaintenanceService $svc, MntWo $w, CarbonImmutable $start, int $lateMinutes, int $tech): void
    {
        $w->update(['assigned_to' => $tech, 'action_code' => 'CLEAN']);
        $svc->confirm($w->refresh());
        $svc->start($w->refresh());
        $w->tasks()->update(['result' => 'OK', 'done_by' => 'uat', 'done_at' => $start]);
        $svc->addLabor($w, $tech, 210, $start->toDateString());
        $svc->done($w->refresh());
        $svc->close($w->refresh());
        $late = $start->addMinutes($lateMinutes);
        $w->refresh()->update(['planned_start' => $start, 'actual_start' => $late, 'actual_end' => $late->addMinutes(230), 'closed_at' => $late->addHours(5)]);
        ProdMachineDowntime::whereKey($w->downtime_id)->update(['start_at' => $late, 'end_at' => $late->addMinutes(230), 'duration_minutes' => 230]);
    }

    public function down(): void
    {
        if (! $this->isDummy()) {
            return;
        }
        $down = MntWo::whereNotNull('downtime_id')->pluck('downtime_id')->merge(MntRequest::whereNotNull('downtime_id')->pluck('downtime_id'));
        ProdMachineDowntime::whereIn('downtime_id', $down)->delete();
        foreach (['mnt_log', 'mnt_meter_reading', 'mnt_wo_labor', 'mnt_wo_part', 'mnt_wo_task', 'mnt_wo', 'mnt_request', 'mnt_plan_part', 'mnt_plan_task', 'mnt_plan'] as $t) {
            DB::connection('tm2prd')->table($t)->delete();
        }
        ProdMachine::whereIn('machine_code', ['OFF-01', 'OFF-02', 'CMP-01', 'FKL-01', 'CUT-01', 'DIE-04'])->whereIn('machine_status', ['MAINTENANCE', 'BREAKDOWN'])->update(['machine_status' => 'READY']);
    }
};
