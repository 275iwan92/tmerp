<?php

namespace App\Assistant;

use App\Models\Sys\SysAppMenu;
use App\Support\V2\AppMenu;
use Assistant\Contracts\Asker;
use Assistant\Contracts\Knowledge;
use Assistant\Contracts\Navigator;

// Layar yang boleh dibuka si penanya = Menu v2 miliknya (App\Support\V2\AppMenu: modul + hak akses yang sama dengan menu).
// Kata cari dan satu kalimat kegunaan diambil dari topik Help layar itu. Layar yang muncul di beberapa menu dihitung sekali.
final class ErpNavigator implements Navigator
{
    private array $screens = [];

    public function __construct(private Knowledge $knowledge) {}

    public function screens(Asker $asker): array
    {
        if (! $asker instanceof ErpAsker) {
            return [];
        }

        return $this->screens[$asker->id().':'.$asker->lang()] ??= $this->build($asker);
    }

    private function build(ErpAsker $asker): array
    {
        $slugs = SysAppMenu::query()->where('target', 'like', 'v2:%')->pluck('target', 'code')->map(fn ($t) => substr($t, 3))->all();
        $topics = collect($this->knowledge->topics($asker))->keyBy('key');
        $out = [];
        $walk = function (array $nodes, string $path) use (&$walk, &$out, $slugs, $topics) {
            foreach ($nodes as $n) {
                if (isset($n['items'])) {
                    $walk($n['items'], $path.' › '.$n['label']);

                    continue;
                }
                $slug = $slugs[$n['code']] ?? null;
                // Belum ada layarnya (lencana "Not yet", nada 'todo') = tidak ditawarkan; layar yang sama di menu lain = sudah tercatat.
                if (($n['badgeTone'] ?? null) === 'todo' || ($slug && isset($out[$slug]))) {
                    continue;
                }
                $topic = $slug ? $topics->get($slug) : null;
                $out[$slug ?? $n['code']] = ['key' => $n['code'], 'label' => $n['label'], 'path' => $path, 'url' => $n['url'], 'icon' => $n['icon'] ?? null,
                    'words' => $topic['words'] ?? '', 'about' => $topic['purpose'] ?? null, 'help' => $topic ? $slug : null];
            }
        };
        foreach (AppMenu::modules($asker->user) as $m) {
            if ($m['code'] !== 'DEV') {
                $walk($m['children'], $m['label']);
            }
        }

        return array_values($out);
    }
}
