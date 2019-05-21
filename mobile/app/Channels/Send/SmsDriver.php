<?php

namespace App\Channels\Send;

use App\Channels\Sms\Sms;

/**
 * 短信发送驱动
 */
class SmsDriver implements SendInterface
{
    protected $config = [
        'sms_name' => '',
        'sms_password' => '',
    ];

    protected $sms;

    public function __construct($config = [])
    {
        $this->config = array_merge($this->config, $config);
        $this->sms = new Sms($this->config);
    }

    /**
     * 发送短信
     * @param  string $to 收信人
     * @param  string $title 标题
     * @param  string $content 内容
     * @param  array $data 其他数据
     * @return array
     */
    public function push($to, $title, $content, $data = [])
    {
        return $this->sms->setSms($title, $content)->sendSms($to);
    }

    public function getError()
    {
        return $this->sms->getError();
    }
}
