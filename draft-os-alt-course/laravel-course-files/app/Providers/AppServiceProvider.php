<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        $request = $this->app->make('request');
        $root = $request->getSchemeAndHttpHost();
        if ($root !== '') {
            URL::forceRootUrl($root);
        }
    }
}
