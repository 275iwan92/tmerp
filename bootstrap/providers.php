<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\PlanningServiceProvider;
use App\Providers\RecruitmentServiceProvider;
use App\Providers\SchedulingServiceProvider;
use Chat\ChatServiceProvider;
use MockupParts\MockupPartsServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    SchedulingServiceProvider::class,
    RecruitmentServiceProvider::class,
    PlanningServiceProvider::class,
    MockupPartsServiceProvider::class,
    ChatServiceProvider::class,
];
