<?php

namespace Aha;

use Aha\Snippets\ServiceRelation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class Serv
{
    public static function dot(array $array, $prepend = '', $name = '')
    {
        $results = [];

        foreach ($array as $key => $value) {
            if (is_array($value) && !empty($value)) {
                $results = array_merge($results, static::dot($value, $prepend.$key.'.', $name));
            } else if ($key == $name) {
                $results[$prepend.$key] = $value;
            }
        }

        return $results;
    }

    public static function matchOne(&$data, $func, $rule, $relate = '')
    {
        return static::matchMany($data, $func, $rule, $relate, 1);
    }

    public static function matchMany(&$data, $func, $rule, $relate = '', $one = 0)
    {
        $pattern = str_replace('*', '\d+', '*.' . $rule);
        $name = substr(strrchr($rule, '.'), 1);
        $dot = static::dot($data, '', $name);
        $items = [];
        foreach ($dot as $key => $value) {
            if (preg_match('/'.$pattern.'/', $key, $arr)) {
                $items[] = ['key' => $key, 'val' => $value];
            };
        }
        $ids = array_column($items, 'val');
        $one_or_many = $one || is_array($relate) ? true : false;
        $query = new ServiceRelation(null, null);
        $query->config('one_or_many', $one_or_many);
        $query->ids = $ids;
        $results = $func($query);
        // 从属关系时，颠倒主外键
        $query->config('foreign_key', $query->config['local_key'] ?: $name);
        $arr = is_array($results) ? new Collection($results) : $query->get();
        $dictionary = $query->buildDictionary($arr);
        if ($query->config['one_or_many']) {
            foreach ($dictionary as $key => $value) {
                $dictionary[$key] = reset($value);
            }
        }
        $relate = $relate ?: ($query->config['alias'] ?: ($query->config['name'] ?: 'data'));
        foreach ($items as $item) {
            $value = array_get($dictionary, $item['val']);
            $prefix = substr($item['key'], 0, strrpos($item['key'], '.'));
            if (is_string($relate)) {
                array_set($data, $prefix . '.' . $relate, $value);
            } else if (is_array($relate)) {
                foreach ($relate as $key) {
                    array_set($data, $prefix . '.' . $key, array_get($value, $key));
                }
            }
        }
    }
}