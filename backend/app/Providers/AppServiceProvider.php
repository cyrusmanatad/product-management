<?php

namespace App\Providers;

use App\Listeners\LogUserLogin;
use App\Models\Product;
use App\Observers\ProductVariantObserver;
use App\Tenancy\TenantContext;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(fn ($user, $token) => rtrim(config('app.url'), '/').'/reset-password?token='.$token.'&email='.urlencode($user->email));
        Product::observe(ProductVariantObserver::class);
        Event::listen(Login::class, LogUserLogin::class);
    }
}
