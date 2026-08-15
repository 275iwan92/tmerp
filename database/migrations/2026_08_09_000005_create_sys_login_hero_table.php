<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Gap B-OA5 / Tier 3B-S3 (docs/TIER_3_OPENING_APP.md §5) — hero login dirujuk
// mockup tapi belum bertabel. Seed pakai 2 URL Wikimedia (offset press, lisensi
// bebas) yang sebelumnya hardcode di Alpine init() login.blade.php — sekarang
// jadi data, sejalan quote (sys_quotes) yang sudah lebih dulu server-driven.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sys_login_hero', function (Blueprint $table) {
            $table->id();
            $table->string('image_url', 500);
            $table->string('caption', 200)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
        });

        DB::table('sys_login_hero')->insert([
            ['image_url' => 'https://upload.wikimedia.org/wikipedia/commons/e/ea/Offset_press_4_color.jpg', 'caption' => 'Mesin cetak offset 4 warna', 'sort_order' => 10, 'is_active' => true, 'created_at' => now()],
            ['image_url' => 'https://upload.wikimedia.org/wikipedia/commons/e/ed/Offset_printing_press.jpg', 'caption' => 'Line finishing packaging', 'sort_order' => 20, 'is_active' => true, 'created_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_login_hero');
    }
};
