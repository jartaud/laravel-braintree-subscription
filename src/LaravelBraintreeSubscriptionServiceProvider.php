<?php

namespace Jartaud\LaravelBraintreeSubscription;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Jartaud\LaravelBraintreeSubscription\Http\Controllers\WebhookController;

class LaravelBraintreeSubscriptionServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        $this->registerRoutes();
        $this->registerResources();
        $this->registerPublishing();
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/laravel-braintree-subscription.php',
            'laravel-braintree-subscription'
        );
    }

    /**
     * Register the package's publishable resources.
     *
     * @return void
     */
    protected function registerPublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/laravel-braintree-subscription.php' => $this->app->configPath('laravel-braintree-subscription.php'),
            ], 'laravel-braintree-subscription-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
            ], 'laravel-braintree-subscription-migrations');

            $this->publishes([
                __DIR__.'/../resources/views' => $this->app->resourcePath('views/vendor/laravel-braintree-subscription'),
            ], 'laravel-braintree-subscription-views');
        }
    }

    /**
     * Register the package resources.
     *
     * @return void
     */
    protected function registerResources()
    {
        $this->loadJsonTranslationsFrom(__DIR__.'/../resources/lang');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'laravel-braintree-subscription');
    }

    protected function registerRoutes()
    {
        if (Cashier::$registersRoutes) {
            Route::prefix(config('laravel-braintree-subscription.path'))
                ->group(function () {
                    Route::post('/', [WebhookController::class, 'handleWebhook'])->name('braintree.webhook');
                });
        }

        return $this;
    }
}
