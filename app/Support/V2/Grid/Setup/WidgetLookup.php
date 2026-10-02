<?php

namespace App\Support\V2\Grid\Setup;

use App\Models\Sys\SecModule;
use App\Models\Sys\SysDashboard;
use App\Models\Sys\SysWidget;
use App\Support\V2\Board\WidgetCatalog;
use App\Support\V2\Grid\DbLookup;
use Devlib\Components\WidgetBoard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

// Setup > Opening Board Widgets: katalog widget opening screen. Isi widget dihitung kelas program (satu Key = satu kelas);
// yang dirawat di sini: judul, kelompok, siapa yang boleh memasang (modul + level security), lebar bawaan, frasa asisten, aktif.
class WidgetLookup extends DbLookup
{
    public function slug(): string
    {
        return 'setup-widgets';
    }

    public function title(): string
    {
        return 'Opening Board Widgets';
    }

    public function module(): string
    {
        return 'SETUP';
    }

    public function moduleLabel(): string
    {
        return 'Setup';
    }

    public function group(): string
    {
        return 'Settings';
    }

    public function icon(): string
    {
        return 'fa-solid fa-table-cells-large';
    }

    public function guide(): ?string
    {
        // Paling banyak 255 huruf (batas kamus terjemahan).
        return 'Widgets of the opening dashboards. The content is calculated by the program; here you set the title, the dashboard it belongs to, '
            .'who may see it (module and minimum level), the default width and the words the assistant listens for.';
    }

    protected function query(): Builder
    {
        app(WidgetCatalog::class)->all(); // kelas widget baru mendapat barisnya sebelum daftar dibaca

        return SysWidget::query()->orderBy('sort_order')->orderBy('title');
    }

    public function columns(): array
    {
        return [
            ['key' => 'widget_key', 'label' => 'Key', 'required' => true, 'unique' => true, 'lockSaved' => true, 'width' => '170px'],
            ['key' => 'title', 'label' => 'Title', 'required' => true, 'width' => '230px'],
            ['key' => 'description', 'label' => 'Description', 'width' => '380px'],
            ['key' => 'dashboard_code', 'label' => 'Dashboard', 'type' => 'select', 'options' => SysDashboard::query()->where('dashboard_code', '<>', SysDashboard::OVERALL)
                ->orderBy('sort_order')->pluck('title', 'dashboard_code')->all(), 'width' => '170px'],
            ['key' => 'group_name', 'label' => 'Group', 'required' => true, 'width' => '120px'],
            ['key' => 'module_code', 'label' => 'Module', 'type' => 'select', 'options' => SecModule::query()->orderBy('sort_order')->pluck('module_name', 'module_code')->all(), 'width' => '170px'],
            ['key' => 'min_level', 'label' => 'Minimum level', 'type' => 'number', 'width' => '110px'],
            ['key' => 'default_size', 'label' => 'Width (of 12)', 'type' => 'select', 'options' => array_combine(WidgetBoard::SIZES, WidgetBoard::SIZES), 'required' => true, 'width' => '110px'],
            ['key' => 'phrases', 'label' => 'Assistant words', 'width' => '420px'],
            ['key' => 'sort_order', 'label' => 'Order', 'type' => 'number', 'width' => '80px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '70px'],
        ];
    }

    protected function rules(): array
    {
        return ['title' => ['string', 'max:100'], 'description' => ['nullable', 'string', 'max:255'], 'group_name' => ['string', 'max:40'], 'phrases' => ['nullable', 'string', 'max:255'],
            'min_level' => ['nullable', 'integer', 'min:0', 'max:9'], 'default_size' => [Rule::in(WidgetBoard::SIZES)], 'module_code' => ['nullable', Rule::exists(SecModule::class, 'module_code')],
            'dashboard_code' => ['nullable', Rule::exists(SysDashboard::class, 'dashboard_code')]];
    }

    protected function prepare(array $in): array
    {
        $in['min_level'] = ($in['min_level'] ?? '') === '' || $in['min_level'] === null ? 0 : $in['min_level'];

        return $in;
    }

    // Baris hanya berarti bila ada kelas widget-nya: Key harus salah satu widget terdaftar.
    protected function check(array $in, ?int $id): ?string
    {
        $key = $id ? SysWidget::query()->whereKey($id)->value('widget_key') : ($in['widget_key'] ?? null);
        $known = array_map(fn (string $class) => (new $class)->key(), WidgetCatalog::CLASSES);

        return in_array($key, $known, true) ? null : t('This Key has no program class. Ask the developer to add the widget first.');
    }
}
