<?php

namespace Assistant\Contracts;

// Daftar layar yang BOLEH dibuka si penanya. Mesin asisten yang mencocokkan pertanyaan dengan daftar ini.
interface Navigator
{
    // Tiap layar: key (kode unik = kode tab), label, path (letak di menu), url, icon?, words? (kata cari tambahan),
    // about? (satu kalimat kegunaan), help? (kunci topik bantuan layar itu).
    /** @return array<int, array<string, ?string>> */
    public function screens(Asker $asker): array;
}
