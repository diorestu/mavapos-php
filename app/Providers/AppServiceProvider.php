<?php

namespace App\Providers;

use App\Services\PakasirClient;
use App\Support\BranchContext;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PakasirClient::class, function (): PakasirClient {
            $config = config('services.pakasir');

            return new PakasirClient(
                project: (string) ($config['project'] ?? ''),
                apiKey: (string) ($config['api_key'] ?? ''),
                baseUrl: (string) ($config['base_url'] ?? 'https://app.pakasir.com/api'),
                timeout: (int) ($config['timeout'] ?? 30),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blade::directive('localtime', fn (string $expression): string => "<?php echo \\App\\Support\\LocalTime::format({$expression}); ?>");

        View::composer(['layouts.app-header', 'layouts.sidebar'], function ($view): void {
            if (! auth()->check()) {
                return;
            }

            $branchContext = app(BranchContext::class);

            $view->with([
                'activeBranch' => $branchContext->active(),
                'branchOptions' => $branchContext->options(),
            ]);
        });
    }
}
