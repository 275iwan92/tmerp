<?php

/*
 * REMARK
 * Tujuan       : Menjaga penanganan tiket Bantuan & dukungan v2: balasan wajib berisi, balasan memajukan OPEN ke IN_PROGRESS tanpa menimpa DONE, tiket CLOSED tidak bisa dibalas, status dari daftar dengan penanda selesai yang terisi dan dikosongkan saat dibuka lagi, hanya administrator, layar form, route, grid, redirect v1 dan menu.
 * Sumber hulu  : App\Services\Support\SupportTicketService, layar support-ticket-form, routes/v2_setup.php, SupportTicketGrid.
 * Input        : User johiwan (administrator); tiket dan balasan uji dibuat di dalam transaksi tm2sys yang di-rollback, jadi data nyata tidak berubah.
 * Output       : Lulus bila aturan terpenuhi dan database kembali seperti semula.
 * Dipakai oleh : php artisan test --filter=SupportTicketServiceTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Sys\SecUser;
use App\Models\Sys\SysSupportReply;
use App\Models\Sys\SysSupportTicket;
use App\Services\Support\SupportTicketService;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Grid\Grids;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SupportTicketServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::connection('tm2sys')->beginTransaction();
    }

    protected function tearDown(): void
    {
        while (DB::connection('tm2sys')->transactionLevel() > 0) {
            DB::connection('tm2sys')->rollBack();
        }
        parent::tearDown();
    }

    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    private function ticket(string $status = 'OPEN'): SysSupportTicket
    {
        return SysSupportTicket::create(['ticket_no' => 'SPTZZ'.random_int(1000, 9999), 'created_by_user_id' => $this->admin()->id_user, 'screen_context' => 'zz-screen', 'description' => 'ZZ masalah uji', 'status' => $status]);
    }

    private function refused(callable $f, string $key, string $why): void
    {
        try {
            $f();
            $this->fail('harus ditolak: '.$why);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors(), $why);
        }
    }

    public function test_reply_and_status_rules(): void
    {
        $svc = app(SupportTicketService::class);
        $admin = $this->admin();
        $t = $this->ticket();

        $this->refused(fn () => $svc->reply($t, '   ', $admin), 'reply', 'balasan kosong');
        $this->refused(fn () => $svc->reply($t, str_repeat('x', 5001), $admin), 'reply', 'balasan terlalu panjang');
        $this->assertSame(0, SysSupportReply::where('ticket_id', $t->id_ticket)->count());

        $r = $svc->reply($t, '  Sedang kami cek  ', $admin);
        $this->assertSame('Sedang kami cek', $r->body);
        $this->assertSame($admin->name, $r->author);
        $this->assertSame('IN_PROGRESS', $t->fresh()->status, 'balasan memajukan OPEN');

        $this->refused(fn () => $svc->setStatus($t, 'WEIRD', null, $admin), 'document', 'status tidak dikenal');
        $this->refused(fn () => $svc->setStatus($t, 'DONE', str_repeat('n', 2001), $admin), 'resolution_note', 'catatan terlalu panjang');
        $svc->setStatus($t, 'DONE', 'Sudah diperbaiki', $admin);
        $t->refresh();
        $this->assertSame(['DONE', $admin->login, 'Sudah diperbaiki'], [$t->status, $t->resolved_by, $t->resolution_note]);
        $this->assertNotNull($t->resolved_at);
        $svc->reply($t, 'Terima kasih', $admin);
        $this->assertSame('DONE', $t->fresh()->status, 'balasan tidak menimpa DONE');
        $svc->setStatus($t, 'DONE', '', $admin);
        $this->assertSame('Sudah diperbaiki', $t->fresh()->resolution_note, 'catatan kosong mempertahankan yang lama');

        $svc->setStatus($t, 'CLOSED', null, $admin);
        $this->refused(fn () => $svc->reply($t, 'Masih ada?', $admin), 'document', 'tiket CLOSED tidak bisa dibalas');
        $svc->setStatus($t, 'OPEN', null, $admin);
        $t->refresh();
        $this->assertSame('OPEN', $t->status);
        $this->assertNull($t->resolved_at, 'dibuka lagi mengosongkan penanda selesai');
        $this->assertNull($t->resolved_by);

        $history = $svc->history($t->load(['creator', 'replies']));
        $this->assertContains('Reply', array_column($history, 'action'));
        $this->assertContains('Sent', array_column($history, 'action'));
    }

    public function test_screen_route_grid_and_v1_state(): void
    {
        $this->actingAs($this->admin());
        $t = $this->ticket();
        $form = route('v2.screen', 'support-ticket-form');
        $this->get(route('v2.screen', 'support-tickets'))->assertOk();
        $this->get($form)->assertNotFound();
        $this->get($form.'?id=999999999')->assertNotFound();
        $open = $form.'?id='.$t->id_ticket;
        $this->get($open)->assertOk()->assertSee($t->ticket_no)->assertSee('ZZ masalah uji')->assertSee('name="reply"', false);

        $save = route('v2.setup.ticket.save');
        $this->post($save, ['id' => $t->id_ticket, 'action' => 'reply', 'reply' => ''])->assertSessionHasErrors('reply');
        $this->post($save, ['id' => $t->id_ticket, 'action' => 'reply', 'reply' => 'Kami cek'])->assertRedirect($open);
        $this->assertSame('IN_PROGRESS', $t->fresh()->status);
        $this->post($save, ['id' => $t->id_ticket, 'action' => 'done', 'resolution_note' => 'Beres'])->assertRedirect($open);
        $this->assertSame('DONE', $t->fresh()->status);
        $this->get($open)->assertOk()->assertSee('name="action" value="reopen"', false);
        $this->post($save, ['id' => $t->id_ticket, 'action' => 'close'])->assertRedirect($open);
        $this->get($open)->assertOk()->assertDontSee('name="reply"', false);
        $this->post($save, ['id' => $t->id_ticket, 'action' => 'reply', 'reply' => 'x'])->assertSessionHasErrors('document');
        $this->post($save, ['id' => $t->id_ticket, 'action' => 'bogus'])->assertStatus(422);
        $this->post($save, ['id' => 999999999, 'action' => 'done'])->assertNotFound();

        $uid = DB::connection('tm2sys')->table('sec_user')->insertGetId(['login' => 'tst_tick_plain', 'name' => 'Test plain', 'active' => 1, 'activate' => 1, 'is_admin' => 0]);
        $this->actingAs(SecUser::findOrFail($uid));
        $this->post($save, ['id' => $t->id_ticket, 'action' => 'reopen'])->assertForbidden();
        $this->actingAs($this->admin());

        $this->assertStringContainsString('/v2/s/support-ticket-form?id={id}', Grids::find('support-tickets')->openUrl());
        $this->assertFileDoesNotExist(resource_path('views/pages/setup/support-tickets.blade.php'));
        $this->assertFileExists(base_path('tmerp_unused/setup_support_tickets_v1_2026-10-20.blade.php'));
        $this->assertStringContainsString('support-tickets', MenuRoutes::map()['SET_TICKETS']);
        $this->get('/setup/support-tickets')->assertRedirect('/v2/s/support-tickets');
        $this->get('/setup/support-tickets?open=7')->assertRedirect('/v2/s/support-ticket-form?id=7');
    }
}
