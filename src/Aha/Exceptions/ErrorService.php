<?php

namespace Aha\Exceptions;

class ErrorService extends \Exception
{
    protected $code = 1200;
    protected $message = '服务出现异常';
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