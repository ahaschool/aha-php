<?php

namespace Aha\Exceptions;

class ErrorValidate extends \Exception
{
    protected $code = 1202;
    protected $message = '验证数据出现问题';
    protected $data = [];

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function getData()
    {
        return $this->data;
    }
}