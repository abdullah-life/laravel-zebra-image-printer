<?php

namespace AbdullahLife\ZebraImagePrinter;

use Illuminate\Support\ServiceProvider;

class ZebraImagePrinterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/zebra-printer.php', 'zebra-printer'
        );

        $this->app->singleton('zebra-printer', function ($app) {
            return new ZebraPrinter();
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/zebra-printer.php' => config_path('zebra-printer.php'),
            ], 'zebra-printer-config');
        }
    }
}
