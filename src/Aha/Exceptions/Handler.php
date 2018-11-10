<?php

namespace Aha\Exceptions;

use Exception;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        AuthorizationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        ValidationException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {
        return static::exception($request, $exception);
        return parent::render($request, $exception);
    }

    public static function exception($request, $e)
    {
        $code = $e->getCode() ?: 1234;
        $message = $e->getMessage() ?: '服务出现异常';
        $data = ['code' => $code, 'message' => $message];
        if ($code == 1303) {
            $data['validates'] = $e->getResponse()->getData();
        } else if (method_exists($e, 'getData')) {
            $data['debug'] = $e->getData();
        }
        $data['error'] = [
            'type' => get_class($e),
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'message' => $e->getMessage(),
        ];
        $trace = $e->getTraceAsString();
        $data['trace'] = array_slice(explode("\n", $trace), 0, 3);
        return response($data)->withException($e);
    }
}
