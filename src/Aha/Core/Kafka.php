<?php

namespace Aha\Core;

class Kafka
{
    public static function getTopic($key)
    {
        static $dict = [];
        if (!$dict) {
            app()->configure('kafka');
        }
        list($type, $name) = explode('.', $key . '.default');
        $config = array_get(config('kafka'), $type);
        if (!$config) {
            throw new \Exception('kafka config error');
        }
        $name = array_get($config['topic'], $name ?: 'default') ?: $name;
        if (!$name) {
            throw new \Exception('kafka topic error');
        }
        $kafka = isset($dict[$type]) ? $dict[$type] : null;
        if (!$kafka) {
            $kafka = new \RdKafka\Producer();
            $kafka->addBrokers($config['broker']);
            $dict[$type] = $kafka;
        }
        $topic = $kafka->newTopic($config['prefix'] . $name);
        return $topic;
    }

    public static function push($data, $on = 'default')
    {
        $topic = static::getTopic($on);
        $topic->produce(RD_KAFKA_PARTITION_UA, 0, json_encode($data));
    }
}