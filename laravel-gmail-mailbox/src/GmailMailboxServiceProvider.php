<?php

namespace Queen\GmailMailbox;

use Illuminate\Support\ServiceProvider;
use Queen\GmailMailbox\Services\GmailService;

class GmailMailboxServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/gmail-mailbox.php',
            'gmail-mailbox'
        );

        $this->app->singleton(GmailService::class, function ($app) {
            return new GmailService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Load Routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        // Load Views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'gmail-mailbox');

        // Load Migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Publishing
        if ($this->app->runningInConsole()) {
            // Publish config
            $this->publishes([
                __DIR__ . '/../config/gmail-mailbox.php' => config_path('gmail-mailbox.php'),
            ], 'gmail-mailbox-config');

            // Publish views
            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/gmail-mailbox'),
            ], 'gmail-mailbox-views');

            // Publish migrations
            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'gmail-mailbox-migrations');
        }
    }
}
