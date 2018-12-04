<?php

namespace Aha\Rich;

use ArrayObject;

// 写扩展用数组方式，写数据用对象方式
class Retdata extends ArrayObject
{
    function __construct($code = 0, $message = '') {
        $this['code'] = $code;
        $this['message'] = $message;
    }

    public function __get($key) {
        return $this[$key];
    }

    public function __set($key, $value) {
        if ($value instanceof \Illuminate\Contracts\Support\Arrayable) {
            $value = $value->toArray();
        }
        $this[$key] = $value;
    }

    public function __isset($key) {
        return isset($this[$key]);
    }

    public function __unset($key) {
        unset($this[$key]);
    }

    public function fill($data)
    {
        foreach ($data as $key => $value) {
            $this[$key] = $value;
        }
    }

    public function toArray()
    {
        return (array) $this;
    }

    public function __toString() {
        return json_encode($this);
    }
}