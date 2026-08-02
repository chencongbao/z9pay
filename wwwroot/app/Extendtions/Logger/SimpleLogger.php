<?php


namespace App\Extendtions\Logger;

use DateTimeZone;
use Illuminate\Foundation\Application;
use App\Extendtions\Logger\NewLogger;


class SimpleLogger
{
    /**
     * @var Logger
     */
    private static $monolog;

    public static function getMonolog()
    {
        if (!self::$monolog instanceof Logger) {
            self::$monolog = new NewLogger(self::channel(), [], [], new DateTimeZone('PRC'));
        }
        return self::$monolog;
    }

    public static function __callStatic($name, $arguments)
    {
        static::write($arguments[0], isset($arguments[2]) ? $arguments[2] : 'info', $name, $arguments[1]);
    }

    protected static function write($msg, $type, $category, $context = [])
    {
        $file = static::getCommonLogDir($category, $type);
        $monolog = self::getMonolog();
        $monolog->useMicrosecondTimestamps(false);
        $monolog->pushHandler(new RotatingFileHandler($file,config('logging.logs_days')));
        // 将类别记录到日志中
        $monolog->pushProcessor(function ($record) use ($category) {
            $record['level_name'] = "{$record['level_name']}.[{$category}]";
            return $record;
        });
        $monolog->$type($msg, $context);
    }

    /**
     * 获取日志存储路径
     * @param $category
     * @param bool $noDate
     * @return string
     */
    public static function getCommonLogDir($category, $type)
    {
        $log = storage_path('logs' . DIRECTORY_SEPARATOR . date('Y-m-d',time()) . DIRECTORY_SEPARATOR . $category . DIRECTORY_SEPARATOR . $type . '.log');
        return $log;
    }

    protected static function channel()
    {
        /** @var Application $app */
        $app = app();
        if ($app->bound('config') &&
            $channel = $app->make('config')->get('app.log_channel')) {
            return $channel;
        }

        return $app->bound('env') ? $app->environment() : 'production';
    }

}
