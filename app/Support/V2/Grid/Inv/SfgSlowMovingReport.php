<?php

namespace App\Support\V2\Grid\Inv;

class SfgSlowMovingReport extends SlowMovingReport
{
    protected function category(): string
    {
        return 'SFG';
    }
}
