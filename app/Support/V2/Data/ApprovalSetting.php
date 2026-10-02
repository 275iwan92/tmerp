<?php

namespace App\Support\V2\Data;

use App\Models\Master\EmpEmployee;
use App\Models\Master\FlowConfig;
use App\Models\Master\FlowConfigStep;
use App\Models\Master\FlowLog;
use App\Models\Master\FlowPositionActing;
use App\Models\Sys\SecUser;
use Devlib\Locale;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Setting Approval (user 2026-09-30, pengganti m_authorize/_detail lama): header per modul (tm2mst.flow_config) + Level 1..n
// (flow_config_step: jabatan, batas nilai, label cetak). Approver = JABATAN, supaya officer pengganti (PLT,
// flow_position_acting) otomatis menerima antrian selama officer aslinya berhalangan.
final class ApprovalSetting
{
    public const MODULE_NAMES = ['PR' => 'Purchase Requisition', 'PO' => 'Purchase Order', 'POC' => 'Cash PO', 'CO' => 'Customer Order', 'AD' => 'Stock Adjustment',
        'OP' => 'Stock Opname', 'PRT' => 'Purchase Return', 'IN' => 'Stock In', 'OUT' => 'Stock Out', 'RTP' => 'Return from Production'];

    public const PRINT_LABELS = ['Diperiksa' => 'Diperiksa', 'Menyetujui' => 'Menyetujui', 'Mengetahui' => 'Mengetahui'];

    public static function rows(): array
    {
        $configs = FlowConfig::with('steps')->orderBy('module_code')->get();
        $pos = self::positions($configs->flatMap->steps->pluck('position_id')->filter()->unique()->all());

        return $configs->map(fn ($c) => ['id' => $c->flow_config_id, 'module' => $c->module_code, 'name' => t(self::MODULE_NAMES[$c->module_code] ?? $c->module_code),
            'levels' => $c->steps->where('step_type', 'APPROVAL')->count(),
            'approvers' => $c->steps->where('step_type', 'APPROVAL')->map(fn ($s) => 'L'.$s->step_no.' '.($pos[$s->position_id]['name'] ?? '—')
                .((float) $s->min_amount > 0 ? ' (≥ '.Locale::number((float) $s->min_amount, 0).')' : ''))->implode(' → ') ?: t('No approval'),
            'final' => $c->final_status ?? '—', 'active' => $c->activate ? 'Aktif' : 'Nonaktif'])->all();
    }

    // Jabatan: nama, pemegang (nama karyawan), PLT yang berlaku hari ini (nama user pengganti).
    public static function positions(array $ids): array
    {
        $ids = array_values(array_filter($ids));
        if (! $ids) {
            return [];
        }
        $names = DB::connection('tm2mst')->table('org_position')->whereIn('position_id', $ids)->pluck('position_name', 'position_id');
        $emps = EmpEmployee::whereIn('position_id', $ids)->get(['position_id', 'full_name', 'data_origin'])->groupBy('position_id');
        $plt = FlowPositionActing::whereIn('position_id', $ids)->where('activate', true)->whereDate('start_date', '<=', now())->whereDate('end_date', '>=', now())
            ->get()->keyBy('position_id');
        $users = SecUser::whereIn('id_user', $plt->pluck('acting_user_id'))->pluck('name', 'id_user');

        return collect($ids)->mapWithKeys(function ($id) use ($names, $emps, $plt, $users) {
            $g = $emps[$id] ?? collect();
            // Data dummy disembunyikan bila ada pemegang asli (ddt berisi keduanya).
            $real = $g->where('data_origin', '<>', 'DUMMY');
            $holder = ($real->isNotEmpty() ? $real : $g)->pluck('full_name')->map(fn ($n) => Str::title(strtolower($n)))->implode(', ');

            return [$id => ['name' => $names[$id] ?? '#'.$id, 'holder' => $holder, 'plt' => isset($plt[$id]) ? ($users[$plt[$id]->acting_user_id] ?? '') : '']];
        })->all();
    }

    public static function positionOptions(): array
    {
        $p = self::positions(DB::connection('tm2mst')->table('org_position')->where('activate', 1)->pluck('position_id')->all());

        return collect($p)->mapWithKeys(fn ($v, $id) => [$id => $v['name'].($v['holder'] ? ' — '.$v['holder'] : ' — '.t('(empty)'))])->sort()->all();
    }

    public static function levels(FlowConfig $c): array
    {
        $steps = $c->steps()->where('step_type', 'APPROVAL')->get();
        $pos = self::positions($steps->pluck('position_id')->all());

        return $steps->map(fn ($s) => ['flow_step_id' => $s->flow_step_id, 'level' => 'Level '.$s->step_no, 'position_id' => $s->position_id,
            'holder' => ($pos[$s->position_id]['holder'] ?? '') ?: '—', 'plt' => ($pos[$s->position_id]['plt'] ?? '') ?: '—',
            'min_amount' => (float) $s->min_amount > 0 ? (float) $s->min_amount : 0, 'max_amount' => $s->max_amount !== null ? (float) $s->max_amount : '',
            'print_label' => $s->print_label ?? 'Menyetujui',
            'used' => FlowLog::where('flow_step_id', $s->flow_step_id)->exists() ? t('Yes') : t('Not yet')])->values()->all();
    }

    public static function create(string $module, string $notes, string $final): FlowConfig
    {
        $module = strtoupper(trim($module));
        abort_if($module === '' || FlowConfig::where('module_code', $module)->exists(), 422, t('The module code is empty or already has a setting.'));

        return FlowConfig::create(['module_code' => $module, 'notes' => $notes, 'final_status' => strtoupper(trim($final)) ?: 'APPROVED',
            'reject_status' => 'REJECTED', 'total_step' => 0, 'activate' => true, 'created_by' => Auth::user()->login]);
    }

    // Header + Level. Level baru/berubah selalu SUBMITTED -> SUBMITTED (status akhir dari header); level yang pernah
    // dipakai dokumen (flow_log) tidak dihapus tetapi dinonaktifkan supaya riwayat tetap utuh.
    public static function save(FlowConfig $c, array $in): void
    {
        $login = Auth::user()->login;
        DB::connection('tm2mst')->transaction(function () use ($c, $in, $login) {
            $c->update(['notes' => trim((string) ($in['notes'] ?? $c->notes)), 'final_status' => strtoupper(trim((string) ($in['final_status'] ?? $c->final_status))) ?: null,
                'updated_by' => $login]);
            foreach (array_keys($in['remove'] ?? []) as $id) {
                $s = FlowConfigStep::where('flow_config_id', $c->flow_config_id)->find((int) $id);
                if ($s) {
                    FlowLog::where('flow_step_id', $s->flow_step_id)->exists() ? $s->update(['activate' => false, 'step_no' => 100 + $s->flow_step_id, 'updated_by' => $login]) : $s->delete();
                }
            }
            $no = 0;
            $num = fn ($v) => ($c = Locale::input($v)) === null ? null : (float) $c;
            foreach ($in['levels'] ?? [] as $key => $row) {
                if (empty($row['position_id']) || isset($in['remove'][$key])) {
                    continue;
                }
                $data = ['step_no' => ++$no, 'step_type' => 'APPROVAL', 'status_from' => 'SUBMITTED', 'status_to' => 'SUBMITTED', 'status_to_reject' => $c->reject_status ?? 'REJECTED',
                    'position_id' => (int) $row['position_id'], 'min_amount' => $num($row['min_amount'] ?? '') ?? 0, 'max_amount' => $num($row['max_amount'] ?? ''),
                    'print_label' => $row['print_label'] ?? 'Menyetujui', 'activate' => true, 'updated_by' => $login];
                $step = is_numeric($key) ? FlowConfigStep::where('flow_config_id', $c->flow_config_id)->find((int) $key) : null;
                $step ? $step->update($data) : FlowConfigStep::create($data + ['flow_config_id' => $c->flow_config_id, 'created_by' => $login]);
            }
            // Nomor level selalu 1..n berurutan (langkah otomatis non-approval tetap di belakang).
            $active = FlowConfigStep::where('flow_config_id', $c->flow_config_id)->where('step_type', 'APPROVAL')->where('activate', true)->orderBy('step_no')->get();
            $active->values()->each(fn ($s, $i) => $s->step_no === $i + 1 ?: $s->update(['step_no' => $i + 1]));
            $c->update(['total_step' => $active->count()]);
        });
    }

    public static function setActive(FlowConfig $c, bool $on): void
    {
        $c->update(['activate' => $on, 'updated_by' => Auth::user()->login]);
    }

    // ---- Officer pengganti (PLT): jabatan x user pengganti x rentang tanggal ----
    public static function actingRows(): array
    {
        $rows = FlowPositionActing::orderByDesc('start_date')->get();
        $pos = self::positions($rows->pluck('position_id')->unique()->all());
        $users = SecUser::whereIn('id_user', $rows->pluck('acting_user_id'))->pluck('name', 'id_user');

        return $rows->map(fn ($r) => ['acting_id' => $r->acting_id, 'position_id' => $r->position_id, 'acting_user_id' => $r->acting_user_id,
            'original' => ($pos[$r->position_id]['holder'] ?? '') ?: '—', 'start_date' => $r->start_date?->toDateString(), 'end_date' => $r->end_date?->toDateString(), 'reason' => $r->reason, 'activate' => (bool) $r->activate,
            'status' => t(! $r->activate ? 'Inactive' : (now()->toDateString() < $r->start_date?->toDateString() ? 'Scheduled' : (now()->toDateString() > $r->end_date?->toDateString() ? 'Ended' : 'In effect'))),
            'user_name' => $users[$r->acting_user_id] ?? '—'])->all();
    }

    public static function userOptions(): array
    {
        return SecUser::where('activate', 1)->orderBy('name')->get(['id_user', 'name', 'login'])->mapWithKeys(fn ($u) => [$u->id_user => $u->name.' ('.$u->login.')'])->all();
    }

    public static function saveActing(array $in): void
    {
        $login = Auth::user()->login;
        DB::connection('tm2mst')->transaction(function () use ($in, $login) {
            foreach (array_keys($in['remove'] ?? []) as $id) {
                FlowPositionActing::whereKey((int) $id)->delete();
            }
            foreach ($in['acting'] ?? [] as $key => $row) {
                if (empty($row['position_id']) || empty($row['acting_user_id']) || empty($row['start_date']) || empty($row['end_date']) || isset($in['remove'][$key])) {
                    continue;
                }
                abort_if($row['end_date'] < $row['start_date'], 422, t('The acting end date must be after the start date.'));
                $data = ['position_id' => (int) $row['position_id'], 'acting_user_id' => (int) $row['acting_user_id'], 'start_date' => $row['start_date'],
                    'end_date' => $row['end_date'], 'reason' => trim((string) ($row['reason'] ?? '')), 'activate' => filter_var($row['activate'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    'updated_by' => $login];
                $r = is_numeric($key) ? FlowPositionActing::find((int) $key) : null;
                $r ? $r->update($data) : FlowPositionActing::create($data + ['created_by' => $login]);
            }
        });
    }
}
