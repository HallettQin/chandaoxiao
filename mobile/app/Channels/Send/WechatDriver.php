<?php

namespace App\Channels\Send;

use App\Channels\Wechat\Wechat;

/**
 * 微信发送驱动
 */
class WechatDriver implements SendInterface
{
    protected $config = [];

    protected $wechat;

    public function __construct($config = [])
    {
        $this->config = array_merge($this->config, $config);
        $this->wechat = new Wechat($this->config);
    }

    /**
     * 发送微信
     * @param  string $to 接收人
     * @param  string $title 标题 code 模板标识
     * @param  string $content 模板消息内容
     * @param  array $data 其他数据 url
     * @return array
     */
    public function push($to, $title, $content, $data = [])
    {
        return $this->wechat->setData($to, $title, $content, $data)->send($to, $title);
    }

    public function getError()
    {
        return $this->wechat->getError();
    }
}
