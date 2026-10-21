<?php

namespace Tests\Feature;

use App\Models\Master\ProdPart;
use App\Models\Sys\SecUser;
use App\Services\ProDev\ProdevWorkbenchService;
use App\Support\V2\Data\Prodev;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/*
 * REMARK
 * Tujuan       : Menguji ProdevWorkbenchService (aturan workbench ProDev, dipindah dari komponen v1): daftar master mesin/plano, job part Mode #2, alasan job kosong, Simpan tanpa object ditolak, artwork Mode #2 ditolak, redirect rute lama.
 * Sumber hulu  : App\Services\ProDev\ProdevWorkbenchService, App\Support\V2\Data\Prodev, routes/web.php.
 * Input        : Part manual (Mode #2) yang sudah ada di data; tidak menulis apa pun.
 * Output       : Assert bentuk keluaran dan pesan penolakan.
 * Dipakai oleh : php artisan test --filter=ProdevWorkbenchServiceTest.
 * Status       : Aktif (T10 tahap 5, 2026-10-21).
 */
class ProdevWorkbenchServiceTest extends TestCase
{
    private function manualPart(): ProdPart
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());
        $fromInquiry = \App\Models\Sdi\IqpPart::whereNotNull('prod_part_id')->pluck('prod_part_id');

        return ProdPart::whereNotIn('id_part', $fromInquiry)->firstOrFail();
    }

    public function test_masters_are_listed_and_a_part_without_dieline_gives_an_honest_reason(): void
    {
        $part = $this->manualPart();
        $svc = Prodev::service($part->id_part);

        $this->assertNotEmpty($svc->machines());
        $this->assertNotEmpty($svc->planos());
        $this->assertSame(['LBR', 'RIM', 'M', 'ROLL', 'KG', 'G', 'PCS', 'LTR'], $svc->lookups()['uoms']);
        $this->assertSame($svc->jobs() === [] ? 0 : $svc->initialJobIndex(), $svc->initialJobIndex());
        if ($svc->jobs() === []) {
            $this->assertStringContainsString((string) $part->part_name, (string) $svc->jobIssue());
        } else {
            $this->assertNull($svc->jobIssue());
        }
    }

    public function test_save_without_objects_and_artwork_of_a_manual_part_are_refused(): void
    {
        $part = $this->manualPart();
        $svc = new ProdevWorkbenchService($part->id_part);

        $out = $svc->saveLayout(['partId' => $part->id_part, 'upCount' => 0]);
        $this->assertFalse($out['ok']);

        $up = $svc->storeArtwork(UploadedFile::fake()->image('a.png', 20, 20));
        $this->assertFalse($up['ok']);

        $this->assertFalse((new ProdevWorkbenchService($part->id_part))->generateSpec($part->id_part + 999999, [])['ok']);
    }

    public function test_server_call_route_reaches_the_service(): void
    {
        $part = $this->manualPart();

        $this->postJson(route('v2.prodev.call', $part->id_part), ['method' => 'searchMaterials', 'args' => ['kertas']])->assertOk();
        $this->postJson(route('v2.prodev.call', $part->id_part), ['method' => 'saveLayout', 'args' => [['partId' => $part->id_part, 'upCount' => 0]]])
            ->assertOk()->assertJsonPath('ok', false);
        $this->postJson(route('v2.prodev.call', $part->id_part), ['method' => 'jobs'])->assertStatus(422);
    }

    public function test_old_prodev_addresses_redirect_to_the_v2_screen(): void
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());

        foreach (['/production/prodev/7' => '/v2/s/prodev?part=7', '/production/old-prodev/7' => '/v2/s/prodev?part=7',
            '/production/old-prodev/7/print' => '/v2/s/prodev?part=7', '/production/old-prodev-list' => '/v2/s/prodev'] as $from => $to) {
            $this->get($from)->assertRedirect($to);
        }
    }
}
