<?php

/*
 * REMARK
 * Tujuan       : Layar Setup > Help and Support > Technical Guide: kondisi sistem per modul dan sub-modul (kartu konsep, kartu engine, relasi, peta besar), bahasa Inggris, dibaca seperti artikel; bagian rumus rahasia hanya untuk yang berhak.
 * Sumber hulu  : tm2sys.sys_guide bagian section = tg (docs/tg/<modul>/<sub-modul>/NN_slug.md lewat php artisan guides:import).
 * Input        : ?q= (cari), ?doc= (id kartu); chip "Modul · Sub-modul".
 * Output       : feed() dan article() seperti GuidesLookup; hanya baca.
 * Dipakai oleh : Menu SET_TECH_GUIDE (v2:setup-tech-guide); tampilan resources/views/v2/screens/setup-tech-guide.blade.php (pohon x-dl::side-menu + artikel).
 * Status       : BARU 2026-10-16; pohon Modul > Sub-modul > Kartu (mockup disetujui Jo).
 */

namespace App\Support\V2\Grid\Setup;

use App\Models\Sys\SysGuide;

class TechGuideLookup extends GuidesLookup
{
    // Folder names are headlined by guides:import (Crm, Prodev); the tree shows the product names.
    private const NAMES = ['Crm' => 'CRM', 'Prodev' => 'ProDev', 'Product Costing' => 'Pro-Cost'];

    public function slug(): string
    {
        return 'setup-tech-guide';
    }

    public function title(): string
    {
        return 'Technical Guide';
    }

    public function icon(): string
    {
        return 'fa-solid fa-diagram-project';
    }

    public function guide(): ?string
    {
        return 'Technical Guide: how each module works today (concept cards, engine cards, relations), written for agents and the owner. Nothing can be changed here.';
    }

    public function screen(): array
    {
        return ['view' => 'v2.screens.setup-tech-guide'] + parent::screen();
    }

    // Tree for x-dl::side-menu: Module > Sub-module > cards (the 'module' text is "Module · Sub-module"; root files are 'Overview').
    public function tree(): array
    {
        $groups = [];
        foreach (SysGuide::query()->where('section', 'tg')->orderBy('sort_order')->orderBy('title')->get(['id_guide', 'module', 'title', 'slug']) as $g) {
            [$mod, $sub] = array_map(fn ($s) => $s === null ? null : (self::NAMES[$s] ?? $s), array_pad(explode(' · ', $g->module, 2), 2, null));
            $groups[$mod][$sub ?? ''][] = ['label' => $this->shortTitle($g->title), 'key' => (string) $g->getKey(), 'url' => $this->url().'?doc='.$g->getKey(),
                'badge' => preg_match('/^ENGINE card/i', $g->title) ? 'ENGINE' : (preg_match('/^Concept card/i', $g->title) ? 'CARD' : null)];
        }
        $tree = [];
        foreach ($groups as $mod => $subs) {
            $items = $subs[''] ?? [];
            foreach ($subs as $sub => $cards) {
                if ($sub !== '') {
                    $items[] = ['label' => $sub, 'icon' => 'fa-solid fa-folder', 'items' => $cards];
                }
            }
            $tree[] = ['label' => $mod, 'icon' => 'fa-solid fa-sitemap', 'items' => $items];
        }

        return $tree;
    }

    // Links between cards are written as relative .md paths; in Help they become ?doc= links, and links to files that are not cards stay as plain text.
    private function cardLinks(string $body, string $from): string
    {
        $ids = SysGuide::query()->where('section', 'tg')->pluck('id_guide', 'source_path');

        return preg_replace_callback('~\[([^\]]+)\]\((\.{1,2}/[^)\s#]+?\.md)\)~', function ($m) use ($ids, $from) {
            $parts = [];
            foreach (explode('/', dirname($from).'/'.$m[2]) as $seg) {
                if ($seg === '..') {
                    array_pop($parts);
                } elseif ($seg !== '.' && $seg !== '') {
                    $parts[] = $seg;
                }
            }
            $id = $ids[implode('/', $parts)] ?? null;

            return $id ? "[{$m[1]}](".$this->url().'?doc='.$id.')' : $m[1];
        }, preg_replace('~\[([^\]]+)\]\(\.{1,2}/[^)\s]*/\)~', '$1', $body) ?? $body) ?? $body;
    }

    // Tree label: drop the "Concept card:" style prefix (the badge says it) and the long parenthesis.
    private function shortTitle(string $title): string
    {
        $t = preg_replace('/^(Concept card|ENGINE card|Sub-module|Card):\s*/i', '', $title) ?? $title;

        return trim(preg_replace('/\s*\(.*$/', '', $t) ?: $t);
    }

    // First card shown when no ?doc= is given: the big map.
    public function firstId(): int
    {
        return (int) SysGuide::query()->where('section', 'tg')->orderByRaw("module = 'Overview' desc")->orderBy('module')->orderBy('sort_order')->value('id_guide');
    }

    // Card diagrams are written as ![alt](../img/NAME.svg); Help escapes HTML, so the image is served by the v2.tg-img route.
    public function article(int $id): ?array
    {
        $a = parent::article($id);
        if ($a) {
            $a['body'] = preg_replace('~\]\((?:\.{1,2}/)+img/([a-z0-9-]+)\.svg\)~', '](/v2/tg-img/$1)', (string) $a['body']);
            $a['body'] = $this->cardLinks($a['body'], (string) SysGuide::query()->whereKey($id)->value('source_path'));
        }

        return $a;
    }

    protected function section(): string
    {
        return 'tg';
    }
}
