{{-- Daftar engine aplikasi tmerp. Situs DevLib tidak memuat kode tmerp, jadi isinya dibaca dari berkas yang dibuat tmerp:
     `php artisan engine:catalog` (App\Support\Engines\EngineRegistry + tanda tangan method dari kode). --}}
@php
    $file = base_path('../resources/data/v2/engines.json');
    $engines = is_file($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
@endphp
@if ($engines)
    <x-dl::engine-catalog :engines="$engines" source="resources/data/v2/engines.json" />
@else
    <x-dl::empty-state icon="fa-gears" title="No engine catalog yet" message="Run php artisan engine:catalog in tmerp to create resources/data/v2/engines.json." />
@endif
