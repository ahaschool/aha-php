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
        // 本地调试环境判断
        if (php_sapi_name() == 'cli' && env('AHA_PRINT') == 'true') {
            \Aha\Help::$debug = true;
        }
        // 首次启动配置别名
        if (!\Aha\Help::$booted) {
            class_alias('\Aha\Snippets\RestfulService', 'Serv');
        }
        \Aha\Help::$booted = true;
    }
}
