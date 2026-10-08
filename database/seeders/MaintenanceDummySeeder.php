<?php

namespace Database\Seeders;

use App\Models\Mnt\MntAsset;
use App\Models\Mnt\MntDocument;
use App\Models\Mnt\MntPlan;
use App\Models\Mnt\MntWatchPoint;
use App\Services\Maintenance\AssetProfileService;
use App\Services\Maintenance\MeterService;
use App\Services\Maintenance\PlanService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// Data contoh Maintenance di database dummy ddtmnt (php artisan db:seed --class=MaintenanceDummySeeder --env=mockup):
// mesin dari Master Mesin menjadi aset, draf SOP / WI dari resources/data/maintenance/sop_wi.json, dan tiga aset contoh
// non-mesin. Tidak ada batas normal atau spare part karangan. Menolak jalan di luar ddtmnt; aman diulang.
class MaintenanceDummySeeder extends Seeder
{
    public function run(): void
    {
        if (DB::connection('tm2mnt')->getDatabaseName() !== 'ddtmnt') {
            throw new RuntimeException('MaintenanceDummySeeder only runs on ddtmnt (use --env=mockup).');
        }

        $svc = new AssetProfileService;
        $r = $svc->syncMachines();
        $this->command?->info("Machines -> assets: {$r['created']} created, {$r['updated']} updated.");

        $this->exampleAssets();
        $this->sopAndWorkInstructions();
        $this->exampleWatchPoints();
        $this->examplePlans();
    }

    // Plan contoh (struktur saja, ditandai Example): bulanan mesin offset dari WI bulanan, inspeksi tahunan kompresor, AC tiga bulanan,
    // servis kendaraan berbasis meter. Interval dan angka hanya contoh di ddtmnt; isi nyata diketik kepala maintenance dari manual pabrikan.
    private function examplePlans(): void
    {
        if (MntPlan::query()->exists()) {
            return;
        }
        $data = json_decode((string) file_get_contents(resource_path('data/maintenance/sop_wi.json')), true, 512, JSON_THROW_ON_ERROR);
        $monthly = collect($data['documents'])->firstWhere('doc_no', 'WI-MNT-OFF-03')['steps'] ?? [];
        $tasks = array_slice($monthly, 4, max(0, count($monthly) - 6));
        $asset = fn (string $code) => MntAsset::where('asset_code', $code)->firstOrFail();
        $make = function (string $code, string $assetCode, string $name, array $attrs, array $taskTexts) use ($asset) {
            $plan = MntPlan::create(['plan_code' => $code, 'asset_id' => $asset($assetCode)->id_asset, 'plan_name' => $name, 'activate' => true,
                'notes' => 'Example plan (dummy). Real intervals and tasks come from the manufacturer manual.', 'created_by' => 'seeder', 'updated_by' => 'seeder'] + $attrs);
            foreach (array_values($taskTexts) as $i => $text) {
                $plan->tasks()->create(['seq_no' => $i + 1, 'task_text' => $text, 'task_kind' => 'INSPECT', 'is_required' => true, 'created_by' => 'seeder', 'updated_by' => 'seeder']);
            }

            return $plan;
        };
        $make('PM-OFF01-01', 'OFF-01', 'Monthly care (example)', ['order_type' => 'PREVENTIVE', 'trigger_mode' => 'TIME', 'interval_value' => 1, 'interval_unit' => 'MONTH',
            'schedule_mode' => 'FLOATING', 'lead_days' => 3, 'est_minutes' => 240, 'needs_stop' => true, 'last_done_date' => today()->subDays(28), 'next_due_date' => today()->addDays(2)], $tasks);
        $make('PM-CMP01-INS', 'CMP-01', 'Annual pressure equipment inspection (example)', ['order_type' => 'INSPECTION', 'trigger_mode' => 'TIME', 'interval_value' => 1, 'interval_unit' => 'YEAR',
            'schedule_mode' => 'FIXED', 'lead_days' => 14, 'est_minutes' => 120, 'needs_stop' => true, 'next_due_date' => today()->addMonths(2)], ['Check that the legal inspection certificate of the pressure vessel is valid.']);
        $make('PM-AC01-01', 'AC-OFFICE-01', 'Filter cleaning (example)', ['order_type' => 'PREVENTIVE', 'trigger_mode' => 'TIME', 'interval_value' => 3, 'interval_unit' => 'MONTH',
            'schedule_mode' => 'FLOATING', 'lead_days' => 7, 'est_minutes' => 60, 'needs_stop' => false, 'last_done_date' => today()->subMonths(3)->addDays(5), 'next_due_date' => today()->addDays(5)],
            ['Clean the indoor unit filters.', 'Check that the drain is clear.', 'Check that the unit cools normally.']);
        $svc = new MeterService('seeder');
        $veh = $asset('VEH-01');
        $svc->record($veh, 'ODOMETER_KM', 41250, CarbonImmutable::now()->subDays(20), 'MANUAL', 'Example reading');
        $svc->record($veh, 'ODOMETER_KM', 42800, CarbonImmutable::now()->subDays(2), 'MANUAL', 'Example reading');
        $make('PM-VEH01-01', 'VEH-01', 'Engine service by odometer (example)', ['order_type' => 'PREVENTIVE', 'trigger_mode' => 'TIME_OR_METER', 'interval_value' => 6, 'interval_unit' => 'MONTH',
            'meter_type' => 'ODOMETER_KM', 'meter_interval' => 5000, 'schedule_mode' => 'FLOATING', 'lead_days' => 7, 'est_minutes' => 180, 'needs_stop' => false,
            'last_done_date' => today()->subMonths(4), 'last_done_meter' => 38000, 'next_due_date' => today()->addMonths(2), 'next_due_meter' => 43000], ['Change the engine oil and filter.', 'Check the tyres and the brakes.']);

        $made = (new PlanService('seeder'))->generateDue();
        $this->command?->info('Example plans created; '.count($made).' Draft order(s) generated.');
    }

    private function exampleAssets(): void
    {
        foreach ([
            ['AC-OFFICE-01', 'Office air conditioner (example)', 'FACILITY', null, 'Office', 'NONE'],
            ['GEN-01', 'Standby generator (example)', 'FACILITY', 'B', 'Utility room', 'RUN_HOURS'],
            ['VEH-01', 'Delivery truck (example)', 'VEHICLE', 'B', 'Dispatch', 'ODOMETER_KM'],
        ] as [$code, $name, $class, $crit, $loc, $meter]) {
            MntAsset::firstOrCreate(['asset_code' => $code], ['asset_name' => $name, 'asset_class' => $class, 'criticality' => $crit, 'location' => $loc,
                'meter_type' => $meter, 'notes' => 'Example (dummy) asset to show that Maintenance covers more than machines.', 'activate' => true, 'created_by' => 'seeder', 'updated_by' => 'seeder']);
        }
    }

    private function sopAndWorkInstructions(): void
    {
        $data = json_decode((string) file_get_contents(resource_path('data/maintenance/sop_wi.json')), true, 512, JSON_THROW_ON_ERROR);
        $count = 0;
        foreach ($data['documents'] as $x) {
            $doc = MntDocument::where('doc_no', $x['doc_no'])->first();
            if ($doc) {
                continue;
            }
            $set = explode('-', $x['doc_no'])[2] ?? null;
            $doc = MntDocument::create(['doc_no' => $x['doc_no'], 'kind' => $x['kind'], 'doc_set' => $set, 'title' => $x['title'], 'revision' => $x['revision'],
                'status' => 'DRAFT', 'activate' => true, 'created_by' => 'seeder', 'updated_by' => 'seeder']);
            $seq = 0;
            $add = function (string $section, string $text) use ($doc, &$seq) {
                $doc->lines()->create(['seq_no' => ++$seq, 'section' => $section, 'line_text' => $text, 'created_by' => 'seeder', 'updated_by' => 'seeder']);
            };
            $add('Purpose', $x['purpose']);
            foreach ($x['sections'] ?? [] as [$title, $content]) {
                foreach ((array) $content as $text) {
                    $add($title, $text);
                }
            }
            foreach ($x['before'] ?? [] as $text) {
                $add('Before you start', $text);
            }
            foreach ($x['steps'] ?? [] as $text) {
                $add('Steps', $text);
            }
            $count++;
        }
        $this->command?->info("SOP / WI documents created: $count.");

        $links = 0;
        foreach ($data['assignment'] as $a) {
            $asset = MntAsset::where('asset_code', $a['machine_code'])->first();
            if (! $asset) {
                continue;
            }
            $ids = MntDocument::whereIn('doc_no', $a['documents'])->pluck('id_document')->all();
            $before = $asset->documents()->count();
            $asset->documents()->syncWithoutDetaching(array_fill_keys($ids, ['created_by' => 'seeder', 'updated_by' => 'seeder']));
            $links += $asset->documents()->count() - $before;
        }
        $this->command?->info("Document links created: $links.");
    }

    // Contoh Daily Check untuk satu mesin offset, tanpa batas normal (batas diisi kepala maintenance dari manual pabrikan).
    private function exampleWatchPoints(): void
    {
        $asset = MntAsset::where('asset_code', 'OFF-02')->first();
        if (! $asset || $asset->watchPoints()->exists()) {
            return;
        }
        foreach ([['ROLLER', 'Surface condition, no glazing', 'Visual', 'SHIFT', 'OPERATOR'], ['HYDRAULIC', 'Oil level, no leak', 'Sight glass', 'SHIFT', 'OPERATOR'],
            ['PRINT_UNIT', 'Unusual noise or vibration', 'Listen / touch', 'SHIFT', 'OPERATOR'], ['GRIPPER', 'Wear and alignment', 'Visual', 'WEEKLY', 'TECHNICIAN'],
            ['LUBRICATION', 'Grease points done', 'Checklist', 'WEEKLY', 'TECHNICIAN'], ['BLADE_KNIFE', 'Sharpness', 'Visual', 'MONTHLY', 'TECHNICIAN']] as $i => [$comp, $check, $how, $freq, $role]) {
            MntWatchPoint::create(['asset_id' => $asset->id_asset, 'component_code' => $comp, 'check_text' => $check, 'method_text' => $how, 'frequency' => $freq,
                'checked_by_role' => $role, 'sort_order' => $i + 1, 'activate' => true, 'created_by' => 'seeder', 'updated_by' => 'seeder']);
        }
    }
}
