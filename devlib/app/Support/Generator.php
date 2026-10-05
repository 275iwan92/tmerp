<?php

namespace App\Support;

use Devlib\Locale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

// Generator devlib.test (user 2026-10-05): form permintaan sederhana -- Name, Description, Type (Master / Transactional /
// Custom), Needs an engine + Engine description, Flow. Form TIDAK menulis kode: ia menyimpan permintaan di database devlib
// (gen_request). Agen membaca permintaan terbuka di awal sesi (`php artisan gen:requests` di folder devlib), mengisi
// pratinjau lewat preview() -- objek yang dibutuhkan, input / output engine, formula -- dan baru membangun setelah user
// menekan Approve. Objek DevLib yang belum ada harus disetujui user satu per satu (gen_request_object).
final class Generator
{
    public const TYPES = ['MASTER' => 'Master', 'TRANSACTIONAL' => 'Transactional', 'CUSTOM' => 'Custom'];

    public const TYPE_HINTS = [
        'MASTER' => 'Data that is kept and switched active / inactive',
        'TRANSACTIONAL' => 'A document: Draft, Confirmed, Cancelled, Closed',
        'CUSTOM' => 'A special form that gets its own object',
    ];

    // status => [label, tone]
    public const STATUS = [
        'REQUESTED' => ['Requested', 'warn'], 'QUESTION' => ['Agent asks', 'info'], 'PREVIEW' => ['Preview ready', 'accent'],
        'APPROVED' => ['Approved', 'success'], 'BUILT' => ['Built', 'success'], 'CANCELLED' => ['Cancelled', 'danger'],
    ];

    public const OPEN = ['REQUESTED', 'QUESTION', 'PREVIEW', 'APPROVED'];

    public static function menu(): array
    {
        $open = self::ready() ? DB::table('gen_request')->whereIn('status', self::OPEN)->count() : 0;

        return ['code' => 'GN', 'short' => 'GN', 'label' => 'Generator', 'icon' => 'fa-solid fa-wand-magic-sparkles', 'children' => [
            ['code' => 'GN-new', 'label' => 'New Request', 'icon' => 'fa-solid fa-wand-magic-sparkles', 'url' => route('generator', ['new' => 1]), 'type' => 'form'],
            ['code' => 'GN-list', 'label' => 'Requests', 'icon' => 'fa-solid fa-list-check', 'url' => route('generator'), 'type' => 'grid'] + ($open ? ['badge' => (string) $open, 'badgeTone' => 'partial'] : []),
        ]];
    }

    // Tabelnya dibuat lewat migrasi; sebelum itu menu tetap tampil tanpa angka.
    public static function ready(): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable('gen_request');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function find(int $id): ?object
    {
        return DB::table('gen_request')->where('id_request', $id)->first();
    }

    public static function objects(int $requestId): array
    {
        return DB::table('gen_request_object')->where('request_id', $requestId)->orderBy('id_object')->get()->all();
    }

    /** @return list<array<string, mixed>> baris grid Requests, terbaru di atas */
    public static function rows(): array
    {
        return DB::table('gen_request')->orderByDesc('id_request')->get()->map(fn ($r) => [
            'id' => $r->id_request, 'no' => $r->request_no, 'name' => $r->name, 'type' => self::TYPES[$r->request_type] ?? $r->request_type,
            'engine' => $r->need_engine ? 'Yes' : 'No', 'requested' => substr((string) $r->created_at, 0, 10), 'by' => $r->requested_by,
            'status' => self::STATUS[$r->status][0] ?? $r->status, 'agent' => $r->agent ? (Summary::AGENTS[$r->agent] ?? $r->agent) : '—',
            'screen' => $r->screen_label ?: '—',
        ])->all();
    }

    public static function statusTones(): array
    {
        return collect(self::STATUS)->mapWithKeys(fn ($s) => [$s[0] => $s[1]])->all();
    }

    // Simpan permintaan baru atau perubahan user atas permintaan yang belum dibangun. Kembalian: [id, pesan error | null].
    public static function save(Request $r, string $login, ?int $id = null): array
    {
        $v = Validator::make($r->all(), [
            'name' => ['required', 'string', 'max:150'], 'description' => ['required', 'string', 'max:5000'],
            'request_type' => ['required', 'in:'.implode(',', array_keys(self::TYPES))],
            'engine_description' => ['nullable', 'string', 'max:5000', 'required_if:need_engine,1'], 'flow_description' => ['nullable', 'string', 'max:5000'],
        ], ['engine_description.required_if' => 'Describe the engine when Needs an engine is ticked.']);
        if ($v->fails()) {
            return [$id, $v->errors()->first()];
        }
        $need = $r->boolean('need_engine');
        $data = ['name' => trim((string) $r->input('name')), 'description' => trim((string) $r->input('description')), 'request_type' => $r->input('request_type'),
            'need_engine' => $need, 'engine_description' => $need ? trim((string) $r->input('engine_description')) : null,
            'flow_description' => trim((string) $r->input('flow_description')) ?: null, 'updated_at' => now()];
        if ($id) {
            $row = self::find($id);
            if (! $row || in_array($row->status, ['BUILT', 'CANCELLED'], true)) {
                return [$id, 'This request is closed and can no longer be changed.'];
            }
            // Isi berubah setelah pratinjau = agen harus membaca ulang.
            DB::table('gen_request')->where('id_request', $id)->update($data + ['status' => 'REQUESTED', 'approved_by' => null, 'approved_at' => null]);

            return [$id, null];
        }
        $id = DB::transaction(function () use ($data, $login) {
            $next = (int) DB::table('gen_request')->lockForUpdate()->max('id_request') + 1;

            return DB::table('gen_request')->insertGetId($data + ['request_no' => 'RQ-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT), 'status' => 'REQUESTED',
                'requested_by' => $login, 'created_at' => now()]);
        });

        return [$id, null];
    }

    // User mengoreksi formula / input / output engine di kotak Preview.
    public static function saveFormula(int $id, Request $r): void
    {
        DB::table('gen_request')->where('id_request', $id)->whereNotIn('status', ['BUILT', 'CANCELLED'])->update([
            'engine_input' => trim((string) $r->input('engine_input')) ?: null, 'engine_output' => trim((string) $r->input('engine_output')) ?: null,
            'engine_formula' => trim((string) $r->input('engine_formula')) ?: null, 'updated_at' => now()]);
    }

    // Persetujuan satu objek yang belum ada di DevLib (dialog "This object is not built yet").
    public static function decideObject(int $objectId, bool $approve, string $login): void
    {
        DB::table('gen_request_object')->where('id_object', $objectId)->where('is_built', false)->update([
            'decision' => $approve ? 'APPROVED' : 'REJECTED', 'decided_by' => $login, 'decided_at' => now(), 'updated_at' => now()]);
    }

    // Approve seluruh pratinjau = izin membangun. Ditolak selama masih ada objek baru yang belum diputuskan.
    public static function approve(int $id, string $login): ?string
    {
        $row = self::find($id);
        if (! $row || $row->status !== 'PREVIEW') {
            return 'Only a request with a ready preview can be approved.';
        }
        if (DB::table('gen_request_object')->where('request_id', $id)->where('decision', 'PENDING')->exists()) {
            return 'Decide every object that is not built yet first.';
        }
        DB::table('gen_request')->where('id_request', $id)->update(['status' => 'APPROVED', 'approved_by' => $login, 'approved_at' => now(), 'updated_at' => now()]);

        return null;
    }

    public static function cancel(int $id): void
    {
        DB::table('gen_request')->where('id_request', $id)->whereNotIn('status', ['BUILT'])->update(['status' => 'CANCELLED', 'updated_at' => now()]);
    }

    // ---- Dipakai AGEN (perintah gen:requests / tinker), bukan layar ----

    // Agen mengisi pratinjau. $objects: [[nama, tag | null]] -- tag null = objek belum ada, user harus menyetujui pembuatannya.
    public static function preview(int $id, string $agent, array $objects, ?string $input = null, ?string $output = null, ?string $formula = null, ?string $note = null): void
    {
        DB::transaction(function () use ($id, $agent, $objects, $input, $output, $formula, $note) {
            DB::table('gen_request_object')->where('request_id', $id)->delete();
            foreach ($objects as [$name, $tag]) {
                DB::table('gen_request_object')->insert(['request_id' => $id, 'object_name' => $name, 'object_tag' => $tag, 'is_built' => $tag !== null,
                    'decision' => $tag !== null ? 'NONE' : 'PENDING', 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('gen_request')->where('id_request', $id)->update(['status' => 'PREVIEW', 'agent' => $agent, 'engine_input' => $input, 'engine_output' => $output,
                'engine_formula' => $formula, 'agent_note' => $note, 'updated_at' => now()]);
        });
    }

    // Agen bertanya (deskripsi kurang jelas) -- user menjawab dengan mengubah permintaannya.
    public static function ask(int $id, string $agent, string $question): void
    {
        DB::table('gen_request')->where('id_request', $id)->update(['status' => 'QUESTION', 'agent' => $agent, 'agent_note' => $question, 'updated_at' => now()]);
    }

    // Agen selesai membangun: tautan layar di mockup.test.
    public static function built(int $id, string $agent, string $screenLabel, ?string $screenUrl = null): void
    {
        DB::table('gen_request')->where('id_request', $id)->update(['status' => 'BUILT', 'agent' => $agent, 'screen_label' => $screenLabel, 'screen_url' => $screenUrl,
            'built_at' => now(), 'updated_at' => now()]);
    }

    public static function stamp(?string $at): string
    {
        return $at ? Locale::stamp($at) : '—';
    }
}
