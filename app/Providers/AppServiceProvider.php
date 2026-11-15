<?php

namespace App\Providers;

use App\Auth\PortalSessionGuard;
use App\Auth\RoleSessionGuard;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->registerCustomGuards();
    }

    protected function registerCustomGuards(): void
    {
        Auth::extend('portal', function ($app, $name, array $config) {
            $provider = Auth::createUserProvider($config['provider'] ?? null);

            $guard = new PortalSessionGuard(
                $name,
                $provider,
                $app['session.store'],
                $app->make('request'),
                rehashOnLogin: $app['config']->get('hashing.rehash_on_login', true),
                timeboxDuration: $app['config']->get('auth.timebox_duration', 200000),
                hashKey: $app['config']->get('app.key'),
            );

            $guard->setCookieJar($app['cookie']);
            $guard->setDispatcher($app['events']);
            $guard->setRequest($app->refresh('request', $guard, 'setRequest'));

            if (isset($config['remember'])) {
                $guard->setRememberDuration($config['remember']);
            }

            return $guard;
        });

        foreach (['admin', 'guru', 'siswa'] as $role) {
            Auth::extend($role, function ($app, $name, array $config) use ($role) {
                $provider = Auth::createUserProvider($config['provider'] ?? null);

                $guard = new RoleSessionGuard(
                    $role,
                    $name,
                    $provider,
                    $app['session.store'],
                    $app->make('request'),
                    rehashOnLogin: $app['config']->get('hashing.rehash_on_login', true),
                    timeboxDuration: $app['config']->get('auth.timebox_duration', 200000),
                    hashKey: $app['config']->get('app.key'),
                );

                $guard->setCookieJar($app['cookie']);
                $guard->setDispatcher($app['events']);
                $guard->setRequest($app->refresh('request', $guard, 'setRequest'));

                if (isset($config['remember'])) {
                    $guard->setRememberDuration($config['remember']);
                }

                return $guard;
            });
        }
    }

    protected function makeGuard($guardClass, $app, $name, $config): StatefulGuard
    {
        $provider = Auth::createUserProvider($config['provider'] ?? null);

        $guard = new $guardClass(
            $name,
            $provider,
            $app['session.store'],
            $app->make('request'),
            rehashOnLogin: $app['config']->get('hashing.rehash_on_login', true),
            timeboxDuration: $app['config']->get('auth.timebox_duration', 200000),
            hashKey: $app['config']->get('app.key'),
        );

        $guard->setCookieJar($app['cookie']);
        $guard->setDispatcher($app['events']);
        $guard->setRequest($app->refresh('request', $guard, 'setRequest'));

        if (isset($config['remember'])) {
            $guard->setRememberDuration($config['remember']);
        }

        return $guard;
    }

    protected function makeRoleGuard(string $role, $app, $name, $config): StatefulGuard
    {
        $provider = Auth::createUserProvider($config['provider'] ?? null);

        $guard = new RoleSessionGuard(
            $role,
            $name,
            $provider,
            $app['session.store'],
            $app->make('request'),
            rehashOnLogin: $app['config']->get('hashing.rehash_on_login', true),
            timeboxDuration: $app['config']->get('auth.timebox_duration', 200000),
            hashKey: $app['config']->get('app.key'),
        );

        $guard->setCookieJar($app['cookie']);
        $guard->setDispatcher($app['events']);
        $guard->setRequest($app->refresh('request', $guard, 'setRequest'));

        if (isset($config['remember'])) {
            $guard->setRememberDuration($config['remember']);
        }

        return $guard;
    }
}
