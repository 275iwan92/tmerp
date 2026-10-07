<?php

declare(strict_types=1);

namespace App\Support\V2\Data;

use App\Services\Data\DataOriginService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

// Setup > Dummy & Legacy Data (v2). Fitur sama dengan layar v1: tampilan DUMMY / LEGACY, kelengkapan data legacy (+ jadikan real),
// snapshot & reset dummy, aturan klasifikasi, ringkasan per tabel. Logika data = DataOriginService; kelas ini hanya perantara layar.
final class DataOriginDesk
{
    public const TYPES = [
        'CREATED_BY' => 'Creator (created_by) matches pattern',
        'COLUMN_LIKE' => 'Column value matches pattern',
        'COLUMN_FILLED' => 'Column is filled',
    ];

    private const SETTINGS = ['show_dummy' => 'DATA_SHOW_DUMMY', 'show_legacy' => 'DATA_SHOW_LEGACY',
        'new_as_dummy' => 'DATA_NEW_AS_DUMMY', 'hide_incomplete' => 'DATA_HIDE_LEGACY_INCOMPLETE'];

    public static function service(): DataOriginService
    {
        return app(DataOriginService::class);
    }

    /** @return array<string, bool> */
    public static function settings(): array
    {
        $s = self::service();
        $hidden = $s->hiddenOrigins();

        return ['show_dummy' => ! in_array('DUMMY', $hidden, true), 'show_legacy' => ! in_array('LEGACY', $hidden, true),
            'new_as_dummy' => $s->newRowsAsDummy(), 'hide_incomplete' => $s->hideLegacyIncomplete()];
    }

    /** @return array{LIVE: int, DUMMY: int, LEGACY: int} */
    public static function totals(): array
    {
        $t = ['LIVE' => 0, 'DUMMY' => 0, 'LEGACY' => 0];
        foreach (self::service()->counts() as $c) {
            foreach (array_keys($t) as $k) {
                $t[$k] += $c[$k];
            }
        }

        return $t;
    }

    /** @return array<string, array{LIVE: int, DUMMY: int, LEGACY: int}> tabel yang memuat DUMMY / LEGACY, terbanyak dulu */
    public static function marked(string $db = ''): array
    {
        return collect(self::service()->counts())
            ->filter(fn ($c, $k) => ($c['DUMMY'] || $c['LEGACY']) && ($db === '' || str_starts_with($k, $db.'.')))
            ->sortByDesc(fn ($c) => $c['DUMMY'] + $c['LEGACY'])->all();
    }

    public static function rules(): Collection
    {
        return DB::connection('tm2sys')->table('sys_data_origin_rule')->orderBy('priority')->orderBy('id_rule')->get();
    }

    public static function rule(int $id): ?object
    {
        return DB::connection('tm2sys')->table('sys_data_origin_rule')->where('id_rule', $id)->first();
    }

    public static function requirements(): Collection
    {
        return DB::connection('tm2sys')->table('sys_data_requirement')->orderBy('db_name')->orderBy('table_name')->get();
    }

    public static function saveSettings(array $in, string $by): string
    {
        foreach (self::SETTINGS as $field => $key) {
            DB::connection('tm2sys')->table('sys_constant')->where('constant_key', $key)->update(
                ['constant_number' => ! empty($in[$field]) ? 1 : 0, 'updated_by' => $by, 'updated_at' => now()]);
        }

        return t('Setting saved — it applies to pages opened from now on.');
    }

    public static function saveRule(array $in, ?int $id, string $by): void
    {
        $d = Validator::make($in, [
            'match_type' => ['required', 'in:'.implode(',', array_keys(self::TYPES))],
            'db_name' => ['nullable', 'in:'.implode(',', DataOriginService::DATABASES)],
            'table_name' => ['nullable', 'regex:/^\w+$/', 'max:64'],
            'column_name' => ['nullable', 'required_unless:match_type,CREATED_BY', 'regex:/^\w+$/', 'max:64'],
            'match_value' => ['nullable', 'required_unless:match_type,COLUMN_FILLED', 'string', 'max:100'],
            'origin' => ['required', 'in:DUMMY,LEGACY'],
            'priority' => ['required', 'integer', 'min:0', 'max:9999'],
            'note' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $blank = fn ($v) => ($v = trim((string) $v)) === '' ? null : $v;
        $payload = ['match_type' => $d['match_type'], 'db_name' => $blank($d['db_name'] ?? null), 'table_name' => $blank($d['table_name'] ?? null),
            'column_name' => $d['match_type'] === 'CREATED_BY' ? null : $blank($d['column_name'] ?? null),
            'match_value' => $d['match_type'] === 'COLUMN_FILLED' ? null : $blank($d['match_value'] ?? null),
            'origin' => $d['origin'], 'priority' => (int) $d['priority'], 'note' => $blank($d['note'] ?? null), 'updated_at' => now(), 'updated_by' => $by];

        $table = DB::connection('tm2sys')->table('sys_data_origin_rule');
        $id ? $table->where('id_rule', $id)->update($payload)
            : $table->insert($payload + ['activate' => 1, 'created_at' => now(), 'created_by' => $by]);
    }

    public static function toggle(string $table, int $id, string $by): void
    {
        $key = ['sys_data_origin_rule' => 'id_rule', 'sys_data_requirement' => 'id_requirement'][$table] ?? abort(404);
        DB::connection('tm2sys')->table($table)->where($key, $id)->update(['activate' => DB::raw('1 - activate'), 'updated_at' => now(), 'updated_by' => $by]);
        if ($table === 'sys_data_requirement') {
            self::service()->computeReady(false);
        }
    }

    public static function saveRequirement(array $in, string $by): string
    {
        $d = Validator::make($in, ['db_name' => ['required', 'in:'.implode(',', DataOriginService::DATABASES)], 'table_name' => ['required', 'regex:/^\w+$/', 'max:64'],
            'column_name' => ['required', 'regex:/^\w+$/', 'max:64'], 'label' => ['required', 'string', 'max:100']])->validate();
        DB::connection('tm2sys')->table('sys_data_requirement')->updateOrInsert(
            ['db_name' => $d['db_name'], 'table_name' => $d['table_name'], 'column_name' => $d['column_name']],
            ['label' => $d['label'], 'activate' => 1, 'created_at' => now(), 'updated_at' => now(), 'created_by' => $by, 'updated_by' => $by]);
        self::service()->computeReady(false);

        return t('Requirement saved and data completeness recalculated.');
    }

    public static function recomputeReady(): string
    {
        $n = array_sum(array_column(self::service()->computeReady(false), 'incomplete'));

        return tf('Completeness recalculated: :n rows do not meet the requirement yet.', ['n' => $n]);
    }

    public static function applyClassify(): string
    {
        $n = array_sum(array_map(fn ($c) => $c['DUMMY'] + $c['LEGACY'], self::service()->classify(false)));

        return tf('Classification applied: :n rows marked. Take the snapshot again if DUMMY data increased.', ['n' => $n]);
    }

    public static function applyPromote(?string $only): string
    {
        $n = array_sum(self::service()->promoteReadyLegacy(false, $only));

        return tf(':n complete LEGACY rows became real data (LIVE).', ['n' => $n]);
    }

    public static function takeSnapshot(string $by): string
    {
        return tf('Snapshot saved: :n DUMMY rows are now the original state for reset.', ['n' => self::service()->snapshot($by)]);
    }

    public static function applyReset(bool $removeNew): string
    {
        $r = self::service()->reset(false, $removeNew);
        Cache::forget('data_origin.tables');

        return tf('Reset done: :restored rows restored, :changed rows recovered', ['restored' => $r['restored'], 'changed' => $r['changed']])
            .($removeNew ? tf(', :new new dummy rows deleted.', ['new' => $r['new']]) : '.');
    }
}
