<?php

namespace Tests\Feature;

use App\Models\Sys\SecUser;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Memastikan alamat lama halaman v1 yang diarsipkan (D.25, 2026-10-20) dialihkan ke layar v2 penggantinya.
 * Sumber hulu  : routes/web.php (Route::redirect / Route::get), tmerp_unused/*_v1_2026-10-20.blade.php.
 * Input        : GET alamat lama sebagai admin.
 * Output       : Redirect ke /v2/s/<layar> atau /v2/print/<jenis>/<id>.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif; tidak menulis data.
 */
class V1ArchiveRedirectTest extends TestCase
{
    public function test_retired_v1_addresses_redirect_to_v2(): void
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());

        foreach ([
            '/purchasing/pr' => '/v2/s/purchase-requisition',
            '/purchasing/pr/7/edit' => '/v2/s/purchase-requisition?id=7',
            '/purchasing/pr/7/print' => '/v2/print/purchase-requisition/7',
            '/purchasing/po' => '/v2/s/purchase-order',
            '/purchasing/po/7/edit' => '/v2/s/purchase-order?id=7',
            '/purchasing/po/7/print' => '/v2/print/purchase-order/7',
            '/purchasing/pv' => '/v2/s/purchase-receive',
            '/purchasing/pv/7/edit' => '/v2/s/purchase-receive?id=7',
            '/dispatch/do' => '/v2/s/delivery-order',
            '/dispatch/do/7' => '/v2/s/delivery-order?id=7',
            '/dispatch/do/7/print' => '/v2/print/delivery-order/7',
            '/dispatch/do/7/print-surat-jalan' => '/v2/print/delivery-note/7',
            '/co/delivery-plan' => '/v2/s/cpdel',
            '/sales/win-rate' => '/v2/s/sales-win-rate',
            '/hrm/org-chart' => '/v2/s/hrm-org-chart',
            '/hrm/payroll/bank-transfer' => '/v2/s/hrm-bank-transfer',
            '/setup/identification' => '/v2/s/setup-identification',
            '/setup/language' => '/v2/s/languages',
            '/setup/constants' => '/v2/s/setup-constants',
            '/setup/data-origin' => '/v2/s/setup-data-origin',
            '/inquiry' => '/v2/s/inquiry',
            '/inquiry/recalc?id=5' => '/v2/s/inquiry-recalc?id=5',
            '/inquiry/revise' => '/v2/s/inquiry-revise',
            '/quotation' => '/v2/s/quotation',
            '/co' => '/v2/s/customer-order',
            '/production/prodev-list' => '/v2/s/prodev',
            '/setup/localization' => '/v2/s/localization',
            '/hrm/shift-roster/print?mode=shift&week=1' => '/v2/hrm/roster/print?mode=shift&week=1',
            '/production/wo/9/print' => '/v2/production/wo/9/print',
            '/production/process-rate' => '/v2/s/production-process-rate',
            '/notifications' => '/v2/notifications',
            '/laporan/eksekutif' => '/v2/s/mgmt-executive',
            '/laporan/penjualan?th=2025&tab=customer' => '/v2/s/mgmt-sales?th=2025&tab=customer',
            '/laporan/pembelian' => '/v2/s/mgmt-purchase',
            '/laporan/customer/7?th=2024' => '/v2/s/mgmt-customer?customer=7&th=2024',
            '/laporan/akuntansi' => '/v2/s/mgmt-accounting',
            '/laporan/produksi' => '/v2/s/mgmt-production',
            '/laporan/sdm' => '/v2/s/mgmt-hr',
            '/laporan/gudang' => '/v2/s/mgmt-warehouse',
        ] as $old => $new) {
            $this->get($old)->assertRedirect($new);
        }

        $this->get('/bantuan')->assertRedirect(route('v2.help'));
    }
}
