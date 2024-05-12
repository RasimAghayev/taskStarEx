<?php

namespace App\Providers;

use App\Interfaces\Company\UserRepositoryInterface;
use App\Repository\Company\UserReposiotry;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserReposiotry::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
    }
}
