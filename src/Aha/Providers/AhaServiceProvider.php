<?php

namespace Aha\Providers;

use Illuminate\Support\ServiceProvider;

class AhaServiceProvider extends ServiceProvider
{
    public function register()
    {
        $arr = glob(dirname(__DIR__) . '/Plugins/Rich*.php');
        foreach ($arr as $path) {
            $name = substr(strchr(basename($path), '.', TRUE), 4);
            $this->registerService('\Aha\Plugins\Rich' . $name);
        }
    }

    public function registerService($plugin)
    {
        // 无服务时，使用boot注册
        if (!defined($plugin . '::service')) {
            if (method_exists($plugin, 'boot')) {
                $plugin::boot();
            }
            return;
        }
        $service = constant($plugin . '::service');
        $bindings = $plugin::$bindings;
        foreach ($bindings as $name => $method) {
            $method = str_replace('static::', $plugin . '::', $method);
            $service::macro($name, function () use ($method) {
                $parameters = func_get_args();
                array_unshift($parameters, $this);
                return call_user_func_array($method, $parameters);
            });
        }
    }

    public function boot()
    {
        $this->app->singleton(
            \Illuminate\Contracts\Debug\ExceptionHandler::class,
            \Aha\Exceptions\Handler::class
        );
        class_alias('\Aha\Snippets\RestfulService', 'Serv');
    }
}
