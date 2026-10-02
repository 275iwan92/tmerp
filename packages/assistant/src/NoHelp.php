<?php

namespace Assistant;

use Assistant\Contracts\Asker;
use Assistant\Contracts\Knowledge;

// Bawaan bila aplikasi belum mengikat Knowledge: tidak ada bantuan yang dikenal.
final class NoHelp implements Knowledge
{
    public function topics(Asker $asker): array
    {
        return [];
    }

    public function url(): ?string
    {
        return null;
    }
}
