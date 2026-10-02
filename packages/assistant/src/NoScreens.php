<?php

namespace Assistant;

use Assistant\Contracts\Asker;
use Assistant\Contracts\Navigator;

// Bawaan bila aplikasi belum mengikat Navigator: tidak ada layar yang dikenal.
final class NoScreens implements Navigator
{
    public function screens(Asker $asker): array
    {
        return [];
    }
}
