<?php

namespace Devlib\Components;

use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionUnionType;

// Ikon kecil </> di pojok layar (Mode Developer): klik -> jendela berisi
// kode pemakaian layar ini, properti tiap komponen (dibaca dari constructor
// class, jadi tak pernah basi), dan rantai induknya.
class CodeButton extends DevlibComponent
{
    public function __construct(
        public string $code = '',
        public string $file = '',
        public array $classes = [],
        public ?string $mockupUrl = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::code-button';
    }

    public function lines(): int
    {
        return substr_count(rtrim($this->code), "\n") + 1;
    }

    /** @return array<int, array{tag: string, lineage: string, props: array}> */
    public function info(): array
    {
        $out = [];
        foreach ($this->classes as $cls) {
            if (! class_exists($cls)) {
                continue;
            }
            $ref = new ReflectionClass($cls);
            $chain = [];
            for ($c = $ref; $c && $c->getName() !== \Illuminate\View\Component::class; $c = $c->getParentClass()) {
                $chain[] = $c->getShortName();
            }
            $props = [];
            foreach ($ref->getConstructor()?->getParameters() ?? [] as $p) {
                $t = $p->getType();
                $type = $t instanceof ReflectionNamedType ? ($t->allowsNull() && $t->getName() !== 'mixed' ? '?' : '').$t->getName()
                    : ($t instanceof ReflectionUnionType ? implode('|', array_map(fn ($x) => $x->getName(), $t->getTypes())) : 'mixed');
                $default = $p->isDefaultValueAvailable() ? $p->getDefaultValue() : null;
                $props[] = [
                    'name' => Str::kebab($p->getName()),
                    'type' => $type,
                    'default' => is_array($default) ? (count($default) > 3 ? '[…'.count($default).' item]' : json_encode($default, JSON_UNESCAPED_UNICODE)) : var_export($default, true),
                    'inherited' => ! $this->declaredHere($ref, $p->getName()),
                ];
            }
            $tag = 'x-dl::'.Str::of(Str::after($cls, 'Devlib\\Components\\'))->explode('\\')->map(fn ($s) => Str::kebab($s))->implode('.');
            $out[] = ['tag' => $tag, 'lineage' => implode(' ← ', $chain), 'props' => $props, 'doc' => $this->docOf($ref), 'view' => $this->viewOf($ref, $tag)];
        }

        return $out;
    }

    // Properti milik kelas ini sendiri (bukan diteruskan ke induk lewat parent::__construct).
    private function declaredHere(ReflectionClass $ref, string $name): bool
    {
        $parent = $ref->getParentClass();
        $pc = $parent ? $parent->getConstructor() : null;
        if (! $pc) {
            return true;
        }
        foreach ($pc->getParameters() as $p) {
            if ($p->getName() === $name) {
                return false;
            }
        }

        return true;
    }

    // Isi tampilan komponen: file view paket, atau blok BLADE di dalam render().
    private function viewOf(ReflectionClass $ref, string $tag): string
    {
        $file = dirname(__DIR__, 2).'/resources/views/components/'.str_replace('.', '/', Str::after($tag, 'x-dl::')).'.blade.php';
        if (is_file($file)) {
            return file_get_contents($file);
        }
        $src = file_get_contents($ref->getFileName());
        if (preg_match("/<<<'BLADE'\R(.*?)\R\s*BLADE;/s", $src, $m)) {
            return preg_replace('/^ {12}/m', '', $m[1]);
        }

        return '';
    }

    private function docOf(ReflectionClass $ref): string
    {
        $src = @file($ref->getFileName()) ?: [];
        $start = $ref->getStartLine() - 2;
        $doc = [];
        for ($i = $start; $i >= 0 && str_starts_with(trim($src[$i]), '//'); $i--) {
            array_unshift($doc, trim(substr(trim($src[$i]), 2)));
        }

        return implode(' ', $doc);
    }

    public function render()
    {
        return view('devlib::components.code-button');
    }
}
