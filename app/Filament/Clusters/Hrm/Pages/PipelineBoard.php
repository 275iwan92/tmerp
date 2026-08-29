<?php

namespace App\Filament\Clusters\Hrm\Pages;

use App\Filament\Clusters\Hrm\HrmCluster;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

// Contoh konversi halaman dashboard non-CRUD (kanban drag-drop, BUKAN
// grid/form standar) ke Filament Custom Page (make:filament-page) TANPA
// menulis ulang logikanya -- class ini HANYA pintu navigasi+routing
// Filament; isinya tetap komponen Livewire v4 asli
// (resources/views/pages/recruitment/pipeline-board.blade.php, identifier
// SFC "pages::recruitment.pipeline-board", state/drag-drop/validasi server
// SAMA PERSIS, tidak disentuh) -- lihat view
// resources/views/filament/clusters/hrm/pages/pipeline-board.blade.php.
//
// Data REAL sys_app_menu: parent group HRM_RECRUITMENT label "Recruitment"
// sort_order=50, item HRM_RCR_PIPELINE label "Pipeline Board" sort_order=40.
class PipelineBoard extends Page
{
    protected string $view = 'filament.clusters.hrm.pages.pipeline-board';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedViewColumns;

    protected static ?string $cluster = HrmCluster::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Recruitment';

    protected static ?string $navigationLabel = 'Pipeline Board';

    protected static ?int $navigationSort = 40;

    protected static ?string $title = 'Pipeline Board';
}
