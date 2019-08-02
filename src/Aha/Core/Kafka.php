<?php

namespace Aha\Core;

class Kafka
{
    public static function getTopic($key, $data = [])
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
            if (env('LOG_KAFKA_OPEN') === true) {
                $conf = new \RdKafka\Conf();
                $conf->setDrMsgCb(function ($ka, $message) use ($key, $data) {
                    if ($message->err) {
                        static::writeFailData($key, $data, 'msg_err');
                        static::writeErrorLog($message->err, '', 'msg_err');
                    }
                });
                $conf->setErrorCb(function ($ka, $err, $reason) use ($key, $data) {
                    static::writeFailData($key, $data, 'error');
                    static::writeErrorLog($err, $reason, 'error');
                });
                $kafka = new \RdKafka\Producer($conf);
                $kafka->setLogLevel(LOG_INFO);
            } else {
                $kafka = new \RdKafka\Producer();
            }
            $kafka->addBrokers($config['broker']);
            $dict[$type] = $kafka;
        }
        $topic = $kafka->newTopic($config['prefix'] . $name);
        return $topic;
    }

    public static function push($data, $on = 'default')
    {
        $topic = static::getTopic($on, $data);
        $topic->produce(RD_KAFKA_PARTITION_UA, 0, json_encode($data));
    }

    public static function writeFailData($key, $data, $source)
    {
        $output = [
            'key' => $key,
            'data' => $data,
            'source' => $source,
        ];
        file_put_contents(env('LOG_PATH') . 'kafka-fail-data.log', json_encode($output, true).PHP_EOL, FILE_APPEND);
    }

    public static function writeErrorLog($err, $reason, $source)
    {
        $output = [
            'error' => $err,
            'reason' => $reason,
            'source' => $source,
        ];
        file_put_contents(env('LOG_PATH') . 'service-error.log', json_encode($output, true).PHP_EOL, FILE_APPEND);
    }
}