<?php

/*
 * REMARK
 * Tujuan       : Menjaga Master Supplier v2: simpan supplier (aturan unik / panjang / tarif pajak 0-100, area dari pencarian wilayah), aktif / nonaktif, Block / Unblock, Delete (hapus bila bersih, nonaktif bila masih dipakai), kontak dan cabang tambahan (satu default, terbatas pada supplier itu), form dan grid terbuka, v1 sudah pindah ke arsip.
 * Sumber hulu  : App\Services\Master\SupplierMasterService, SupplierGuardService, layar md-supplier-form, grid md-supplier, routes/v2_master.php.
 * Input        : User johiwan (admin); supplier uji berkode ZZTEST* di tm2mst.sup_supplier (+ kontak dan cabangnya), dihapus di tearDown; uji Delete pada supplier yang sudah dipakai berjalan di transaksi tm2mst yang dibatalkan.
 * Output       : Lulus bila aturan terpenuhi dan tidak ada jalur ke halaman v1 master.supplier.
 * Dipakai oleh : php artisan test --filter=SupplierMasterServiceTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Master\SupSupplier;
use App\Models\Master\SupSupplierBranch;
use App\Models\Master\SupSupplierContact;
use App\Models\Sys\SecUser;
use App\Services\Master\SupplierGuardService;
use App\Services\Master\SupplierMasterService;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Grid\Grids;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SupplierMasterServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        while (DB::connection('tm2mst')->transactionLevel() > 0) {
            DB::connection('tm2mst')->rollBack();
        }
        $ids = SupSupplier::withoutGlobalScopes()->where('supplier_code', 'like', 'ZZTEST%')->pluck('id_supplier');
        SupSupplierBranch::whereIn('supplier_id', $ids)->delete();
        SupSupplierContact::whereIn('supplier_id', $ids)->delete();
        SupSupplier::withoutGlobalScopes()->whereIn('id_supplier', $ids)->delete();
        $this->assertSame(0, SupSupplier::withoutGlobalScopes()->where('supplier_code', 'like', 'ZZTEST%')->count(), 'supplier uji harus bersih');
        parent::tearDown();
    }

    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    private function input(array $over = []): array
    {
        return $over + ['supplier_code' => 'ZZTEST-S1', 'supplier_name' => 'ZZTEST Supplier', 'is_subcon' => 1, 'tax_purchase_value' => '11,5', 'tax_service_value' => '2',
            'email' => 'zz@example.com', 'default_area' => 'VILLAGE:53961', 'ship_postal_code' => '65117', 'default_address' => 'Jl. Uji 1'];
    }

    public function test_supplier_is_saved_with_area_flags_and_defaults(): void
    {
        $svc = app(SupplierMasterService::class);
        $s = $svc->save(null, $this->input(), $this->admin());

        $this->assertTrue((bool) $s->activate);
        $this->assertFalse((bool) $s->is_blacklisted);
        $this->assertTrue((bool) $s->is_subcon);
        $this->assertSame('johiwan', $s->created_by);
        $this->assertSame(53961, (int) $s->default_area_id);
        $this->assertSame('VILLAGE', $s->default_area_level);
        $this->assertSame('65117', $s->postal_code);
        $this->assertSame(11.5, (float) $s->tax_purchase_value);

        $s2 = $svc->save($s, $this->input(['supplier_name' => 'ZZTEST Renamed', 'is_subcon' => 0, 'default_area' => '']), $this->admin());
        $this->assertSame('ZZTEST Renamed', $s2->supplier_name);
        $this->assertFalse((bool) $s2->is_subcon);
        $this->assertNull($s2->default_area_id);

        $svc->setActive($s2, false, $this->admin());
        $this->assertFalse((bool) $s2->fresh()->activate);
        $this->assertTrue($svc->toggleBlock($s2, $this->admin()));
        $this->assertFalse($svc->toggleBlock($s2->fresh(), $this->admin()));
    }

    public function test_rules_are_refused(): void
    {
        $svc = app(SupplierMasterService::class);
        $svc->save(null, $this->input(), $this->admin());
        foreach ([$this->input(), $this->input(['supplier_code' => 'ZZTEST-S2', 'supplier_name' => '']), $this->input(['supplier_code' => 'ZZTEST-S3', 'tax_purchase_value' => '101']),
            $this->input(['supplier_code' => 'ZZTEST-S4', 'tax_service_value' => '-1']), $this->input(['supplier_code' => 'ZZTEST-S5', 'email' => 'bad']), $this->input(['supplier_code' => 'ZZTEST-S6', 'category' => 'NO-SUCH'])] as $bad) {
            try {
                $svc->save(null, $bad, $this->admin());
                $this->fail('input must be refused');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
        $this->assertSame(1, SupSupplier::withoutGlobalScopes()->where('supplier_code', 'like', 'ZZTEST%')->count());
    }

    public function test_delete_removes_a_clean_supplier_and_only_deactivates_a_used_one(): void
    {
        $svc = app(SupplierMasterService::class);
        $user = $this->admin();
        $clean = $svc->save(null, $this->input(), $user);
        $svc->saveBranch($clean, null, ['branch_name' => 'B'], $user);
        $svc->saveContact($clean, null, ['contact_name' => 'K'], $user);
        $this->assertSame([], app(SupplierGuardService::class)->blockingReasons($clean->id_supplier));

        $this->assertSame([], $svc->delete($clean, $user));
        $this->assertNull(SupSupplier::find($clean->id_supplier));
        $this->assertSame(0, SupSupplierBranch::where('supplier_id', $clean->id_supplier)->count());
        $this->assertSame(0, SupSupplierContact::where('supplier_id', $clean->id_supplier)->count());

        $used = SupSupplier::withoutGlobalScopes()->where('activate', true)->whereIn('id_supplier', DB::connection('tm2acc')->table('fin_invoice')->where('invoice_type', 'SUPPLIER_BILL')->select('partner_id'))->first();
        if (! $used) {
            $this->markTestSkipped('No supplier with a vendor bill in this database.');
        }
        DB::connection('tm2mst')->beginTransaction();
        $reasons = $svc->delete($used, $user);
        $this->assertContains('vendor bills', $reasons);
        $this->assertNotNull(SupSupplier::withoutGlobalScopes()->find($used->id_supplier));
        $this->assertFalse((bool) SupSupplier::withoutGlobalScopes()->find($used->id_supplier)->activate);
        DB::connection('tm2mst')->rollBack();
        $this->assertTrue((bool) SupSupplier::withoutGlobalScopes()->find($used->id_supplier)->activate);
    }

    public function test_contacts_and_branches_keep_one_default_and_stay_inside_their_supplier(): void
    {
        $svc = app(SupplierMasterService::class);
        $user = $this->admin();
        $s = $svc->save(null, $this->input(), $user);
        $other = $svc->save(null, $this->input(['supplier_code' => 'ZZTEST-OTHER', 'supplier_name' => 'ZZTEST Other']), $user);

        $k1 = $svc->saveContact($s, null, ['contact_name' => 'First', 'is_default' => 1], $user);
        $k2 = $svc->saveContact($s, null, ['contact_name' => 'Second', 'is_default' => 1], $user);
        $this->assertFalse((bool) $k1->fresh()->is_default);
        $this->assertTrue((bool) $k2->fresh()->is_default);
        $svc->setDefaultContact($s, $k1->id_supplier_contact);
        $this->assertTrue((bool) $k1->fresh()->is_default);

        $b1 = $svc->saveBranch($s, null, ['branch_name' => 'Surabaya', 'branch_area' => 'VILLAGE:53961', 'ship_postal_code' => '65117', 'is_default' => 1], $user);
        $this->assertSame(53961, (int) $b1->area_id);
        $b2 = $svc->saveBranch($s, null, ['branch_name' => 'Malang', 'is_default' => 1], $user);
        $this->assertFalse((bool) $b1->fresh()->is_default);
        $this->assertTrue((bool) $b2->fresh()->is_default);

        $svc->deleteBranch($other, $b1->id_supplier_branch);
        $svc->deleteContact($other, $k1->id_supplier_contact);
        $this->assertNotNull(SupSupplierBranch::find($b1->id_supplier_branch), 'another supplier cannot delete this branch');
        $this->assertNotNull(SupSupplierContact::find($k1->id_supplier_contact));
        $svc->deleteBranch($s, $b1->id_supplier_branch);
        $svc->deleteContact($s, $k1->id_supplier_contact);
        $this->assertNull(SupSupplierBranch::find($b1->id_supplier_branch));
        $this->assertNull(SupSupplierContact::find($k1->id_supplier_contact));

        $this->expectException(ValidationException::class);
        $svc->saveContact($s, null, ['contact_name' => ''], $user);
    }

    public function test_forms_and_routes_work_for_admin(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('v2.screen', 'md-supplier'))->assertOk();
        $this->get(route('v2.screen', 'md-supplier-form').'?new=1')->assertOk()->assertSee('New Supplier');

        $this->post(route('v2.md.supplier.save'), $this->input(['supplier_code' => 'ZZTEST-ROUTE']))->assertRedirect();
        $s = SupSupplier::where('supplier_code', 'ZZTEST-ROUTE')->firstOrFail();
        $this->get(route('v2.screen', 'md-supplier-form').'?id='.$s->id_supplier)->assertOk()->assertSee('ZZTEST Supplier')->assertSee('Delete');
        $this->post(route('v2.md.supplier.save'), $this->input(['supplier_code' => 'ZZTEST-ROUTE', 'supplier_name' => '']))->assertSessionHasErrors('supplier_name');

        $this->post(route('v2.md.supplier.contact', $s->id_supplier), ['contact_name' => 'Route contact', 'is_default' => 1])->assertRedirect();
        $k = SupSupplierContact::where('supplier_id', $s->id_supplier)->firstOrFail();
        $this->post(route('v2.md.supplier.branch', $s->id_supplier), ['branch_name' => 'Route branch'])->assertRedirect();
        $b = SupSupplierBranch::where('supplier_id', $s->id_supplier)->firstOrFail();
        $this->get(route('v2.screen', 'md-supplier-form').'?id='.$s->id_supplier.'&tab=contact')->assertOk()->assertSee('Route contact');
        $this->post(route('v2.md.supplier.contact.delete', [$s->id_supplier, $k->id_supplier_contact]))->assertRedirect();
        $this->post(route('v2.md.supplier.branch.delete', [$s->id_supplier, $b->id_supplier_branch]))->assertRedirect();

        $this->post(route('v2.md.supplier.save'), ['id' => $s->id_supplier, 'action' => 'block'])->assertRedirect();
        $this->assertTrue((bool) $s->fresh()->is_blacklisted);
        $this->get(route('v2.screen', 'md-supplier-form').'?id='.$s->id_supplier)->assertOk()->assertSee('Unblock');
        $this->post(route('v2.md.supplier.save'), ['id' => $s->id_supplier, 'action' => 'deactivate'])->assertRedirect();
        $this->assertFalse((bool) $s->fresh()->activate);
        $this->post(route('v2.md.supplier.save'), ['id' => $s->id_supplier, 'action' => 'delete'])->assertRedirect(route('v2.screen', 'md-supplier'));
        $this->assertNull(SupSupplier::find($s->id_supplier));
    }

    public function test_grid_and_v1_state(): void
    {
        $grid = Grids::find('md-supplier');
        $this->assertStringContainsString('/v2/s/md-supplier-form?id={id}', $grid->openUrl());
        $this->assertStringContainsString('/v2/s/md-supplier-form?new=1', $grid->newUrl());

        $this->assertFileDoesNotExist(resource_path('views/pages/master/supplier.blade.php'));
        $this->assertArrayNotHasKey('MD_SUPP', MenuRoutes::map());
        $this->actingAs($this->admin())->get('/master/supplier')->assertRedirect('/v2/s/md-supplier');
    }
}
