<?php

namespace Aha\Traits;

use Illuminate\Support\Facades\Request;

trait BaseResponse
{
    public function __construct()
    {
        $this->ret = new \Aha\Rich\Retdata;
    }

    public function request($key = null, $default = null)
    {
        $value = Request::input($key, $default);
        if ($value === null || $value === '') {
            return ($default === null) ? $value : $default;
        } else {
            $type = gettype($default);
            if ($type == 'integer') {
                return intval($value);
            } else if ($type == 'array') {
                return (array)$value;
            } else if ($type == 'double') {
                return (double)$value;
            } else {
                return $value;
            }
        }
        return $default;
    }

    public function requestOrFail($key)
    {
        $value = Request::input($key);
        if (!$value) {
            throw new \Aha\Exceptions\ErrorInput('require:' . $key);
        }
        return $value;
    }

    public function requestOnly($keys)
    {
        $keys = is_array($keys) ? $keys : func_get_args();
        return Request::only($keys);
    }

    public function requestExcept($keys)
    {
        $keys = is_array($keys) ? $keys : func_get_args();
        return Request::except($keys);
    }

    public function response($data = null)
    {
        if (is_object($data) && method_exists($data, 'toArray')) {
            $this->ret->data = $data->toArray();
        } else if (is_array($data)) {
            $this->ret->data = $data;
        } else if ($data) {
            $this->ret->data = $data;
        }
        // nextpage处理
        if (isset($this->ret->cursor) && $this->ret->cursor) {
            $this->ret->nextpage = \App\Context\Cursor::nextpage($this->ret->cursor);
        }
        if (php_sapi_name() == 'cli' && env('APP_ENV') == 'local') {
            $this->ret->query_logs = \DB::getQueryLog();
            return $this->ret->toArray();
        }
        return response()->json($this->ret);
    }

    public function requestValidate($roles, $data = null)
    {
        if ($data === null) {
            $data = Request::only(array_keys($roles));
            foreach ($data as $key => $value) {
                if ($value === null) {
                    unset($data[$key]);
                }
            }
        }
        $v = \Validator::make($data, $roles);
        $v->addExtension('data', function() use($v) {
            if ($v->fails()) {
                $err = $v->errors();
                throw new \Aha\Exceptions\ErrorValidate($err);
            }
            return $v->getData();
        });
        return $v;
    }
}