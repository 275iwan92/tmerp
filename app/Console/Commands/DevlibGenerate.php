<?php

namespace App\Console\Commands;

use App\Support\V2\Grid\Grids;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;
use Throwable;

// Generator otomatis TANPA AI untuk master sederhana (user 2026-10-05: "cara 1 ditambah cara 2 supaya langsung").
// Dari satu tabel master yang sudah ada, membuat layar Grid Lookup v2 yang langsung bisa dipakai:
//   kelas turunan MasterDataLookup (kolom, label, aturan panjang dibaca dari kamus skema resources/data/v2/schema.json),
//   model Eloquent bila belum ada, pendaftaran di Grids::ALL, topik Help, dan baris menu (migration, dijalankan di situs dummy).
// Sengaja sempit: tabel harus sudah ada dan tidak punya kolom rujukan ke tabel lain (*_id) atau enum -- yang seperti itu,
// tipe Transactional / Custom dan semua engine tetap dikerjakan agen lewat permintaan Generator di devlib.test.
// Gagal di tengah = semua berkas yang sudah ditulis dikembalikan. Dipanggil tombol Generate Now di devlib.test, atau:
//   php artisan devlib:generate tm2mst.ref_delivery_method --title="Delivery Method" --env=mockup
class DevlibGenerate extends Command
{
    protected $signature = 'devlib:generate
        {table : db.tabel, mis. tm2mst.ref_delivery_method}
        {--title= : judul layar (English)}
        {--sibling=MD_CURRENCY : kode menu tetangga; baris baru ikut induk dan hak aksesnya}
        {--check : periksa saja, tidak menulis apa pun}
        {--json : keluaran JSON untuk devlib.test}';

    protected $description = 'Buat layar Grid Lookup v2 dari satu tabel master sederhana (tanpa AI)';

    private const SKIP = ['created_at', 'created_by', 'updated_at', 'updated_by', 'data_origin', 'data_ready', 'data_origin_manual', 'deleted_at'];

    private const FOLDERS = ['tm2mst' => 'Master', 'tm2sdi' => 'Sdi', 'tm2pur' => 'Pur', 'tm2inv' => 'Inventory', 'tm2prd' => 'Prd', 'tm2acc' => 'Acc', 'tm2sys' => 'Sys'];

    /** @var array<string, string|null> berkas yang disentuh => isi semula (null = berkas baru) */
    private array $touched = [];

    public function handle(): int
    {
        try {
            $plan = $this->plan();
        } catch (Throwable $e) {
            return $this->finish(false, $e->getMessage());
        }
        if ($this->option('check')) {
            return $this->finish(true, 'This table can be generated.', $plan);
        }
        if (! app()->environment('mockup')) {
            return $this->finish(false, 'Run it on the dummy site: add --env=mockup. The menu row is written to the dummy database only.');
        }
        try {
            $this->write($plan);
        } catch (Throwable $e) {
            $this->rollback();

            return $this->finish(false, 'Nothing was kept: '.$e->getMessage());
        }

        return $this->finish(true, 'Screen generated.', $plan);
    }

    // Semua yang akan dibuat, diputuskan sebelum ada berkas yang ditulis. Melempar pesan bila tabelnya tidak cocok.
    private function plan(): array
    {
        [$db, $table] = array_pad(explode('.', (string) $this->argument('table'), 2), 2, '');
        $schema = json_decode(File::get(resource_path('data/v2/schema.json')), true);
        $def = $schema[$db][$table] ?? null;
        if (! $def || ($def['type'] ?? '') !== 'table') {
            throw new \RuntimeException("Table {$db}.{$table} is not in the schema dictionary (resources/data/v2/schema.json). A new table needs a migration, which the agent writes.");
        }
        // Salinan lama / arsip, data pegawai, dan tabel milik Inquiry / ProDev / ProCost (zona beku, tarif costing) tidak dibuat otomatis.
        if (preg_match('/(__oldpk$|_legacy|_migrated_)/', $table) || preg_match('/^(iqp_|prod_|emp_|sec_)/', $table) || preg_match('/(_rate$|^mat_grammage$)/', $table)) {
            throw new \RuntimeException("Table {$db}.{$table} is an archive, employee data, or belongs to Inquiry / Pro-Dev / Pro-Cost. Send it as a request so the agent handles it.");
        }
        if (! isset(self::FOLDERS[$db])) {
            throw new \RuntimeException("Database {$db} is not supported by the automatic generator.");
        }
        $pk = null;
        $columns = [];
        $rules = [];
        $blockers = [];
        $code = null;
        foreach ($def['columns'] as $name => [$type, $nullable, $key]) {
            if ($key === 'PRI') {
                $pk = $name;

                continue;
            }
            if (in_array($name, self::SKIP, true)) {
                continue;
            }
            if (str_ends_with($name, '_id') || isset($def['fk'][$name])) {
                $blockers[] = "{$name} (refers to another table)";

                continue;
            }
            if (str_starts_with($type, 'enum') || str_starts_with($type, 'set')) {
                $blockers[] = "{$name} (fixed list of choices)";

                continue;
            }
            $col = ['key' => $name, 'label' => $this->label($name, $table)];
            if ($name === 'activate') {
                $col = ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '80px'];
            } elseif ($type === 'tinyint(1)') {
                $col += ['type' => 'check', 'width' => '90px'];
            } elseif (preg_match('/^(decimal|double|float)(?:\((\d+),(\d+)\))?/', $type, $m)) {
                $col += ['type' => 'number', 'decimals' => (int) ($m[3] ?? 2), 'width' => '130px'];
            } elseif (preg_match('/^(tinyint|smallint|mediumint|int|bigint)/', $type)) {
                $col += ['type' => 'number', 'decimals' => 0, 'width' => '110px'];
                $rules[$name] = ['integer'];
            } elseif ($type === 'date') {
                $col += ['type' => 'date', 'width' => '130px'];
            } elseif (preg_match('/^(?:var)?char\((\d+)\)/', $type, $m)) {
                $rules[$name] = ['string', 'max:'.$m[1]];
                $col += ['width' => (int) $m[1] <= 20 ? '150px' : '300px'];
                if ($key === 'UNI' && $code === null) {
                    $code = $name;
                    $col += ['unique' => true, 'lockSaved' => true, 'case' => 'uppercase'];
                }
            } elseif (preg_match('/text$/', $type)) {
                $col += ['width' => '320px'];
            } else {
                $blockers[] = "{$name} ({$type})";

                continue;
            }
            if (! $nullable && ($col['type'] ?? '') !== 'check') {
                $col = array_slice($col, 0, 2, true) + ['required' => true] + $col;
            }
            $columns[] = $col;
        }
        if (! $pk) {
            throw new \RuntimeException("Table {$db}.{$table} has no single primary key.");
        }
        if ($blockers) {
            throw new \RuntimeException('This table is not a simple master: '.implode(', ', $blockers).'. Send it as a request so the agent builds it.');
        }
        if (! $columns) {
            throw new \RuntimeException("Table {$db}.{$table} has no column to show.");
        }
        $base = Str::studly(preg_replace('/^(ref|mst|sys)_/', '', $table));
        $title = trim((string) $this->option('title')) ?: Str::headline($base);
        $slug = 'md-'.Str::slug(Str::headline($base));
        if (Grids::find($slug)) {
            throw new \RuntimeException("A screen with the address {$slug} already exists.");
        }
        $lookup = app_path("Support/V2/Grid/Master/{$base}Lookup.php");
        if (File::exists($lookup)) {
            throw new \RuntimeException("{$base}Lookup.php already exists. Generated files are not overwritten.");
        }
        $model = $this->modelOf($db, $table);
        if ($model && ($used = $this->screenOf($model))) {
            throw new \RuntimeException("Table {$db}.{$table} already has a screen: {$used}.");
        }
        $order = $code ?? $columns[0]['key'];

        return compact('db', 'table', 'pk', 'columns', 'rules', 'code', 'base', 'title', 'slug', 'lookup', 'order') + [
            'model' => $model ?? 'App\\Models\\'.self::FOLDERS[$db].'\\'.Str::studly($table), 'newModel' => $model === null,
            'timestamps' => isset($def['columns']['created_at'], $def['columns']['updated_at']), 'hasActive' => isset($def['columns']['activate']),
            'menuCode' => 'GEN_'.strtoupper(str_replace('-', '_', $slug)), 'url' => 'http://mockup.test/v2/s/'.$slug,
        ];
    }

    private function label(string $column, string $table): string
    {
        $prefix = preg_replace('/^(ref|mst|sys)_/', '', $table).'_';
        $name = str_starts_with($column, $prefix) ? substr($column, strlen($prefix)) : $column;
        $name = preg_replace('/^[a-z]+_(code|name)$/', '$1', $name);

        return ['code' => 'Code', 'name' => 'Name', 'sort_order' => 'Order', 'notes' => 'Notes', 'description' => 'Description'][$name] ?? Str::headline($name);
    }

    // Model Eloquent yang sudah memegang tabel itu (dicocokkan dari isi berkasnya), null bila belum ada.
    private function modelOf(string $db, string $table): ?string
    {
        foreach ((new Finder)->files()->in(app_path('Models'))->name('*.php') as $file) {
            $src = $file->getContents();
            if (preg_match('/\$table\s*=\s*\''.preg_quote($table, '/').'\'/', $src) && preg_match('/\$connection\s*=\s*\''.preg_quote($db, '/').'\'/', $src)
                && preg_match('/namespace\s+([^;]+);/', $src, $ns)) {
                return $ns[1].'\\'.$file->getBasename('.php');
            }
        }

        return null;
    }

    // Kelas grid v2 yang sudah memakai model itu (tabelnya sudah punya layar), null bila belum ada.
    private function screenOf(string $model): ?string
    {
        $short = class_basename($model);
        foreach ((new Finder)->files()->in(app_path('Support/V2/Grid'))->name('*.php') as $file) {
            if (preg_match('/\b'.preg_quote($short, '/').'::/', $file->getContents()) && str_contains($file->getContents(), 'use '.$model.';')) {
                return $file->getBasename('.php');
            }
        }

        return null;
    }

    private function put(string $path, string $content): void
    {
        $this->touched[$path] ??= File::exists($path) ? File::get($path) : null;
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $content);
    }

    private function rollback(): void
    {
        foreach ($this->touched as $path => $before) {
            $before === null ? File::delete($path) : File::put($path, $before);
        }
    }

    private function write(array $p): void
    {
        if ($p['newModel']) {
            $this->put(base_path(str_replace('\\', '/', lcfirst($p['model'])).'.php'), $this->modelSource($p));
        }
        $this->put($p['lookup'], $this->lookupSource($p));

        // Daftarkan di Grids::ALL (baris terakhir daftar).
        $grids = app_path('Support/V2/Grid/Grids.php');
        $src = File::get($grids);
        $needle = "    ];\n\n    public static function find(";
        if (substr_count($src, $needle) !== 1) {
            throw new \RuntimeException('Grids.php has an unexpected shape; the grid was not registered.');
        }
        $this->put($grids, str_replace($needle, "        Master\\{$p['base']}Lookup::class,\n".$needle, $src));

        // Topik Help (wajib untuk tiap layar grid, dua bahasa).
        $help = resource_path('data/help/generated.json');
        $topics = File::exists($help) ? json_decode(File::get($help), true) : [];
        $topics[] = $this->helpTopic($p);
        $this->put($help, json_encode($topics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

        // Baris menu = migration (aturan proyek), dijalankan sekarang di situs dummy saja.
        $migration = database_path('migrations/'.now()->format('Y_m_d_His').'_menu_v2_'.str_replace('-', '_', $p['slug']).'.php');
        $this->put($migration, $this->migrationSource($p));

        // Pemeriksaan sebelum dianggap jadi: berkasnya sah, kelasnya bisa dibuat, kolomnya terbaca dari database.
        foreach (array_keys($this->touched) as $path) {
            if (str_ends_with($path, '.php')) {
                exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1', $out, $code);
                if ($code !== 0) {
                    throw new \RuntimeException('Generated file is not valid PHP: '.basename($path));
                }
            }
        }
        require_once $p['lookup'];
        if ($p['newModel']) {
            require_once base_path(str_replace('\\', '/', lcfirst($p['model'])).'.php');
        }
        $class = 'App\\Support\\V2\\Grid\\Master\\'.$p['base'].'Lookup';
        (new $class)->rows();

        Artisan::call('migrate', ['--path' => 'database/migrations/'.basename($migration), '--force' => true]);
    }

    private function export(array $columns): string
    {
        $lines = [];
        foreach ($columns as $c) {
            $parts = [];
            foreach ($c as $k => $v) {
                $parts[] = "'{$k}' => ".(is_bool($v) ? ($v ? 'true' : 'false') : (is_int($v) ? $v : "'".addslashes((string) $v)."'"));
            }
            $lines[] = '            ['.implode(', ', $parts).'],';
        }

        return implode("\n", $lines);
    }

    private function lookupSource(array $p): string
    {
        $short = class_basename($p['model']);
        $rules = [];
        foreach ($p['rules'] as $col => $r) {
            $rules[] = "'{$col}' => ['".implode("', '", $r)."']";
        }
        $orderBy = ($p['hasActive'] ? "->orderByDesc('activate')" : '')."->orderBy('{$p['order']}')";
        $prepare = $p['code'] ? "\n    protected function prepare(array \$in): array\n    {\n        return \$this->upper(\$in, '{$p['code']}');\n    }\n" : '';
        $rulesMethod = $rules ? "\n    // Panjang = lebar kolom tabel ({$p['table']}).\n    protected function rules(): array\n    {\n        return [".implode(', ', $rules)."];\n    }\n" : '';

        return "<?php\n\nnamespace App\\Support\\V2\\Grid\\Master;\n\nuse {$p['model']};\nuse Illuminate\\Database\\Eloquent\\Builder;\n\n"
            ."// {$p['title']} ({$p['db']}.{$p['table']}). Dibuat generator otomatis `php artisan devlib:generate` (user 2026-10-05) dari kamus skema:\n"
            ."// kolom, label dan panjangnya mengikuti tabel; perilaku (isi di sel, Save, Active) = induk MasterDataLookup. Boleh disunting tangan.\n"
            ."class {$p['base']}Lookup extends MasterDataLookup\n{\n"
            ."    public function slug(): string\n    {\n        return '{$p['slug']}';\n    }\n\n"
            ."    public function title(): string\n    {\n        return '".addslashes($p['title'])."';\n    }\n\n"
            ."    protected function query(): Builder\n    {\n        return {$short}::query(){$orderBy};\n    }\n\n"
            ."    public function columns(): array\n    {\n        return [\n".$this->export($p['columns'])."\n        ];\n    }\n"
            .$prepare.$rulesMethod."}\n";
    }

    private function modelSource(array $p): string
    {
        $ns = Str::beforeLast($p['model'], '\\');
        $casts = collect($p['columns'])->where('type', 'check')->map(fn ($c) => "'{$c['key']}' => 'boolean'")->implode(', ');

        return "<?php\n\nnamespace {$ns};\n\nuse Illuminate\\Database\\Eloquent\\Model;\n\n"
            ."// {$p['db']}.{$p['table']}. Dibuat generator otomatis `php artisan devlib:generate` (user 2026-10-05).\n"
            .'class '.class_basename($p['model'])." extends Model\n{\n"
            ."    protected \$connection = '{$p['db']}';\n\n    protected \$table = '{$p['table']}';\n\n    protected \$primaryKey = '{$p['pk']}';\n\n"
            .($p['timestamps'] ? '' : "    public \$timestamps = false;\n\n")
            ."    protected \$guarded = ['{$p['pk']}'];\n"
            .($casts ? "\n    protected \$casts = [{$casts}];\n" : '')."}\n";
    }

    private function helpTopic(array $p): array
    {
        $t = $p['title'];
        $fields = array_map(fn ($c) => ['label' => $c['label'],
            'en' => 'Column '.$c['label'].' of '.$t.'.'.(! empty($c['required']) ? ' Required.' : ''),
            'id' => 'Kolom '.$c['label'].' pada '.$t.'.'.(! empty($c['required']) ? ' Wajib diisi.' : '')], $p['columns']);

        return ['key' => $p['slug'], 'type' => 'INPUT', 'module' => 'MDATA', 'title' => $t,
            'purpose' => ['en' => "The list of {$t}. Open it to add a row, change it or switch it off.", 'id' => "Daftar {$t}. Buka untuk menambah baris, mengubahnya atau menonaktifkannya."],
            'keywords' => strtolower($t).', '.str_replace('_', ' ', $p['table']).', master, lookup',
            'steps' => [
                ['en' => "Open {$t}. Each row is edited directly in the cell.", 'id' => "Buka {$t}. Tiap baris diedit langsung di selnya."],
                ['en' => 'Click Row to add a new line and fill the required columns.', 'id' => 'Klik Row untuk menambah baris baru dan isi kolom yang wajib.'],
                ['en' => 'Click Save. A saved code cannot be changed; untick Active instead of deleting.', 'id' => 'Klik Save. Kode yang sudah disimpan tidak bisa diganti; hilangkan centang Active, jangan dihapus.'],
            ],
            'fields' => $fields];
    }

    private function migrationSource(array $p): string
    {
        $title = addslashes($p['title']);

        return "<?php\n\nuse Illuminate\\Database\\Migrations\\Migration;\nuse Illuminate\\Support\\Facades\\DB;\n\n"
            ."// Menu layar {$p['title']} (Grid Lookup v2, {$p['slug']}), dibuat generator otomatis `php artisan devlib:generate` (user 2026-10-05).\n"
            ."// Baris baru ikut induk dan hak akses baris tetangga {$this->option('sibling')}.\n"
            ."return new class extends Migration\n{\n    protected \$connection = 'tm2sys';\n\n    public function up(): void\n    {\n"
            ."        \$menu = fn () => DB::connection('tm2sys')->table('sys_app_menu');\n"
            ."        \$near = \$menu()->where('code', '{$this->option('sibling')}')->first();\n"
            ."        if (! \$near || \$menu()->where('code', '{$p['menuCode']}')->exists()) {\n            return;\n        }\n"
            ."        \$menu()->insert([\n"
            ."            'parent_id' => \$near->parent_id, 'code' => '{$p['menuCode']}', 'label' => '{$title}', 'icon' => 'fa-solid fa-table-cells',\n"
            ."            'menu_type' => 'item', 'object_type' => 'grid', 'target' => 'v2:{$p['slug']}', 'sort_order' => (int) \$near->sort_order + 1,\n"
            ."            'activate' => 1, 'required_object' => \$near->required_object, 'status_code' => 'READY_FOR_QC',\n"
            ."            'status_updated_by' => 'generator', 'status_updated_at' => now(), 'created_at' => now(),\n"
            ."        ]);\n    }\n\n    public function down(): void\n    {\n"
            ."        DB::connection('tm2sys')->table('sys_app_menu')->where('code', '{$p['menuCode']}')->delete();\n    }\n};\n";
    }

    private function finish(bool $ok, string $message, array $plan = []): int
    {
        $result = ['ok' => $ok, 'message' => $message] + ($plan ? [
            'title' => $plan['title'], 'slug' => $plan['slug'], 'url' => $plan['url'], 'class' => $plan['base'].'Lookup', 'model' => $plan['model'],
            'newModel' => $plan['newModel'], 'columns' => array_column($plan['columns'], 'label'),
            'files' => array_map(fn ($f) => str_replace('\\', '/', Str::after($f, base_path().DIRECTORY_SEPARATOR)), array_keys($this->touched)),
        ] : []);
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $ok ? $this->info($message) : $this->error($message);
            foreach ($result as $k => $v) {
                if (! in_array($k, ['ok', 'message'], true)) {
                    $this->line(str_pad($k, 10).': '.(is_array($v) ? implode(', ', $v) : var_export($v, true)));
                }
            }
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
