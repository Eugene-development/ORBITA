<?php

$providers = [App\Providers\AppServiceProvider::class];
if (class_exists(App\Modules\Orders\CommerceServiceProvider::class)) {
    $providers[] = App\Modules\Orders\CommerceServiceProvider::class;
}
return $providers;
