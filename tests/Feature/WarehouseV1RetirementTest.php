<?php

/*
 * REMARK
 * Tujuan       : Menjaga penghapusan Master Warehouse v1 (Master T4d): tiga halaman v1 dan grid md-warehouse hilang, rute lama mengalihkan ke lembar Gedung dan Cetak Label Lokasi, menu MD_WH dan INV_REF_WH membuka lembar Gedung, dan lembar Gedung menerima tipe VIRTUAL.
 * Sumber hulu  : routes/web.php, App\Support\Menu\MenuRoutes, App\Support\V2\Grid\Inv\BuildingLookup, migration 2026_10_19_000010, tm2sys.sys_app_menu.
 * Input        : User johiwan (admin); hanya membaca (tidak ada data yang ditulis).
 * Output       : Lulus bila tidak ada jalur ke halaman v1 master.warehouse.
 * Dipakai oleh : php artisan test --filter=WarehouseV1RetirementTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Sys\SecUser;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Grid\Grids;
use App\Support\V2\Grid\Inv\BuildingLookup;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WarehouseV1RetirementTest extends TestCase
{
    public function test_v1_pages_and_grid_are_gone_and_old_urls_redirect(): void
    {
        $this->assertDirectoryDoesNotExist(resource_path('views/pages/master/warehouse'));
        $this->assertNull(Grids::find('md-warehouse'));
        foreach (['MD_WH', 'INV_REF_WH'] as $code) {
            $this->assertArrayNotHasKey($code, MenuRoutes::map());
            $this->assertSame('v2:wh-buildings', DB::connection('tm2sys')->table('sys_app_menu')->where('code', $code)->value('target'));
        }

        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());
        $this->get('/master/warehouse')->assertRedirect('/v2/s/wh-buildings');
        $this->get('/master/warehouse/create')->assertRedirect('/v2/s/wh-buildings');
        $this->get('/master/warehouse/16/edit')->assertRedirect('/v2/s/wh-buildings');
        $this->get('/master/warehouse/16/print-labels')->assertRedirect('/v2/s/label-location');
        $this->get(route('v2.screen', 'wh-buildings'))->assertOk();
        $this->get(route('v2.screen', 'wh-bins'))->assertOk();
        $this->get(route('v2.screen', 'label-location'))->assertOk();
    }

    public function test_buildings_sheet_accepts_the_virtual_type(): void
    {
        $sheet = new BuildingLookup;
        $type = collect($sheet->columns())->firstWhere('key', 'warehouse_type');
        $this->assertArrayHasKey('VIRTUAL', $type['options']);

        $rules = (new \ReflectionMethod($sheet, 'rules'))->invoke($sheet);
        $this->assertTrue(\Illuminate\Support\Facades\Validator::make(['warehouse_type' => 'VIRTUAL'], ['warehouse_type' => $rules['warehouse_type']])->passes());
        $this->assertTrue(\Illuminate\Support\Facades\Validator::make(['warehouse_type' => 'BOGUS'], ['warehouse_type' => $rules['warehouse_type']])->fails());
    }
}
