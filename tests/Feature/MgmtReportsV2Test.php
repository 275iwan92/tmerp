<?php

namespace Tests\Feature;

use App\Models\Sys\SecUser;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Layar Management Reports v2 (mgmt-sales, -purchase, -customer, -accounting, -production, -hr, -warehouse) terbuka dan menampilkan angka yang sama dengan sumbernya.
 * Sumber hulu  : App\Support\V2\Data\Mgmt\*, tabel legacy tm2sdi / tm2pur, ManagementReportService.
 * Input        : GET sebagai admin; tidak menulis data.
 * Output       : Status 200 dan teks kunci tiap layar.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif (T9 Management Reports, 2026-10-20).
 */
class MgmtReportsV2Test extends TestCase
{
    public function test_every_management_report_screen_opens(): void
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());
        $customer = (int) DB::connection('tm2sdi')->table('co_order_legacy')->whereNotNull('customer_id')->value('customer_id');

        foreach ([
            'mgmt-sales' => 'Sales Details', 'mgmt-sales?tab=customer' => 'Customer Ranking', 'mgmt-sales?tab=sales' => 'Per Salesperson', 'mgmt-sales?basis=shipment' => 'Selected Period Value',
            'mgmt-purchase' => 'Purchase Details', 'mgmt-purchase?tab=invoice' => 'Accounts Payable', 'mgmt-customer?customer='.$customer => 'Relationship Span',
            'mgmt-accounting' => 'Active COA Accounts', 'mgmt-production' => 'Total Work Orders', 'mgmt-hr' => 'Active Employees Working', 'mgmt-warehouse' => 'Raw Material Stock Value',
        ] as $path => $needle) {
            $this->get('/v2/s/'.$path)->assertOk()->assertSee($needle);
        }
        $this->get('/v2/s/mgmt-customer')->assertNotFound();
    }

    public function test_sales_totals_match_the_legacy_tables(): void
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());
        $year = (int) DB::connection('tm2sdi')->table('co_order_legacy')->selectRaw('MAX(YEAR(co_date)) y')->value('y');
        $docs = DB::connection('tm2sdi')->table('co_order_legacy')->whereYear('co_date', $year)->count();

        $r = new \App\Support\V2\Data\Mgmt\SalesDetail(['th' => $year, 'basis' => 'order']);
        $this->assertSame($docs, $r->periodTotal()['docs']);
        $this->assertSame($docs, $r->documents()->total());
    }
}
