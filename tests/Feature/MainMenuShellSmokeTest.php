<?php

namespace Tests\Feature;

use App\Models\Sys\SecUser;
use App\Support\Branding\Brand;
use App\View\Components\Ui\Button;
use Livewire\Livewire;
use Tests\TestCase;

class MainMenuShellSmokeTest extends TestCase
{
    public function test_admin_sees_full_menu_shell(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        $response = $this->get('/menu');

        $response->assertOk();
        // FIX 2026-09-03: brand name dulu literal "TELASIH MAKMUR" hardcode di
        // <title>, sekarang configurable via Brand::name() (docs/configurationstandard.md,
        // dibangun sesi sebelumnya) -- default "ERP TM", bisa diganti admin.
        // Assert dinamis, bukan string usang.
        $response->assertSee(Brand::name());
        // Modul dari sys_app_menu harus muncul di mega menu (admin bypass semua izin).
        $response->assertSee('Sales');
        $response->assertSee('Procurement');
        $response->assertSee('Setup', false);
    }

    public function test_sales_staff_sees_only_permitted_modules(): void
    {
        $sales = SecUser::where('login', 'rina')->firstOrFail();
        $this->actingAs($sales);

        $response = $this->get('/menu');

        $response->assertOk();
        // rina (SALES_STAFF) hanya di-grant INQUIRY -> module SALES saja yang boleh tampil.
        $response->assertSee('Sales');
        $response->assertDontSee('Procurement');
        $response->assertDontSee('>Setup<', false);
    }

    public function test_setup_menu_item_opens_security_index_with_tab(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        $this->get('/security?tab=groups')->assertOk()->assertSee('Grup');
    }

    // Settings dipecah 2026-09-07 dari 1 layar gado-gado (setup.configuration)
    // jadi 4 route+layar sendiri -- docs/configurationstandard.md.
    public function test_settings_submenus_each_open_own_screen(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        $this->get('/setup/identification')->assertOk()->assertSee('Identitas Aplikasi');
        $this->get('/setup/language')->assertOk()->assertSee('Language');
        $this->get('/setup/localization')->assertOk()->assertSee('Terjemahan Teks UI');
        $this->get('/setup/constants')->assertOk()->assertSee('Constants');
    }

    // "Library Component for UI/UX" (2026-09-09) -- showcase resources/views/
    // components/form/*.blade.php baru (16 komponen, docs/mockups/UIUX_components_part1.html
    // + mockup_UIUX_calendar.html versi ringkas), menu SET_UILIB di grup Settings.
    public function test_component_library_screen_opens_and_is_in_settings_menu(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        // FIX 2026-09-09: browser tab title diganti "DevLib UI UX" + kartu
        // heading "Library Component for UI/UX" dihapus dari layar (redundan
        // di atas tiap tab) -- assert ke title baru, bukan teks lama yg sudah
        // tak ada di response.
        $this->get('/setup/component-library')->assertOk()->assertSee('DevLib UI UX');

        $component = Livewire::test('pages::main-menu');
        $tree = collect($component->instance()->menuTree());
        $setup = $tree->firstWhere('code', 'SETUP');
        // 2026-10-04: Setup regrouped into eight groups; DevLib UI UX lives in Others.
        $othersGroup = collect($setup['children'])->firstWhere('code', 'SET_GRP_OTHERS');

        $this->assertTrue(collect($othersGroup['children'])->contains('code', 'SET_UILIB'));
    }

    // Tab Calendar (2026-09-09) -- replika docs/mockups/mockup_UIUX_calendar.html
    // (bukan lagi versi ringkas x-form.mini-calendar) -- view Day/Week/Month,
    // drag-drop (calMoveEvent), CRUD event (calCreateNew/calSaveEdit/calDeleteEvent).
    public function test_calendar_tab_views_and_event_crud(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        $c = Livewire::test('pages::setup.component-library');
        $c->assertOk()->assertSee('Event Calendar');

        foreach (['month', 'day', 'week'] as $view) {
            $c->call('calSetView', $view)->assertOk();
        }

        $beforeCount = count($c->get('calEvents'));
        $c->call('calCreateNew');
        $newId = $c->get('calEditingId');
        $this->assertNotNull($newId);
        $this->assertCount($beforeCount + 1, $c->get('calEvents'));

        $c->set('calEditTitle', 'Rapat Demo Test')->call('calSaveEdit');
        $this->assertNull($c->get('calEditingId'));
        $saved = collect($c->get('calEvents'))->firstWhere('id', $newId);
        $this->assertSame('Rapat Demo Test', $saved['title']);

        $targetDate = now()->addDays(5)->toDateString();
        $c->call('calMoveEvent', $newId, $targetDate);
        $moved = collect($c->get('calEvents'))->firstWhere('id', $newId);
        $this->assertStringStartsWith($targetDate, $moved['start']);

        $c->call('calDeleteEvent', $newId);
        $this->assertCount($beforeCount, $c->get('calEvents'));
    }

    // 2026-09-07: footer "Settings"/"Security" dulu 2 array Alpine literal
    // hardcode di main-menu.blade.php -- sekarang 100% data-driven dari
    // sys_app_menu (SET_GRP_SETTINGS/SET_GRP_SECURITY, migration
    // 2026_09_07_000004) supaya gampang dikonfigurasi lewat tabel, bukan
    // edit Blade. Assert LANGSUNG ke computed menuTree() (bukan scraping
    // HTML/JSON mentah -- rapuh terhadap perubahan serialisasi Alpine).
    public function test_settings_group_leaves_appear_in_sidebar_shell(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        $component = Livewire::test('pages::main-menu');
        $tree = collect($component->instance()->menuTree());

        $setup = $tree->firstWhere('code', 'SETUP');
        $this->assertNotNull($setup, 'Modul SETUP harus ada di menuTree() utk admin.');

        // 2026-10-04: eight groups fixed by the user (migration 2026_10_04_000010). Setting Approval / Acting Officer
        // exist on ddt only, so Security is checked by the items both sites share.
        $children = collect($setup['children']);
        $this->assertSame(
            ['Security', 'Support & Ticket', 'Help and Support', 'Language and Localization', 'Dummy Data & Migration', 'Constants', 'Dashboard and Widgets', 'Others'],
            $children->where('menuType', 'group')->pluck('label')->values()->all()
        );
        $labels = fn (string $code) => collect($children->firstWhere('code', $code)['children'])->pluck('label')->all();
        $this->assertSame(['Account', 'Group', 'Group per Submodule'], array_slice($labels('SET_GRP_SECURITY'), 0, 3));
        $this->assertSame(['Support Tickets'], $labels('SET_GRP_SUPPORT'));
        $this->assertSame(['Help Topics', 'Help Lines', 'Assistant Questions', 'Assistant Log'], $labels('SET_GRP_HELP'));
        $this->assertSame(['Languages', 'Language', 'Localization', 'Translation Dictionary', 'Message Groups'], $labels('SET_GRP_LANG'));
        $this->assertSame(['Dummy & Legacy Data', 'Migration'], $labels('SET_GRP_DUMMY'));
        $this->assertSame(['Constants', 'Constant Categories', 'Document Number Series'], $labels('SET_GRP_CONSTANTS'));
        $this->assertSame(['Opening Dashboards', 'Opening Board Widgets', 'Opening Board Templates', 'KPI Targets', 'Daily Quotes'], $labels('SET_GRP_DASHBOARD'));
        $this->assertSame(['Identification', 'Menu Setup', 'DevLib UI UX'], $labels('SET_GRP_OTHERS'));

        // SET_THEME (dead v1 entry, NOT_BUILT) stays inactive inside Others, so it must not appear anywhere in the tree.
        $this->assertFalse(collect($children->firstWhere('code', 'SET_GRP_OTHERS')['children'])->contains('code', 'SET_THEME'));
    }

    // Kerangka i18n app-wide (2026-09-07, App\Support\Localization\Translator)
    // -- picker Bahasa di dropdown profil dulu dummy (cuma toast, tidak
    // menyimpan/mengubah apapun). Sekarang persist ke sec_user.lang_code +
    // beberapa string chrome shell paling dasar (Tema/Bahasa/Logout/dst)
    // sungguh berubah. TIDAK ada RefreshDatabase di project ini (test jalan
    // atas DB real) -- WAJIB revert lang_code di finally, jangan tinggalkan
    // akun demo dalam state 'en'.
    public function test_setting_language_persists_and_switches_shell_chrome(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);
        $originalLang = $admin->lang_code;

        try {
            $component = Livewire::test('pages::main-menu');
            $component->call('setLang', 'en');

            $admin->refresh();
            $this->assertSame('en', $admin->lang_code);

            $component2 = Livewire::test('pages::main-menu');
            $component2->assertSee('Theme');
            $component2->assertSee('Log out');

            // Kode bahasa tak dikenal (bukan dari sys_languages) HARUS ditolak.
            $component2->call('setLang', 'zz-INVALID');
            $admin->refresh();
            $this->assertSame('en', $admin->lang_code);
        } finally {
            $admin->update(['lang_code' => $originalLang]);
        }
    }

    // Tab bar diperluas 4->6 (2026-09-09): Grid | Form | Calendar | Kanban |
    // Gantt Chart | Graphics. setMainTab() harus terima keenamnya.
    public function test_main_tab_bar_accepts_all_six_tabs(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        $c = Livewire::test('pages::setup.component-library');
        $this->assertSame('base', Livewire::test('pages::setup.component-library')->get('mainTab'), 'Tab Dasar (x-ui) = tab pembuka.');
        foreach (['base', 'grid', 'forms', 'calendar', 'kanban', 'gantt', 'graphics'] as $tab) {
            $c->call('setMainTab', $tab);
            $this->assertSame($tab, $c->get('mainTab'));
        }

        $c->call('setMainTab', 'not-a-real-tab');
        $this->assertSame('graphics', $c->get('mainTab'), 'Tab tak dikenal harus ditolak, mainTab tetap nilai valid terakhir.');
    }

    // Kanban Board (2026-09-09, rebuild penuh docs/mockups/mockup_UIUX_kanban.html)
    // -- komponen reusable x-kanban.board, server = sumber kebenaran posisi
    // kartu/checklist/komentar (docs/kanbanboardstandard.md).
    public function test_kanban_board_move_add_and_card_detail_mutations(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        $c = Livewire::test('pages::setup.component-library');
        $c->assertOk()->assertSee('Kanban Board');

        // Pindah kartu #1 dari BARU ke PROSES, taruk di index 0.
        $c->call('kanbanMoveCard', 1, 'PROSES', 0);
        $moved = collect($c->get('demoKanbanCards'))->firstWhere('id', 1);
        $this->assertSame('PROSES', $moved['columnKey']);
        $this->assertSame(0, $moved['order']);

        // Tambah kartu baru ke kolom SELESAI.
        $beforeCount = count($c->get('demoKanbanCards'));
        $c->call('kanbanAddCard', 'SELESAI', 'Order Baru Demo');
        $this->assertCount($beforeCount + 1, $c->get('demoKanbanCards'));
        $newCard = collect($c->get('demoKanbanCards'))->firstWhere('title', 'Order Baru Demo');
        $this->assertSame('SELESAI', $newCard['columnKey']);

        // Tambah kolom baru.
        $beforeCols = count($c->get('demoKanbanColumns'));
        $c->call('kanbanAddColumn', 'Ditolak');
        $this->assertCount($beforeCols + 1, $c->get('demoKanbanColumns'));

        // Buka kartu #2, toggle checklist item, tambah komentar -- server jadi
        // sumber kebenaran (bug lama: modal Alpine x-if beku, sudah diperbaiki
        // jadi $kanbanActiveCardId server-side).
        $c->call('kanbanOpenCard', 1);
        $this->assertSame(1, $c->get('kanbanActiveCardId'));

        $c->call('kanbanAddChecklistItem', 1, 'Item checklist baru');
        $card1 = collect($c->get('demoKanbanCards'))->firstWhere('id', 1);
        $this->assertTrue(collect($card1['checklist'])->contains('text', 'Item checklist baru'));

        $c->call('kanbanAddComment', 1, 'Komentar demo test');
        $card1 = collect($c->get('demoKanbanCards'))->firstWhere('id', 1);
        $this->assertTrue(collect($card1['comments'])->contains('text', 'Komentar demo test'));

        $c->call('kanbanCloseCard');
        $this->assertNull($c->get('kanbanActiveCardId'));

        // Toggle tampilan Board <-> Tabular.
        $c->call('kanbanSetView', 'tabular');
        $this->assertSame('tabular', $c->get('kanbanView'));
    }

    // Tab Grid (2026-09-09) -- showcase docs/griduistandard.md di atas data
    // dummy statis, paginasi manual di atas Collection (HasGridPagination).
    public function test_grid_tab_search_and_pagination(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        $c = Livewire::test('pages::setup.component-library');
        $c->assertOk()->assertSee('Customer Demo 1');

        $c->set('demoGridSearch', 'Demo 3');
        $filtered = $c->instance()->demoGridItems();
        $this->assertSame(6, $filtered->total(), 'Demo 3/30/31/32/33/34 = 6 baris cocok.');

        $c->set('demoGridSearch', '');
        $all = $c->instance()->demoGridItems();
        $this->assertSame(34, $all->total());
        $this->assertSame(25, $all->perPage());
    }

    // 2026-09-23: tab Grid DevLib = acuan perilaku grid Master Pegawai --
    // kotak statistik = filter (klik saring, klik lagi lepas), angka kotak
    // tidak ikut berubah saat disaring; tab Dasar membaca katalog dari class.
    public function test_devlib_grid_stat_boxes_filter_and_base_catalog(): void
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());

        $c = Livewire::test('pages::setup.component-library');
        $boxesBefore = collect($c->instance()->statBoxes())->pluck('value', 'label')->all();
        $this->assertCount(4, $boxesBefore);

        $c->call('toggleStatFilter', 'inactive');
        $this->assertSame('inactive', $c->get('statFilter'));
        $this->assertSame(6, $c->instance()->demoGridItems()->total(), 'id kelipatan 5 (5..30) = 6 nonaktif.');
        $this->assertSame($boxesBefore, collect($c->instance()->statBoxes())->pluck('value', 'label')->all());

        $c->call('toggleStatFilter', 'inactive');
        $this->assertSame('', $c->get('statFilter'));
        $this->assertSame(34, $c->instance()->demoGridItems()->total());

        $c->call('setDemoGridView', 'card');
        $this->assertSame('card', $c->get('demoGridView'));

        $cat = $c->instance()->uiCatalog();
        $this->assertSame((new Button)->variantKeys(), $cat['buttonVariants']);
        $this->assertContains('accent', $c->instance()->uiTokens());
        $c->assertSee('preset="save"', false);
    }

    // Tab Graphics (2026-09-09) -- pie/bar/line via Chart.js, config dibangun
    // server-side (docs/reportstandard.md + skill dataviz: hue kategorikal
    // tetap, tanpa dual-axis).
    public function test_graphics_chart_configs_are_well_formed(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        $c = Livewire::test('pages::setup.component-library');

        $pie = $c->instance()->graphicsPieConfig();
        $this->assertSame('pie', $pie['type']);
        $this->assertCount(3, $pie['data']['labels']);
        $this->assertCount(3, $pie['data']['datasets'][0]['backgroundColor']);

        $bar = $c->instance()->graphicsBarConfig();
        $this->assertSame('bar', $bar['type']);
        $this->assertCount(4, $bar['data']['datasets'][0]['data']);

        $line = $c->instance()->graphicsLineConfig();
        $this->assertSame('line', $line['type']);
        $this->assertCount(6, $line['data']['labels']);
        $this->assertArrayNotHasKey('y1', $line['options']['scales'] ?? [], 'Tidak boleh dual-axis (skill dataviz).');
    }
}
