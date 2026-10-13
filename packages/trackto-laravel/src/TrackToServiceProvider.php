<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Laravel;

use ExpertSystems\TrackTo\Laravel\Console\PingCommand;
use ExpertSystems\TrackTo\Laravel\Console\ShortenCommand;
use ExpertSystems\TrackTo\TrackToClient;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * The client is a lazily built immutable singleton: per-call changes go through its with*() copies.
 */
final class TrackToServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/trackto.php', 'trackto');

        $this->app->singleton(
            TrackToClient::class,
            fn (Application $app): TrackToClient => ClientFactory::make($app->make('config')->get('trackto', [])),
        );

        $this->app->alias(TrackToClient::class, 'trackto');
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/trackto.php' => $this->app->configPath('trackto.php')], 'trackto-config');

        if ($this->app->runningInConsole()) {
            $this->commands([PingCommand::class, ShortenCommand::class]);
        }
    }
}
