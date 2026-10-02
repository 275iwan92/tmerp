<?php

namespace Assistant\Llm;

use Assistant\Contracts\LanguageModel;

// Tanpa model bahasa: asisten tetap menjawab lewat pencocokan kata kunci.
final class NoModel implements LanguageModel
{
    public function available(): bool
    {
        return false;
    }

    public function choose(string $question, array $options): ?string
    {
        return null;
    }
}
