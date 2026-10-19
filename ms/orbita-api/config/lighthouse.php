<?php
$config = require base_path('vendor/nuwave/lighthouse/src/lighthouse.php');
$config['route']['middleware'] = [
    'web', Illuminate\Session\Middleware\AuthenticateSession::class,
    App\Modules\Catalog\GraphQLRequestGuard::class,
    Nuwave\Lighthouse\Http\Middleware\AcceptJson::class,
    Nuwave\Lighthouse\Http\Middleware\AttemptAuthentication::class, 'throttle:180,1',
];
$config['guards'] = ['web'];
$config['namespaces']['models'] = ['App\\Modules\\Catalog\\Models'];
$config['schema_cache']['enable'] = env('LIGHTHOUSE_SCHEMA_CACHE_ENABLE', env('APP_ENV') === 'production');
$config['query_cache']['enable'] = env('APP_ENV') === 'production';
$config['security']['max_query_complexity'] = 500;
$config['security']['max_query_depth'] = 10;
$config['security']['disable_introspection'] = env('APP_ENV') === 'production' ? GraphQL\Validator\Rules\DisableIntrospection::ENABLED : GraphQL\Validator\Rules\DisableIntrospection::DISABLED;
$config['persisted_queries'] = false;
$config['field_middleware'] = array_map(fn ($class) => $class === Nuwave\Lighthouse\Schema\Directives\TrimDirective::class ? App\Modules\Catalog\PreserveMediaHashTrim::class : $class, $config['field_middleware']);
return $config;
