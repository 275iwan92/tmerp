<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Setting Approval (user 2026-09-30): flow_config = header per modul, flow_config_step = Level 1..n (jabatan, batas nilai,
// label cetak -- pengganti m_authorize/_detail lama). Setiap level SUBMITTED -> SUBMITTED; level yang nilainya di luar batas
// dilewati; status akhir & status tolak dari header (final_status / reject_status). Level lama tidak dihapus
// (dirujuk flow_log) tetapi activate=0.
return new class extends Migration
{
    // modul => [final_status, [ [position_code, min_amount, print_label], ... ] ]
    private const SETTINGS = [
        'PR' => ['APPROVED', [['OPERATIONALMANA', 0, 'Menyetujui']]],
        'PO' => ['APPROVED', [['PRODUCTCOSTING', 0, 'Diperiksa'], ['OPERATIONALMANA', 0, 'Menyetujui'], ['PRESIDENDIREKTU', 1000001, 'Mengetahui']]],
        'CO' => ['CONFIRMED', [['FASPV', 0, 'Diperiksa'], ['OPERATIONALMANA', 0, 'Menyetujui']]],
        'AD' => ['POSTED', [['OPERATIONALMANA', 0, 'Menyetujui']]],
        'OP' => ['POSTED', [['WAREHOUSEADMIN', 0, 'Menyetujui']]],
        'PRT' => ['CONFIRMED', [['OPERATIONALMANA', 0, 'Menyetujui']]],
    ];

    public function up(): void
    {
        if (! Schema::connection('tm2mst')->hasColumn('flow_config', 'final_status')) {
            Schema::connection('tm2mst')->table('flow_config', function (Blueprint $t) {
                $t->string('final_status', 30)->nullable()->after('total_step')->comment('Status dokumen setelah level terakhir menyetujui; null = status_to langkah');
                $t->string('reject_status', 30)->nullable()->after('final_status')->comment('Status dokumen bila ditolak; null = status_to_reject langkah');
            });
            Schema::connection('tm2mst')->table('flow_config_step', function (Blueprint $t) {
                $t->string('print_label', 50)->nullable()->after('label')->comment('Label tanda tangan di cetakan: Diperiksa / Menyetujui / Mengetahui');
                $t->boolean('activate')->default(true)->after('print_label');
            });
        }

        $db = DB::connection('tm2mst');
        $now = now();
        $db->transaction(function () use ($db, $now) {
            foreach (self::SETTINGS as $module => [$final, $levels]) {
                $config = $db->table('flow_config')->where('module_code', $module)->first();
                if (! $config) {
                    continue;
                }
                $db->table('flow_config')->where('flow_config_id', $config->flow_config_id)->update(['final_status' => $final, 'reject_status' => 'REJECTED',
                    'total_step' => count($levels), 'updated_at' => $now, 'updated_by' => 'migration']);
                $old = $db->table('flow_config_step')->where('flow_config_id', $config->flow_config_id)->where('step_type', 'APPROVAL')->orderBy('step_no')->orderBy('flow_step_id')->get();
                $used = collect();
                foreach ($levels as $i => [$code, $min, $print]) {
                    $pos = $db->table('org_position')->where('position_code', $code)->value('position_id') ?? throw new RuntimeException("org_position {$code}");
                    $row = ['step_no' => $i + 1, 'step_type' => 'APPROVAL', 'status_from' => 'SUBMITTED', 'status_to' => 'SUBMITTED', 'status_to_reject' => 'REJECTED',
                        'position_id' => $pos, 'min_amount' => $min, 'max_amount' => null, 'label' => null, 'print_label' => $print, 'activate' => 1, 'updated_at' => $now, 'updated_by' => 'migration'];
                    // Pakai ulang baris lama dengan jabatan sama (flow_log tetap merujuk level yang benar), selain itu baris lama berikutnya / baru.
                    $reuse = $old->first(fn ($s) => $s->position_id === $pos && ! $used->contains($s->flow_step_id)) ?? $old->first(fn ($s) => ! $used->contains($s->flow_step_id));
                    if ($reuse) {
                        $db->table('flow_config_step')->where('flow_step_id', $reuse->flow_step_id)->update($row);
                        $used->push($reuse->flow_step_id);
                    } else {
                        $used->push($db->table('flow_config_step')->insertGetId($row + ['flow_config_id' => $config->flow_config_id, 'created_at' => $now, 'created_by' => 'migration',
                            'data_origin' => 'LIVE', 'data_ready' => 1]));
                    }
                }
                foreach ($old->whereNotIn('flow_step_id', $used) as $s) {
                    $db->table('flow_config_step')->where('flow_step_id', $s->flow_step_id)->update(['activate' => 0, 'step_no' => 100 + $s->flow_step_id, 'updated_at' => $now, 'updated_by' => 'migration']);
                }
                // Langkah otomatis (mis. PR FULLY_ORDERED -> CLOSED) di belakang level approval.
                $db->table('flow_config_step')->where('flow_config_id', $config->flow_config_id)->where('step_type', '<>', 'APPROVAL')->where('step_no', '<', 50)
                    ->update(['step_no' => DB::raw('50 + step_no')]);
            }
        });
    }

    public function down(): void
    {
        // Setting approval = data bisnis (diubah lewat layar Setting Approval); kolom dibiarkan.
    }
};
