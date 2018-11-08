<?php

namespace Aha;

class Assist
{
    public static function getCacheClient($database = 0)
    {
        $redis = new \Redis();
        $redis->connect(env('REDIS_HOST'), env('REDIS_PORT'));
        $redis->auth(env('REDIS_PASSWORD')); 
        $redis->select($database);
        return $redis;
    }

    public static function getRedis($key)
    {
        static $dict = [];
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
    public static function doRedis($redis, $command)
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

    public static function getCacheValue($key, $fun, $expire = 0)
    {
        $client = static::getCacheClient();
        $key = 'COMMON.' . $key;
        $value = $client->get($key);
        if (!$value && $fun) {
            $value = $fun();
            if ($expire) {
                $client->setex($key, $expire, $value);
            } else {
                $client->set($key, $value);
            }
        }
        return $value;
    }

    public static function encrypt($value, $key = '')
    {
        return base64_encode(openssl_encrypt(serialize($value), 'aes-128-cbc', $key ?: 'baX3WykGjZWJ6qwT', 0, 'GXeFpZ93ANTjnsaC'));
    }

    public static function decrypt($value, $key = '')
    {
        return unserialize(openssl_decrypt(base64_decode($value), 'aes-128-cbc', $key ?: 'baX3WykGjZWJ6qwT', 0, 'GXeFpZ93ANTjnsaC'));
    }

    // 每日日志功能，用户调试某些特殊功能
    public static function log($data, $name = null)
    {
        $name = trim($name ?: 'logs/debug.log');
        $file = $name{0} == '/' ? $name : storage_path($name);
        try {
            $time = file_exists($file) ? filemtime($file) : 0;
            if ($time && $time < strtotime(date('Y-m-d'))) {
                unlink($file);
            }
            $content = json_encode($data, JSON_UNESCAPED_UNICODE);
            $str = date('Y-m-d H:i:s') . ' - ' . $content . PHP_EOL;
            file_put_contents($file, $str, FILE_APPEND);
        } catch (\Exception $e) {}
    }
}