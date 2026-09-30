<?php

namespace Devlib\Components;

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
    protected const TEXT_PROPS = ['label', 'title', 'subtitle', 'description', 'hint', 'placeholder', 'tooltip', 'message', 'text', 'search', 'empty', 'caption', 'heading', 'addLabel'];

    // Array berisi label: [['label' => ..], ..] atau [kunci => label | [label, ikon]]; parties/criteria: kunci = label.
    protected const TEXT_LISTS = ['columns', 'stats', 'tabs', 'filters', 'groups', 'signatures'];

    protected const TEXT_KEYS = ['parties', 'criteria'];

    private bool $translated = false;

    protected function translateTexts(): void
    {
        if ($this->translated) {
            return;
        }
        $this->translated = true;
        $tr = fn ($v) => is_string($v) ? \Devlib\Lang::text($v) : $v;
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
                    is_array($c) && array_is_list($c) && $c => array_replace($c, [0 => $tr($c[0])] + (isset($c[1]) && $p === 'signatures' ? [1 => $tr($c[1])] : [])),
                    default => $c,
                }, $this->{$p});
            }
        }
        foreach (static::TEXT_KEYS as $p) {
            if (property_exists($this, $p) && is_array($this->{$p})) {
                $this->{$p} = array_combine(array_map(fn ($k) => is_string($k) ? $tr($k) : $k, array_keys($this->{$p})), $this->{$p});
            }
        }
    }

    public function resolveView()
    {
        $this->translateTexts();
        $view = parent::resolveView();

        return function (array $data = []) use ($view) {
            if ($this->ability !== null && ! \Devlib\Access::can($this->ability)) {
                return new \Illuminate\Support\HtmlString('');
            }

            return $view instanceof \Closure ? $view($data) : $view;
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
