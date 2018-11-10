<?php

$config = [
    'default' => [
        'code' => 1001,
        'message' => '输入信息有误',
    ], 'Illuminate\Auth\Access\AuthorizationException' => [
        'code' => 1301,
        'message' => '授权凭证错误',
    ], 'Illuminate\Database\Eloquent\ModelNotFoundException' => [
        'code' => 1302,
        'message' => '数据资源不存在',
    ], 'Illuminate\Validation\ValidationException' => [
        'code' => 1303,
        'message' => '验证信息不通过',
    ], 'Symfony\Component\HttpKernel\Exception\HttpException' => [
        'code' => 1304,
        'message' => '访问协议不正确',
    ], 'Symfony\Component\HttpKernel\Exception\NotFoundHttpException' => [
        'code' => 1305,
        'message' => '访问地址不正确',
    ],
];

return $config;