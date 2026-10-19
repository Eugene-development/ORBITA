<?php
namespace App\Providers;
use App\Modules\Auth\EmployeeCommand;
use App\Modules\Catalog\Models;
use App\Modules\Schema\CatalogApproveCommand;
use App\Modules\Schema\CatalogInspectCommand;
use App\Modules\Recovery\RecoveryImportCommand;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}
    public function boot(): void
    {
        $this->app['events']->listen(\Illuminate\Console\Events\CommandStarting::class, function ($event): void {
            if (! $this->app->environment('testing') && in_array($event->command, ['migrate:fresh', 'migrate:refresh', 'migrate:reset', 'db:wipe'], true)) {
                throw new \RuntimeException('Destructive database commands are disabled outside isolated tests. Legacy catalog tables are immutable.');
            }
        });
        Relation::enforceMorphMap([
            'catalog' => Models\Catalog::class, 'rubric' => Models\Rubric::class, 'category' => Models\Category::class,
            'product' => Models\Product::class, 'price' => Models\Price::class, 'unit' => Models\Unit::class,
            'image' => Models\Image::class, 'text' => Models\Text::class, 'property' => Models\Property::class,
            'seotitle' => Models\SeoTitle::class, 'seodescription' => Models\SeoDescription::class, 'menu' => Models\Menu::class,
            'project' => Models\Project::class, 'resource' => Models\Resource::class, 'seoquery' => Models\SeoQuery::class, 'position' => Models\Position::class,
        ]);
        Gate::define('catalog-crud', fn ($employee) => $employee->is_active && in_array($employee->role, ['admin', 'editor'], true));
        Gate::define('manage-employees', fn ($employee) => $employee->is_active && $employee->role === 'admin');
        RateLimiter::for('employee-login', fn (Request $request) => [Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()), Limit::perMinute(30)->by($request->ip())]);
        ResetPassword::createUrlUsing(fn ($user, $token) => rtrim(config('app.url'), '/').'/admin/reset-password?'.http_build_query(['token' => $token, 'email' => $user->email]));
        $this->loadMigrationsFrom(base_path('../orbita-db/database/migrations'));
        $this->commands([EmployeeCommand::class, CatalogInspectCommand::class, CatalogApproveCommand::class, RecoveryImportCommand::class]);
    }
}
