<?php

namespace Tests\Feature;

use App\Models\Master\OrgDepartment;
use App\Models\Pur\PrLine;
use App\Models\Pur\PrRequisition;
use App\Models\Sys\SecUser;
use App\Support\V2\Data\PurchaseRequisition;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

// pr_line.i_coa_account_id NOT NULL, dan baris teks bebas tidak punya kategori yang menuntun ke akun: dulu addFreeText()
// menyimpan NULL -> SQLSTATE 1048, "+ Item Bebas" selalu gagal (v1 dan v2, satu komponen). Akun sekarang dipilih saat menambah.
class PurchaseRequisitionFreeTextLineTest extends TestCase
{
    /** @var list<int> */
    private array $prIds = [];

    protected function tearDown(): void
    {
        // pr_line ikut terhapus (FK pr_id ON DELETE CASCADE).
        PrRequisition::whereIn('pr_id', $this->prIds)->delete();

        parent::tearDown();
    }

    private function draft(): PrRequisition
    {
        $this->actingAs(SecUser::where('is_admin', true)->firstOrFail());
        $pr = PurchaseRequisition::create((int) OrgDepartment::where('activate', true)->value('department_id'));
        $this->prIds[] = $pr->pr_id;

        return $pr;
    }

    /** @return list<int> */
    private function accounts(bool $postable = true): array
    {
        return DB::connection('tm2acc')->table('fin_account')->where('is_postable', $postable)->orderBy('account_code')->limit(2)
            ->pluck('account_id')->map(fn ($id) => (int) $id)->all();
    }

    public function test_free_text_line_is_saved_with_the_chosen_account(): void
    {
        $pr = $this->draft();
        [$account] = $this->accounts();

        $error = PurchaseRequisition::addFree($pr, 'Air conditioner service', 'JOB', $account);

        $this->assertSame('', $error);
        $line = PrLine::where('pr_id', $pr->pr_id)->sole();
        $this->assertSame('FREE_TEXT', $line->i_item_source);
        $this->assertSame($account, (int) $line->i_coa_account_id);
        $this->assertSame((int) $pr->i_department_id, (int) $line->i_department_id);
        $this->assertFalse($line->is_stock);
    }

    public function test_free_text_line_without_an_account_is_refused_with_a_message(): void
    {
        $pr = $this->draft();

        $error = PurchaseRequisition::addFree($pr, 'Air conditioner service', 'JOB', null);

        $this->assertNotSame('', $error);
        $this->assertSame(0, PrLine::where('pr_id', $pr->pr_id)->count());
    }

    public function test_free_text_line_with_a_non_postable_account_is_refused(): void
    {
        $pr = $this->draft();
        $header = $this->accounts(false);
        if ($header === []) {
            $this->markTestSkipped('Tidak ada akun non-postable di fin_account.');
        }

        $error = PurchaseRequisition::addFree($pr, 'Air conditioner service', 'JOB', $header[0]);

        $this->assertNotSame('', $error);
        $this->assertSame(0, PrLine::where('pr_id', $pr->pr_id)->count());
    }

    public function test_account_of_a_line_can_be_changed_but_not_emptied(): void
    {
        $pr = $this->draft();
        [$first, $second] = $this->accounts();
        PurchaseRequisition::addFree($pr, 'Air conditioner service', 'JOB', $first);
        $line = PrLine::where('pr_id', $pr->pr_id)->sole();

        PurchaseRequisition::save($pr, ['lines' => [$line->pr_line_id => ['i_coa_account_id' => (string) $second]]]);
        $this->assertSame($second, (int) $line->refresh()->i_coa_account_id);

        PurchaseRequisition::save($pr, ['lines' => [$line->pr_line_id => ['i_coa_account_id' => '']]]);
        $this->assertSame($second, (int) $line->refresh()->i_coa_account_id);
    }

    public function test_v1_entry_screen_asks_for_the_account_on_the_free_text_tab(): void
    {
        $pr = $this->draft();
        [$account] = $this->accounts();

        $screen = Livewire::test('pages::pur.pr.entry', ['pr' => $pr->pr_id])
            ->assertSeeHtml('wire:model="freeAccountId"')
            ->set('freeDescription', 'Renovasi kantin')
            ->call('addFreeText');
        $this->assertNotSame('', $screen->get('freeError'));
        $this->assertSame(0, PrLine::where('pr_id', $pr->pr_id)->count());

        $screen->set('freeAccountId', (string) $account)->call('addFreeText')
            ->assertSet('freeError', '')->assertSet('freeAccountId', '')->assertSee('Renovasi kantin');
        $line = PrLine::where('pr_id', $pr->pr_id)->sole();
        $this->assertSame($account, (int) $line->i_coa_account_id);

        // Memilih "— pilih —" di kolom Akun COA baris tidak lagi menyimpan NULL.
        $screen->call('updateLine', $line->pr_line_id, 'i_coa_account_id', '');
        $this->assertSame($account, (int) $line->refresh()->i_coa_account_id);
    }

    public function test_v2_form_adds_a_free_item_only_with_an_account(): void
    {
        $pr = $this->draft();
        [$account] = $this->accounts();
        $url = route('v2.pr.save', $pr->pr_id);
        // php artisan test --env=mockup berjalan dengan APP_ENV situs dummy (bukan "testing"), jadi token form tetap diperiksa.
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->post($url, ['action' => 'add_free', 'free_description' => 'Fire extinguisher refill', 'free_uom' => 'PCS'])
            ->assertRedirect()->assertSessionHasErrors('free');
        $this->assertSame(0, PrLine::where('pr_id', $pr->pr_id)->count());

        $this->post($url, ['action' => 'add_free', 'free_description' => 'Fire extinguisher refill', 'free_uom' => 'PCS', 'free_account_id' => $account])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($account, (int) PrLine::where('pr_id', $pr->pr_id)->sole()->i_coa_account_id);

        $this->get(route('v2.screen', 'purchase-requisition').'?id='.$pr->pr_id)->assertOk()->assertSee('free_account_id', false);
    }
}
