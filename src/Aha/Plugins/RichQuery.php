<?php

namespace Aha\Plugins;

use Illuminate\Http\Request;
use Illuminate\Database\Query\Builder;

class RichQuery
{
    public const service = '\Illuminate\Database\Query\Builder';
    public static $bindings = [
        'param' => 'static::adapt',
        'apply' => 'static::apply',
        'adapt' => 'static::adapt',
        'match' => 'static::match',
    ];

    public static function apply(Builder $builder, $rule)
    {
        $arr = (array)$rule;
        foreach ($arr as $val) {
            self::match($builder, trim(strchr($val . '|', '|', true)));
        }
        return $builder;
    }

    public static function adapt(Builder $builder, $param = null)
    {
        if (is_array($param)) {
            $arr = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS, 8);
            $obj = array_pop($arr);
            if (isset($obj['object']) && $obj['object'] instanceof \Illuminate\Database\Eloquent\Builder) {
                $obj['object']->macro('adapt', function ($q, $k) {
                    return call_user_func('\Aha\RichBuilder::adapt', $q->getQuery(), $k);
                });
            }
        }
        static $obj = [];
        if (func_num_args() > 2) {
            $arr = is_array(func_get_arg(2)) ? func_get_arg(2) : [];
            $obj = array_merge($arr, $param);
        } else if (is_array($param)) {
            $builder->data = (array)$param;
        } else if (is_string($param) && stripos($param, '.')) {
            $param = substr(strchr($param, '.'), 1);
        };
        $data = isset($builder->data) ? $builder->data : $obj;
        return is_string($param) ? array_get($data, $param) : $builder;
    }

    public static function match(Builder $builder, $name, $default = 0)
    {
        $key = trim(strrchr(' ' . $name, ' '));
        $column = strchr($name . ' ', ' ', true);
        $value = $key ? $builder->adapt($key) : null;
        if ($default && $default instanceof \Closure) {
            $value = $value ? $default($builder, $value) : null;
            $value = $value && is_object($value) ? null : $value;
            $default = null;
        }
        $value = $default ? ($value ?: $default) : $value;
        if (!$value) {
            return $builder;
        }
        $operator = trim(ltrim(rtrim($name, $key), $column)) ?: '=';
        $column = ($column == '-') ? $key : $column;
        if ($operator == 'search') {
            $fields = explode(',', $column);
            $keyword = '%' . $value . '%';
            $builder->where(function($query) use($fields, $keyword) {
                foreach ($fields as $field) {
                    $query->orWhere($field, 'like', $keyword);
                }
            });
        } else if ($operator == 'page') {
            $builder->forPage($builder->adapt('page') ?: 1, $value);
        } else if ($operator == 'range') {
            $sqls = ['false'];
            $sqls[] = 'now() between start_time and end_time';
            $sqls[] = 'now() < start_time';
            $sqls[] = 'now() > end_time';
            $sqls[] = 'now() not between start_time and end_time';
            $sqls[] = 'now() >= start_time';
            $value = isset($sqls[$value]) ? $value : 0;
            $fields = ($column != $key) ? explode(',', $column) : [];
            $builder->whereRaw(str_replace(array_slice(['start_time', 'end_time', 'now()'], 0, count($fields)), $fields, $sqls[$value]));
        } else if ($operator == 'in') {
            $builder->whereIn($column, (array)$value);
        } else {
            $builder->where($column, $operator, $value);
        }
        return $builder;
    }
}