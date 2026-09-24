<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\RecruitmentServiceProvider;
use App\Providers\SchedulingServiceProvider;
use MockupParts\MockupPartsServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    SchedulingServiceProvider::class,
    RecruitmentServiceProvider::class,
    MockupPartsServiceProvider::class,
];
