<?php

/*
 * REMARK
 * Tujuan       : Isi awal Help (topik, judul Indonesia title_id, langkah, isian) dari resources/data/help/*.json ke tabel help + kamus, dan daftar layar menu yang belum punya topik.
 * Sumber hulu  : resources/data/help/*.json (bentuk: _format.md), sys_app_menu.
 * Input        : Daftar topik hasil baca JSON; login pelaksana.
 * Output       : Baris sys_help_topic / sys_help_line dan terjemahan kamus konteks help.
 * Dipakai oleh : php artisan help:import, HelpMap, ErpKnowledge.
 * Status       : Aktif.
 */

namespace App\Services\Help;

use App\Models\Sys\SysAppMenu;
use App\Models\Sys\SysHelpLine;
use App\Models\Sys\SysHelpTopic;
use App\Support\Localization\Dictionary;
use Illuminate\Support\Facades\DB;

// Help per layar (sys_help_topic + sys_help_line). Teks tersimpan English; bahasa lain lewat kamus konteks 'help'.
final class HelpCenter
{
    public const CONTEXT = 'help';

    // Muat isi awal dari resources/data/help/*.json (bentuk: resources/data/help/_format.md).
    // Topik yang sudah ada TIDAK ditimpa (bisa sudah diubah user di layar) kecuali $force; terjemahan hanya mengisi yang kosong.
    /** @return array{topics: int, skipped: int, lines: int, translations: int} */
    public static function import(array $topics, string $login, bool $force = false): array
    {
        $done = ['topics' => 0, 'skipped' => 0, 'lines' => 0, 'translations' => 0];
        $pairs = [];
        $db = DB::connection('tm2sys');

        $db->transaction(function () use ($topics, $login, $force, &$done, &$pairs) {
            foreach ($topics as $i => $t) {
                $topic = SysHelpTopic::query()->where('topic_key', $t['key'])->first();
                if ($topic && ! $force) {
                    $done['skipped']++;

                    continue;
                }
                $topic ??= new SysHelpTopic(['topic_key' => $t['key'], 'created_by' => $login]);
                $topic->fill(['topic_type' => $t['type'], 'module_code' => $t['module'], 'title' => $t['title'], 'purpose' => $t['purpose']['en'],
                    'keywords' => $t['keywords'] ?? null, 'sort_order' => ($i + 1) * 10, 'activate' => true, 'updated_by' => $login])->save();
                $pairs[$t['purpose']['en']] = $t['purpose']['id'];
                if (! empty($t['title_id'])) {
                    $pairs[$t['title']] = $t['title_id'];
                }

                SysHelpLine::query()->where('help_topic_id', $topic->getKey())->delete();
                $rows = [];
                foreach ([SysHelpLine::STEP => $t['steps'] ?? [], SysHelpLine::FIELD => $t['fields'] ?? []] as $type => $list) {
                    foreach (array_values($list) as $n => $l) {
                        $rows[] = ['help_topic_id' => $topic->getKey(), 'line_type' => $type, 'field_label' => $l['label'] ?? null, 'body' => $l['en'],
                            'sort_order' => ($n + 1) * 10, 'activate' => 1, 'created_at' => now(), 'updated_at' => now(), 'created_by' => $login, 'updated_by' => $login];
                        $pairs[$l['en']] = $l['id'];
                    }
                }
                SysHelpLine::query()->insert($rows);
                $done['topics']++;
                $done['lines'] += count($rows);
            }
        });

        $done['translations'] = self::translate($pairs, 'id');

        return $done;
    }

    // English => terjemahan, masuk kamus konteks 'help'. Terjemahan yang sudah terisi dibiarkan (bisa sudah dikoreksi di layar).
    private static function translate(array $pairs, string $lang): int
    {
        $db = DB::connection('tm2sys');
        $norm = fn (string $s) => mb_strtolower(trim($s));
        foreach (array_chunk(array_keys($pairs), 200) as $chunk) {
            $db->table('sys_dictionary')->insertOrIgnore(array_map(fn ($en) => ['context' => self::CONTEXT, 'source_en' => trim((string) $en),
                'created_at' => now(), 'created_by' => 'help:import'], $chunk));
        }
        $ids = [];
        foreach ($db->table('sys_dictionary')->where('context', self::CONTEXT)->pluck('id_dictionary', 'source_en') as $en => $id) {
            $ids[$norm((string) $en)] = $id;
        }
        $filled = $db->table('sys_dictionary_lang')->whereIn('id_dictionary', array_values($ids))->where('lang_code', $lang)->where('text', '<>', '')
            ->pluck('id_dictionary')->flip();
        $n = 0;
        foreach ($pairs as $en => $text) {
            $id = $ids[$norm((string) $en)] ?? null;
            if ($id && ! isset($filled[$id]) && trim((string) $text) !== '') {
                $db->table('sys_dictionary_lang')->updateOrInsert(['id_dictionary' => $id, 'lang_code' => $lang], ['text' => trim($text), 'updated_at' => now(), 'updated_by' => 'help:import']);
                $filled[$id] = true;
                $n++;
            }
        }
        Dictionary::flush();

        return $n;
    }

    // Layar v2 di menu aktif yang belum punya topik help (slug => label menu) -- supaya layar baru tidak lolos tanpa help.
    public static function missing(): array
    {
        $have = SysHelpTopic::query()->pluck('topic_key')->flip();

        return SysAppMenu::query()->where('menu_type', 'item')->where('activate', true)->where('target', 'like', 'v2:%')->orderBy('label')->get(['label', 'target'])
            ->mapWithKeys(fn ($m) => [substr($m->target, 3) => $m->label])->reject(fn ($label, $slug) => isset($have[$slug]))->all();
    }
}
