<?php

namespace Aha\Plugins;

use Illuminate\Http\Request;

class RichRequest
{
    public const service = '\Illuminate\Http\Request';

    public static $convert = false;

    public static $bindings = [
        'findOrFail' => 'static::findOrFail',
        'validate' => 'static::validate',
    ];

    public static function findOrFail(Request $request, $field)
    {
        $value = $request->input($field);
        if (!$value) {
            throw new \Exception('输入信息有误', 1001);
        }
        return $value;
    }

    public static function convert(Request $request, $role)
    {
        $key = $request->getMethod() . strrchr($request->path(), '/');
        $key = 'post/update';
        $dict = [];
        switch (strtolower($key)) {
            case 'get/get':
                $dict['id'] = array_get($role, 'id') ?: 'int|required';
                break;
            case 'post/get':
                $dict['id'] = array_get($role, 'id') ?: 'int|required';
                break;
            case 'post/create':
                array_forget($role, 'id');
                $dict = $role;
                break;
            case 'post/update':
                array_forget($role, 'status');
                $dict = $role;
                $dict['id'] = array_get($role, 'id', 'int|required');
                break;
            case 'post/status':
                $dict['id'] = array_get($role, 'id', 'int|required');
                $dict['status'] = array_get($role, 'status', 'int|required');
                break;
            case 'post/updown':
                $dict['id'] = array_get($role, 'id', 'int|required');
                $dict['down'] = array_get($role, 'down', 'int|required');
                break;
            case 'post/edit':
                $dict = $role;
            default:
                $dict = $role;
                break;
        }
        return $dict;
    }

    public static function validate(Request $request, $roles, $callback = null)
    {
        // 转换验证规则
        if (static::$convert) {
            $roles = static::convert($request, $roles);
        }
        // 快速过滤字段
        $keys = array_unique(array_map(function($value) {
            return strchr($value . '.', '.', true);
        }, array_keys($roles)));
        // 解决日期类型null值收不到问题
        $data = array_intersect_key($request->input(), array_flip($keys));
        $keys = [];
        $attributes = '<' . implode('>' . PHP_EOL . '<', array_keys(array_dot($data))) . '>';
        foreach ($roles as $key => $value) {
            $pattern = '<' . str_replace('\*', '([^\.]+)', preg_quote($key)) . '>';
            preg_match_all('/'.$pattern.'/U', $attributes, $arr);
            $keys = array_merge($keys, $arr[0]);
        };
        $result = [];
        foreach ($keys as $key) {
            $key = str_replace(['<', '>'], '', $key);
            array_set($result, $key, array_get($data, $key));
        };
        $validate = app('validator')->make($result, $roles);
        if ($callback) {
            $callback($validate, $result);
        }
        if ($validate->fails()) {
            $err = $validate->errors();
            $response = new \Illuminate\Http\JsonResponse($err);
            throw new \Illuminate\Validation\ValidationException($validate, $response);
        }
        return $validate->getData();
    }
}