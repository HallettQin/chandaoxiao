<?php

namespace App\Api\Foundation;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class ApiLogger
{
    const DEBUG = Logger::DEBUG;

    const INFO = Logger::INFO;

    const NOTICE = Logger::NOTICE;

    const WARNING = Logger::WARNING;

    const ERROR = Logger::ERROR;

    private static $logger;

    private static $filePath;

    private static $level = [
        'debug' => self::DEBUG,
        'info' => self::INFO,
        'notice' => self::NOTICE,
        'warning' => self::WARNING,
        'error' => self::ERROR,
    ];

    /**
     * 生成并返回日志对象
     * @param string $name
     * @param string $level
     * @return Logger
     */
    public static function init($name = 'api', $level = 'error')
    {
        $logFile = self::getLogFile();  //文件位置

        self::$logger = new Logger($name);

        $l = self::$level[$level];

        if (empty($l)) {
            die('错误等级不在范围内');
        }

        self::$logger->pushHandler(new StreamHandler($logFile, $l));

        return self::$logger;
    }

    /**
     * 设置日志文件
     * @param $path
     */
    public static function setLogFile($path)
    {
        if (empty($path)) {
            return ;
        }

        self::$filePath = $path;
    }

    /**
     * 获取日志文件
     * @return string
     */
    public static function getLogFile()
    {
        $path = self::$filePath;

        if (empty($path)) {
            $path = ROOT_PATH.'storage/monologs/' . date('y_m_d') . '.log';  //文件位置
        }
        return $path;
    }
}
