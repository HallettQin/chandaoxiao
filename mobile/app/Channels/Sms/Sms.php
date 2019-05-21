<?php

namespace App\Channels\Sms;

class Sms
{
    protected $config = [
        'sms_name' => '',
        'sms_password' => '',
    ];

    protected $sms;

    /**
     * 驱动对象
     * @var array
     */
    protected static $objArr = [];

    /**
     * 构建函数
     * @param array $config 邮箱配置
     */
    public function __construct($config = [])
    {
        $this->config = array_merge($this->config, $config);
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
        $sms_type = $this->config['sms_type'];
        if (!isset(self::$objArr[$sms_type])) {
            $smsDriver = __NAMESPACE__ . '\Driver\\' . ucfirst($this->config['sms_type']);
            if (!class_exists($smsDriver)) {
                throw new \Exception("Sms Driver '{$smsDriver}' not found'", 500);
            }
            self::$objArr[$sms_type] = new $smsDriver($this->config[$sms_type]);
        }
        return call_user_func_array([self::$objArr[$sms_type], $method], $args);
    }
}
