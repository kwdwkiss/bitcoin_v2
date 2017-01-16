<?php

namespace Modules\Bitcoin\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Bitcoin\Service\HuoRestApi;
use Modules\Bitcoin\Service\OkRestApi;
use Modules\Core\Providers\ModuleServiceProviderTrait;

class BitcoinServiceProvider extends ServiceProvider
{
    use ModuleServiceProviderTrait;
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerCommand();
        $this->registerService();
        $apiLogEnable = config('bit.apiLogEnable', true);
        $this->app->singleton('huoRestApi', function () use ($apiLogEnable) {
            return new HuoRestApi(env('HUOBI_API_KEY'), env('HUOBI_SECRET_KEY'), app('guzzle'), $apiLogEnable);
        });
        $this->app->singleton('okRestApi', function () use ($apiLogEnable) {
            return new OkRestApi(env('OKCOIN_API_KEY'), env('OKCOIN_SECRET_KEY'), app('guzzle'), $apiLogEnable);
        });
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path('bitcoin.php'),
        ]);
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/config.php', 'bitcoin'
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = base_path('resources/views/modules/bitcoin');

        $sourcePath = __DIR__ . '/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath
        ]);

        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path . '/modules/bitcoin';
        }, \Config::get('view.paths')), [$sourcePath]), 'bitcoin');
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $langPath = base_path('resources/lang/modules/bitcoin');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'bitcoin');
        } else {
            $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'bitcoin');
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }
}
