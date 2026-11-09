<?php

namespace App\Providers;

use App\Interfaces\Company\UserRepositoryInterface;
use App\Repository\Company\UserRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
    }

    public function boot(): void
    {
    }
}
