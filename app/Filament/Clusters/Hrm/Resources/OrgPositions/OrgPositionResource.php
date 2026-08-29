<?php

namespace App\Filament\Clusters\Hrm\Resources\OrgPositions;

use App\Filament\Clusters\Hrm\HrmCluster;
use App\Filament\Clusters\Hrm\Resources\OrgPositions\Pages\ManageOrgPositions;
use App\Models\Master\OrgPosition;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

// Terjemahan contoh dari resources/views/pages/hrm/position.blade.php (menu
// code HRM_ORG_STRUCTURE, "Master Posisi"). Data REAL sys_app_menu: parent
// group HRM_ORG label "Organization" sort_order=5 (group PALING ATAS di
// grup HRM, lebih dulu dari "Employee"), item ini sort_order=10. Posisi
// dipertahankan persis lewat properti statis di bawah -- lihat catatan yang
// sama di EmpEmployeeResource.
class OrgPositionResource extends Resource
{
    protected static ?string $model = OrgPosition::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $cluster = HrmCluster::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Organization';

    protected static ?string $navigationLabel = 'Master Posisi';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('position_code')
                    ->label('Kode Posisi')
                    ->required()
                    ->maxLength(30),
                TextInput::make('position_name')
                    ->label('Nama Posisi')
                    ->required()
                    ->maxLength(150),
                Select::make('department_id')
                    ->label('Departemen')
                    ->relationship('department', 'department_name')
                    ->searchable()
                    ->preload(),
                Select::make('reports_to_position_id')
                    ->label('Melapor ke')
                    ->relationship('reportsTo', 'position_name')
                    ->searchable()
                    ->preload(),
                Toggle::make('activate')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('position_code')
                    ->label('Kode')
                    ->searchable(),
                TextColumn::make('position_name')
                    ->label('Nama Posisi')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('department.department_name')
                    ->label('Departemen')
                    ->placeholder('—'),
                TextColumn::make('reportsTo.position_name')
                    ->label('Melapor ke')
                    ->placeholder('—'),
                IconColumn::make('activate')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOrgPositions::route('/'),
        ];
    }
}
