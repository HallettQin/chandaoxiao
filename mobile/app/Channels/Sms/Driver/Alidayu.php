<?php

namespace App\Channels\Sms\Driver;

class Alidayu
{

    /**
     * 短信类配置
     * @var array
     */
    protected $config = [
        'ali_appkey' => '',
        'ali_secretkey' => '',
    ];

    /**
     * @var objcet 短信对象
     */
    protected $content = [];
    protected $phones = [];
    protected $errorInfo = '';

    /**
     * 构建函数
     * @param array $config 短信配置
     */
    public function __construct($config = [])
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 设置短信信息
     * @param $title
     * @param $content
     * @return $this
     */
    public function setSms($title, $content)
    {
        $sql = "SELECT * FROM {pre}alidayu_configure WHERE send_time = '$title'";
        $msg = $GLOBALS['db']->getRow($sql);
        foreach ($content as $key => $vo) {
            settype($content[$key], 'string');
        }
        // 组装数据
        $this->content = [
            'sms_type' => 'normal',
            'sms_free_sign_name' => $msg['set_sign'],
            'sms_template_code' => $msg['temp_id'],
            'sms_param' => json_encode($content)
        ];
        return $this;
    }

    /**
     * 发送短信
     * @param  string $to 收件人
     * @return boolean
     */
    public function sendSms($to)
    {
        $sendTo = explode(",", $to);
        foreach ($sendTo as $add) {
            if (preg_match("/^0?1((3|7|8)[0-9]|5[0-35-9]|4[57])\d{8}$/", $add)) {
                array_push($this->phones, $add);
            }
        }

        if ($this->phones) {
            foreach ($this->phones as $mobile) {
                return $this->send($mobile);
            }
        }
        return false;
    }

    /**
     * 发送操作
     */
    public function send($mobile)
    {
        require_once(dirname(ROOT_PATH) . '/plugins/aliyunyu/TopSdk.php');
        $c = new \TopClient;
        $c->appkey = $this->config['ali_appkey'];
        $c->secretKey = $this->config['ali_secretkey'];
        $c->format = 'json';
        $req = new \AlibabaAliqinFcSmsNumSendRequest;
        $req->setSmsType($this->content['sms_type']);
        $req->setSmsFreeSignName($this->content['sms_free_sign_name']);
        $req->setSmsParam($this->content['sms_param']);
        $req->setRecNum($mobile);
        $req->setSmsTemplateCode($this->content['sms_template_code']);
        $resp = $c->execute($req);
        if ($resp->code == 0) {
            return true;
        } elseif ($resp->sub_msg) {
            $this->errorInfo = $resp->sub_msg;
        } else {
            $this->errorInfo = $resp->msg;
        }
        logResult($this->errorInfo, 'sms');
        return false;
    }

    /**
     * 返回错误信息
     * @return string
     */
    public function getError()
    {
        return $this->errorInfo;
    }

    /**
     * 析构函数
     */
    public function __destruct()
    {
        unset($this->sms);
    }
}
