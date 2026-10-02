<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Opening screen = papan widget (user 2026-10-02: "banyak widget termasuk grafik; lewat asisten AI seseorang bisa minta
// dipasangkan widget-nya; top management dulu, yang lain masih ikut template"). Rancangan: docs/md_for_systems/openingboard_review.md.
//   sys_widget          katalog widget: judul, kelompok, modul + level minimum, frasa pemicu asisten (kelas = App\Support\V2\Board\Widgets)
//   sys_board_template  susunan bawaan per profil (MANAGEMENT) = titik awal papan user
//   sys_user_board      tanda papan user sudah dibuat dari template (satu baris per user)
//   sys_user_widget     susunan papan milik user: widget, urutan, lebar
//   sys_kpi_target      target per bulan (perusahaan = sales_person_id 0, atau per sales person)
//   sys_daily_quote     kata harian, satu baris per hari dalam tahun (1..366)
// Semua di tm2sys; sales_person_id -> tm2mst.sales_person = relasi logis (beda database).
return new class extends Migration
{
    // profil => [[widget, lebar], ...] berurutan (12 kolom: 3 = seperempat, 4 = sepertiga, 6 = setengah, 8, 12 = penuh)
    private const TEMPLATE = [
        'MANAGEMENT' => [['order_value', 3], ['new_orders', 3], ['overdue_receivables', 3], ['ontime_delivery', 3], ['revenue_trend', 8], ['targets_by_area', 4],
            ['sales_pipeline', 6], ['sales_ranking', 6], ['waiting_decision', 4], ['orders_by_status', 4], ['top_customers', 4], ['quote_of_day', 12]],
    ];

    // kunci => [angka, teks, uraian]
    private const CONSTANTS = [
        'COMPANY_WEATHER_CITY' => [null, 'Malang', 'City name shown beside the weather on the opening screen.'],
        'COMPANY_LATITUDE' => [-7.935, null, 'Latitude of the office, used to fetch the weather on the opening screen.'],
        'COMPANY_LONGITUDE' => [112.655, null, 'Longitude of the office, used to fetch the weather on the opening screen.'],
    ];

    // kode => [kode menu sebelahnya, urut, label, ikon, layar]
    private const MENU = [
        'SET_WIDGETS' => ['SET_ASSISTANT_LOG', 51, 'Opening Board Widgets', 'fa-solid fa-table-cells-large', 'setup-widgets'],
        'SET_BOARD_TEMPLATE' => ['SET_ASSISTANT_LOG', 52, 'Opening Board Templates', 'fa-solid fa-table-columns', 'setup-board-template'],
        'SET_KPI_TARGETS' => ['SET_ASSISTANT_LOG', 53, 'KPI Targets', 'fa-solid fa-bullseye', 'setup-kpi-targets'],
        'SET_DAILY_QUOTES' => ['SET_ASSISTANT_LOG', 54, 'Daily Quotes', 'fa-solid fa-quote-left', 'setup-daily-quotes'],
    ];

    private function isDummy(): bool
    {
        return str_starts_with(DB::connection('tm2sys')->getDatabaseName(), 'ddt');
    }

    public function up(): void
    {
        $sys = Schema::connection('tm2sys');
        $audit = function (Blueprint $t) {
            $t->timestamp('created_at')->nullable();
            $t->string('created_by', 50)->nullable();
            $t->timestamp('updated_at')->nullable();
            $t->string('updated_by', 50)->nullable();
        };
        if (! $sys->hasTable('sys_widget')) {
            $sys->create('sys_widget', function (Blueprint $t) use ($audit) {
                $t->id('id_widget');
                $t->string('widget_key', 50)->unique();
                $t->string('title', 100);
                $t->string('description', 255)->nullable();
                $t->string('group_name', 40)->default('General');
                $t->string('module_code', 20)->nullable();        // sec_module: pemasang wajib boleh membuka modul ini; kosong = semua
                $t->unsignedTinyInteger('min_level')->default(0); // sec_group.mgmt_level minimum di atas hak modul
                $t->unsignedTinyInteger('default_size')->default(4);
                $t->string('phrases', 255)->nullable();           // kata pemicu untuk asisten, dipisah koma
                $t->unsignedInteger('sort_order')->default(0);
                $t->boolean('activate')->default(true);
                $audit($t);
            });
        }
        if (! $sys->hasTable('sys_board_template')) {
            $sys->create('sys_board_template', function (Blueprint $t) use ($audit) {
                $t->id('id_board_template');
                $t->string('profile_code', 30);
                $t->string('widget_key', 50);
                $t->unsignedInteger('sort_order')->default(0);
                $t->unsignedTinyInteger('size')->default(4);
                $t->boolean('activate')->default(true);
                $audit($t);
                $t->unique(['profile_code', 'widget_key']);
            });
        }
        if (! $sys->hasTable('sys_user_board')) {
            $sys->create('sys_user_board', function (Blueprint $t) {
                $t->id('id_user_board');
                $t->unsignedBigInteger('user_id')->unique();
                $t->string('profile_code', 30);
                $t->string('period_code', 12)->default('monthly'); // periode papan pilihan user: daily .. yearly
                $t->timestamp('created_at')->nullable();
                $t->timestamp('updated_at')->nullable();
            });
        }
        if (! $sys->hasTable('sys_user_widget')) {
            $sys->create('sys_user_widget', function (Blueprint $t) use ($audit) {
                $t->id('id_user_widget');
                $t->unsignedBigInteger('user_id');
                $t->string('widget_key', 50);
                $t->unsignedInteger('sort_order')->default(0);
                $t->unsignedTinyInteger('size')->default(4);
                $audit($t);
                $t->unique(['user_id', 'widget_key']);
            });
        }
        if (! $sys->hasTable('sys_kpi_target')) {
            $sys->create('sys_kpi_target', function (Blueprint $t) use ($audit) {
                $t->id('id_kpi_target');
                $t->string('target_key', 30);                          // REVENUE | NEW_ORDERS | NEW_CUSTOMERS | ONTIME_DELIVERY | OVERDUE_RECEIVABLES
                $t->date('period_month');                              // tanggal 1 bulan itu
                $t->unsignedBigInteger('sales_person_id')->default(0); // 0 = perusahaan; selain itu tm2mst.sales_person (logis)
                $t->decimal('target_value', 18, 2);
                $t->string('notes', 255)->nullable();
                $t->boolean('activate')->default(true);
                $audit($t);
                $t->unique(['target_key', 'period_month', 'sales_person_id'], 'sys_kpi_target_unique');
            });
        }
        if (! $sys->hasTable('sys_daily_quote')) {
            $sys->create('sys_daily_quote', function (Blueprint $t) use ($audit) {
                $t->id('id_daily_quote');
                $t->unsignedSmallInteger('day_no')->unique();          // 1..366; 60 = 29 Februari
                $t->string('quote_text', 500);
                $t->string('author', 100)->nullable();
                $t->boolean('activate')->default(true);
                $audit($t);
            });
        }

        $db = DB::connection('tm2sys');
        $by = 'claude (v2 build)';
        foreach (self::TEMPLATE as $profile => $rows) {
            foreach ($rows as $i => [$key, $size]) {
                if (! $db->table('sys_board_template')->where('profile_code', $profile)->where('widget_key', $key)->exists()) {
                    $db->table('sys_board_template')->insert(['profile_code' => $profile, 'widget_key' => $key, 'sort_order' => ($i + 1) * 10, 'size' => $size,
                        'activate' => 1, 'created_at' => now(), 'created_by' => $by]);
                }
            }
        }
        foreach (self::CONSTANTS as $key => [$number, $text, $description]) {
            if (! $db->table('sys_constant')->where('constant_key', $key)->exists()) {
                $db->table('sys_constant')->insert(['constant_key' => $key, 'category_code' => 'BRANDING', 'constant_group' => 'COMPANY', 'constant_number' => $number,
                    'constant_text' => $text, 'description' => $description, 'activate' => 1, 'created_at' => now(), 'created_by' => $by]);
            }
        }

        // Kata harian: isi awal dari berkas contoh DevLib (366 baris "teks | penulis"); sesudah itu dirawat di Setup > Daily Quotes.
        $file = base_path('devlib/public/media/text/motivasi-harian.txt');
        if (is_file($file) && ! $db->table('sys_daily_quote')->exists()) {
            $day = 0;
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || $day >= 366) {
                    continue;
                }
                [$text, $author] = array_map('trim', explode('|', $line, 2) + [1 => '']);
                $db->table('sys_daily_quote')->insert(['day_no' => ++$day, 'quote_text' => mb_substr($text, 0, 500), 'author' => $author !== '' ? mb_substr($author, 0, 100) : null,
                    'activate' => 1, 'created_at' => now(), 'created_by' => $by]);
            }
        }

        $menu = fn () => $db->table('sys_app_menu');
        foreach (self::MENU as $code => [$sibling, $sort, $label, $icon, $slug]) {
            // Database yang belum punya menu asisten (tm2 sebelum asisten dipasang): letakkan di sebelah Constants.
            $near = $menu()->where('code', $sibling)->first() ?? $menu()->where('code', 'SET_CONSTANTS')->first();
            if ($near && ! $menu()->where('code', $code)->exists()) {
                $menu()->insert(['parent_id' => $near->parent_id, 'code' => $code, 'label' => $label, 'icon' => $icon, 'menu_type' => 'item', 'object_type' => 'grid',
                    'target' => 'v2:'.$slug, 'sort_order' => $sort, 'activate' => 1, 'required_object' => $near->required_object, 'status_code' => 'READY_FOR_QC',
                    'status_updated_by' => $by, 'status_updated_at' => now(), 'created_at' => now()]);
            }
        }

        // Target contoh HANYA di database dummy: 12 bulan terakhir + bulan berjalan, perusahaan dan per sales person.
        if ($this->isDummy() && ! $db->table('sys_kpi_target')->exists()) {
            $people = DB::connection('tm2mst')->table('sales_person')->where('activate', 1)->orderBy('id_sales_person')->pluck('id_sales_person')->all();
            for ($i = 11; $i >= 0; $i--) {
                $month = now()->startOfMonth()->subMonthsNoOverflow($i)->toDateString();
                $rows = [['REVENUE', 0, 150_000_000 + (11 - $i) * 5_000_000], ['NEW_ORDERS', 0, 12], ['NEW_CUSTOMERS', 0, 3], ['ONTIME_DELIVERY', 0, 95],
                    ['OVERDUE_RECEIVABLES', 0, 500_000_000]];
                foreach ($people as $n => $person) {
                    $rows[] = ['REVENUE', $person, [60_000_000, 40_000_000, 30_000_000, 15_000_000, 10_000_000][$n] ?? 10_000_000];
                }
                foreach ($rows as [$key, $person, $value]) {
                    $db->table('sys_kpi_target')->insert(['target_key' => $key, 'period_month' => $month, 'sales_person_id' => $person, 'target_value' => $value,
                        'notes' => 'Dummy target for UAT', 'activate' => 1, 'created_at' => now(), 'created_by' => $by]);
                }
            }
        }
    }

    public function down(): void
    {
        $db = DB::connection('tm2sys');
        $db->table('sys_app_menu')->whereIn('code', array_keys(self::MENU))->delete();
        $db->table('sys_constant')->whereIn('constant_key', array_keys(self::CONSTANTS))->delete();
        foreach (['sys_daily_quote', 'sys_kpi_target', 'sys_user_widget', 'sys_user_board', 'sys_board_template', 'sys_widget'] as $table) {
            Schema::connection('tm2sys')->dropIfExists($table);
        }
    }
};
