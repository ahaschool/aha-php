<?php

namespace Aha\Snippets;

use Illuminate\Support\Facades\Request;
use Illuminate\Support\Arr;

class RestfulService
{
    public static $ready = true;
    protected $uri = 'default:';

    public function get($query = [])
    {
        return static::invokeApi('GET', $this->uri, [
            'query' => $query
        ]);
    }

    public function put($data = [])
    {
        return static::invokeApi('PUT', $this->uri, [
            'json' => $data
        ]);
    }

    public function post($data = [])
    {
        return static::invokeApi('POST', $this->uri, [
            'json' => $data
        ]);
    }

    public function __call($method, $parameters)
    {
        $this->uri .= '/' . $method;
        if ($parameters) {
            $this->uri .= '/' . trim($parameters[0]);
        }
        return $this;
    }

    public static function __callStatic($method, $parameters)
    {
        $instance = new static;
        call_user_func_array([$instance, $method], $parameters);
        return $instance;
    }

    public static function with($host, $uri = '')
    {
        $instance = new static;
        $instance->uri = $host . ':' . ($uri ? $uri : '');
        return $instance;
    }

    public static function invokeApi($method, $uri, $options = [])
    {
        $method = strtoupper($method);
        if (stripos($uri, '{')) {
            $len = preg_match_all("/{([^\/]+)}/", $uri, $arr);
            foreach ($arr[1] as $key => $var) {
                if (!isset($options[$var])) {
                    throw new \Exception("$uri.$var is error!");
                } else if (!$options[$var]) {
                    throw new \Exception("$uri.$var is empty!");
                }
                $uri = str_replace($arr[0][$key], $options[$var], $uri);
            }
        }
        $query = isset($options['query']) ? $options['query'] : [];
        if ($query) {
            $uri .= (stripos($uri, '?') ? '&' : '?') . preg_replace('/%5B[0-9]+%5D/simU', '', http_build_query($query));
        }
        list($type, $path) = explode(':', $uri, 2);
        if ($type != 'https' && $type != 'http') {
            $key = 'SERV_' . strtoupper($type);
            $value = env($key);
            if (!$value) {
                throw new \Exception('env.' . $key . ' is not exists!');
            } else if (substr($value, 0, 4) != 'http') {
                $type = 'http://' . $value . '/';
            } else {
                $type = $value . '/';
            }
        } else {
            $type .= ':';
        }
        if ($path{0} == '/' && substr($type, -1) == '/') {
            $api_url = $type . substr($path, 1);
        } else {
            $api_url = $type . $path;
        }
        $ch = curl_init ();
        curl_setopt ( $ch, CURLOPT_URL, $api_url);
        $header = ['Accept: application/json'];
        if ($method != 'GET') {
            $content = json_encode(isset($options['json']) ? $options['json'] : []);
            curl_setopt ( $ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt ( $ch, CURLOPT_POSTFIELDS, $content);
            $header[] = 'Content-Type: application/json';
            $header[] = 'Content-Length:' . strlen($content);
        }
        $remote_ip = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
        $remote_ip = strchr($remote_ip . ',', ',', TRUE);
        $header[] = 'X-Forwarded-For:' . trim($remote_ip);
        $x_env = Request::header('X-Env', 'W10=');
        $track_env = static::$ready ? static::getTrackEnv() : [];
        foreach ($track_env as $key => $value) {
            $str = str_replace('_', ' ', $key);
            $str = str_replace(' ', '-', ucwords($str));
            $header[] = 'X-Env-' . $str . ':' . $value;
        }
        $header[] = 'X-Token:' . (env('X-Token') ?: env('X-TOKEN'));
        curl_setopt ( $ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, TRUE );
        curl_setopt ( $ch, CURLOPT_TIMEOUT, 5);
        curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        $result = curl_exec ( $ch );
        $errno = curl_errno( $ch );
        $errmsg = curl_error( $ch );
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close ( $ch );
        if ($errno > 0) {
            $data = ['code' => $errno, 'message' => $errmsg];
        } else if($http_status != 200) {
            $data = ['code' => $http_status, 'message' => $result];
        }else if(stripos($result, '{') !== 0) {
            $data = ['code' => -1, 'message' => $result];
        } else {
            $data = json_decode($result, true);
        }
        if ($data['code'] != 0) {
            if ($data['code'] > 1000) {
                throw new \Exception($data['message'], $data['code']);
            } else {
                throw new \Aha\Exceptions\ErrorService([
                    'http_status' => $http_status,
                    'api_url' => $api_url,
                    'options' => $options,
                    'errno' => $errno,
                    'errmsg' => $errmsg,
                    'result' => $result
                ]);
            }
        }
        return $data;
    }

    public static function getTrackEnv()
    {
        $env = Request::header('X-Env');
        $env = $env ? json_decode(base64_decode($env), true) : [];
        $pk = base64_decode(Arr::get($env, 'pk', ''));
        if ($pk && $pk{0} != '/') {
            $pk = '';
            $env['pk'] = $pk;
        }
        if ($pk) {
            $env['pk'] = $pk;
        }
        // pp处理
        $pp = Arr::get($env, 'pp', '');
        if ($pp && $pp == base64_encode(base64_decode($pp))) {
            $str = base64_decode($pp);
            $env['pp'] = strchr($str . '$', '$', true);
        } else {
            $env['pp'] = '';
        }
        $result = [
            'siteid' => intval(Arr::get($env, 'siteid', 0)),
            'fromid' => intval(Arr::get($env, 'fromid', 0)),
            'guniqid' => Arr::get($env, 'guniqid', ''),
            'user_id' => Arr::get($env, 'user_id', 0),
            'app_type' => Arr::get($env, 'app_type', 0),
            'utm_source' => trim(Arr::get($env, 'utm_source', '')),
            'utm_medium' => trim(Arr::get($env, 'utm_medium', '')),
            'utm_campaign' => trim(Arr::get($env, 'utm_campaign', '')),
            'utm_content' => trim(Arr::get($env, 'utm_content', '')),
            'utm_key' => trim(Arr::get($env, 'utm_key', '')),
            'pp' => Arr::get($env, 'pp', ''),
            'pd' => Arr::get($env, 'pd', ''),
            'pk' => Arr::get($env, 'pk', ''),
            'userids' => Arr::get($env, 'pk', ''),
            'tracker' => trim(Arr::get($env, 'tracker', '')),
        ];
        return array_filter($result);
    }
}
