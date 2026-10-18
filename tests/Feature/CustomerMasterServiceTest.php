<?php

/*
 * REMARK
 * Tujuan       : Menjaga Master Customer v2: simpan customer (aturan unik / panjang / daftar nilai, area dari pencarian wilayah), aktif / nonaktif, Block / Unblock (wewenang + alasan), kontak dan cabang tambahan (satu default, terbatas pada customer itu), form dan grid terbuka, tautan CRM, v1 sudah pindah ke arsip.
 * Sumber hulu  : App\Services\Master\CustomerMasterService, layar md-customer-form, grid md-customer, routes/v2_master.php.
 * Input        : User johiwan (admin); customer uji berkode ZZTEST* di tm2mst.cust_customer (+ kontak dan cabangnya), dihapus di tearDown.
 * Output       : Lulus bila aturan terpenuhi dan tidak ada jalur ke halaman v1 master.customer.
 * Dipakai oleh : php artisan test --filter=CustomerMasterServiceTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Master\CustCustomer;
use App\Models\Master\CustCustomerBranch;
use App\Models\Master\CustCustomerContact;
use App\Models\Sys\SecUser;
use App\Services\Master\CustomerMasterService;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Grid\Grids;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerMasterServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        $ids = CustCustomer::withoutGlobalScopes()->where('customer_code', 'like', 'ZZTEST%')->pluck('id_customer');
        CustCustomerBranch::whereIn('customer_id', $ids)->delete();
        CustCustomerContact::whereIn('customer_id', $ids)->delete();
        CustCustomer::withoutGlobalScopes()->whereIn('id_customer', $ids)->delete();
        $this->assertSame(0, CustCustomer::withoutGlobalScopes()->where('customer_code', 'like', 'ZZTEST%')->count(), 'customer uji harus bersih');
        parent::tearDown();
    }

    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    private function input(array $over = []): array
    {
        return $over + ['customer_code' => 'ZZTEST-1', 'customer_name' => 'ZZTEST Customer', 'customer_type' => 'local', 'approval_status' => 'DRAFT', 'tax_profile' => 'PPN_11',
            'credit_rating' => 'B', 'customer_tier' => 2, 'kyc_status' => 'PENDING', 'credit_limit' => '1.500.000,50', 'email' => 'zz@example.com', 'is_prospect' => 1,
            'default_area' => 'VILLAGE:53961', 'ship_postal_code' => '65117', 'default_address' => 'Jl. Uji 1'];
    }

    public function test_customer_is_saved_with_area_flags_and_defaults(): void
    {
        $svc = app(CustomerMasterService::class);
        $c = $svc->save(null, $this->input(), $this->admin());

        $this->assertTrue((bool) $c->activate);
        $this->assertFalse((bool) $c->is_blacklisted);
        $this->assertSame('johiwan', $c->created_by);
        $this->assertSame(53961, (int) $c->default_area_id);
        $this->assertSame('VILLAGE', $c->default_area_level);
        $this->assertSame('65117', $c->postal_code);
        $this->assertSame(1500000.5, (float) $c->credit_limit);
        $this->assertTrue((bool) $c->is_prospect);
        $this->assertFalse((bool) $c->credit_check_enabled);
        $this->assertStringContainsString('KLOJEN', (string) $c->addressLabel());

        $c2 = $svc->save($c, $this->input(['customer_name' => 'ZZTEST Renamed', 'default_area' => '', 'is_prospect' => 0]), $this->admin());
        $this->assertSame('ZZTEST Renamed', $c2->customer_name);
        $this->assertNull($c2->default_area_id);
        $this->assertFalse((bool) $c2->is_prospect);

        $svc->setActive($c2, false, $this->admin());
        $this->assertFalse((bool) $c2->fresh()->activate);
    }

    public function test_rules_are_refused(): void
    {
        $svc = app(CustomerMasterService::class);
        $svc->save(null, $this->input(), $this->admin());
        foreach ([$this->input(), $this->input(['customer_code' => 'ZZTEST-2', 'customer_name' => '']), $this->input(['customer_code' => 'ZZTEST-3', 'customer_type' => 'export']),
            $this->input(['customer_code' => 'ZZTEST-4', 'customer_tier' => 9]), $this->input(['customer_code' => 'ZZTEST-5', 'credit_limit' => '-1']), $this->input(['customer_code' => 'ZZTEST-6', 'email' => 'bad'])] as $bad) {
            try {
                $svc->save(null, $bad, $this->admin());
                $this->fail('input must be refused');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
        $this->assertSame(1, CustCustomer::withoutGlobalScopes()->where('customer_code', 'like', 'ZZTEST%')->count());
    }

    public function test_block_and_unblock_follow_authority_and_reason(): void
    {
        $svc = app(CustomerMasterService::class);
        $c = $svc->save(null, $this->input(), $this->admin());

        $svc->block($c, $this->admin());
        $c->refresh();
        $this->assertTrue((bool) $c->is_blacklisted);
        $this->assertSame('BLOCKED', $c->approval_status);

        try {
            $svc->unblock($c, '', $this->admin());
            $this->fail('a reason is required');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('unblock_reason', $e->errors());
        }
        $nobody = new SecUser(['login' => 'zz-nobody']);
        $nobody->is_admin = false;
        if (! $svc->canUnblock($nobody)) {
            try {
                $svc->unblock($c, 'because', $nobody);
                $this->fail('an unauthorised user must be refused');
            } catch (ValidationException $e) {
                $this->assertStringContainsString('not authorised', $e->errors()['unblock_reason'][0]);
            }
        }

        $svc->unblock($c, 'payment received', $this->admin());
        $c->refresh();
        $this->assertFalse((bool) $c->is_blacklisted);
        $this->assertSame('APPROVED', $c->approval_status);
        $this->assertStringContainsString('by johiwan] payment received', $c->note);
    }

    public function test_contacts_and_branches_keep_one_default_and_stay_inside_their_customer(): void
    {
        $svc = app(CustomerMasterService::class);
        $user = $this->admin();
        $c = $svc->save(null, $this->input(), $user);
        $other = $svc->save(null, $this->input(['customer_code' => 'ZZTEST-OTHER', 'customer_name' => 'ZZTEST Other']), $user);

        $k1 = $svc->saveContact($c, null, ['contact_name' => 'First', 'is_default' => 1], $user);
        $k2 = $svc->saveContact($c, null, ['contact_name' => 'Second', 'is_default' => 1, 'email' => 'k@example.com'], $user);
        $this->assertFalse((bool) $k1->fresh()->is_default);
        $this->assertTrue((bool) $k2->fresh()->is_default);
        $svc->setDefaultContact($c, $k1->id_customer_contact);
        $this->assertTrue((bool) $k1->fresh()->is_default);
        $this->assertFalse((bool) $k2->fresh()->is_default);

        $b1 = $svc->saveBranch($c, null, ['branch_name' => 'Surabaya', 'branch_area' => 'VILLAGE:53961', 'ship_postal_code' => '65117', 'is_default' => 1], $user);
        $this->assertSame(53961, (int) $b1->area_id);
        $b2 = $svc->saveBranch($c, null, ['branch_name' => 'Malang', 'is_default' => 1], $user);
        $this->assertFalse((bool) $b1->fresh()->is_default);
        $this->assertTrue((bool) $b2->fresh()->is_default);

        $svc->deleteBranch($other, $b1->id_customer_branch);
        $svc->deleteContact($other, $k1->id_customer_contact);
        $this->assertNotNull(CustCustomerBranch::find($b1->id_customer_branch), 'another customer cannot delete this branch');
        $this->assertNotNull(CustCustomerContact::find($k1->id_customer_contact));

        $svc->deleteBranch($c, $b1->id_customer_branch);
        $svc->deleteContact($c, $k1->id_customer_contact);
        $this->assertNull(CustCustomerBranch::find($b1->id_customer_branch));
        $this->assertNull(CustCustomerContact::find($k1->id_customer_contact));

        $this->expectException(ValidationException::class);
        $svc->saveContact($c, null, ['contact_name' => ''], $user);
    }

    public function test_forms_and_routes_work_for_admin(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('v2.screen', 'md-customer'))->assertOk();
        $this->get(route('v2.screen', 'md-customer-form').'?new=1')->assertOk()->assertSee('New Customer');

        $this->post(route('v2.md.customer.save'), $this->input(['customer_code' => 'ZZTEST-ROUTE']))->assertRedirect();
        $c = CustCustomer::where('customer_code', 'ZZTEST-ROUTE')->firstOrFail();
        $this->get(route('v2.screen', 'md-customer-form').'?id='.$c->id_customer)->assertOk()->assertSee('ZZTEST Customer')->assertSee('Block');
        $this->post(route('v2.md.customer.save'), $this->input(['customer_code' => 'ZZTEST-ROUTE', 'customer_name' => '']))->assertSessionHasErrors('customer_name');

        $this->post(route('v2.md.customer.contact', $c->id_customer), ['contact_name' => 'Route contact', 'is_default' => 1])->assertRedirect();
        $k = CustCustomerContact::where('customer_id', $c->id_customer)->firstOrFail();
        $this->post(route('v2.md.customer.branch', $c->id_customer), ['branch_name' => 'Route branch'])->assertRedirect();
        $b = CustCustomerBranch::where('customer_id', $c->id_customer)->firstOrFail();
        $this->get(route('v2.screen', 'md-customer-form').'?id='.$c->id_customer.'&tab=sites')->assertOk()->assertSee('Route contact')->assertSee('Route branch');
        $this->post(route('v2.md.customer.contact.delete', [$c->id_customer, $k->id_customer_contact]))->assertRedirect();
        $this->post(route('v2.md.customer.branch.delete', [$c->id_customer, $b->id_customer_branch]))->assertRedirect();

        $this->post(route('v2.md.customer.save'), ['id' => $c->id_customer, 'action' => 'block'])->assertRedirect();
        $this->assertTrue((bool) $c->fresh()->is_blacklisted);
        $this->get(route('v2.screen', 'md-customer-form').'?id='.$c->id_customer)->assertOk()->assertSee('Unblock');
        $this->post(route('v2.md.customer.unblock', $c->id_customer), ['unblock_reason' => 'route test'])->assertRedirect();
        $this->assertFalse((bool) $c->fresh()->is_blacklisted);
        $this->post(route('v2.md.customer.save'), ['id' => $c->id_customer, 'action' => 'deactivate'])->assertRedirect();
        $this->assertFalse((bool) $c->fresh()->activate);
    }

    public function test_grid_crm_link_and_v1_state(): void
    {
        $grid = Grids::find('md-customer');
        $this->assertStringContainsString('/v2/s/md-customer-form?id={id}', $grid->openUrl());
        $this->assertStringContainsString('/v2/s/md-customer-form?new=1', $grid->newUrl());

        $this->assertFileDoesNotExist(resource_path('views/pages/master/customer.blade.php'));
        $this->assertArrayNotHasKey('MD_CUST', MenuRoutes::map());
        $this->assertArrayNotHasKey('PRE_MD_CUST', MenuRoutes::map());
        $this->actingAs($this->admin())->get('/master/customer')->assertRedirect('/v2/s/md-customer');
        $this->assertStringNotContainsString("route('master.customer')", file_get_contents(app_path('Support/V2/Data/Crm.php')));
    }
}
