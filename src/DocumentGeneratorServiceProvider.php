<?php

namespace Ayoratoumvone\Documentgeneratorx;

use Illuminate\Support\ServiceProvider;

class DocumentGeneratorServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton('document-generator', function ($app) {
            return new DocumentGenerator();
        });

        $this->mergeConfigFrom(
            __DIR__ . '/../config/documentgenerator.php',
            'documentgenerator'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/documentgenerator.php' => config_path('documentgenerator.php'),
            ], 'documentgenerator-config');

            $this->publishes([
                __DIR__ . '/../resources/templates' => storage_path('app/document-templates'),
            ], 'documentgenerator-templates');
        }
    }
}
