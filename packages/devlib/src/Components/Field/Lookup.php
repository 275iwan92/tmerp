<?php

namespace Devlib\Components\Field;

// Pilih dari data master (bukan ketik bebas) + isi otomatis field lain.
// Definisi datanya di Devlib\Lookup (sekali per data), layar cukup menyebut namanya.
class Lookup extends Field
{
    public array $def;

    public function __construct(
        public string $lookup = '',
        public array $fill = [],
        public bool $create = false,
        public array $params = [],
        string $name = '',
        string $label = '',
        ?string $tooltip = null,
        ?string $hint = null,
        ?string $error = null,
        mixed $value = null,
        ?string $placeholder = null,
        bool $required = false,
        bool $disabled = false,
        bool $readonly = false,
        ?string $icon = null,
        string $size = 'sm',
        ?string $i18n = null,
        ?string $source = null,
    ) {
        parent::__construct($name, $label, $tooltip, $hint, $error, $value, $placeholder, $required, $disabled, $readonly, $icon, $size, $i18n, $source);
        $this->def = \Devlib\Lookup::get($lookup);
        $this->fill = $fill ?: $this->def['fill'];
        $this->source ??= 'lookup "'.$lookup.'" = '.$this->def['source'];
    }

    public function control(): string
    {
        return 'lookup';
    }

    // Mode remote (def punya search): URL cari ke server + parameter tambahan layar.
    public function url(): ?string
    {
        return $this->def['remote'] ? route('devlib.lookup', ['name' => $this->lookup] + $this->params) : null;
    }

    public function current(): ?array
    {
        if ($this->def['remote']) {
            return \Devlib\Lookup::find($this->lookup, $this->value);
        }
        foreach ($this->def['rows'] as $r) {
            if ((string) ($r[$this->def['key']] ?? '') === (string) $this->value) {
                return $r;
            }
        }

        return null;
    }
}
