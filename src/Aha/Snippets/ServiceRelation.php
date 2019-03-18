<?php

namespace Aha\Snippets;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;

class ServiceRelation extends Relation
{
    public $config = [
        'alias' => null,
        'name' => null,
        'uri' => null,
        'arg' => null,
        'method' => null,
        'foreign_key' => null,
        'local_key' => null,
        'one_or_many' => false,
    ];
    public $ids = [];

    public function getResults()
    {
        return $this->get();
    }

    // 关系查询用
    public function get($columns = ['*'])
    {
        try {
            $method = $this->config['method'] ?: 'get';
            $api = \Serv::with($this->config['name'], $this->config['uri']);
            $ret = $api->$method($this->config['arg'] ?: []);
            $data = $ret['data'] ?: [];
        } catch (\Exception $e) {
            $data = [];
        }
        return new Collection($data);
    }

    // 添加约束
    public function addConstraints()
    {
        // todo
    }

    // 初始关系，每个关系置为空
    public function initRelation(array $models, $relation)
    {
        $one = $this->config['one_or_many'];
        foreach ($models as $model) {
            $model->setRelation($relation, $one ? null : new Collection);
        }

        return $models;
    }

    // 添加急切约束
    public function addEagerConstraints(array $models)
    {
        $local = $this->config['local_key'] ?: 'id';
        $this->ids = $this->getKeys($models, $local);
    }

    // 匹配关系，将每个关系赋值上
    public function match(array $models, Collection $results, $relation)
    {
        $local = $this->config['local_key'] ?: 'id';
        $one = $this->config['one_or_many'];
        $dictionary = $this->buildDictionary($results);
        foreach ($models as $model) {
            if (isset($dictionary[$key = $model->getAttribute($local)])) {
                $value = $dictionary[$key];
                if ($one) {
                    $result = new ServiceModel(reset($value));
                } else {
                    $result = new Collection($value);
                }
                $model->setRelation($relation, $result);
            }
        }
        return $models;
    }

    public function buildDictionary(Collection $results)
    {
        $foreign = $this->config['foreign_key'] ?: $this->config['name'] . '_id';
        return $results->mapToDictionary(function ($result) use ($foreign) {
            return [$result[$foreign] => $result];
        })->all();
    }

    public function service($name, $uri, $arg = [])
    {
        $arr = stripos($uri, ':') ? explode(':', $uri) : ['get', $uri];
        $this->config['name'] = $name;
        $this->config['arg'] = $arg;
        $this->config['uri'] = $arr[1];
        $this->config['method'] = $arr[0];
        return $this;
    }

    public function config($name, $value = null)
    {
        $this->config[$name] = $value;
        return $this;
    }

    public function __construct($query, $parent)
    {
        if ($query && $parent) {
            parent::__construct($query, $parent);
        }
    }

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

class ServiceModel extends Model
{
    protected $connection = 'service';
    protected $guarded = [];
    public $timestamps = false;
}