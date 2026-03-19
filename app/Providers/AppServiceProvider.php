<?php

namespace App\Providers;

use App\Repositories\GameSystemRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\GameSystemService;
use Illuminate\Support\ServiceProvider;
use PHPSupabase\Service;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Service::class, function () {
            $url = config('supabase.url');
            $key = config('supabase.service_key');
            if (empty($url) || empty($key)) {
                throw new \RuntimeException(
                    'SUPABASE_URL and SUPABASE_SERVICE_KEY must be set in .env for auth.'
                );
            }
            return new Service($key, $url);
        });

        $this->app->bind(GameSystemRepository::class, fn () => new GameSystemRepository(app(\App\Models\GameSystem::class)));
        $this->app->bind(UserRepository::class, fn () => new UserRepository(app(\App\Models\User::class)));
        $this->app->bind(GameSystemService::class, fn () => new GameSystemService(app(GameSystemRepository::class)));
        $this->app->bind(AuthService::class, fn () => new AuthService(app(Service::class), app(UserRepository::class)));

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
