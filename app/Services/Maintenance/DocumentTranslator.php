<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Support\Localization\Dictionary;
use Illuminate\Support\Facades\DB;

// SOP dan Work Instruction disimpan English (bahasa dasar database); tampil dalam bahasa user lewat kamus, konteks 'maintenance_doc'.
// Teks yang diubah user tanpa terjemahan tampil English sampai penerjemah mengisinya di Setup > Translation Dictionary.
// Terjemahan Indonesia bawaan: resources/data/maintenance/sop_wi_id.json (kunci = teks English).
final class DocumentTranslator
{
    public const CONTEXT = 'maintenance_doc';

    public static function t(?string $en): string
    {
        return Dictionary::t($en, self::CONTEXT);
    }

    /** Masukkan terjemahan bawaan ke kamus. Yang sudah terisi (mungkin sudah dikoreksi di layar) dibiarkan kecuali $force. @return int terjemahan yang ditulis */
    public static function import(bool $force = false, string $lang = 'id'): int
    {
        $file = resource_path('data/maintenance/sop_wi_id.json');
        $pairs = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR)['id'] ?? [];
        $db = DB::connection('tm2sys');
        $norm = fn (string $s) => mb_strtolower(trim($s));
        foreach (array_chunk(array_keys($pairs), 200) as $chunk) {
            $db->table('sys_dictionary')->insertOrIgnore(array_map(fn ($en) => ['context' => self::CONTEXT, 'source_en' => trim((string) $en), 'created_at' => now(), 'created_by' => 'maintenance:translate-docs'], $chunk));
        }
        $ids = [];
        foreach ($db->table('sys_dictionary')->where('context', self::CONTEXT)->pluck('id_dictionary', 'source_en') as $en => $id) {
            $ids[$norm((string) $en)] = $id;
        }
        $filled = $db->table('sys_dictionary_lang')->whereIn('id_dictionary', array_values($ids))->where('lang_code', $lang)->where('text', '<>', '')->pluck('id_dictionary')->flip();
        $n = 0;
        foreach ($pairs as $en => $text) {
            $id = $ids[$norm((string) $en)] ?? null;
            if ($id && ($force || ! isset($filled[$id])) && trim((string) $text) !== '') {
                $db->table('sys_dictionary_lang')->updateOrInsert(['id_dictionary' => $id, 'lang_code' => $lang], ['text' => trim($text), 'updated_at' => now(), 'updated_by' => 'maintenance:translate-docs']);
                $n++;
            }
        }
        Dictionary::flush();

        return $n;
    }
}
