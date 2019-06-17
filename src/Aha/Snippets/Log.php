<?php

namespace Aha\Snippets;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

class Log
{
    CONST LOGNAME = 'service';
    CONST ERROR_LOGNAME = 'service-error'; //错误日志

    CONST DEBUG = 100;
    CONST INFO = 200;
    CONST NOTICE = 250;
    CONST WARNING = 300;
    CONST ERROR = 400;
    CONST CRITICAL = 500;
    CONST ALERT = 550;
    CONST EMERGENCY = 600;

    private static function logger($log_name, $lineFormatter = '')
    {
        $dir = rtrim(env('LOG_PATH', '/data/logs'), '/') . '/';
        if (false == file_exists($dir)) {
            mkdir(env('LOG_PATH'), 0777);
        }
        $handler = new StreamHandler($dir . $log_name . '.log');
        $handler->setFormatter(new LineFormatter($lineFormatter, null, false, false));;
        return (new Logger($log_name))->pushHandler($handler);
    }

    public static function mkLineFormatter($level, $context)
    {
        $microtime = explode(".", microtime(true))[1] ?? 0;
        $progress = php_sapi_name() . '-' . getmypid();
        if (isset($context['error'])) {
            return "%datetime%," . substr($microtime, 0, 3) . " " . strtoupper($level) . " [{$progress}][{$context['error']['file']}:{$context['error']['line']}] %message% %context%\n";
        } else {
            return "%datetime%," . substr($microtime, 0, 3) . " " . strtoupper($level) . " [{$progress}] %message% %context%\n";
        }
    }

    /**
     * 包装日志数据
     * @param array $context
     * @return array
     */
    public static function mkData($context = [])
    {
        $context['uri'] = $_SERVER['REQUEST_URI'] ?? '';
        if (!empty($_SERVER['PHP_AUTH_USER'])) {
            $context['username'] = $_SERVER['PHP_AUTH_USER'];
        }
        if (!empty($_SERVER['PHP_AUTH_PW'])) {
            $context['password'] = $_SERVER['PHP_AUTH_PW'];
        }
        $context['data'] = $GLOBALS['app']->request->all();
        return $context;
    }

    /**
     * 日志级别 100
     * @param string $msg
     * @param array $context
     * @return bool
     */
    public static function addDebug($msg = 'default debug msg', $context = [])
    {
        $log = static::logger(self::LOGNAME, self::mkLineFormatter('DEBUG', $context));
        return env('LOG_LEVEL') <= self::DEBUG ? $log->addDebug($msg, self::mkData($context)) : true;
    }

    /**
     * 日志级别 200
     * @param string $msg
     * @param array $context
     * @return bool
     */
    public static function addInfo($msg = 'default info msg', $context = [])
    {
        $log = static::logger(self::LOGNAME, self::mkLineFormatter('INFO', $context));
        return env('LOG_LEVEL') <= self::INFO ? $log->addInfo($msg, self::mkData($context)) : true;
    }

    /**
     * 日志级别 250
     * @param string $msg
     * @param array $context
     * @return bool
     */
    public static function addNotice($msg = 'default notice msg', $context = [])
    {
        $log = static::logger(self::LOGNAME, self::mkLineFormatter('NOTICE', $context));
        return env('LOG_LEVEL') <= self::NOTICE ? $log->addNotice($msg, self::mkData($context)) : true;
    }

    /**
     * 日志级别 300
     * @param string $msg
     * @param array $context
     * @return bool
     */
    public static function addWarning($msg = 'default warning msg', $context = [])
    {
        $log = static::logger(self::LOGNAME, self::mkLineFormatter('WARNING', $context));
        return env('LOG_LEVEL') <= self::WARNING ? $log->addWarning($msg, self::mkData($context)) : true;
    }

    /**
     * 日志级别 400
     * @param string $msg
     * @param array $context
     * @return bool
     */
    public static function addError($msg = 'default error msg', $context = [])
    {
        $lineFormatter = self::mkLineFormatter('ERROR', $context);
        static::logger(self::LOGNAME, $lineFormatter)->addInfo($msg, self::mkData($context));

        $log = static::logger(self::ERROR_LOGNAME, $lineFormatter);
        return env('LOG_LEVEL') <= self::ERROR ? $log->addError($msg, self::mkData($context)) : true;
    }

    /**
     * 日志级别 500
     * @param string $msg
     * @param array $context
     * @return bool
     */
    public static function addCritical($msg = 'default critical msg', $context = [])
    {
        $log = static::logger(self::LOGNAME, self::mkLineFormatter('CRITICAL', $context));
        return env('LOG_LEVEL') <= self::CRITICAL ? $log->addCritical($msg, self::mkData($context)) : true;
    }

    /**
     * 日志级别 550
     * @param string $msg
     * @param array $context
     * @return bool
     */
    public static function addAlert($msg = 'default alert msg', $context = [])
    {
        $log = static::logger(self::LOGNAME, self::mkLineFormatter('ALERT', $context));
        return env('LOG_LEVEL') <= self::ALERT ? $log->addAlert($msg, self::mkData($context)) : true;
    }

    /**
     * 日志级别 600
     * @param string $msg
     * @param \Exception $e
     * @return bool
     */
    public static function addException($msg = 'default exception msg', \Exception $e)
    {
        $context['code'] = $e->getCode();
        $context['message'] = $e->getMessage();
        $context['trace'] = explode("\n", $e->getTraceAsString());
        return static::singleton()->addEmergency($msg, static::mkData($context));
    }
}
