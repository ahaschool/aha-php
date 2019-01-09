<?php

namespace Aha\Exceptions;

class ErrorInput extends \Exception
{
    protected $code = 1001;
    protected $message = '输入信息有误';
}
