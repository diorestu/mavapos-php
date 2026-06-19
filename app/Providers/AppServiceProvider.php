<?php

namespace App\Providers;

use App\Services\PakasirClient;
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
        //
    }
}
