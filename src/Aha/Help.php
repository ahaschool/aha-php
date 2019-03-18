<?php

namespace Aha;

class Help
{
    public static $booted = false;

    public static function encrypt($value, $key = '')
    {
        return base64_encode(openssl_encrypt(serialize($value), 'aes-128-cbc', $key ?: 'baX3WykGjZWJ6qwT', 0, 'GXeFpZ93ANTjnsaC'));
    }

    public static function decrypt($value, $key = '')
    {
        return unserialize(openssl_decrypt(base64_decode($value), 'aes-128-cbc', $key ?: 'baX3WykGjZWJ6qwT', 0, 'GXeFpZ93ANTjnsaC'));
    }

    public static function matchOne(&$data, $func, $rule, $name = '')
    {
        \Aha\Snippets\ServiceRelation::matchOne($data, $func, $rule, $name);
    }

    public static function matchMany(&$data, $func, $rule, $name = '')
    {
        \Aha\Snippets\ServiceRelation::matchMany($data, $func, $rule, $name);
    }

    // json数据post请求
    public static function post($url, $data)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        $header = ['Content-Type:application/json'];
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        $content = json_encode($data);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $content);
        curl_setopt($curl, CURLOPT_TIMEOUT, 5);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        $content = curl_exec($curl);
        curl_close($curl);
        $result = json_decode($content ?: '', TRUE);
        return $result;
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

    // 仅限开发时调试打印
    public static $debug = false;
    public static function print()
    {
        if (static::$debug) {
            $obj = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
            echo PHP_EOL . $obj['file'] . ' : ' . $obj['line'] . PHP_EOL;
            print_r(func_get_args());
        }
    }
}