<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Modules\Analytics\AnalyticsServiceProvider::class,
    App\Modules\Analytics\GoogleAnalytics\GoogleAnalyticsServiceProvider::class,
    App\Modules\Ai\AiServiceProvider::class,
    App\Modules\Reports\ReportsServiceProvider::class,
    App\Modules\Projects\ProjectsServiceProvider::class,
    App\Modules\Users\UsersServiceProvider::class,
];
