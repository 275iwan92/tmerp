<?php

namespace Assistant\Contracts;

// Isi bantuan aplikasi dalam bahasa si penanya. Mesin asisten hanya mengutip dari sini, tidak pernah mengarang.
interface Knowledge
{
    // Tiap topik: key, title, purpose, words (kata cari, dua bahasa), steps (daftar teks berurutan),
    // fields (daftar ['label', 'text']), screen? (key layar di Navigator bila topik ini milik satu layar).
    /** @return array<int, array<string, mixed>> */
    public function topics(Asker $asker): array;

    // Alamat halaman bantuan untuk dibuka sebagai tab (null = tidak ada).
    public function url(): ?string;
}
