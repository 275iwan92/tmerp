<?php

namespace Assistant\Contracts;

use Assistant\Reply;

// Satu pertanyaan angka = satu kelas. Angkanya dihitung di sini oleh program (bukan oleh model bahasa) supaya bisa diuji.
// key / title / phrases / module / minLevel = nilai AWAL baris katalog; sesudah itu dirawat user di layar katalog.
interface Metric
{
    public function key(): string;

    public function title(): string;

    /** @return array<int, string> Frasa pemicu, dua bahasa, huruf kecil ("sales hari ini", "sales today"). */
    public function phrases(): array;

    // Modul pemilik angka: penanya wajib boleh membuka modul ini. null = terbuka untuk semua.
    public function module(): ?string;

    // Level keamanan minimum di atas hak modul (0 = cukup hak modul).
    public function minLevel(): int;

    // Tampil sebagai contoh pertanyaan di layar asisten (false untuk kelas penjaga yang tidak menjawab angka).
    public function suggest(): bool;

    // Hak sudah diperiksa mesin; kelas ini masih boleh mempersempit isi jawaban (mis. tanpa rupiah).
    public function answer(Asker $asker, string $question): Reply;
}
