<?php

namespace App\Support\V2;

use App\Models\Sys\SecUser;
use App\Models\Sys\SysAppMenu;
use App\Models\Sys\SysHelpLine;
use App\Models\Sys\SysHelpTopic;
use App\Services\Help\HelpCenter;
use App\Support\Localization\Dictionary;

/*
 * REMARK
 * Tujuan       : Menyusun data peta bantuan (pohon menu yang boleh dilihat user + topik help) untuk x-dl::help-map.
 * Sumber hulu  : AppMenu::modules() (sys_app_menu: aktif, hide_in_menu = 0, hak akses), sys_help_topic / sys_help_line.
 * Input        : user login. Kunci pencocokan: topic_key = slug dari target 'v2:<slug>' pada item menu.
 * Output       : ['tree' => simpul modul > grup > layar, 'topics' => topik per kunci (judul, purpose, langkah, isian)].
 * Dipakai oleh : resources/views/v2/screens/help.blade.php (tab Help Topics).
 * Status       : Aktif (dummy mockup).
 */
class HelpMap
{
    public static function payload(SecUser $user): array
    {
        $targets = SysAppMenu::query()->where('target', 'like', 'v2:%')->pluck('target', 'code');
        $help = fn (?string $en) => Dictionary::t($en, HelpCenter::CONTEXT);
        $topics = SysHelpTopic::query()->where('activate', true)
            ->with(['lines' => fn ($q) => $q->where('activate', true)->orderBy('sort_order')])->get()->keyBy('topic_key');

        $used = [];
        $topicOf = function (string $key) use ($topics, $help, &$used): array {
            $t = $topics[$key];
            $used[$key] = [
                'key' => $key, 'type' => strtoupper($t->topic_type), 'title' => $help($t->title), 'purpose' => $help($t->purpose),
                'keywords' => (string) $t->keywords,
                'steps' => $t->lines->where('line_type', SysHelpLine::STEP)->map(fn ($l) => $help($l->body))->values()->all(),
                'fields' => $t->lines->where('line_type', SysHelpLine::FIELD)->map(fn ($l) => [(string) $l->field_label, $help($l->body)])->values()->all(),
            ];

            return $used[$key];
        };

        $leaf = function (array $i) use ($targets, $topics, $topicOf): ?array {
            $target = $targets[$i['code']] ?? null;
            if (! $target) {
                return null;   // v1 screens and "To Do" items have no v2 slug, so no guide is expected
            }
            $slug = substr($target, 3);
            if ($topics->has($slug)) {
                $topicOf($slug);
            }

            return ['label' => $i['label'], 's' => ['slug' => $slug, 'url' => $i['url'], 'code' => $i['code'], 'topic' => $topics->has($slug) ? $slug : null]];
        };

        $walk = function (array $nodes, string $id) use (&$walk, $leaf): array {
            $out = [];
            foreach ($nodes as $k => $n) {
                if (isset($n['items'])) {
                    $kids = $walk($n['items'], $id.'/'.$k);
                    if ($kids) {
                        $out[] = ['id' => $id.'/'.$k, 'label' => $n['label'], 'icon' => $n['icon'] ?? null, 'kids' => $kids];
                    }
                } elseif ($l = $leaf($n)) {
                    $out[] = $l;
                }
            }

            return $out;
        };

        $tree = [];
        foreach (AppMenu::modules($user) as $m) {
            if ($m['code'] === 'DEV') {
                continue;
            }
            $kids = $walk($m['children'], $m['code']);
            if ($kids) {
                $tree[] = ['id' => $m['code'], 'label' => $m['label'], 'icon' => $m['icon'], 'kids' => $kids];
            }
        }

        // Guides are not tied to a menu item: they sit in one extra "module" of their own.
        $guides = $topics->where('topic_type', SysHelpTopic::GUIDE)->sortBy([['sort_order', 'asc'], ['title', 'asc']])
            ->map(fn ($t) => ['label' => $topicOf($t->topic_key)['title'], 's' => ['slug' => null, 'url' => null, 'code' => null, 'topic' => $t->topic_key]])->values()->all();
        if ($guides) {
            $tree[] = ['id' => 'GENERAL', 'label' => t('General guides (no menu)'), 'icon' => 'fa-solid fa-book-open', 'kids' => $guides];
        }

        return ['tree' => $tree, 'topics' => $used];
    }
}
