<?php

namespace App\Providers;

use App\Support\ActualizationFormatter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer(['okpd2.index', 'tnved.index'], function ($view): void {
            $view->with('mappingsUpdatedAt', ActualizationFormatter::mappings());
            $view->with('okpd2Url', '/okpd2');
            $view->with('tnvedUrl', '/okpd2/tnved');
            $view->with('okpd2ShareUrl', '/p/okpd2/');
            $view->with('tnvedShareUrl', '/p/okpd2/tnved');
        });
    }
}
