<?php

namespace Aha\Core;

class Redis
{
    public static function getRedis($key)
    {
        static $dict = [];
        if (!$dict) {
            app()->configure('redis');
        }
        list($type, $name) = explode('.', $key . '.default');
        $config = array_get(config('redis'), $type);
        $redis = isset($dict[$type]) ? $dict[$type] : null;
        if (!$redis) {
            $redis = new \Redis();
            $redis->connect($config['ip'], $config['port']);
            if ($config['password']) {
                $redis->auth($config['password']);
            }
            $dict[$type] = $redis;
        }
        $database = array_get($config['databases'], $name);
        $redis->select($database);
        return $redis;
    }

    // 屏蔽异常处理缓存
    public static function do($redis, $command)
    {
        if (is_string($redis)) {
            $redis = static::getRedis($redis);
        }
        try {
            $args = array_slice(func_get_args(), 2);
            return call_user_func_array([$redis, $command], $args);
        } catch (\Exception $e) {
            return NULL;
        }
    }
}