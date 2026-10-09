<?php

namespace Tests\Feature;

use App\Models\Inventory\InvObsoleteProvision;
use App\Models\Inventory\InvObsoleteProvisionLine;
use App\Services\Inventory\Valuation\ObsolescenceService;
use App\Services\Inventory\Valuation\ValuationAccounts;
use App\Services\Inventory\Valuation\ValuationRefused;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Provisi stok usang: kelas dari umur tanpa pergerakan (batas di Settings > Constants), usulan = nilai x persen kelas, jurnal hanya atas aksi user (selisih
// terhadap provisi sebelumnya). Jalankan terhadap database dummy: powershell -NoProfile -File tools/test-ddt.ps1 tests/Feature/ObsolescenceTest.php
class ObsolescenceTest extends TestCase
{
    private const USER = 'obstest';

    /** @var array<string, array{0: mixed, 1: mixed}> */
    private array $saved = [];

    protected function tearDown(): void
    {
        foreach ($this->saved as $key => [$number, $text]) {
            DB::connection('tm2sys')->table('sys_constant')->where('constant_key', $key)->update(['constant_number' => $number, 'constant_text' => $text]);
        }
        $ids = InvObsoleteProvision::query()->where('created_by', self::USER)->pluck('id_provision');
        InvObsoleteProvisionLine::query()->whereIn('id_provision', $ids)->delete();
        InvObsoleteProvision::query()->whereIn('id_provision', $ids)->delete();
        $vouchers = DB::connection('tm2acc')->table('fin_voucher')->where('created_by', self::USER)->pluck('voucher_id');
        DB::connection('tm2acc')->table('fin_voucher_line')->whereIn('voucher_id', $vouchers)->delete();
        DB::connection('tm2acc')->table('fin_voucher')->whereIn('voucher_id', $vouchers)->delete();
        parent::tearDown();
    }

    private function setConstant(string $key, float $number): void
    {
        $row = DB::connection('tm2sys')->table('sys_constant')->where('constant_key', $key)->first();
        $this->saved[$key] ??= [$row->constant_number, $row->constant_text];
        DB::connection('tm2sys')->table('sys_constant')->where('constant_key', $key)->update(['constant_number' => $number]);
    }

    public function test_classes_follow_the_day_limits_and_never_moved_is_dead(): void
    {
        $this->setConstant('INV_AGING_SLOW_DAYS', 90);
        $this->setConstant('INV_AGING_NONMOVING_DAYS', 180);
        $this->setConstant('INV_AGING_DEAD_DAYS', 365);

        $this->assertSame('ACTIVE', ObsolescenceService::classify(89));
        $this->assertSame('SLOW', ObsolescenceService::classify(90));
        $this->assertSame('NON_MOVING', ObsolescenceService::classify(180));
        $this->assertSame('DEAD', ObsolescenceService::classify(365));
        $this->assertSame('DEAD', ObsolescenceService::classify(null));
    }

    public function test_every_row_has_the_class_percentage_and_provision_of_its_age(): void
    {
        $this->setConstant('INV_OBSOLETE_PCT_SLOW', 10);
        $this->setConstant('INV_OBSOLETE_PCT_NONMOVING', 25);
        $this->setConstant('INV_OBSOLETE_PCT_DEAD', 50);
        $service = app(ObsolescenceService::class);

        $rows = $service->rows();
        $this->assertNotEmpty($rows, 'the dummy data has stock on hand');
        foreach ($rows as $r) {
            $this->assertSame(ObsolescenceService::classify($r['idle_days']), $r['aging_class']);
            $this->assertSame(['ACTIVE' => 0.0, 'SLOW' => 10.0, 'NON_MOVING' => 25.0, 'DEAD' => 50.0][$r['aging_class']], $r['pct']);
            $this->assertSame(round($r['value'] * $r['pct'] / 100, 2), $r['provision']);
        }
        $summary = $service->summary($rows);
        $this->assertSame(round(array_sum(array_column($rows, 'provision')), 2), round(array_sum(array_column($summary, 'provision')), 2));
        $this->assertSame(count($rows), array_sum(array_column($summary, 'items')));
    }

    public function test_rows_alone_never_post_a_journal(): void
    {
        $before = DB::connection('tm2acc')->table('fin_voucher')->where('source_type', 'OBS_PROVISION')->count();

        app(ObsolescenceService::class)->summary(app(ObsolescenceService::class)->rows());

        $this->assertSame($before, DB::connection('tm2acc')->table('fin_voucher')->where('source_type', 'OBS_PROVISION')->count());
    }

    public function test_post_journals_the_difference_to_the_previous_provision_only(): void
    {
        $this->setConstant('INV_OBSOLETE_PCT_SLOW', 10);
        $this->setConstant('INV_OBSOLETE_PCT_NONMOVING', 25);
        $this->setConstant('INV_OBSOLETE_PCT_DEAD', 50);
        $service = app(ObsolescenceService::class);
        $previous = ObsolescenceService::previousTotal();
        $target = round((float) array_sum(array_column($service->rows(), 'provision')), 2);
        if (abs($target - $previous) < 0.01) {
            $this->markTestSkipped('The provision is already up to date.');
        }

        $head = $service->post(self::USER);

        $this->assertSame(round($target - $previous, 2), $head->adjustment);
        $this->assertSame($target, $head->provision_total);
        $this->assertSame(InvObsoleteProvisionLine::query()->where('id_provision', $head->id_provision)->count(), count(array_filter($service->rows(), fn ($r) => $r['provision'] > 0)));
        $lines = DB::connection('tm2acc')->table('fin_voucher_line')->where('voucher_id', $head->voucher_id)->get();
        $up = $head->adjustment > 0;
        $this->assertSame(abs($head->adjustment), round((float) $lines->firstWhere('account_id', ValuationAccounts::id($up ? 'INV_ACC_OBS_EXPENSE' : 'INV_ACC_OBS_PROVISION'))->debit, 2));
        $this->assertSame(abs($head->adjustment), round((float) $lines->firstWhere('account_id', ValuationAccounts::id($up ? 'INV_ACC_OBS_PROVISION' : 'INV_ACC_OBS_EXPENSE'))->credit, 2));

        $this->expectException(ValuationRefused::class);
        $service->post(self::USER);
    }

    public function test_a_lower_provision_reverses_part_of_the_previous_one(): void
    {
        $this->setConstant('INV_OBSOLETE_PCT_SLOW', 10);
        $this->setConstant('INV_OBSOLETE_PCT_NONMOVING', 25);
        $this->setConstant('INV_OBSOLETE_PCT_DEAD', 50);
        $service = app(ObsolescenceService::class);
        $first = $service->post(self::USER);
        $this->setConstant('INV_OBSOLETE_PCT_DEAD', 25);

        $second = $service->post(self::USER);

        $this->assertLessThan(0, $second->adjustment);
        $this->assertSame($first->provision_total, $second->previous_total);
        $lines = DB::connection('tm2acc')->table('fin_voucher_line')->where('voucher_id', $second->voucher_id)->get();
        $this->assertSame(abs($second->adjustment), round((float) $lines->firstWhere('account_id', ValuationAccounts::id('INV_ACC_OBS_PROVISION'))->debit, 2));
    }
}
