<?php

namespace App\Filament\Clusters\Hrm\Resources\EmpEmployees;

use App\Filament\Clusters\Hrm\HrmCluster;
use App\Filament\Clusters\Hrm\Resources\EmpEmployees\Pages\ManageEmpEmployees;
use App\Models\Master\EmpEmployee;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

// Terjemahan contoh dari layar lama resources/views/pages/hrm/employee.blade.php
// (menu code HRM_EMPLOYEE, "Master Employee") -- data REAL dari sys_app_menu
// (lihat migration 2026_08_29_000004): parent group HRM_MASTER label
// "Employee" sort_order=10, item ini sort_order=10. Posisi dipertahankan
// PERSIS lewat $cluster+$navigationGroup+$navigationSort di bawah, sesuai
// syarat mutlak user (tidak merombak hierarki/urutan menu lama).
class EmpEmployeeResource extends Resource
{
    protected static ?string $model = EmpEmployee::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    // Level "Module" (HRM) -- lihat App\Filament\Clusters\Hrm\HrmCluster.
    protected static ?string $cluster = HrmCluster::class;

    // Level "Group" (HRM_MASTER, label "Employee", sort_order=10 di DB).
    // Tipe properti di parent Resource:: adalah UnitEnum|string|null -- override
    // properti (beda dari override method) di PHP HARUS invariant persis, jadi
    // WAJIB ikut union type ini walau isinya cuma string biasa (bukan enum).
    protected static string|\UnitEnum|null $navigationGroup = 'Employee';

    // Level "Item" (HRM_EMPLOYEE, label "Master Employee", sort_order=10).
    protected static ?string $navigationLabel = 'Master Employee';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('employee_code')
                    ->label('Kode Karyawan')
                    ->required()
                    ->maxLength(30),
                TextInput::make('full_name')
                    ->label('Nama Lengkap')
                    ->required()
                    ->maxLength(150),
                Select::make('position_id')
                    ->label('Posisi')
                    ->relationship('position', 'position_name')
                    ->searchable()
                    ->preload(),
                TextInput::make('nik_ktp')
                    ->label('No. KTP')
                    ->maxLength(20),
                TextInput::make('phone')
                    ->label('No. Telepon')
                    ->tel()
                    ->maxLength(30),
                DatePicker::make('join_date')
                    ->label('Tanggal Bergabung'),
                Select::make('work_status')
                    ->label('Status Kerja')
                    ->options([
                        'PERMANENT' => 'Karyawan Tetap',
                        'CONTRACT' => 'Kontrak',
                        'PROBATION' => 'Masa Percobaan',
                    ]),
                Toggle::make('activate')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee_code')
                    ->label('Kode')
                    ->searchable(),
                TextColumn::make('full_name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('position.position_name')
                    ->label('Posisi')
                    ->placeholder('—'),
                TextColumn::make('join_date')
                    ->label('Bergabung')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('work_status')
                    ->label('Status Kerja')
                    ->badge(),
                IconColumn::make('activate')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('work_status')
                    ->label('Status Kerja')
                    ->options([
                        'PERMANENT' => 'Karyawan Tetap',
                        'CONTRACT' => 'Kontrak',
                        'PROBATION' => 'Masa Percobaan',
                    ]),
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
            'index' => ManageEmpEmployees::route('/'),
        ];
    }
}
