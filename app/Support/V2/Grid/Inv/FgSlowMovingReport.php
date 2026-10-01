<?php

namespace App\Support\V2\Grid\Inv;

class FgSlowMovingReport extends SlowMovingReport
{
    protected function category(): string
    {
        return 'FG';
    }
}
