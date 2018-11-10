<?php

namespace Aha\Core;

class Qiniu
{
    public static function fetch($url, $bucket)
    {
        $accessKey = env('QINIU_ACCESS_KEY');
        $secretKey = env('QINIU_SECRET_KEY');
        $domain_url = str_replace('bucket', $bucket, env('QINIU_DOMAIN'));
        $name = $bucket . ':' . date('Ymd') . md5($url);
        $from_url = str_replace(['+', '/'], ['-', '_'], base64_encode($url));
        $to_url = str_replace(['+', '/'], ['-', '_'], base64_encode($name));
        $url = '/fetch/' . $from_url . '/to/' . $to_url;
        $sign = hash_hmac('sha1', $url . "\n", $secretKey, TRUE);
        $token = $accessKey . ':' . str_replace(['+', '/'], ['-', '_'], base64_encode($sign));
        $header = ['Host: iovip.qbox.me', 'Content-Type: application/json', 'Authorization: QBox ' . $token];
        $url = trim('http://iovip.qbox.me' . $url, '\n');
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_POSTFIELDS, TRUE);
        curl_setopt($curl, CURLOPT_TIMEOUT, 5);
        $result = json_decode(curl_exec($curl), TRUE);
        return isset($result['key']) ? $domain_url . $result['key'] : '';
    }

    public static function uploadToken($bucket, $key, $expires, $policy)
    {
        $accessKey = env('QINIU_ACCESS_KEY');
        $secretKey = env('QINIU_SECRET_KEY');
        $deadline = time() + $expires;
        $scope = $bucket . ':' . $key;
        $args = $policy;
        $args['scope'] = $scope;
        $args['deadline'] = $deadline;
        $data = json_encode($args);
        $encodedData = str_replace(['+', '/'], ['-', '_'], base64_encode($data));
        $sign = $accessKey . ':' . str_replace(['+', '/'], ['-', '_'], base64_encode(hash_hmac('sha1', $encodedData, $secretKey, true)));
        return $sign . ':' . $encodedData;
    }

    public static function saveas($body, $savefile)
    {
        $accessKey = env('QINIU_ACCESS_KEY');
        $secretKey = env('QINIU_SECRET_KEY');
        $body['fops'] = $body['fops'] . '|saveas/' . str_replace(['+', '/'], ['-', '_'], base64_encode($savefile));
        $body = http_build_query($body);
        $token = $accessKey . ':' . str_replace(['+', '/'], ['-', '_'], base64_encode(hash_hmac('sha1', '/pfop' . "\n" . $body, $secretKey, true)));
        $url = 'http://api.qiniu.com/pfop';
        $header = ['Authorization: QBox ' . $token];
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        curl_setopt($curl, CURLOPT_TIMEOUT, 5);
        $result = json_decode(curl_exec($curl), TRUE);
        return $result;
    }

    public static function upload($file, $alias = '')
    {
        $policy['returnBody'] = '{"name": $(fname), "key": $(key), "size": $(fsize), "image": $(imageInfo), "audio": $(avinfo.audio), "video": $(avinfo.video), "hash": $(etag)}';
        $bucket = 'resource';
        $key = $alias ?: 'upload_' . md5($file) . uniqid();
        $token = static::uploadToken($bucket, $key, 600, $policy);
        $url = 'https://up.qbox.me';
        $cfile = new \CURLFile($file);
        $data = ['file' => $cfile, 'key' => $key, 'token' => $token];
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        $output = curl_exec($curl);
        curl_close($curl);
        $result = json_decode($output, true);
        $domain_url = str_replace('bucket', $bucket, env('QINIU_DOMAIN'));
        $result['url'] = $domain_url . $key;
        return $result;
    }
}