<?php

namespace Tests\Feature;

use App\Models\Acc\FinApprovalRule;
use App\Models\Sys\SecUser;
use App\Support\V2\Data\FinanceDesk as Desk;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// Finance Control Center v2 (C3a): Dashboard + Process Engine. Aturan uji bernama "ZZ Test Rule" dan dibersihkan lagi.
class FinanceDeskTest extends TestCase
{
    protected function tearDown(): void
    {
        FinApprovalRule::where('rule_name', 'like', 'ZZ Test Rule%')->delete();
        parent::tearDown();
    }

    private function input(array $over = []): array
    {
        return $over + ['rule_name' => 'ZZ Test Rule', 'process_code' => 'JOURNAL', 'min_amount' => 1500000, 'approver_group' => array_key_first(Desk::approverGroups()), 'sod' => 1];
    }

    public function test_metric_items_map_to_status_card_props(): void
    {
        $card = Desk::card(['!', 're', 'Overdue', 'Follow-up', '7']);
        $this->assertSame(['title' => 'Overdue', 'desc' => 'Follow-up', 'status' => '7', 'tone' => 'danger', 'icon' => 'triangle-exclamation'], $card);
        $this->assertSame('warn', Desk::card(['x', 'or', 't', 'd', '1'])['tone']);
        $this->assertSame('neutral', Desk::card(['x', '??', 't', 'd', '1'])['tone']);
        $this->assertCount(2, Desk::cards([['x', 're', 'a', 'b', '1'], ['x', 'bl', 'c', 'd', '2']]));
    }

    public function test_dashboard_and_engine_shapes(): void
    {
        $kpis = Desk::dashboardKpis(Desk::metrics()->headline(now()->toDateString()));
        $this->assertSame(['Cash & Bank', 'Accounts Receivable', 'Accounts Payable', 'Net Profit YTD'], array_column($kpis, 'label'));
        $this->assertSame('down', $kpis[1]['goodWhen']);

        $trend = Desk::cashTrend();
        $this->assertCount(12, $trend['labels']);
        $this->assertCount(12, $trend['series'][0]['data']);

        $m = Desk::metrics()->engine();
        $this->assertCount(4, Desk::engineKpis($m));
        $steps = Desk::postingFlow($m);
        $this->assertSame(['source', 'validation', 'approval', 'subledger', 'gl', 'close'], array_column($steps, 'key'));
        $this->assertCount(6, Desk::integrationMap());
    }

    public function test_rule_create_toggle_and_csv(): void
    {
        $rule = Desk::saveRule($this->input(), 'test');
        $this->assertTrue((bool) $rule->activate);
        $this->assertTrue((bool) $rule->segregation_of_duties);

        $this->assertFalse(Desk::toggleRule($rule->rule_id, 'test'));
        $this->assertTrue(Desk::toggleRule($rule->rule_id, 'test'));

        [$headers, $rows] = Desk::rulesCsv();
        $this->assertSame(['Rule', 'Process', 'Minimum amount', 'Approver group', 'Segregation of duties', 'Active'], $headers);
        $this->assertContains('ZZ Test Rule', array_column($rows, 0));

        $off = Desk::saveRule($this->input(['rule_name' => 'ZZ Test Rule 2', 'sod' => 0]), 'test');
        $this->assertFalse((bool) $off->segregation_of_duties);
    }

    public function test_rule_validation(): void
    {
        foreach ([['rule_name' => ''], ['process_code' => 'NOPE'], ['min_amount' => -1], ['approver_group' => 'NO_SUCH_GROUP']] as $bad) {
            try {
                Desk::saveRule($this->input($bad), 'test');
                $this->fail('Expected a ValidationException for '.json_encode($bad));
            } catch (ValidationException) {
                $this->assertSame(0, FinApprovalRule::where('rule_name', 'like', 'ZZ Test Rule%')->count());
            }
        }
    }

    public function test_screens_and_routes(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $admin = SecUser::where('is_admin', true)->firstOrFail();
        $this->actingAs($admin)->get(route('v2.screen', 'acc-dashboard'))->assertOk()->assertSee('Cash Flow Overview');
        $this->actingAs($admin)->get(route('v2.screen', 'acc-engine'))->assertOk()->assertSee('Workflow Rules');

        $this->actingAs($admin)->post(route('v2.acc.engine.rule-save'), $this->input())->assertRedirect()->assertSessionHas('v2_flash');
        $rule = FinApprovalRule::where('rule_name', 'ZZ Test Rule')->firstOrFail();
        $this->actingAs($admin)->post(route('v2.acc.engine.rule-toggle', $rule->rule_id))->assertRedirect();
        $this->assertFalse((bool) $rule->fresh()->activate);

        $this->actingAs($admin)->post(route('v2.acc.engine.rule-save'), $this->input(['rule_name' => '']))->assertSessionHasErrors('rule_name');
        $csv = $this->actingAs($admin)->get(route('v2.acc.engine.rules-csv'));
        $csv->assertOk();
        $this->assertStringContainsString('ZZ Test Rule', $csv->streamedContent());
    }
}
