<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ringkasanstatus.md, kebijakanbesar.md, catatanauditmenyeluruh.md dipecah
// dari docs/BACKLOG.md (permintaan user 2026-09-22: "Ringkasan status juga
// tulis di md file tersendiri" + "catatan audit pindahkan ke md file
// sendiri, kebijakan tulis juga di kebijakan md file"). Tidak dimiliki
// 1 menu, jadi terdaftarnya di sys_doc_reference (CLAUDE.md §6 DEC-NAMING).
return new class extends Migration
{
    private const DOCS = [
        ['ringkasanstatus.md', 'Ringkasan Status Lintas-Modul', 'Diskusi status per nomor R#, dipecah dari BACKLOG.md.'],
        ['kebijakanbesar.md', 'Kebijakan Besar Belum Dieksekusi', 'Kebijakan yang sudah diputuskan user tapi belum dieksekusi, dipecah dari BACKLOG.md.'],
        ['catatanauditmenyeluruh.md', 'Catatan Audit Menyeluruh docs/*.md', 'Hasil audit dokumen lama (2026-09-02) + koreksi dokumen stale, dipecah dari BACKLOG.md.'],
    ];

    public function up(): void
    {
        $sort = 210;

        foreach (self::DOCS as [$file, $title, $desc]) {
            DB::connection('tm2sys')->table('sys_doc_reference')->updateOrInsert(
                ['file_name' => $file],
                [
                    'title' => $title,
                    'category' => 'GLOBAL_RULE',
                    'related_menu_code' => null,
                    'description' => $desc,
                    'sort_order' => $sort,
                    'activate' => 1,
                ]
            );
            $sort += 1;
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_doc_reference')
            ->whereIn('file_name', array_column(self::DOCS, 0))
            ->delete();
    }
};
