<?php

namespace App\Support;

use Devlib\Locale;
use Dotenv\Dotenv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
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
            'status' => self::STATUS[$r->status][0] ?? $r->status, 'agent' => $r->agent ? (Summary::AGENTS[$r->agent] ?? ucfirst($r->agent)) : '—',
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
            'source_table' => ['nullable', 'string', 'max:120', 'regex:/^tm2[a-z]+\.[a-z0-9_]+$/'],
            'engine_description' => ['nullable', 'string', 'max:5000', 'required_if:need_engine,1'], 'flow_description' => ['nullable', 'string', 'max:5000'],
        ], ['engine_description.required_if' => 'Describe the engine when Needs an engine is ticked.']);
        if ($v->fails()) {
            return [$id, $v->errors()->first()];
        }
        $need = $r->boolean('need_engine');
        $data = ['name' => trim((string) $r->input('name')), 'description' => trim((string) $r->input('description')), 'request_type' => $r->input('request_type'),
            'source_table' => $r->input('request_type') === 'MASTER' ? (trim((string) $r->input('source_table')) ?: null) : null,
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

    // ---- Generator otomatis TANPA AI: hanya tipe Master yang menunjuk tabel yang sudah ada ----

    public const AUTO_DATABASES = ['tm2mst', 'tm2acc', 'tm2sdi', 'tm2pur', 'tm2inv', 'tm2prd'];

    /** @return array<string, string> tabel dari kamus skema tmerp (resources/data/v2/schema.json) */
    public static function tables(): array
    {
        $file = base_path('../resources/data/v2/schema.json');
        $schema = is_file($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
        $out = [];
        foreach (self::AUTO_DATABASES as $db) {
            foreach ($schema[$db] ?? [] as $table => $def) {
                if (is_array($def) && ($def['type'] ?? '') === 'table') {
                    $out[$db.'.'.$table] = $db.'.'.$table;
                }
            }
        }

        return $out;
    }

    public static function canAuto(?object $row): bool
    {
        return $row && $row->request_type === 'MASTER' && $row->source_table && ! $row->need_engine && ! in_array($row->status, ['BUILT', 'CANCELLED'], true);
    }

    // Jalankan `php artisan devlib:generate` di folder tmerp (situs dummy). Berhasil = permintaan langsung Built dengan tautan
    // layarnya; ditolak (tabel tidak sederhana, sudah punya layar, ...) = alasannya disimpan dan permintaan tetap untuk agen.
    /** @return array{0: bool, 1: string} */
    public static function generate(int $id): array
    {
        $row = self::find($id);
        if (! self::canAuto($row)) {
            return [false, 'Automatic build is only for a Master request that names an existing table and needs no engine.'];
        }
        $dir = dirname(PHP_BINARY);
        $php = is_file($dir.'/php.exe') ? $dir.'/php.exe' : (is_file($dir.'/php') ? $dir.'/php' : 'php');
        // Proses anak TIDAK boleh mewarisi isi .env situs devlib (DB_DATABASE=devlib, APP_KEY, ...): tmerp harus membaca .env-nya sendiri.
        $clean = array_fill_keys(array_keys(Dotenv::parse((string) @file_get_contents(base_path('.env')))), false);
        $run = Process::path(base_path('..'))->env($clean)->timeout(120)->run([$php, 'artisan', 'devlib:generate', $row->source_table, '--title='.$row->name, '--env=mockup', '--json']);
        $out = null;
        foreach (array_reverse(preg_split('/\R/', trim($run->output()))) as $line) {
            if (($json = json_decode(trim($line), true)) && isset($json['ok'])) {
                $out = $json;
                break;
            }
        }
        if (! $out) {
            $out = ['ok' => false, 'message' => 'The generator did not answer: '.mb_substr(trim($run->output().' '.$run->errorOutput()), 0, 400)];
        }
        if (! $out['ok']) {
            DB::table('gen_request')->where('id_request', $id)->update(['build_log' => $out['message'], 'updated_at' => now()]);

            return [false, $out['message']];
        }
        self::built($id, 'generator', $out['title'].' ('.$out['slug'].')', $out['url']);
        DB::table('gen_request')->where('id_request', $id)->update(['build_log' => 'Generated automatically: '.implode(', ', $out['files'] ?? [])]);

        return [true, 'Screen generated: '.$out['title']];
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
