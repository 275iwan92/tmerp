<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Upload artwork/desain per part di Inquiry (permintaan user 2026-09-04) --
// dasar engine saran default kertas/finishing/tinta (kombinasi analisis warna
// gambar + histori Inquiry sejenis) & referensi visual yg ikut dibawa ke
// ProDev (lihat sendToProDev() di inquiry/form.blade.php).
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sdi')->table('iqp_part', function (Blueprint $table): void {
            $table->string('artwork_path', 255)->nullable()->after('flat_area');
            $table->string('artwork_original_filename', 255)->nullable()->after('artwork_path');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2sdi')->table('iqp_part', function (Blueprint $table): void {
            $table->dropColumn(['artwork_path', 'artwork_original_filename']);
        });
    }
};
