<?php

namespace Tests\Feature;

use App\Models\Acc\FinAccount;
use App\Models\Acc\FinBankStatement;
use App\Models\Acc\FinVoucher;
use App\Models\Acc\FinVoucherLine;
use App\Models\Sys\SecUser;
use App\Support\V2\Data\BankDesk as Bank;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// Bank & Reconciliation v2 (C3b). Mutasi uji berdeskripsi "ZZ TEST ..." tanggal 2099 (tidak mengganggu rekonsiliasi nyata) dan dibersihkan lagi,
// termasuk jurnal penyesuaian DRAFT yang dibuat tes. Tes tidak menjalankan Reconcile / Auto-Match (menulis rekaman rekonsiliasi nyata).
class BankDeskTest extends TestCase
{
    private int $accountId;

    /** @var list<int> */
    private array $vouchers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->accountId = (int) (array_key_first(Bank::accountOptions()) ?? $this->markTestSkipped('No cash / bank account.'));
    }

    protected function tearDown(): void
    {
        FinBankStatement::where('description', 'like', 'ZZ TEST%')->delete();
        if ($this->vouchers) {
            FinVoucherLine::whereIn('voucher_id', $this->vouchers)->delete();
            FinVoucher::whereIn('voucher_id', $this->vouchers)->delete();
        }
        parent::tearDown();
    }

    private function csv(string $body, string $name = 'bank.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $body);
    }

    private function importLines(): int
    {
        return Bank::import($this->accountId, $this->csv("date,description,reference,debit,credit,balance\n05/01/2099,ZZ TEST Admin fee,REF-A,\"1.234.567,50\",0,100\n2099-01-06,ZZ TEST Customer payment,REF-B,0,2500000,2600000\n"), 'test');
    }

    public function test_import_parses_dates_and_indonesian_amounts_as_unmatched_lines(): void
    {
        $this->assertSame(2, $this->importLines());
        $out = FinBankStatement::where('description', 'ZZ TEST Admin fee')->firstOrFail();
        $this->assertSame('UNMATCHED', $out->match_status);
        $this->assertSame('2099-01-05', $out->txn_date->toDateString());
        $this->assertEqualsWithDelta(1234567.5, (float) $out->amount_out, 0.001);
        $in = FinBankStatement::where('description', 'ZZ TEST Customer payment')->firstOrFail();
        $this->assertEqualsWithDelta(2500000, (float) $in->amount_in, 0.001);
    }

    public function test_import_rejects_a_bad_header_and_a_wrong_file(): void
    {
        foreach ([$this->csv("foo,bar\n1,2\n"), UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'), null] as $file) {
            try {
                Bank::import($this->accountId, $file, 'test');
                $this->fail('Expected a ValidationException.');
            } catch (ValidationException) {
                $this->assertSame(0, FinBankStatement::where('description', 'like', 'ZZ TEST%')->count());
            }
        }
    }

    public function test_ignore_and_stats_count_unmatched_lines(): void
    {
        $before = Bank::stats($this->accountId)['unmatched_n'];
        $this->importLines();
        $this->assertSame($before + 2, Bank::stats($this->accountId)['unmatched_n']);

        $line = FinBankStatement::where('description', 'ZZ TEST Admin fee')->firstOrFail();
        Bank::ignore($line->stmt_line_id, 'test');
        $this->assertSame('IGNORED', $line->fresh()->match_status);
        $this->assertSame($before + 1, Bank::stats($this->accountId)['unmatched_n']);
    }

    public function test_manual_match_pairs_a_voucher_once(): void
    {
        $vl = FinVoucherLine::with('voucher')->where('account_id', $this->accountId)->whereHas('voucher', fn ($q) => $q->where('status', 'POSTED')->where('stat_cancel', 0))->where('debit', '>', 0)->first();
        $vl ?: $this->markTestSkipped('No posted bank movement to match.');
        $a = FinBankStatement::create(['account_id' => $this->accountId, 'txn_date' => '2099-01-07', 'description' => 'ZZ TEST match A', 'amount_in' => $vl->debit, 'amount_out' => 0, 'import_batch' => 'ZZ', 'created_by' => 'test']);
        $b = FinBankStatement::create(['account_id' => $this->accountId, 'txn_date' => '2099-01-08', 'description' => 'ZZ TEST match B', 'amount_in' => $vl->debit, 'amount_out' => 0, 'import_batch' => 'ZZ', 'created_by' => 'test']);

        $alreadyUsed = in_array($vl->voucher_id, array_keys(Bank::matchedVouchers()), true);
        if (! $alreadyUsed) {
            Bank::manualMatch($a->stmt_line_id, $vl->voucher_id, 'test');
            $this->assertSame('MATCHED', $a->fresh()->match_status);
            $this->assertSame((int) $vl->voucher_id, (int) $a->fresh()->matched_voucher_id);
        }
        $this->expectException(ValidationException::class);
        Bank::manualMatch($b->stmt_line_id, $vl->voucher_id, 'test');
    }

    public function test_adjustment_creates_a_draft_journal_and_marks_the_line_adjusted(): void
    {
        $this->importLines();
        $line = FinBankStatement::where('description', 'ZZ TEST Admin fee')->firstOrFail();
        $counter = array_key_first(array_diff_key(Bank::counterAccounts(), [$this->accountId => 1]));

        $no = Bank::adjust($line->stmt_line_id, (int) $counter, 'test');
        $voucher = FinVoucher::where('voucher_number', $no)->firstOrFail();
        $this->vouchers[] = $voucher->voucher_id;

        $this->assertSame('DRAFT', $voucher->status);
        $this->assertSame('ADJUSTED', $line->fresh()->match_status);
        $this->assertEqualsWithDelta(1234567.5, (float) FinVoucherLine::where('voucher_id', $voucher->voucher_id)->sum('credit'), 0.001);
        $this->assertEqualsWithDelta((float) FinVoucherLine::where('voucher_id', $voucher->voucher_id)->sum('debit'), (float) FinVoucherLine::where('voucher_id', $voucher->voucher_id)->sum('credit'), 0.001, 'journal balanced');
    }

    public function test_adjustment_rejects_an_unknown_account(): void
    {
        $this->importLines();
        $line = FinBankStatement::where('description', 'ZZ TEST Admin fee')->firstOrFail();
        $this->expectException(ValidationException::class);
        Bank::adjust($line->stmt_line_id, 999999999, 'test');
    }

    public function test_kpis_and_flow_shapes(): void
    {
        $s = Bank::stats($this->accountId);
        $kpis = Bank::kpis($s, FinAccount::find($this->accountId));
        $this->assertSame(['Statement Balance', 'GL Book Balance', 'Unmatched Items', 'Last Reconciled'], array_column($kpis, 'label'));
        $this->assertSame(['import', 'normalize', 'match', 'investigate', 'reconcile'], array_column(Bank::flow($s), 'key'));
    }

    public function test_screen_and_routes(): void
    {
        $admin = SecUser::where('is_admin', true)->firstOrFail();
        $screen = route('v2.screen', 'acc-bank').'?account='.$this->accountId;
        $this->actingAs($admin)->get($screen)->assertOk()->assertSee('Bank Reconciliation Flow');

        $this->actingAs($admin)->post(route('v2.acc.bank.import'), ['account' => $this->accountId, 'csv' => $this->csv("date,description,debit,credit\n2099-02-01,ZZ TEST Route line,100,0\n")])
            ->assertRedirect()->assertSessionHas('v2_flash');
        $line = FinBankStatement::where('description', 'ZZ TEST Route line')->firstOrFail();
        $this->actingAs($admin)->get($screen.'&stmt='.$line->stmt_line_id)->assertOk()->assertSee('Investigate Statement Line');

        $this->actingAs($admin)->post(route('v2.acc.bank.ignore', $line->stmt_line_id), ['account' => $this->accountId])->assertRedirect();
        $this->assertSame('IGNORED', $line->fresh()->match_status);
        $this->actingAs($admin)->post(route('v2.acc.bank.import'), ['account' => $this->accountId, 'csv' => $this->csv("foo\n1\n")])->assertSessionHasErrors('bank');
    }
}
