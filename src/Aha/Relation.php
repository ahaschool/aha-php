<?php

namespace Aha;

class Relation
{
    public static function getIdsByPivots($items, $field = 'id')
    {
        if (!$items) {
            return [];
        }
        $pivots = array_column($items, 'pivot');
        return array_combine(array_column($pivots, $field), $pivots);
    }

    public static function getItems($ids, $name, $data = [])
    {
        $items = [];
        list($kname, $vname) = explode('.', $name . '.');
        foreach ($ids as $k => $v) {
            if ($vname) {
                $items[] = [$kname => $k, $vname => $v] + $data;
            } else {
                $items[] = [$kname => $v] + $data;
            }
        }
        return $items;
    }

    public static function getDicts($items, $id = null)
    {
        $id = $id ?: key(current($items) ?: []);
        return array_combine(array_column($items, $id), $items);
    }

    public static function getModels($items, $relation)
    {
        if (!$items) {
            return [];
        }
        $instance = $relation->getRelated();
        $field = $instance->getKeyName();
        $class = get_class($instance);
        $results = array_map(function ($item) use($class, $field) {
            $instance = new $class;
            $key = array_pull($item, $field);
            if ($key) {
                $instance->$field = $key;
                $instance->exists = true;
            }
            $instance->fill($item);
            return $instance;
        }, $items);
        return $results;
    }

    public static function sync($relation, $items, $softDelete = false, $mergeData = [])
    {
        $data = $mergeData instanceof \Closure ? $mergeData() : $mergeData;
        $items = array_map(function ($item) use ($data) {
            return array_merge($item, $data);
        }, array_filter($items) ?: []);
        $key = $relation->getRelated()->getKeyName();
        $arr = static::getModels($items, $relation);
        $items = $relation->saveMany($arr);
        $ids = array_pluck($items, $key);
        if ($softDelete) {
            $relation->whereNotIn($key, $ids)->update(['status' => 2]);
        } else {
            $relation->whereNotIn($key, $ids)->delete();
        }
        return $items;
    }

    public static function saved($relation)
    {
        $args = func_get_args();
        if ($relation instanceof \Illuminate\Database\Eloquent\Model) {
            $model = $relation;
            $model::saved(function($m) use($model, $args) {
                if ($m != $model) {
                    return;
                }
                $method = $args[1];
                if (is_string($method)) {
                    $relation = $m->$method();
                    $items = $args[2];
                    $data = [$m->getForeignKey() => $m->getKey()];
                    $data += isset($args[3]) ? $args[3] : [];
                    \Aha\Relation::sync($relation, $items, 0, $data);
                } else if ($method instanceof \Closure) {
                    $method();
                }
            });
        } else {
            $model = $relation->getParent();
            $model::saved(function($m) use($model, $args) {
                if ($m == $model) {
                    call_user_func_array('\Aha\Relation::sync', $args);
                }
            });
        }
    }
}