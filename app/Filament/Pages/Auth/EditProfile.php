<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

// User Profile /admin -- tambahan "Tampilan Dashboard" (docs/admindashboardstandard.md
// §10, mockup docs/mockups/mockup_dashboardcolor.html): preferensi warna
// dashboard PERSONAL per-user (sec_user.admin_theme_mode/admin_theme_accent),
// beda dari sys_theme shell lama. Hidden field nampung state, UI aslinya
// custom Blade+Alpine (View component) persis mockup -- disimpan via mekanisme
// save() BAWAAN EditProfile (handleRecordUpdate() -> $record->update($data)),
// tidak perlu override save() sendiri karena kedua kolom sudah $fillable di
// SecUser.
class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            ...parent::form($schema)->getComponents(),
            $this->getDashboardThemeSectionComponent(),
        ]);
    }

    protected function getDashboardThemeSectionComponent(): Component
    {
        return Section::make('Tampilan Dashboard')
            ->description('Pilih mode & warna aksen dashboard -- cuma berlaku utk akun Anda.')
            ->schema([
                Hidden::make('admin_theme_mode')->default('light'),
                Hidden::make('admin_theme_accent')->default('blue'),
                View::make('filament.forms.dashboard-theme-picker'),
            ]);
    }
}
