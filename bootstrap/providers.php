<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\HorizonServiceProvider::class,
    // TelescopeServiceProvider is deliberately absent: Telescope is a
    // require-dev package, so the class it extends only exists locally.
    // AppServiceProvider::register() registers it behind a class_exists +
    // environment('local') guard instead.
];
