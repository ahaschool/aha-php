<?php

namespace Aha\Plugins;

use Aha\Snippets\ServiceRelation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Request;

class RichOrm implements Scope
{
    protected static $scopes = [
        'items' => 'static::items',
        'page' => 'static::page',
        'relate' => 'static::relate',
        'request' => 'static::request',
        'findOrSave' => 'static::findOrSave',
        'withOne' => 'static::withOne',
        'withMany' => 'static::withMany',
    ];

    public static function findOrSave($builder, $data)
    {
        $model = $builder->findOrNew(array_pull($data, 'id'));
        if ($data) {
            $model->fill($data);
            $model->save();
        }
        return $model;
    }

    public static function relate($builder, $relation, \Closure $where = null, $type = 'inner')
    {
        $model = $builder->getModel();
        $refer = $model->$relation();
        $table = $refer->getRelated()->getTable();
        $builder->join($table, function ($join) use ($refer, $where) {
            if ($refer instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo) {
                $join->on($refer->getQualifiedOwnerKeyName(), '=', $refer->getQualifiedForeignKey());
            } else {
                $join->on($refer->getQualifiedForeignKeyName(), '=', $refer->getQualifiedParentKeyName());
            }
            if ($where) {
                $where($join);
            }
        }, NULL, NULL, $type);
        return $builder;
    }

    public static function items($builder, $values, $origin = [])
    {
        $class = get_class($builder->getModel());
        $results = array_map(function($row) use($class, $origin){
            $instance = new $class(array_merge($row, $origin));
            $instance->exists = !!$instance->getKey();
            return $instance;
        }, $values);
        return new Collection($results);
    }

    public static function request($builder, $name, $default = '')
    {
        if(!is_string($name) || !$name) {
            return;
        }
        $key = substr(strrchr(' ' . $name, ' '), 1);
        $value = \Illuminate\Support\Facades\Request::query($key);
        if (!$value && !$default) {
            return;
        }
        $column = strchr($name . ' ', ' ', true);
        $operator = trim(ltrim(rtrim($name, $key), $column)) ?: '=';
        if ($operator == 'range') {
            $sqls = [
                'now() between start_time and end_time',
                'now() < start_time',
                'now() > end_time',
                'now() not between start_time and end_time',
                'now() >= start_time',
                'length("range: start_time and end_time") > 1'
            ];
            $fields = explode(',', $column);
            $value = $value * 1 ?: $default;
            $value = (in_array($value, [1, 2, 3, 4]) ? $value : 5);
            $builder->whereRaw(str_replace(array_slice(['start_time', 'end_time', 'now()'], 0, count($fields)), $fields, $sqls[$value-1]));
        } else if ($operator == 'search') {
            $fields = explode(',', $column);
            $keyword = $value ?: $default;
            if (stripos($keyword, '%') === FALSE)
                $keyword = '%' . $keyword . '%';
            $builder->where(function($query) use($fields, $keyword){
                foreach ($fields as $field)
                    $query->orWhere($field, 'like', $keyword);
            });
        } else if ($column == 'available_type') {
            if (!in_array($value, [1, 2, 3, 4])) {
                throw new \App\Exceptions\ErrorInput('Invalid:available_type');
            }
            $sqls = [
                'now() between start_time and end_time',
                'now() < start_time',
                'now() > end_time',
                'now() not between start_time and end_time',
            ];
            $builder->whereRaw(str_replace(array_slice(['start_time', 'end_time', 'now()'], 0, func_num_args() - 2), array_slice(func_get_args(), 2), $sqls[$value-1]));
        } else if ($value && settype($value, gettype($default))) {
            if (is_array($default)) {
                $builder->whereIn($column, $value);
            } else {
                $builder->where($column, $operator, $value);
            }
        } else if ($value === null && $default) {
            $builder->where($column, $operator, $default);
        }
    }

    public static function page($builder, $mode = 'cursor')
    {
        $query = $builder->getQuery();
        $query->pagemode = $mode;
        if ($mode == 'cursor') {
            $offset = intval(base64_decode(Request::query('cursor')));
            $limit = intval(Request::query('limit')) ?: 10;
            $query->limit($limit)->offset($offset);
        } else if ($mode == 'offset') {
            $offset = intval(Request::query('offset'));
            $limit = intval(Request::query('limit')) ?: 10;
            $query->limit($limit)->offset($offset);
        } else if ($mode == 'next') {
            $offset = intval($query->offset + $query->limit);
            $query->limit(1)->offset($offset);
            $query->cursor = base64_encode($offset);
        } else if ($mode == 'count') {
            $page = intval(Request::query('page')) ?: 1;
            $count = intval(Request::query('count')) ?: 10;
            $query->forPage($page, $count);
        } else if ($mode == 'clear') {
            $query->offset = null;
            $query->limit = null;
        }
        return $builder;
    }

    public static function withOne($builder, $name, $func = null)
    {
        return static::withMany($builder, $name, $func, 1);
    }

    public static function withMany($builder, $name, $func = null, $one = 0)
    {
        list($name, $str) = explode(':', $name . ':');
        $method = function ($builder) use ($name, $str, $one) {
            $builder->macro($name, function ($builder) use ($str, $one) {
                $arr = explode(',', $str . ',');
                $m = $builder->getModel();
                $q = $m->newQuery();
                $query = new ServiceRelation($q, $m);
                if ($arr[0]) {
                    $query->config('foreign_key', $arr[0]);
                }
                if ($arr[1]) {
                    $query->config('local_key', $arr[1]);
                }
                $query->config('one_or_many', $one ? true : false);
                return $query;
            });
        };
        $item = ['model' => $builder->getModel(),  'method' => $method];
        array_push(static::$methods, $item);
        $builder->with([$name => $func ?: function () {}]);
        return $builder;
    }



    // 以下为核心方法
    protected static $macros = [];
    protected static $methods = [];
    public function extend(Builder $builder)
    {
        $model = $builder->getModel();
        foreach (static::$methods as $item) {
            if ($item['model'] == $model) {
                $item['method']($builder);
            }
        }
        foreach (static::$macros as $key => $value) {
            $builder->macro($key, $value);
        }
    }

    public function apply(Builder $builder, Model $refer)
    {
        $this->extend($builder);
    }

    public static function boot()
    {
        foreach (static::$scopes as $name => $method) {
            $method = str_replace('static::', static::class . '::', $method);
            static::$macros[$name] = function () use ($method) {
                $parameters = func_get_args();
                return call_user_func_array($method, $parameters);
            };
        }
        $scope = new static;
        app('events')->listen('eloquent.booting: *', function ($model) use ($scope) {
            $model = trim(strrchr(' ' . $model, ' '));
            $model::addGlobalScope($scope);
        });
    }
}