<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    \SocialiteProviders\Manager\ServiceProvider::class,
    AppServiceProvider::class,
    FortifyServiceProvider::class,
];
