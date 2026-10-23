<?php

/*
 * REMARK
 * Tujuan       : Data Opening Board per pengguna (ringkasan, job description, SOP departemen, widget).
 * Sumber hulu  : Model pegawai, jabatan, tm2mnt.mnt_document (+ mnt_document_line), register SOP / WI tunggal sejak 2026-10-18 (sop_document dilebur).
 * Input        : Pengguna yang login.
 * Output       : Array papan awal.
 * Dipakai oleh : Layar home v2.
 * Status       : Aktif. SOP departemen kini dari MntDocument (company, in force).
 */

namespace App\Support\V2\Board;

use App\Models\Master\EmpEmployee;
use App\Models\Master\OrgPosition;
use App\Models\Mnt\MntDocument;
use App\Models\Sys\SecUser;
use App\Models\Sys\SysBoardTemplate;
use App\Models\Sys\SysConstant;
use App\Models\Sys\SysDashboard;
use App\Models\Sys\SysUserBoard;
use App\Models\Sys\SysUserWidget;
use App\Support\V2\Data\ApprovalCenter;
use Devlib\Components\Dashboard;
use Devlib\Components\WidgetBoard;
use Devlib\Locale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

// Opening screen = papan widget milik user (user 2026-10-02). Top management dulu: yang boleh membuka Laporan Manajemen
// (Presiden Direktur, Direktur, General Manager, Operational Manager) mendapat papan profil MANAGEMENT; jabatan lain masih
// layar pembuka lama sampai templatenya dibuat. Susunan pertama = salinan sys_board_template; sesudah itu milik user
// (Customize di layar, atau perintah lewat asisten). Rancangan + definisi angka: docs/md_for_systems/openingboard_review.md.
final class Board
{
    public const MANAGEMENT = 'MANAGEMENT';

    // Dashboard yang disusun pemiliknya (tanda centang + Customize); dashboard lain = template bidang, hanya dibaca.
    public const OVERALL = SysDashboard::OVERALL;

    public const ACTIONS = ['add', 'remove', 'left', 'right', 'move', 'wider', 'narrower', 'reset', 'period', 'dashboard'];

    // Database yang belum menjalankan migrasi papan (tm2* sebelum disetujui user): layar pembuka lama, halaman tidak gagal.
    public static function ready(): bool
    {
        return Schema::connection('tm2sys')->hasTable('sys_dashboard');
    }

    public static function profile(SecUser $user): ?string
    {
        return self::ready() && $user->canAccessModule('MGMT') ? self::MANAGEMENT : null;
    }

    /** @return Collection<int, SysUserWidget> susunan papan user, urut; dibuat dari template saat pertama kali */
    public static function layout(SecUser $user): Collection
    {
        if (! SysUserBoard::query()->where('user_id', $user->id_user)->exists()) {
            self::fromTemplate($user);
        }

        return SysUserWidget::query()->where('user_id', $user->id_user)->orderBy('sort_order')->orderBy('id_user_widget')->get();
    }

    private static function fromTemplate(SecUser $user): void
    {
        $profile = self::profile($user) ?? self::MANAGEMENT;
        $catalog = app(WidgetCatalog::class);
        DB::connection('tm2sys')->transaction(function () use ($user, $profile, $catalog) {
            SysUserWidget::query()->where('user_id', $user->id_user)->delete();
            $n = 0;
            foreach (self::template(self::OVERALL) as $t) {
                if ($catalog->allowed($user, $t->widget_key)) {
                    SysUserWidget::create(['user_id' => $user->id_user, 'widget_key' => $t->widget_key, 'sort_order' => ++$n * 10, 'size' => $t->size,
                        'created_by' => $user->login, 'updated_by' => $user->login]);
                }
            }
            SysUserBoard::query()->updateOrCreate(['user_id' => $user->id_user], ['profile_code' => $profile]);
        });
    }

    /** @return Collection<int, SysBoardTemplate> baris template aktif satu dashboard, urut */
    private static function template(string $dashboard): Collection
    {
        return SysBoardTemplate::query()->where('profile_code', $dashboard)->where('activate', true)->orderBy('sort_order')->orderBy('id_board_template')->get();
    }

    /** @return array<int, array{code: string, title: string, icon: ?string}> dashboard yang boleh dibuka user ini, urut */
    public static function dashboards(SecUser $user): array
    {
        $catalog = app(WidgetCatalog::class);
        $templates = SysBoardTemplate::query()->where('activate', true)->get(['profile_code', 'widget_key'])->groupBy('profile_code');
        $level = $user->is_admin ? PHP_INT_MAX : $user->maxMgmtLevel();

        return SysDashboard::query()->where('activate', true)->orderBy('sort_order')->orderBy('id_dashboard')->get()
            ->filter(function (SysDashboard $d) use ($user, $catalog, $templates, $level) {
                if ($d->dashboard_code === self::OVERALL) {
                    return true;
                }
                $module = ! $d->module_code || $user->is_admin || $user->canAccessModule($d->module_code) || $user->canAccessModule('MGMT');

                // Dashboard bidang tampil bila user berhak atas bidangnya DAN ada widget templatenya yang boleh ia lihat.
                return $module && $level >= (int) $d->min_level
                    && ($templates->get($d->dashboard_code) ?? collect())->contains(fn ($t) => $catalog->allowed($user, $t->widget_key));
            })
            ->map(fn (SysDashboard $d) => ['code' => $d->dashboard_code, 'title' => $d->title, 'icon' => $d->icon])->values()->all();
    }

    // Dashboard yang sedang dibuka user (tersimpan); yang tidak lagi boleh dibuka kembali ke Overall.
    public static function dashboard(SecUser $user): string
    {
        $code = (string) SysUserBoard::query()->where('user_id', $user->id_user)->value('dashboard_code');

        return in_array($code, array_column(self::dashboards($user), 'code'), true) ? $code : self::OVERALL;
    }

    // Satu aksi papan. Kembalian = pesan salah (English, belum diterjemahkan) atau null bila berhasil.
    // $to = hanya untuk 'move': widget yang ditempati (yang dipindah masuk di depannya); kosong = ke ujung papan.
    public static function apply(SecUser $user, string $action, ?string $key, ?string $to = null): ?string
    {
        if (! in_array($action, self::ACTIONS, true)) {
            return 'Unknown board action.';
        }
        if ($action === 'reset') {
            self::fromTemplate($user);

            return null;
        }
        if ($action === 'dashboard') {
            if (! in_array((string) $key, array_column(self::dashboards($user), 'code'), true)) {
                return 'This dashboard is not available for your account.';
            }
            self::layout($user);
            SysUserBoard::query()->where('user_id', $user->id_user)->update(['dashboard_code' => $key]);

            return null;
        }
        if ($action === 'period') {
            if (! isset(Numbers::PERIODS[(string) $key])) {
                return 'Unknown period.';
            }
            self::layout($user);
            SysUserBoard::query()->where('user_id', $user->id_user)->update(['period_code' => $key]);

            return null;
        }
        $layout = self::layout($user)->values();
        $i = $layout->search(fn (SysUserWidget $w) => $w->widget_key === $key);
        if ($action === 'add') {
            $catalog = app(WidgetCatalog::class);
            if (! $key || ! $catalog->allowed($user, $key)) {
                return 'This widget is not available for your account.';
            }
            if ($i !== false) {
                return 'This widget is already on your board.';
            }
            SysUserWidget::create(['user_id' => $user->id_user, 'widget_key' => $key, 'sort_order' => ((int) $layout->max('sort_order')) + 10,
                'size' => $catalog->all()[$key]['row']->default_size, 'created_by' => $user->login, 'updated_by' => $user->login]);

            return null;
        }
        if ($i === false) {
            return 'This widget is not on your board.';
        }
        $row = $layout[$i];
        if ($action === 'move') {
            $rest = $layout->reject(fn (SysUserWidget $w) => $w->widget_key === $key)->values();
            $at = $to ? $rest->search(fn (SysUserWidget $w) => $w->widget_key === $to) : false;
            $rest->splice($at === false ? $rest->count() : $at, 0, [$row]);
            DB::connection('tm2sys')->transaction(fn () => $rest->values()->each(fn (SysUserWidget $w, int $n) => $w->update(['sort_order' => ($n + 1) * 10])));
        } elseif ($action === 'remove') {
            $row->delete();
        } elseif ($action === 'wider' || $action === 'narrower') {
            $at = array_search((int) $row->size, WidgetBoard::SIZES, true);
            $at = max(0, min(count(WidgetBoard::SIZES) - 1, ($at === false ? 1 : $at) + ($action === 'wider' ? 1 : -1)));
            $row->update(['size' => WidgetBoard::SIZES[$at], 'updated_by' => $user->login]);
        } else {
            $j = $action === 'left' ? $i - 1 : $i + 1;
            if (isset($layout[$j])) {
                [$layout[$i], $layout[$j]] = [$layout[$j], $layout[$i]];
                DB::connection('tm2sys')->transaction(fn () => $layout->each(fn (SysUserWidget $w, int $n) => $w->update(['sort_order' => ($n + 1) * 10])));
            }
        }

        return null;
    }

    // Periode papan pilihan user (tersimpan); papan yang belum pernah dipilih = bulanan.
    public static function period(SecUser $user): string
    {
        $code = (string) SysUserBoard::query()->where('user_id', $user->id_user)->value('period_code');

        return isset(Numbers::PERIODS[$code]) ? $code : Numbers::DEFAULT_PERIOD;
    }

    /** @return array{widgets: array<int, array<string, mixed>>, catalog: array<int, array<string, mixed>>} isi x-dl::widget-board */
    public static function props(SecUser $user): array
    {
        $catalog = app(WidgetCatalog::class);
        $mine = $catalog->for($user);
        $numbers = Numbers::make(self::period($user));
        $widgets = [];
        $on = [];
        $layout = self::layout($user);
        $dashboard = self::dashboard($user);
        $overall = $dashboard === self::OVERALL;
        foreach ($overall ? $layout : self::template($dashboard) as $slot) {
            $entry = $mine[$slot->widget_key] ?? null;
            if (! $entry) {
                continue; // widget dimatikan di katalog atau haknya dicabut: tidak tampil, susunannya tetap tersimpan
            }
            $on[] = $slot->widget_key;
            $widget = $entry['widget'];
            $base = ['key' => $slot->widget_key, 'title' => $entry['row']->title, 'icon' => $widget->icon(), 'tone' => $widget->tone(), 'size' => (int) $slot->size];
            try {
                $widgets[] = $base + ['type' => $widget->type()] + $widget->build($numbers, $user);
            } catch (Throwable $e) {
                // Satu widget yang gagal tidak boleh menjatuhkan layar pembuka: tampil sebagai kartu kosong, salahnya masuk log.
                report($e);
                $widgets[] = $base + ['type' => 'list', 'data' => ['rows' => [], 'empty' => 'This widget could not be loaded']];
            }
        }

        // Daftar centang (hanya Overall): dikelompokkan menurut dashboard asal widget; yang tanpa dashboard memakai kelompoknya.
        $titles = SysDashboard::query()->orderBy('sort_order')->pluck('title', 'dashboard_code');
        $order = array_flip($titles->keys()->all());
        $choices = $overall ? collect($mine)->map(fn ($entry, $key) => ['key' => $key, 'title' => $entry['row']->title, 'description' => $entry['row']->description,
            'group' => $titles[$entry['row']->dashboard_code] ?? $entry['row']->group_name, 'rank' => $order[$entry['row']->dashboard_code] ?? 999,
            'icon' => $entry['widget']->icon(), 'type' => $entry['widget']->type(), 'installed' => in_array($key, $on, true)])->sortBy('rank', SORT_REGULAR)->values()->all() : [];

        return ['widgets' => $widgets, 'catalog' => $choices];
    }

    /** @return array<string, mixed> isi x-dl::dashboard: salam, jam, cuaca, Job Description / SOP jabatan, lalu papan widget */
    public static function opening(SecUser $user): array
    {
        $numbers = Numbers::make();
        $position = ($id = $user->currentPositionId()) ? OrgPosition::with(['department', 'reportsTo'])->find($id) : null;
        $name = (string) ($user->name ?: $user->login);
        $brief = [];
        if ($user->canViewSalesPrice()) {
            // Ringkasan salam selalu bulan berjalan (tidak ikut pilihan periode papan): Confirmed dan Draft disebut terpisah.
            $split = Numbers::split($numbers->orders($numbers->month(), $numbers->now->endOfMonth()));
            $target = $numbers->target('REVENUE', $numbers->month());
            $brief[] = [t('Confirmed orders this month'), Dashboard::fmt($split['confirmed'], 'money')];
            $brief[] = [t('Draft orders this month'), Dashboard::fmt($split['draft'] + $split['waiting'], 'money')];
            if ($target !== null) {
                $brief[] = [t('Left to target'), Dashboard::fmt(max(0, $target - $split['confirmed']), 'money')];
            }
        }
        $waiting = collect(array_keys(ApprovalCenter::SCREENS))->sum(function (string $screen) {
            try {
                return count(ApprovalCenter::docs($screen));
            } catch (Throwable $e) {
                report($e);

                return 0;
            }
        });
        $brief[] = [t('Waiting for your decision'), (string) $waiting];
        $brief[] = [t('Working days left this month'), (string) $numbers->workDaysLeft()];

        return [
            'profile' => Str::lower(self::MANAGEMENT),
            'profile_label' => t('Management Dashboard'),
            'kpi_title' => 'My Board',
            'user' => ['first' => strtok($name, ' '), 'name' => $name, 'code' => $user->employee_id ? EmpEmployee::where('employee_id', $user->employee_id)->value('employee_code') : null,
                'position' => $position?->position_name, 'department' => $position?->department?->department_name, 'superior' => $position?->reportsTo?->position_name],
            'brief' => $brief,
            'attendance' => null,
            'weather' => self::weather(),
            'jobdesc' => $position && trim((string) $position->job_description) !== ''
                ? ['title' => $position->position_name, 'updated' => Locale::d($position->job_description_updated_at) ?? '—', 'html' => $position->job_description] : null,
            'sops' => $position?->department_id ? MntDocument::company()->inForce()->with('lines')->where('department_id', $position->department_id)->orderBy('doc_no')->get()
                ->map(fn (MntDocument $s) => ['code' => $s->doc_no, 'title' => $s->title, 'revision' => $s->revision, 'effective' => Locale::d($s->effective_date) ?? '—',
                    'html' => $s->contentText()])->all() : [],
        ] + self::board($user);
    }

    /** @return array<string, mixed> bagian papan saja: susunan + katalog + alamat aksi */
    private static function board(SecUser $user): array
    {
        $dashboards = self::dashboards($user);
        $dashboard = self::dashboard($user);

        return ['profile' => Str::lower(self::MANAGEMENT), 'board_url' => route('v2.board'),
            'kpi_title' => $dashboard === self::OVERALL ? 'My Board' : (collect($dashboards)->firstWhere('code', $dashboard)['title'] ?? 'My Board'),
            'board_periods' => Numbers::PERIODS, 'board_period' => self::period($user),
            'board_dashboards' => $dashboards, 'board_dashboard' => $dashboard, 'board_editable' => $dashboard === self::OVERALL,
            'board_source' => 'sys_widget + sys_user_widget ('.config('database.connections.tm2sys.database').')'] + self::props($user);
    }

    // Lokasi kantor dari Settings > Constants (COMPANY_WEATHER_CITY / COMPANY_LATITUDE / COMPANY_LONGITUDE); belum diisi = tanpa cuaca.
    private static function weather(): ?array
    {
        $city = SysConstant::text('COMPANY_WEATHER_CITY');
        $lat = SysConstant::query()->where('constant_key', 'COMPANY_LATITUDE')->where('activate', true)->value('constant_number');
        $lon = SysConstant::query()->where('constant_key', 'COMPANY_LONGITUDE')->where('activate', true)->value('constant_number');

        return $city && $lat !== null && $lon !== null ? ['city' => $city, 'lat' => (float) $lat, 'lon' => (float) $lon] : null;
    }

    // HTML papan saja (jawaban aksi Customize dan muat ulang sesudah perintah asisten).
    public static function html(SecUser $user): string
    {
        return view('v2.partials.board', ['board' => self::board($user)])->render();
    }
}
