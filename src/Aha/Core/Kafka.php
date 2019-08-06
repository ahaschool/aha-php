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
            if (env('LOG_KAFKA_OPEN') === true && env('LOG_PATH')) {
                $log_path = env('LOG_PATH');
                $conf = new \RdKafka\Conf();
                $conf->setDrMsgCb(function ($ka, $message) use ($key, $data, $log_path) {
                    if ($message->err) {
                        static::writeFailData($key, $data, 'msg_err', $log_path);
                        static::writeErrorLog($message->err, '', 'msg_err', $log_path);
                    }
                });
                $conf->setErrorCb(function ($ka, $err, $reason) use ($key, $data, $log_path) {
                    static::writeFailData($key, $data, 'error', $log_path);
                    static::writeErrorLog($err, $reason, 'error', $log_path);
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

    public static function writeFailData($key, $data, $source, $path)
    {
        $output = [
            'key' => $key,
            'data' => $data,
            'time' => time(),
            'source' => $source,
        ];
        file_put_contents($path . 'kafka-fail-data.log', json_encode($output, true).PHP_EOL, FILE_APPEND);
    }

    public static function writeErrorLog($err, $reason, $source, $path)
    {
        $output = [
            'error' => $err,
            'reason' => $reason,
            'time' => time(),
            'source' => $source,
        ];
        file_put_contents($path . 'service-error.log', json_encode($output, true).PHP_EOL, FILE_APPEND);
    }
}