<?php

declare(strict_types=1);

namespace Zing\Skeleton;

use Illuminate\Support\ServiceProvider;

class SkeletonServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                $this->getConfigPath() => config_path('skeleton.php'),
            ], 'config');
        }

        $this->app->singleton('skeleton', SkeletonManager::class);
    }

    public function register(): void
    {
        $this->mergeConfigFrom($this->getConfigPath(), 'skeleton');
    }

    protected function getConfigPath(): string
    {
        return __DIR__ . '/../config/skeleton.php';
    }
}
