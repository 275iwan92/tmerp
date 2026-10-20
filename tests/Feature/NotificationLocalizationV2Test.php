<?php

namespace Tests\Feature;

use App\Models\Sys\SecUser;
use App\Models\Sys\SysNotification;
use App\Models\Sys\SysTranslation;
use App\Support\V2\Grid\Setup\LocalizationLookup;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Notification Center v2 (grid, buka baris menandai terbaca, Mark all read) dan Localization v2 (sheet kunci x bahasa, simpan sel) memakai tabel yang sama dengan layar v1.
 * Sumber hulu  : sys_notifications, sys_translations, sys_languages (tm2sys); NotificationGrid, LocalizationLookup, rute v2.notifications*.
 * Input        : GET / POST sebagai admin; baris uji ber-prefix T9 dihapus di tearDown.
 * Output       : Status 200 / redirect dan nilai tabel.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif (T9 pindahan v1, 2026-10-20).
 */
class NotificationLocalizationV2Test extends TestCase
{
    private array $notificationIds = [];

    protected function tearDown(): void
    {
        SysNotification::whereIn('id', $this->notificationIds)->delete();
        SysTranslation::where('trans_key', 'like', 'zz_t9_%')->delete();
        parent::tearDown();
    }

    public function test_notification_center_opens_marks_read_and_follows_the_link(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);
        $a = SysNotification::create(['user_id' => $admin->login, 'title' => 'T9 uji satu', 'message' => 'isi', 'type' => 'warning', 'link' => '/v2/s/inquiry', 'is_read' => false]);
        $b = SysNotification::create(['user_id' => $admin->login, 'title' => 'T9 uji dua', 'message' => 'isi', 'type' => 'info', 'link' => 'http://evil.example/x', 'is_read' => false]);
        $this->notificationIds = [$a->id, $b->id];

        $this->get(route('v2.notifications'))->assertOk()->assertSee('Mark all read');

        $this->get(route('v2.notifications.open', $a->id))->assertRedirect('/v2/s/inquiry');
        $this->assertTrue($a->fresh()->is_read);
        // tautan keluar situs tidak diikuti
        $this->get(route('v2.notifications.open', $b->id))->assertRedirect(route('v2.notifications'));
        $this->assertTrue($b->fresh()->is_read);

        $c = SysNotification::create(['user_id' => $admin->login, 'title' => 'T9 uji tiga', 'type' => 'info', 'is_read' => false]);
        $this->notificationIds[] = $c->id;
        $this->post(route('v2.notifications.read-all'))->assertRedirect(route('v2.notifications'));
        $this->assertTrue($c->fresh()->is_read);

        $this->get(route('v2.notifications.open', 999999999))->assertNotFound();
    }

    public function test_localization_sheet_lists_keys_and_saves_cells(): void
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());
        SysTranslation::create(['lang_code' => 'en', 'trans_key' => 'zz_t9_key', 'trans_value' => 'Hello']);

        $this->get('/v2/s/localization')->assertOk();

        $grid = new LocalizationLookup;
        $row = collect($grid->rows())->firstWhere('trans_key', 'zz_t9_key');
        $this->assertSame('Hello', $row['lang_en']);
        $this->assertIsInt($row['id']);

        $grid->save([['trans_key' => 'zz_t9_key', 'lang_en' => 'Hello!', 'lang_id' => 'Halo']]);
        $this->assertSame('Hello!', SysTranslation::where(['lang_code' => 'en', 'trans_key' => 'zz_t9_key'])->value('trans_value'));
        $this->assertSame('Halo', SysTranslation::where(['lang_code' => 'id', 'trans_key' => 'zz_t9_key'])->value('trans_value'));
        $this->assertContains('lang_id', array_column($grid->columns(), 'key'));
    }
}
