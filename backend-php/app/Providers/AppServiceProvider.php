<?php

namespace App\Providers;

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
        \Illuminate\Support\Facades\DB::listen(function ($query) {
            \Illuminate\Support\Facades\Log::channel('stderr')->info('[SQL-QUERY] ' . $query->sql, [
                'bindings' => $query->bindings,
                'time_ms' => $query->time,
            ]);
        });
    }
}
