<?php

/*
 * REMARK
 * Tujuan       : Menguji substitusi bahan sesudah CO Confirm: permintaan + dampak biaya, OTP satu permintaan satu kode (salah ditolak, benar menyetujui dan menerapkan), pemberi bukan pemohon, dan bahan cadangan.
 * Sumber hulu  : SpecChangeService, SpecOtpService, SpecLock; tabel spc_change_request / spc_otp / prod_material_alt (migration 2026_10_16_000500).
 * Input        : Satu baris BOM WO yang belum berjalan dan dua bahan dari data yang ada; semua perubahan dikembalikan di tearDown.
 * Output       : Lulus / dilewati (tabel belum ada atau data uji tidak tersedia).
 * Dipakai oleh : php artisan test --filter=SpecChangeSubstitutionTest
 * Status       : Aktif.
 */

namespace Tests\Feature;

use App\Models\Master\MatRaw;
use App\Models\Master\ProdMaterialAlt;
use App\Models\Prd\PrdWoMaterial;
use App\Models\Prd\SpcChangeRequest;
use App\Models\Sys\SecUser;
use App\Models\Sys\SysNotification;
use App\Services\Production\SpecChange\SpecChangeService;
use App\Services\Production\SpecChange\SpecLock;
use DomainException;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SpecChangeSubstitutionTest extends TestCase
{
    private PrdWoMaterial $line;

    private array $before;

    private int $subId;

    private SecUser $requester;

    private SecUser $approver;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::connection('tm2prd')->hasTable('spc_change_request')) {
            $this->markTestSkipped('spc_change_request belum ada di database ini.');
        }
        $this->line = PrdWoMaterial::query()->whereNotNull('material_id')->orderByDesc('detail_id')->get()
            ->first(fn ($m) => ! SpecLock::locked((int) $m->wo_id) && ! SpcChangeRequest::query()->where('wo_material_id', $m->detail_id)->where('status', 'PENDING')->exists()) ?? $this->markTestSkipped('Tidak ada baris BOM WO yang belum berjalan.');
        $this->before = $this->line->only(['is_substituted', 'substitute_material_id', 'substitute_reason', 'substitute_approved_by', 'substitute_approved_at']);
        $this->subId = (int) MatRaw::query()->where('material_id', '<>', $this->line->material_id)->value('material_id');
        $users = SecUser::query()->where('activate', 1)->get();
        $this->approver = $users->first(fn ($u) => $u->maxMgmtLevel() >= 4) ?? $this->markTestSkipped('Tidak ada pengguna level 4+.');
        $this->requester = $users->first(fn ($u) => $u->maxMgmtLevel() < 4 && $u->login !== $this->approver->login) ?? $this->markTestSkipped('Tidak ada pemohon uji.');
    }

    protected function tearDown(): void
    {
        if (isset($this->line)) {
            SpcChangeRequest::query()->where('wo_material_id', $this->line->detail_id)->where('requested_by', $this->requester->login)->delete();
            PrdWoMaterial::query()->whereKey($this->line->detail_id)->update($this->before);
            ProdMaterialAlt::query()->where('material_id', $this->line->material_id)->where('alt_material_id', $this->subId)->where('created_by', $this->requester->login)->delete();
        }
        parent::tearDown();
    }

    private function request(bool $backup = false): SpcChangeRequest
    {
        return SpecChangeService::requestSubstitution((int) $this->line->detail_id, $this->subId, 'Bahan asli belum datang (uji)', $backup, $this->requester->login);
    }

    public function test_request_waits_for_approval_and_carries_the_cost_impact(): void
    {
        $r = $this->request();

        $this->assertSame('PENDING', $r->status);
        $this->assertArrayHasKey('delta', $r->cost_impact);
        $this->assertFalse((bool) PrdWoMaterial::query()->find($this->line->detail_id)->is_substituted, 'the line must not change before approval');
    }

    public function test_otp_is_per_request_checked_and_applies_the_substitution(): void
    {
        $r = $this->request(backup: true);
        $code = \App\Services\Production\SpecChange\SpecOtpService::issue($r, $this->approver);

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        try {
            SpecChangeService::approveWithOtp($r, $code === '000000' ? '111111' : '000000', $this->requester);
            $this->fail('a wrong code must be refused');
        } catch (DomainException) {
        }
        $this->assertSame('PENDING', $r->refresh()->status);

        $done = SpecChangeService::approveWithOtp($r, $code, $this->requester);
        $line = PrdWoMaterial::query()->find($this->line->detail_id);

        $this->assertSame('APPLIED', $done->status);
        $this->assertSame($this->approver->login, $done->decided_by);
        $this->assertTrue((bool) $line->is_substituted);
        $this->assertSame($this->subId, (int) $line->substitute_material_id);
        $this->assertTrue(ProdMaterialAlt::query()->where('material_id', $this->line->material_id)->where('alt_material_id', $this->subId)->exists());
    }

    public function test_a_non_approver_cannot_give_an_otp(): void
    {
        $this->expectException(DomainException::class);
        \App\Services\Production\SpecChange\SpecOtpService::issue($this->request(), $this->requester);
    }

    public function test_an_approver_cannot_approve_their_own_request(): void
    {
        $own = SpecChangeService::requestSubstitution((int) $this->line->detail_id, $this->subId, 'Permintaan sendiri (uji)', false, $this->approver->login);

        try {
            $this->expectException(DomainException::class);
            \App\Services\Production\SpecChange\SpecOtpService::issue($own, $this->approver);
        } finally {
            $own->delete();
        }
    }

    public function test_a_cost_change_notifies_gm_and_direksi(): void
    {
        $r = $this->request();
        $r->update(['cost_impact' => ['delta' => 123456.0, 'delta_pct_of_order' => 7.5]]);
        $title = tf('Material substitution on WO :wo', ['wo' => \App\Models\Prd\PrdWo::query()->whereKey($r->wo_id)->value('wo_no')]);
        SysNotification::query()->where('title', $title)->delete();

        try {
            SpecChangeService::notifyImpact($r);
            $to = \App\Services\Inventory\QcNotifier::recipients(['GM', 'DIREKSI']);

            $this->assertSame(count($to), SysNotification::query()->where('title', $title)->count());
        } finally {
            SysNotification::query()->where('title', $title)->delete();
        }
    }

    public function test_cost_impact_is_the_price_difference_times_the_required_quantity(): void
    {
        $line = new PrdWoMaterial(['qty_required' => 10, 'wo_id' => 0]);
        $impact = SpecChangeService::costImpact($line, new MatRaw(['avg_unitprice' => 100]), new MatRaw(['avg_unitprice' => null, 'last_unitprice' => 150]));

        $this->assertSame(500.0, $impact['delta']);
        $this->assertNull($impact['order_value']);
    }
}
