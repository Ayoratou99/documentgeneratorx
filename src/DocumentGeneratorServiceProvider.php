<?php

namespace Ayoratoumvone\Documentgeneratorx;

use Illuminate\Support\ServiceProvider;

class DocumentGeneratorServiceProvider extends ServiceProvider
{
    /**
     * Events provided by this package
     */
    protected array $events = [
        \Ayoratoumvone\Documentgeneratorx\Events\DocumentGenerating::class,
        \Ayoratoumvone\Documentgeneratorx\Events\DocumentGenerated::class,
        \Ayoratoumvone\Documentgeneratorx\Events\DocumentGenerationFailed::class,
        \Ayoratoumvone\Documentgeneratorx\Events\BatchGenerationCompleted::class,
    ];

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
            // Publish config
            $this->publishes([
                __DIR__ . '/../config/documentgenerator.php' => config_path('documentgenerator.php'),
            ], 'documentgenerator-config');

            // Publish templates
            $this->publishes([
                __DIR__ . '/../resources/templates' => storage_path('app/document-templates'),
            ], 'documentgenerator-templates');

            // Publish example listeners
            $this->publishes([
                __DIR__ . '/../stubs/Listeners' => app_path('Listeners/DocumentGenerator'),
            ], 'documentgenerator-listeners');
        }
    }

    /**
     * Get the events provided by the package.
     */
    public function provides(): array
    {
        return ['document-generator'];
    }
}
