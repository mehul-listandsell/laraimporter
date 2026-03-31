<?php

namespace LaraImporter;

use Illuminate\Support\ServiceProvider;

class LaraImporterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/laraimporter.php', 'laraimporter');

        $this->app->singleton(Services\DatabaseConnectionService::class);
        $this->app->singleton(Services\FileParserService::class);
    }

    public function boot(): void
    {
        // Routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        // Views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'laraimporter');

        // Translations
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'laraimporter');

        // Migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Publishables
        if ($this->app->runningInConsole()) {
            // Config
            $this->publishes([
                __DIR__ . '/../config/laraimporter.php' => config_path('laraimporter.php'),
            ], 'laraimporter-config');

            // Views (for customization)
            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/laraimporter'),
            ], 'laraimporter-views');

            // Translations
            $this->publishes([
                __DIR__ . '/../resources/lang' => $this->app->langPath('vendor/laraimporter'),
            ], 'laraimporter-lang');

            // Migrations
            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'laraimporter-migrations');
        }
    }
}
