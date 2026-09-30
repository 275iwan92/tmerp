<?php

namespace Database\Seeders;

use App\Models\Master\ProdMasterCard;
use App\Services\MasterCard\MasterCardService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

// Isi dummy Master Card Collection (user 2026-10-01) lewat jalur impor yang sama dengan layar: 5 master card Excel asli
// (docs/attachments/MC-*.xlsx) + foto kartu KS Power A/B. Produk kelimanya sudah ada (UatMasterCardSeeder, UatKsPowerSeeder)
// sehingga kartu otomatis tertaut ke Master Product-nya; foto KS Power sengaja tetap Incomplete (contoh kartu foto yang
// harus dilengkapi). Artwork per part = potongan proof di docs/attachments/mastercard/. Hanya ddt*; diulang = reset.
//   php artisan db:seed --class=UatMasterCardCollectionSeeder --env=mockup --force
class UatMasterCardCollectionSeeder extends Seeder
{
    private const DIR = __DIR__.'/../../docs/attachments/';

    // nama file kartu => [part => artwork]
    private const CARDS = [
        'MC-Osima MS N100 CV (DS003146).xlsx' => ['A' => 'dieline-osima-n100-a.jpg', 'B' => 'dieline-osima-n100-b.jpg'],
        'MC-YAMA Din170 MF (DS003026) B.xlsx' => ['B' => 'dieline-yama-din170-b.jpg'],
        'MC-OUTER ALSAMSI CREAM LOCK.xlsx' => ['A' => 'dieline-alsamsi-outer-a.jpg', 'B' => 'dieline-alsamsi-outer-b.jpg'],
        'MC-ALSAMSI EXCLUSIVE TOP (DS003047).xlsx' => ['_' => 'dieline-alsamsi-exclusive-top.jpg'],
        'MC-Yahya 250g (24pcs) C22 (DS002159) 1020x650mm.xlsx' => ['_' => 'dieline-yahya-250g-c22.jpg'],
        'mastercard/MC-KS Power N120 MF (DS003637) A.png' => [],
        'mastercard/MC-KS Power N120 MF (DS003637) B.png' => [],
    ];

    public function run(): void
    {
        if (! str_starts_with(DB::connection('tm2mst')->getDatabaseName(), 'ddt')) {
            throw new RuntimeException('UatMasterCardCollectionSeeder hanya boleh di database dummy ddt* (--env=mockup).');
        }
        foreach (ProdMasterCard::where('data_origin', 'DUMMY')->pluck('id_master_card') as $id) {
            Storage::disk(MasterCardService::DISK)->deleteDirectory("master-card/{$id}");
        }
        ProdMasterCard::where('data_origin', 'DUMMY')->delete();

        $svc = new MasterCardService('uat');
        foreach (self::CARDS as $file => $artworks) {
            $card = $svc->import(new UploadedFile(self::DIR.$file, basename($file), null, null, true), 'DUMMY');
            foreach ($artworks as $part => $art) {
                $svc->storeFile($card, 'ARTWORK', file_get_contents(self::DIR.'mastercard/'.$art), $art, 'image/jpeg', 1, $part === '_' ? null : $part);
            }
            $this->command?->info("#{$card->id_master_card} {$card->product_name} -> ".ProdMasterCard::STATUS[$card->status].($card->product_id ? " (Master Product #{$card->product_id})" : ''));
        }
    }
}
