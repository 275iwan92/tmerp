<?php

namespace App\Support\V2\Grid\Setup;

use App\Models\Sys\SecModule;
use App\Models\Sys\SecUser;
use App\Models\Sys\SysHelpLine;
use App\Models\Sys\SysHelpTopic;
use App\Services\Help\HelpCenter;
use App\Support\Localization\Dictionary;
use App\Support\V2\Grid\DbGrid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// Help Center (semua user): satu baris = satu layar / laporan / panduan; baris dibuka = langkah cara pakai + penjelasan tiap
// isian. Isi dari sys_help_topic / sys_help_line, tampil dalam bahasa layar lewat kamus konteks 'help'.
class HelpCenterReport extends DbGrid
{
    private array $modules = [];

    public function slug(): string
    {
        return 'help-center';
    }

    public function title(): string
    {
        return 'Help Center';
    }

    public function module(): string
    {
        return SecUser::PUBLIC_MODULE;
    }

    public function moduleLabel(): string
    {
        return 'General';
    }

    public function group(): string
    {
        return 'Help';
    }

    public function family(): string
    {
        return self::REPORT;
    }

    public function icon(): string
    {
        return 'fa-solid fa-life-ring';
    }

    public function search(): string
    {
        return 'Search screen, purpose or keyword...';
    }

    public function guide(): ?string
    {
        return 'One row = one screen, report or general guide. Open a row to read how to use it and what every field means. Search by screen name or by what you want to do.';
    }

    protected function models(): array
    {
        return [SysHelpTopic::class, SysHelpLine::class];
    }

    protected function query(): Builder
    {
        return SysHelpTopic::query()->where('activate', true)->with(['lines' => fn ($q) => $q->where('activate', true)])
            ->orderByRaw("FIELD(topic_type, 'GUIDE', 'INPUT', 'REPORT')")->orderBy('module_code')->orderBy('sort_order')->orderBy('title');
    }

    protected function prefetch(Collection $items): void
    {
        $this->modules = SecModule::query()->pluck('module_name', 'module_code')->all();
    }

    protected function row(Model $m): array
    {
        $help = fn (?string $en) => Dictionary::t($en, HelpCenter::CONTEXT);

        return [
            'title' => $m->title, 'type' => SysHelpTopic::TYPES[$m->topic_type] ?? $m->topic_type,
            'module' => $this->modules[$m->module_code] ?? t('General'), 'purpose' => $help($m->purpose), 'keywords' => $m->keywords,
            'lines' => $m->lines->map(fn (SysHelpLine $l) => [
                'kind' => $l->line_type === SysHelpLine::STEP ? Dictionary::t('How to use') : Dictionary::t('Field'),
                'field' => $l->line_type === SysHelpLine::STEP ? (string) ($m->lines->where('line_type', SysHelpLine::STEP)->values()->search($l) + 1) : Dictionary::t($l->field_label),
                'text' => $help($l->body),
            ])->all(),
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'title', 'label' => 'Screen'],
            ['key' => 'type', 'label' => 'Type', 'type' => 'badge', 'tones' => ['Input screen' => 'info', 'Report' => 'accent', 'General guide' => 'success']],
            ['key' => 'module', 'label' => 'Module'],
            ['key' => 'purpose', 'label' => 'What it is for'],
            ['key' => 'keywords', 'label' => 'Keywords', 'visible' => false],
        ];
    }

    public function detail(): array
    {
        return ['key' => 'lines', 'title' => 'How to use and field explanations', 'columns' => [
            ['key' => 'kind', 'label' => 'Kind'],
            ['key' => 'field', 'label' => 'Step / Field', 'strong' => true],
            ['key' => 'text', 'label' => 'Explanation'],
        ]];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'General guide', 'where' => ['type' => 'General guide'], 'tone' => 'success'],
            ['label' => 'Input screen', 'where' => ['type' => 'Input screen'], 'tone' => 'info'],
            ['label' => 'Report', 'where' => ['type' => 'Report'], 'tone' => 'accent'],
        ];
    }

    public function filters(): array
    {
        return ['module' => 'Module', 'type' => 'Type'];
    }

    public function groups(): array
    {
        return ['module' => 'Module', 'type' => 'Type'];
    }
}
