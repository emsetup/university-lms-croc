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
        $host = strtolower((string) $request->getHost());
        // Публичный домен всегда HTTPS — иначе asset()/route() могут дать http:// и mixed content.
        if ($host === 'practice.croc.ru' || str_ends_with($host, '.practice.croc.ru')) {
            URL::forceScheme('https');
        }
        $root = $request->getSchemeAndHttpHost();
        if ($root !== '') {
            if ($host === 'practice.croc.ru' || str_ends_with($host, '.practice.croc.ru')) {
                $root = 'https://'.$request->getHttpHost();
            }
            URL::forceRootUrl($root);
        }
    }
}
