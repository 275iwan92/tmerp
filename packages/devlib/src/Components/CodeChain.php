<?php

namespace Devlib\Components;

use Devlib\Lang;

// Dropdown yang saling terkait dan MENYUSUN KODE (Material Category -> kode item, user 2026-10-03; pola yang sama untuk Product).
// Tiap tingkat = satu dropdown; isi tingkat berikutnya hanya anak dari pilihan tingkat di atasnya. Tiap pilihan membawa
// potongan kode (prefix); kode jadi = gabungan potongan dari tingkat 1 sampai tingkat terakhir yang dipilih, tampil selagi memilih.
//   nodes   : [[id, induk (0 = puncak), level, nama, potongan kode, aktif 1|0], ...]
//   levels  : label tiap tingkat, mis. ['Category 1', ..., 'Category 5'] (jumlahnya = kedalaman rantai)
//   value   : id pilihan terdalam (tingkat di atasnya dihitung dari induknya)
//   navigate: alamat berpola {id} -- tiap memilih, halaman dibuka di alamat itu (layar master: form baris yang dipilih)
//   add-url : alamat berpola {level} dan {parent} -- tombol Add di tiap tingkat (menambah di bawah pilihan tingkat sebelumnya)
//   inactive: true = pilihan nonaktif ikut tampil (bertanda); false = hanya yang aktif + yang sedang terpilih
// Terkirim: {name} = id terdalam, {name}_1 .. {name}_N = id per tingkat, {name}_code = kode jadi. Event dl:chain-change.
class CodeChain extends DevlibComponent
{
    /** @var array<int, array> id => node */
    private array $map = [];

    public function __construct(
        public array $nodes = [],
        public array $levels = [],
        public mixed $value = null,
        public string $name = 'chain',
        public string $codeLabel = 'Code',
        public ?string $navigate = null,
        public ?string $addUrl = null,
        public bool $inactive = false,
        public bool $disabled = false,
        public ?string $source = null,
    ) {
        foreach ($nodes as $n) {
            $this->map[(int) $n[0]] = [(int) $n[0], (int) $n[1], (int) $n[2], (string) $n[3], (string) $n[4], (int) ($n[5] ?? 1)];
        }
    }

    public function tag(): string
    {
        return 'x-dl::code-chain name='.$this->name;
    }

    public function source(): string
    {
        return $this->source ?? 'rantai pilihan';
    }

    /** @return array<int, int> level => id terpilih */
    public function picked(): array
    {
        $out = [];
        $id = (int) $this->value;
        for ($i = 0; $id && isset($this->map[$id]) && $i < count($this->levels); $i++) {
            $out[$this->map[$id][2]] = $id;
            $id = $this->map[$id][1];
        }
        ksort($out);

        return $out;
    }

    // Tingkat bisa dipilih bila tingkat di atasnya sudah dipilih.
    public function open(int $level): bool
    {
        return ! $this->disabled && ($level === 1 || isset($this->picked()[$level - 1]));
    }

    /** @return array<int, array> anak yang boleh tampil di bawah satu induk */
    private function childrenOf(int $parent, int $level, ?int $keep = null): array
    {
        return array_values(array_filter($this->map, fn ($n) => $n[1] === $parent && $n[2] === $level && ($this->inactive || $n[5] === 1 || $n[0] === $keep)));
    }

    // Label pilihan: nama; saudara bernama sama dibedakan dengan potongan kodenya; yang nonaktif diberi tanda.
    private function label(array $n, array $siblings): string
    {
        $twins = count(array_filter($siblings, fn ($s) => mb_strtolower(trim($s[3])) === mb_strtolower(trim($n[3])))) > 1;

        return $n[3].($twins ? ' ('.$n[4].')' : '').($n[5] === 1 ? '' : ' — '.Lang::text('inactive'));
    }

    /** @return array<string|int, string> id => label, didahului pilihan kosong */
    public function options(int $level): array
    {
        if (! $this->open($level)) {
            return [];
        }
        $picked = $this->picked();
        $rows = $this->childrenOf($level === 1 ? 0 : $picked[$level - 1], $level, $picked[$level] ?? null);
        $out = ['' => Lang::t('f_choose')];
        foreach ($rows as $n) {
            $out[$n[0]] = $this->label($n, $rows);
        }

        return $out;
    }

    public function placeholder(int $level): string
    {
        // Sudah diterjemahkan di sini; raw() supaya field tidak menerjemahkan (dan mencatatnya di kamus) sekali lagi.
        return Lang::raw($this->open($level) ? Lang::t('f_choose') : str_replace(':level', Lang::text($this->levels[$level - 2] ?? ''), Lang::text('Pick :level first')));
    }

    public function segment(int $level): string
    {
        $id = $this->picked()[$level] ?? null;

        return $id ? $this->map[$id][4] : '';
    }

    public function code(): string
    {
        return implode('', array_map(fn ($id) => $this->map[$id][4], $this->picked()));
    }

    public function addHref(int $level): ?string
    {
        if (! $this->addUrl || ! $this->open($level)) {
            return null;
        }

        return str_replace(['{level}', '{parent}'], [(string) $level, (string) ($this->picked()[$level - 1] ?? '')], $this->addUrl);
    }

    // Bahan perilaku JS: pilihan yang boleh tampil (label sudah jadi) per induk.
    public function payload(): array
    {
        $picked = $this->picked();
        $byParent = [];
        foreach ($this->map as $n) {
            if ($this->inactive || $n[5] === 1 || in_array($n[0], $picked, true)) {
                $byParent[$n[1].':'.$n[2]][] = $n;
            }
        }
        $out = [];
        foreach ($byParent as $rows) {
            foreach ($rows as $n) {
                $out[] = [$n[0], $n[1], $n[2], $this->label($n, $rows), $n[4]];
            }
        }

        return $out;
    }

    public function hint(): string
    {
        $n = count($this->picked());

        return match (true) {
            $n === 0 => str_replace(':level', Lang::text($this->levels[0] ?? ''), Lang::text('Pick :level first')),
            $n === count($this->levels) => Lang::text('Complete'),
            default => str_replace([':n', ':of'], [(string) $n, (string) count($this->levels)], Lang::text('Level :n of :of')),
        };
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-chain') }} {!! $dl() !!} data-dl-chain data-name="{{ $name }}" data-levels="{{ count($levels) }}"
                data-nodes="{{ json_encode($payload()) }}" data-navigate="{{ $navigate }}" data-add="{{ $addUrl }}"
                data-t-choose="{{ \Devlib\Lang::t('f_choose') }}" data-t-complete="{{ \Devlib\Lang::text('Complete') }}"
                data-t-level="{{ \Devlib\Lang::text('Level :n of :of') }}" data-t-first="{{ str_replace(':level', \Devlib\Lang::text($levels[0] ?? ''), \Devlib\Lang::text('Pick :level first')) }}">
                <input type="hidden" name="{{ $name }}" value="{{ $value }}" data-cc-value>
                <input type="hidden" name="{{ $name }}_code" value="{{ $code() }}" data-cc-codeval>
                <div class="dl-chain__cols" style="--dl-chain-n:{{ max(1, count($levels)) }}">
                    @foreach ($levels as $i => $label)
                        <div class="dl-chain__col" data-cc-level="{{ $i + 1 }}">
                            <x-dl::field.select :name="$name.'_'.($i + 1)" :label="$label" :options="$options($i + 1)" :value="$picked()[$i + 1] ?? ''"
                                :placeholder="$placeholder($i + 1)" :disabled="! $open($i + 1)" />
                            <div class="dl-chain__seg" data-cc-seg>{{ $segment($i + 1) }}</div>
                            @if ($addUrl)
                                <a @if ($addHref($i + 1)) href="{{ $addHref($i + 1) }}" @endif @class(['dl-btn', 'dl-btn--secondary', 'dl-btn--sm', 'dl-chain__add', 'is-disabled' => ! $addHref($i + 1)]) data-cc-add><span class="dl-btn__icon"><i class="fa-solid fa-plus"></i></span>Add</a>
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="dl-chain__code">
                    <span class="dl-chain__codelabel">{{ \Devlib\Lang::text($codeLabel) }}</span>
                    <b data-cc-code>{{ $code() !== '' ? $code() : '—' }}</b>
                    <small data-cc-hint>{{ $hint() }}</small>
                </div>
            </div>
            BLADE;
    }
}
