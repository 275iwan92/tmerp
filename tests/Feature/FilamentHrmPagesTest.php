<?php

namespace Tests\Feature;

use App\Filament\Clusters\Hrm\Resources\EmpEmployees\EmpEmployeeResource;
use App\Filament\Clusters\Hrm\Resources\OrgPositions\OrgPositionResource;
use App\Models\Sys\SecUser;
use Tests\TestCase;

// UI-10: form Create/Edit panel /admin = halaman penuh turunan App\Filament\Base\*,
// bukan modal di atas grid (modal menelan klik sidebar).
class FilamentHrmPagesTest extends TestCase
{
    public function test_list_create_edit_are_full_pages_with_standard_labels(): void
    {
        $this->actingAs(SecUser::find(1));

        foreach ([OrgPositionResource::class, EmpEmployeeResource::class] as $resource) {
            $model = new ($resource::getModel());
            $record = $model->newQuery()->where($model->getKeyName(), '>', 1)->value($model->getKeyName());

            $this->get($resource::getUrl('index'))->assertOk()
                ->assertSee($resource::getUrl('create'), false);

            $this->get($resource::getUrl('create'))->assertOk()
                ->assertSee('Simpan')->assertSee('Batal')
                ->assertDontSee('Create &amp; create another', false);

            $this->get($resource::getUrl('edit', ['record' => $record]))->assertOk()
                ->assertSee('Simpan')->assertSee('Batal');
        }
    }
}
