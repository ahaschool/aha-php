<?php

namespace Aha\Plugins;

use Illuminate\Http\Request;

class RichRequest
{
    public const service = '\Illuminate\Http\Request';
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

    public static function validate(Request $request, $roles)
    {
        $keys = array_unique(array_map(function($value) {
            return strchr($value . '.', '.', true);
        }, array_keys($roles)));
        // 解决日期类型null值收不到问题
        $keys = array_flip($keys);
        $data = array_intersect_key($request->input(), $keys);
        // $data = array_filter($request->only($keys), function($value) {
        //     return (null !== $value);
        // });
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
        if ($validate->fails()) {
            $err = $validate->errors();
            $response = new \Illuminate\Http\JsonResponse($err);
            throw new \Illuminate\Validation\ValidationException($validate, $response);
        }
        return $validate->getData();
    }
}