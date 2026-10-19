<?php
return [
    'defaults' => ['guard' => 'web', 'passwords' => 'employees'],
    'guards' => ['web' => ['driver' => 'session', 'provider' => 'employees']],
    'providers' => ['employees' => ['driver' => 'eloquent', 'model' => App\Modules\Auth\Employee::class]],
    'passwords' => ['employees' => ['provider' => 'employees', 'table' => 'orbita_password_reset_tokens', 'expire' => 60, 'throttle' => 60]],
    'password_timeout' => 10800,
];
