<?php

namespace App\Support\V2\Grid\Inv;

class RmSlowMovingReport extends SlowMovingReport
{
    protected function category(): string
    {
        return 'RM';
    }
}
