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
}

class ServiceModel extends Model
{
    protected $connection = 'service';
    protected $guarded = [];
    public $timestamps = false;
}