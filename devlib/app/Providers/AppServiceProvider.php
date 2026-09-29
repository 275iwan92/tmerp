<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Contoh Lookup remote (data besar dicari ke server): 300 lokasi fiktif, baris tidak ditanam di halaman.
        $places = fn () => collect(range(1, 300))->map(fn ($i) => ['id' => $i, 'name' => 'Kelurahan Contoh '.$i,
            'city' => ['Surabaya', 'Sidoarjo', 'Gresik', 'Malang', 'Jakarta'][$i % 5], 'postal' => (string) (60000 + $i)]);
        \Devlib\Lookup::define('area_remote', ['key' => 'id', 'label' => 'name', 'source' => 'contoh 300 lokasi (search di server)',
            'columns' => ['name' => 'Kelurahan', 'city' => 'Kota', 'postal' => 'Kode Pos'], 'fill' => ['postal' => 'postal'],
            'search' => fn (string $q) => $places()->filter(fn ($r) => stripos($r['name'].' '.$r['city'].' '.$r['postal'], $q) !== false)->values(),
            'find' => fn ($v) => $places()->firstWhere('id', (int) $v)]);
    }
}
