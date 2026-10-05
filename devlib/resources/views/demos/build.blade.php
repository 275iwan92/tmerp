{{-- Papan Build Progress. Situs DevLib tidak memuat kode / database tmerp, jadi isinya dibaca dari berkas yang dibuat tmerp:
     `php artisan build:catalog --env=mockup` (App\Support\Build\BuildRegistry). --}}
@php($build = \App\Support\Catalog::build())
@if ($build)
    <x-dl::build-board :build="$build" :library="\App\Support\Catalog::stats()" :engines-url="route('lib', ['slug' => 'p9-engines'])" source="resources/data/v2/build.json" />
@else
    <x-dl::empty-state icon="fa-chart-simple" title="No build catalog yet" message="Run php artisan build:catalog --env=mockup in tmerp to create resources/data/v2/build.json." />
@endif
