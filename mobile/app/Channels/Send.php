<?php

namespace App\Channels;

/**
 * 消息发送类
 */
class Send
{
    protected $send;

    /**
     * 驱动配置
     * @var array
     */
    protected $config = [
        'driver' => 'Email',
        'driverConfig' => [],
    ];

    /**
     * 驱动
     * @var string
     */
    protected $driver;

    /**
     * 驱动对象
     * @var array
     */
    protected static $objArr = [];

    /**
     * 驱动配置
     * Send constructor.
     * @param $config
     * @throws \Exception
     */
    public function __construct($config)
    {
        $this->config = $config;
        $this->send = $config['driver'];
        if (empty($this->config) || !isset($this->config['driver'])) {
            throw new \Exception('send config error', 500);
        }
    }

    /**
     * 回调驱动
     * @param $method
     * @param $args
     * @return mixed
     * @throws \Exception
     */
    public function __call($method, $args)
    {
        if (!isset(self::$objArr[$this->send])) {
            $sendDriver = __NAMESPACE__ . '\Send\\' . ucfirst($this->config['driver']) . 'Driver';
            if (!class_exists($sendDriver)) {
                throw new \Exception("Send Driver '{$sendDriver}' not found'", 500);
            }
            self::$objArr[$this->send] = new $sendDriver($this->config['driverConfig']);
        }
        return call_user_func_array([self::$objArr[$this->send], $method], $args);
    }
}
