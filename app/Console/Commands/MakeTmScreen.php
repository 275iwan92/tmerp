<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

// Generator kerangka layar Livewire SFC sesuai standar (griduistandard.md,
// FORM_UI_STANDARD.md). Tujuannya hemat token: pola berulang (trait grid,
// toolbar, header navy, tombol Simpan/Back) tidak ditulis ulang tiap layar --
// yang diisi manual cuma kolom, field, dan query khas layar itu.
class MakeTmScreen extends Command
{
    protected $signature = 'make:tm-screen
        {type : master (grid Edit+toggle aktif) | grid (worklist Buka+Confirm) | form}
        {view : nama view pages::, mis. master.gudang atau master.gudang.index}
        {--model= : class model relatif App\\Models, mis. Master\\WhWarehouse}
        {--title= : judul layar}
        {--route= : route tujuan klik 2x (grid) / route edit setelah create (form)}
        {--create-route= : route "+ New Record" (master), default <route>.create}
        {--list-route= : route Back (form)}
        {--param= : nama parameter route, default "record"}
        {--name-col= : kolom nama (grid)}
        {--code-col= : kolom kode (grid)}
        {--fields= : kolom form dipisah koma}
        {--force : timpa file yang sudah ada}';

    protected $description = 'Buat kerangka layar TMERP (grid/master/form) dari stub standar';

    public function handle(): int
    {
        $type = $this->argument('type');
        if (! in_array($type, ['master', 'grid', 'form'], true)) {
            $this->error('type harus master|grid|form');

            return self::FAILURE;
        }

        $modelFqn = 'App\\Models\\'.ltrim((string) $this->option('model'), '\\');
        if (! $this->option('model') || ! class_exists($modelFqn)) {
            $this->error("Model tidak ditemukan: {$modelFqn}");

            return self::FAILURE;
        }

        $model = new $modelFqn;
        $view = $this->argument('view');
        $path = resource_path('views/pages/'.str_replace('.', '/', $view).'.blade.php');

        if (File::exists($path) && ! $this->option('force')) {
            $this->error("Sudah ada: {$path} (pakai --force)");

            return self::FAILURE;
        }

        $fillable = $model->getFillable();
        $route = $this->option('route') ?: 'TODO.route';
        $param = $this->option('param') ?: 'record';

        $vars = [
            'MODEL_FQN' => $modelFqn,
            'MODEL' => class_basename($modelFqn),
            'PK' => $model->getKeyName(),
            'TITLE' => $this->option('title') ?: Str::headline(class_basename($modelFqn)),
            'GRID_KEY' => $view,
            'T_PREFIX' => str_replace(['.', '-'], '_', $view),
            'ROUTE_PARAM' => $param,
            'OPEN_ROUTE' => $route,
            'EDIT_ROUTE' => $route,
            'LIST_ROUTE' => $this->option('list-route') ?: 'TODO.list',
            'NAME_COL' => $this->option('name-col') ?: $this->guessColumn($fillable, ['_name', 'name'], 'name'),
            'CODE_COL' => $this->option('code-col') ?: $this->guessColumn($fillable, ['_code', '_no', 'code'], 'code'),
        ];

        $vars += $type === 'form' ? $this->formVars() : $this->gridVars($type, $route);

        $stub = File::get(base_path('stubs/tm-screen/'.($type === 'form' ? 'form' : 'grid').'.stub'));
        // 2 putaran: blok sisipan (ACTION_CELL, EXTRA_METHODS) sendiri berisi {{MODEL}}/{{PK}}.
        for ($pass = 0; $pass < 2; $pass++) {
            foreach ($vars as $key => $value) {
                $stub = str_replace('{{'.$key.'}}', $value, $stub);
            }
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $stub);

        $this->info("Dibuat: {$path}");
        $this->line("Tambahkan route di routes/web.php (grup module: yang sesuai), mis.:");
        $this->line("  Route::livewire('/uri', 'pages::{$view}')->name('...');");
        if (str_contains($stub, 'TODO.')) {
            $this->warn('Masih ada route TODO.* -- isi --route/--list-route atau edit manual.');
        }

        return self::SUCCESS;
    }

    private function guessColumn(array $fillable, array $suffixes, string $fallback): string
    {
        foreach ($suffixes as $suffix) {
            foreach ($fillable as $col) {
                if (str_ends_with($col, $suffix)) {
                    return $col;
                }
            }
        }

        return $fallback;
    }

    private function gridVars(string $type, string $route): array
    {
        $pk = '$row->'.'{{PK}}';
        $indent = str_repeat(' ', 40);

        if ($type === 'master') {
            $create = $this->option('create-route') ?: Str::beforeLast($route, '.').'.create';

            return [
                'STAT_KEY' => 'active', 'STAT_COL' => 'activate', 'STAT_VAL' => 'true', 'STAT_TONE' => 'active', 'STAT_LABEL' => 'Aktif',
                'NEW_RECORD' => <<<BLADE
                <x-slot:newRecord>
                    <a href="{{ route('{$create}') }}"
                        class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] no-underline cursor-pointer">+ New Record</a>
                </x-slot:newRecord>
BLADE,
                'ACTION_CELL' => $indent."<x-grid.action-dropdown :actions=\"[['icon' => '✎', 'label' => Translator::t('fin_coa_action_edit', 'Edit'), 'action' => 'openItem('.{$pk}.')', 'tooltip' => Translator::t('hrm_emp_dblclick_detail_hint', 'Klik 2x untuk buka detail')]]\" />\n"
                    .$indent."<x-grid.toggle-switch :on=\"(bool) \$row->activate\" action=\"toggleActive({{ {$pk} }})\" :confirm=\"\$row->activate ? Translator::t('fin_coa_deactivate', 'Nonaktifkan').'?' : Translator::t('fin_coa_activate', 'Aktifkan').'?'\" />",
                'EXTRA_METHODS' => <<<'PHP'

    public function toggleActive(int $id): void
    {
        $r = {{MODEL}}::findOrFail($id);
        $r->activate = ! $r->activate;
        $r->updated_by = Auth::user()->login;
        $r->save();

        unset($this->items, $this->stats);
    }
PHP,
            ];
        }

        return [
            'STAT_KEY' => 'draft', 'STAT_COL' => 'stat_confirm', 'STAT_VAL' => 'false', 'STAT_TONE' => 'warn', 'STAT_LABEL' => 'Draft',
            'NEW_RECORD' => '',
            'ACTION_CELL' => $indent."<x-grid.action-icon icon=\"↗\" :title=\"Translator::t('shared_open', 'Buka')\" action=\"openItem({{ {$pk} }})\" />\n"
                .$indent."@if (! \$row->stat_confirm)\n"
                .$indent."    <x-grid.action-icon icon=\"✓\" variant=\"success\" :title=\"Translator::t('shared_confirm', 'Confirm')\" action=\"confirmItem({{ {$pk} }})\" :confirm=\"Translator::t('shared_confirm_prompt', 'Confirm dokumen ini?')\" />\n"
                .$indent.'@endif',
            'EXTRA_METHODS' => <<<'PHP'

    public function confirmItem(int $id): void
    {
        {{MODEL}}::whereKey($id)->where('stat_confirm', false)->update([
            'stat_confirm' => true,
            'updated_by' => Auth::user()->login,
        ]);

        unset($this->items, $this->stats);
    }
PHP,
        ];
    }

    private function formVars(): array
    {
        $fields = array_filter(array_map('trim', explode(',', (string) $this->option('fields'))));
        $props = $mount = $rules = $inputs = [];

        foreach ($fields as $f) {
            $label = Str::headline($f);
            if ($f === 'activate' || str_starts_with($f, 'is_')) {
                $props[] = "    public bool \${$f} = true;\n";
                $mount[] = "            \$this->{$f} = (bool) \$r->{$f};";
                $rules[] = "            '{$f}' => 'boolean',";
                $inputs[] = "                <x-form.checkbox wire:model=\"{$f}\" label=\"{$label}\" />";

                continue;
            }
            $props[] = "    public string \${$f} = '';\n";
            $mount[] = "            \$this->{$f} = (string) \$r->{$f};";
            $rules[] = "            '{$f}' => 'required|string|max:255',";
            $inputs[] = "                <x-form.input label=\"{$label}\" wire:model=\"{$f}\" name=\"{$f}\" />";
        }

        return [
            'PROPS' => implode("\n", $props),
            'MOUNT' => implode("\n", $mount),
            'RULES' => implode("\n", $rules),
            'FIELDS' => implode("\n", $inputs) ?: '                {{-- field di sini, lihat resources/views/components/_manifest.yaml --}}',
        ];
    }
}
