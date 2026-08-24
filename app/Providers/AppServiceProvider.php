<?php

namespace App\Providers;

use App\Services\TrackerConfig;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TrackerConfig::class, fn () => new TrackerConfig());
    }

    public function boot(): void
    {
        Blade::directive('igimg', function (string $expression) {
            return "<?php echo (\$__u = ({$expression})) ? e(route('img.proxy', ['u' => \$__u])) : ''; ?>";
        });
    }
}
