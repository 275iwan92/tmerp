<?php

namespace Tests\Feature;

use App\Models\Sys\SecUser;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Cetak Roster Shift v2 (tiga mode) terbuka, memakai x-dl::print-sheet dan membawa kode formulir yang sama dengan v1.
 * Sumber hulu  : App\Support\V2\Data\RosterPrint, rute v2.hrm.roster.print.
 * Input        : GET sebagai admin; tidak menulis data.
 * Output       : Status 200 dan teks kunci tiap mode; v1 /hrm/shift-roster/print dialihkan.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif (T9 pindahan v1, 2026-10-20).
 */
class RosterPrintV2Test extends TestCase
{
    public function test_three_print_modes_open_with_their_form_codes(): void
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());

        foreach (['roster' => 'FRM-HR-ROS-01 Rev.0', 'shift' => 'FRM-HR-SHF-01 Rev.0', 'formasi' => 'FRM-HR-FRM-01 Rev.0'] as $mode => $code) {
            $this->get(route('v2.hrm.roster.print', ['mode' => $mode]))->assertOk()->assertSee($code)->assertSee('dl-psheet', false);
        }
        $this->get(route('v2.hrm.roster.print', ['mode' => 'bogus']))->assertOk()->assertSee('FRM-HR-ROS-01 Rev.0');
    }
}
