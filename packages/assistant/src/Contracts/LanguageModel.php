<?php

namespace Assistant\Contracts;

// Model bahasa lokal. Tugasnya HANYA memahami kalimat: memilih satu dari pilihan yang disodorkan mesin. Ia tidak
// menghitung, tidak menulis SQL, dan tidak mengarang jawaban.
interface LanguageModel
{
    public function available(): bool;

    /**
     * @param  array<string, string>  $options  kunci pilihan => uraian singkat
     * @return ?string kunci pilihan, atau null bila tidak ada yang cocok / model tidak menjawab
     */
    public function choose(string $question, array $options): ?string;
}
