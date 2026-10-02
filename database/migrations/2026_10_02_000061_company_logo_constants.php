<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// Logo perusahaan Telasih Makmur (user 2026-10-02) -- terpisah dari logo & nama APLIKASI (APP_LOGO_PATH / APP_NAME tidak
// disentuh). Tiga versi di resources/branding (turunan lebar 1600 px dari docs/attachments/Asset 3@4x.png & Asset 4@4x.png)
// disalin ke disk public, path-nya dicatat di sys_constant. Dipakai kop cetakan; diganti lewat Settings > Identifikasi.
return new class extends Migration
{
    private const LOGOS = [
        'COMPANY_LOGO_PATH' => ['company-logo.png', 'Company logo, colour on a light background (colour printouts) -- path on the public disk'],
        'COMPANY_LOGO_BW_PATH' => ['company-logo-bw.png', 'Company logo, black and white (black and white printouts) -- made from the colour logo'],
        'COMPANY_LOGO_INVERSE_PATH' => ['company-logo-inverse.png', 'Company logo with white lettering, for dark backgrounds -- path on the public disk'],
    ];

    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        foreach (self::LOGOS as $key => [$file, $description]) {
            Storage::disk('public')->put('branding/'.$file, file_get_contents(resource_path('branding/'.$file)));
            if ($sys->table('sys_constant')->where('constant_key', $key)->whereNotNull('constant_text')->exists()) {
                continue;
            }
            $sys->table('sys_constant')->updateOrInsert(
                ['constant_key' => $key],
                ['category_code' => 'BRANDING', 'constant_group' => 'COMPANY', 'constant_text' => 'branding/'.$file,
                    'description' => $description, 'activate' => 1, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_constant')->whereIn('constant_key', array_keys(self::LOGOS))->delete();
    }
};
