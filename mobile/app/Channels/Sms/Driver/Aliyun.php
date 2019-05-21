<?php

namespace App\Channels\Sms\Driver;

use App\Extensions\Http;

class Aliyun
{
    const ENDPOINT_URL = 'http://dysmsapi.aliyuncs.com';
    const ENDPOINT_METHOD = 'SendSms';
    const ENDPOINT_VERSION = '2017-05-25';
    const ENDPOINT_FORMAT = 'JSON';
    const ENDPOINT_REGION_ID = 'cn-hangzhou';
    const ENDPOINT_SIGNATURE_METHOD = 'HMAC-SHA1';
    const ENDPOINT_SIGNATURE_VERSION = '1.0';

    /**
     * 短信类配置
     * @var array
     */
    protected $config = [
        'access_key_id' => '',
        'access_key_secret' => '',
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
        $sql = "SELECT * FROM {pre}alitongxin_configure WHERE send_time = '$title'";
        $msg = $GLOBALS['db']->getRow($sql);
        foreach ($content as $key => $vo) {
            settype($content[$key], 'string');
        }
        // 组装数据
        $this->content = [
            'SignName' => $msg['set_sign'],
            'TemplateCode' => $msg['temp_id'],
            'TemplateParam' => json_encode($content)
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
        $params = [
            'RegionId' => self::ENDPOINT_REGION_ID,
            'AccessKeyId' => $this->config['access_key_id'],
            'Format' => self::ENDPOINT_FORMAT,
            'SignatureMethod' => self::ENDPOINT_SIGNATURE_METHOD,
            'SignatureVersion' => self::ENDPOINT_SIGNATURE_VERSION,
            'SignatureNonce' => uniqid(),
            'Timestamp' => $this->getTimestamp(),
            'Action' => self::ENDPOINT_METHOD,
            'Version' => self::ENDPOINT_VERSION,
            'PhoneNumbers' => strval($mobile),
            'SignName' => $this->content['SignName'],
            'TemplateCode' => $this->content['TemplateCode'],
            'TemplateParam' => $this->content['TemplateParam'],
        ];

        $params['Signature'] = $this->generateSign($params);

        $response = Http::doPost(self::ENDPOINT_URL, $params);

        $resp = json_decode($response, true);
        if ($resp['Code'] == 'OK') {
            return true;
        } else {
            $this->errorInfo = $resp['Message'];
            logResult($this->errorInfo, 'sms');
        }
        return false;
    }

    /**
     * Generate Sign.
     *
     * @param array $params
     *
     * @return string
     */
    protected function generateSign($params)
    {
        ksort($params);
        $accessKeySecret = $this->config['access_key_secret'];
        $stringToSign = 'POST&%2F&' . urlencode(http_build_query($params, null, '&', PHP_QUERY_RFC3986));

        return base64_encode(hash_hmac('sha1', $stringToSign, $accessKeySecret . '&', true));
    }

    /**
     * @return false|string
     */
    protected function getTimestamp()
    {
        $timezone = date_default_timezone_get();
        date_default_timezone_set('GMT');
        $timestamp = date('Y-m-d\TH:i:s\Z');
        date_default_timezone_set($timezone);

        return $timestamp;
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
