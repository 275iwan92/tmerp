<?php

namespace Tests\Feature\Report;

use App\Models\Sys\SecUser;
use Livewire\Livewire;
use Tests\TestCase;

// Verifikasi shell layout baru (layouts::report -- sidebar navy meniru
// persis printflow_erp_complete_dashboard_mockup.html) tidak error render
// utk 8 layar existing + costing baru + 4 coming-soon (keputusan user
// 2026-09-13 "TIRU PERSIS mockup").
class ManagementReportShellTest extends TestCase
{
    public function test_all_management_report_pages_render_with_new_sidebar_layout(): void
    {
        $direksi = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($direksi);

        Livewire::test('pages::report.executive')->assertOk();
        Livewire::test('pages::report.sales')->assertOk();
        Livewire::test('pages::report.purchase')->assertOk();
        Livewire::test('pages::report.accounting')->assertOk();
        Livewire::test('pages::report.production')->assertOk();
        Livewire::test('pages::report.hr')->assertOk();
        Livewire::test('pages::report.warehouse')->assertOk();
        Livewire::test('pages::report.costing')->assertOk();
    }

    // Keputusan awal (jujur "belum tersedia" tanpa angka) DIBATALKAN user
    // 2026-09-13: "KALAU TIDAK ADA TETAP DIBUAT DUMMY DATA NYA, DENGAN
    // TULISAN INI DUMMY DATA" -- sekarang tampil dashboard KPI+grafik+tabel
    // penuh gaya mockup dgn angka contoh dari mockup asli, ditandai jelas
    // "DATA DUMMY" (bukan disamarkan sbg data produksi).
    public function test_control_tower_pages_render_dummy_dashboard_clearly_labeled(): void
    {
        $direksi = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($direksi);

        foreach (['qms', 'cmms', 'ehs', 'assets'] as $slug) {
            Livewire::test('pages::report.coming-soon', ['slug' => $slug])
                ->assertOk()
                ->assertSee('DATA DUMMY')
                ->assertSee('DUMMY');
        }
    }

    // Tombol Logout terpisah di shell ini DIHAPUS (keluhan user 2026-09-14:
    // "hilangkan icon logout di management report") -- keluar tetap
    // dijangkau lewat tab "Operasional" (route('menu')) lalu avatar
    // dropdown di sana, jadi TIDAK di-assert lagi di sini.
    public function test_report_routes_are_reachable_via_http_with_menu_link(): void
    {
        $direksi = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($direksi);

        $response = $this->get(route('report.executive'));

        $response->assertOk();
        $response->assertSee(route('menu'), false);
        $response->assertSee(route('report.control-tower', 'qms'), false);
    }
}
