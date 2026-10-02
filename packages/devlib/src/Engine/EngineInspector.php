<?php

namespace Devlib\Engine;

use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;

// Membaca tanda tangan (parameter + tipe + nilai bawaan + tipe hasil) method sebuah kelas engine langsung dari kodenya,
// supaya daftar engine (x-dl::engine-catalog) tidak pernah berbeda dari kode: yang ditulis tangan hanya penjelasannya.
final class EngineInspector
{
    /**
     * @param  list<string>  $methods  method titik masuk; kosong = semua method public milik kelas itu sendiri
     * @return array{class: string, file: string, lines: int, entries: list<array{method: string, static: bool, params: list<array{name: string, type: string, default: ?string}>, returns: string}>}
     */
    public static function describe(string $class, array $methods = [], ?string $basePath = null): array
    {
        $ref = new ReflectionClass($class);
        $file = (string) $ref->getFileName();
        $own = array_values(array_filter($ref->getMethods(ReflectionMethod::IS_PUBLIC),
            fn (ReflectionMethod $m) => $m->getDeclaringClass()->getName() === $ref->getName() && ! str_starts_with($m->getName(), '__')));
        $pick = $methods ? array_map(fn (string $name) => $ref->getMethod($name), $methods) : $own;

        return [
            'class' => $ref->getName(),
            'file' => self::relative($file, $basePath),
            'lines' => is_file($file) ? count(file($file)) : 0,
            'entries' => array_map(fn (ReflectionMethod $m) => [
                'method' => $m->getName(),
                'static' => $m->isStatic(),
                'params' => array_map(fn ($p) => [
                    'name' => $p->getName(),
                    'type' => self::type($p->getType()),
                    'default' => $p->isDefaultValueAvailable() ? self::literal($p->getDefaultValue()) : null,
                ], $m->getParameters()),
                'returns' => self::type($m->getReturnType()),
            ], $pick),
        ];
    }

    // Berkas yang bukan kelas PHP (mis. engine JavaScript): hanya letak + jumlah baris.
    public static function file(string $path, ?string $basePath = null): array
    {
        return ['class' => null, 'file' => self::relative($path, $basePath), 'lines' => is_file($path) ? count(file($path)) : 0, 'entries' => []];
    }

    // Nama pendek tanpa namespace: App\Models\Sdi\IqpCosting -> IqpCosting, ?Carbon\Carbon -> ?Carbon.
    private static function type(?ReflectionType $t): string
    {
        if ($t === null) {
            return 'mixed';
        }
        if ($t instanceof ReflectionNamedType) {
            $name = $t->isBuiltin() ? $t->getName() : substr(strrchr('\\'.$t->getName(), '\\'), 1);

            return ($t->allowsNull() && $name !== 'null' && $name !== 'mixed' ? '?' : '').$name;
        }

        return preg_replace('/[A-Za-z_\\\\]+\\\\/', '', (string) $t);
    }

    private static function literal(mixed $v): string
    {
        return match (true) {
            $v === null => 'null',
            is_bool($v) => $v ? 'true' : 'false',
            is_array($v) => $v ? '[...]' : '[]',
            is_string($v) => "'".$v."'",
            default => (string) $v,
        };
    }

    private static function relative(string $file, ?string $basePath): string
    {
        $file = str_replace('\\', '/', $file);
        $base = $basePath ? rtrim(str_replace('\\', '/', $basePath), '/').'/' : '';

        return $base !== '' && str_starts_with($file, $base) ? substr($file, strlen($base)) : $file;
    }
}
