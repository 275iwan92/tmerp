<?php

namespace App\Support\V2\Grid\Sales;

// Pro-Dev > Revise: sama dengan Pro-Dev > New, untuk inquiry jenis REVISE.
class ProdevReviseGrid extends ProdevNewGrid
{
    protected function type(): string
    {
        return 'REVISE';
    }
}
