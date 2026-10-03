<?php

namespace Devlib\Components;

use Devlib\Access;
use Devlib\Lang;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;

// Induk SEMUA komponen DevLib. Tiap turunan otomatis membawa "KTP" (atribut
// data-dl) = nama komponen + rantai induknya + sumber datanya, yang dibaca
// Mode Developer (devlib.js) untuk tooltip asal-usul objek.
abstract class DevlibComponent extends Component
{
    // Nama tag, mis. "x-dl::login".
    abstract public function tag(): string;

    // Sumber data yang ditampilkan komponen (tabel/view/konfigurasi).
    public function source(): string
    {
        return '—';
    }

    public function lineage(): string
    {
        $chain = [];
        for ($c = static::class; $c && $c !== Component::class; $c = get_parent_class($c)) {
            $chain[] = class_basename($c);
        }

        return implode(' ← ', $chain);
    }

    // Hak akses: atribut can="modul.aksi" di komponen mana pun; tanpa hak = tidak dirender.
    // Urutan Laravel: shouldRender() -> withAttributes() -> render tampilan. Atribut can hanya
    // ada di withAttributes(), jadi ditangkap di sana dan dicek saat tampilan dirender.
    protected ?string $ability = null;

    public function withAttributes(array $attributes)
    {
        if (array_key_exists('can', $attributes)) {
            $this->ability = (string) $attributes['can'];
            unset($attributes['can']);
        }

        return parent::withAttributes($attributes);
    }

    // Teks tampilan otomatis ikut bahasa (LOCKED user 2026-10-01): layar menulis ENGLISH, kamus menerjemahkan ke bahasa
    // tampilan/cetak -- layar tidak perlu memanggil apa pun. Turunan boleh mengubah daftar (Button/Badge: kosong, istilah
    // tombol & status selalu English). Teks bebas milik data (nilai isian, isi baris) TIDAK diterjemahkan.
    // Prop teks yang memuat NILAI DATA (no. dokumen, nama customer di judul form / kartu): nilainya dibungkus Lang::raw()
    // (helper raw() di tmerp) supaya tidak diterjemahkan dan tidak tercatat di kamus -- lihat Devlib\Lang::raw().
    protected const TEXT_PROPS = ['label', 'title', 'subtitle', 'description', 'hint', 'placeholder', 'tooltip', 'message', 'text', 'search', 'empty', 'caption', 'heading', 'addLabel'];

    // Array berisi label: [['label' => ..], ..] atau [kunci => label | [label, ikon]]; parties/criteria: kunci = label.
    protected const TEXT_LISTS = ['columns', 'stats', 'tabs', 'filters', 'groups', 'signatures'];

    protected const TEXT_KEYS = ['parties', 'criteria'];

    private bool $translated = false;

    // Kelompok pesan di Translation Dictionary (pohon kiri ala ScriptCase, user 2026-10-03): teks yang pertama kali tampil
    // lewat komponen ini tercatat di kelompok tersebut. Turunan boleh menimpa dictionaryGroup(); null = kelompok umum.
    private const GROUP_OF = [
        'buttons' => ['Button', 'IconButton', 'SplitButton', 'MenuButton', 'BackButton', 'CodeButton', 'ActionBar'],
        'grid' => ['Grid', 'LookupGrid', 'Sheet', 'Table', 'Pivot', 'Report', 'Pagination', 'Drilldown', 'PickMatrix', 'Lines', 'Kanban', 'Gantt'],
        'form' => ['Form', 'ActionForm', 'Wizard', 'Field', 'Import', 'Approval', 'CodeChain', 'Master'],
        'print' => ['PrintDoc', 'PrintOut', 'DocFooter', 'DocShare', 'Label', 'LabelSheet', 'LabelGuide', 'Barcode', 'Qr'],
        'messages' => ['Alert', 'Modal', 'Announce', 'EmptyState', 'ErrorPage', 'Tooltip', 'LockScreen', 'Skeleton', 'Spinner'],
        'menu' => ['LayerMenu', 'LayerShell', 'MegaMenu', 'SideMenu', 'Breadcrumb', 'Shell', 'Ribbon', 'Tabs', 'Accordion', 'AccordionGroup'],
    ];

    public function dictionaryGroup(): ?string
    {
        $chain = [static::class, ...array_values(class_parents($this))];
        foreach ($chain as $class) {
            $name = substr($class, strrpos($class, '\\') + 1);
            $name = str_contains($class, '\\Components\\Field\\') ? 'Field' : (str_contains($class, '\\Components\\Wizard\\') ? 'Wizard' : $name);
            foreach (self::GROUP_OF as $group => $classes) {
                if (in_array($name, $classes, true)) {
                    return $group;
                }
            }
        }

        return null;
    }

    protected function translateTexts(): void
    {
        if ($this->translated) {
            return;
        }
        $this->translated = true;
        $tr = fn ($v) => is_string($v) ? Lang::text($v) : $v;
        foreach (static::TEXT_PROPS as $p) {
            if (property_exists($this, $p) && is_string($this->{$p} ?? null)) {
                $this->{$p} = $tr($this->{$p});
            }
        }
        foreach (static::TEXT_LISTS as $p) {
            if (property_exists($this, $p) && is_array($this->{$p})) {
                $this->{$p} = array_map(fn ($c) => match (true) {
                    is_string($c) => $tr($c),
                    is_array($c) && isset($c['label']) => array_replace($c, ['label' => $tr($c['label'])]),
                    // [0] = label, juga bila disertai opsi bernama (kolom tabel: ['Qty', 'num' => true]).
                    is_array($c) && isset($c[0]) && is_string($c[0]) => array_replace($c, [0 => $tr($c[0])] + (isset($c[1]) && $p === 'signatures' ? [1 => $tr($c[1])] : [])),
                    default => $c,
                }, $this->{$p});
            }
        }
        foreach (static::TEXT_KEYS as $p) {
            if (property_exists($this, $p) && is_array($this->{$p})) {
                $this->{$p} = array_combine(array_map(fn ($k) => is_string($k) ? $tr($k) : $k, array_keys($this->{$p})), $this->{$p});
            }
        }
        // Lang::raw() di prop yang tidak diterjemahkan (label tombol / badge): penandanya tetap dibuang.
        foreach (get_object_vars($this) as $p => $v) {
            if (is_string($v) && Lang::hasRaw($v)) {
                $this->{$p} = Lang::plain($v);
            }
        }
    }

    public function resolveView()
    {
        // Petunjuk kelompok berlaku selama prop dan view komponen ini diterjemahkan; komponen anak menimpanya lalu
        // mengembalikannya (lihat Lang::hint).
        $prev = Lang::hint($this->dictionaryGroup() ?? Lang::currentHint());
        try {
            $this->translateTexts();
            $view = parent::resolveView();
        } finally {
            Lang::hint($prev);
        }

        return function (array $data = []) use ($view) {
            if ($this->ability !== null && ! Access::can($this->ability)) {
                return new HtmlString('');
            }
            $prev = Lang::hint($this->dictionaryGroup() ?? Lang::currentHint());
            try {
                return $view instanceof \Closure ? $view($data) : $view;
            } finally {
                Lang::hint($prev);
            }
        };
    }

    // Kelas perilaku JS untuk turunan ini (null = kelas JS induk). Turunan PHP + turunan JS = satu pasang:
    //   class KanbanProduksi extends Kanban { public function jsClass(): ?string { return 'KanbanProduksi'; } }
    //   Devlib.register(class KanbanProduksi extends Devlib.classes.Kanban { allowed(from, to) {...} })
    public function jsClass(): ?string
    {
        return null;
    }

    // Dipakai di elemen akar view: <div {!! $dl() !!}>.
    public function dl(): string
    {
        return 'data-dl="'.e($this->tag()).'" data-dl-class="'.e($this->lineage()).'" data-dl-src="'.e($this->source()).'"'
            .($this->jsClass() ? ' data-dl-js="'.e($this->jsClass()).'"' : '');
    }
}
