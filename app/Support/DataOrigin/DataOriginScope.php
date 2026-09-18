<?php

namespace App\Support\DataOrigin;

use App\Services\Data\DataOriginService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

// Menyembunyikan baris DUMMY / LEGACY sesuai setting admin (sys_constant
// DATA_SHOW_DUMMY / DATA_SHOW_LEGACY) di semua model Eloquent yang tabelnya
// punya kolom data_origin. Query builder mentah (DB::table / view) TIDAK ikut
// tersaring -- layar yang memakai itu perlu filter sendiri bila diperlukan.
// Pakai Model::withoutGlobalScope(DataOriginScope::class) untuk melihat semua.
class DataOriginScope implements Scope
{
    private static ?array $hidden = null;

    private static ?bool $newAsDummy = null;

    private static ?bool $hideIncomplete = null;

    public function apply(Builder $builder, Model $model): void
    {
        $hidden = self::$hidden ??= app(DataOriginService::class)->hiddenOrigins();

        if ($hidden) {
            $builder->whereNotIn($model->qualifyColumn('data_origin'), $hidden);
        }

        // Legacy yang belum memenuhi syarat wajib tidak boleh jadi acuan
        // (keputusan user 2026-09-18) -- disembunyikan bila setting menyala.
        self::$hideIncomplete ??= app(DataOriginService::class)->hideLegacyIncomplete();

        if (self::$hideIncomplete) {
            $builder->where(fn ($q) => $q
                ->where($model->qualifyColumn('data_origin'), '<>', 'LEGACY')
                ->orWhere($model->qualifyColumn('data_ready'), 1));
        }
    }

    public static function register(): void
    {
        $flagged = app(DataOriginService::class)->flaggedTables();

        foreach (self::models() as [$class, $db, $table]) {
            if (isset($flagged["$db.$table"])) {
                $class::addGlobalScope(new self);
            }
        }

        // Mode UAT: data baru ikut ditandai DUMMY supaya bisa di-reset.
        Event::listen('eloquent.creating: *', function (string $event, array $payload) use ($flagged) {
            $model = $payload[0] ?? null;
            if (! $model instanceof Model || $model->getAttribute('data_origin')) {
                return;
            }

            self::$newAsDummy ??= app(DataOriginService::class)->newRowsAsDummy();
            $db = config('database.connections.'.$model->getConnectionName().'.database');

            if (self::$newAsDummy && isset($flagged[$db.'.'.$model->getTable()])) {
                $model->setAttribute('data_origin', 'DUMMY');
            }
        });
    }

    /** @return array<int, array{0: class-string<Model>, 1: string, 2: string}> */
    private static function models(): array
    {
        return Cache::rememberForever('data_origin.models', function () {
            $out = [];

            foreach ((new Finder)->files()->in(app_path('Models'))->name('*.php') as $file) {
                $class = 'App\\Models\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());

                if (! class_exists($class)) {
                    continue;
                }

                $ref = new ReflectionClass($class);
                if (! $ref->isInstantiable() || ! $ref->isSubclassOf(Model::class)) {
                    continue;
                }

                $model = new $class;
                $out[] = [$class, (string) config('database.connections.'.$model->getConnectionName().'.database'), $model->getTable()];
            }

            return $out;
        });
    }
}
