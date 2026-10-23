<?php

namespace Pipeandpiper\Mail;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class GenericMailProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot(): void
    {
        if (File::exists(__DIR__.'/../vendor/autoload.php')) {
            include __DIR__.'/../vendor/autoload.php';
        }
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'generic-mailable');

        Blade::directive('embed_image', function($expression) {
            return "<?php echo embed_image($expression); ?>";
        });
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register(): void
    {
    }
}
