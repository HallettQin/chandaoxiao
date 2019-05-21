<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018-8-22
 * Time: 0:51
 */
/* 访问控制 */
if (!defined('IN_ECS'))
{
    die('Hacking attempt');
}

$payment_lang = ROOT_PATH . 'languages/' .$GLOBALS['_CFG']['lang']. '/payment/wxpay.php';
if (file_exists($payment_lang))
{
    global $_LANG;

    include_once($payment_lang);
}

/**
 * 微信类
 */
class wechat{
    const PAY_PREFIX = 'https://api.mch.weixin.qq.com';
    const PAY_UNIFIEDORDER = '/pay/unifiedorder?';
    const PAY_ORDERQUERY = '/pay/orderquery?';
    const PAY_REFUND = '/secapi/pay/refund?';
    const PAY_REFUNDQUERY = '/pay/refundquery?';
    const TAGS_CREATE_URL = '/tags/create?';
    const TAGS_GET_URL = '/tags/get?';
    const TAGS_UPDATE_URL = '/tags/update?';
    const TAGS_DELETE_URL = '/tags/delete?';
    const USER_TAG_URL = '/user/tag/get?';
    const TAGS_MEMBER_BATCHTAGGING_URL = '/tags/members/batchtagging?';
    const TAGS_MEMBER_BATCHUNTAGGING_URL = '/tags/members/batchuntagging?';
    const TAGS_GETIDLIST_URL = '/tags/getidlist?';

    private $appid;
    private $mch_id;
    private $key;

    public function __construct($options)
    {
        $this->appid = isset($options['wxpay_appid']) ? $options['wxpay_appid'] : '';
        $this->mch_id = isset($options['wxpay_mchid']) ? $options['wxpay_mchid'] : '';
        $this->key = isset($options['wxpay_key']) ? $options['wxpay_key'] : '';
    }

    //退款
    function payrefund($arr = array()) {
        $arr['refund_fee_type'] = isset($arr['refund_fee_type']) ? $arr['refund_fee_type'] : 'CNY';
        $arrdata = $this->getPaySign($arr);
        $xmldata = $this->xml_encode($arrdata);

        $result = $this->postXmlSSLCurl(self::PAY_PREFIX . self::PAY_REFUND, $xmldata);
        if ($result) {
            $json = (array) simplexml_load_string($result, 'SimpleXMLElement', LIBXML_NOCDATA);
            if ($json['return_code'] != 'SUCCESS') {
                $this->errCode = $json['return_code'];
                $this->errMsg = $json['return_msg'];
                return false;
            }
            else if ($json['result_code'] != 'SUCCESS') {
                $this->errCode = $json['err_code'];
                $this->errMsg = $json['err_code_des'];
                return false;
            }

            return $json;
        }

        return false;
    }

    public function postXmlSSLCurl($url, $xml, $second = 30)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
        curl_setopt($ch, CURLOPT_SSLCERT, ROOT_PATH . 'data/certs/apiclient_cert.pem'); //证书
        curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
        curl_setopt($ch, CURLOPT_SSLKEY, ROOT_PATH . 'data/certs/apiclient_key.pem');  //证书
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        $data = curl_exec($ch);

        if ($data) {
            curl_close($ch);
            return $data;
        }
        else {
            $error = curl_errno($ch);
            echo 'curl出错，错误码:' . $error . '<br>';
            echo '<a href=\'http://curl.haxx.se/libcurl/c/libcurl-errors.html\'>错误原因查询</a></br>';
            curl_close($ch);
            return false;
        }
    }

    private function getPaySign($arr) {
        if (empty($arr)) {
            return false;
        }

        $arr['appid'] = $this->appid;
        $arr['mch_id'] = $this->mch_id;
        $arr['nonce_str'] = $this->generateNonceStr();
        $paySign = $this->getPaySignature($arr);
        $arr['sign'] = $paySign;
        return $arr;
    }

    /**
     * 生成随机字串
     * @param number $length 长度，默认为16，最长为32字节
     * @return string
     */
    public function generateNonceStr($length=16)
    {
        // 密码字符集，可任意添加你需要的字符
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $str;
    }

    //签名
    public function getPaySignature($arrdata, $method = 'md5')
    {
        ksort($arrdata);
        $paramstring = '';

        foreach ($arrdata as $key => $value) {
            if (!$value) {
                continue;
            }

            if (strlen($paramstring) == 0) {
                $paramstring .= $key . '=' . $value;
            }
            else {
                $paramstring .= '&' . $key . '=' . $value;
            }
        }

        $paramstring = $paramstring . '&key=' . $this->key;
        $Sign = $method($paramstring);
        $Sign = strtoupper($Sign);
        return $Sign;
    }

    /**
     * XML编码
     * @param mixed $data 数据
     * @param string $root 根节点名
     * @param string $item 数字索引的子节点名
     * @param string $attr 根节点属性
     * @param string $id   数字索引子节点key转换的属性名
     * @param string $encoding 数据编码
     * @return string
     */
    public function xml_encode($data, $root='xml', $item='item', $attr='', $id='id', $encoding='utf-8')
    {
        if (is_array($attr)) {
            $_attr = [];
            foreach ($attr as $key => $value) {
                $_attr[] = "{$key}=\"{$value}\"";
            }
            $attr = implode(' ', $_attr);
        }
        $attr   = trim($attr);
        $attr   = empty($attr) ? '' : " {$attr}";
        $xml   = "<{$root}{$attr}>";
        $xml   .= self::data_to_xml($data, $item, $id);
        $xml   .= "</{$root}>";
        return $xml;
    }

    public static function xmlSafeStr($str)
    {
        return '<![CDATA['.preg_replace("/[\\x00-\\x08\\x0b-\\x0c\\x0e-\\x1f]/", '', $str).']]>';
    }

    /**
     * 数据XML编码
     * @param mixed $data 数据
     * @return string
     */
    public static function data_to_xml($data)
    {
        $xml = '';
        foreach ($data as $key => $val) {
            is_numeric($key) && $key = "item id=\"$key\"";
            $xml    .=  "<$key>";
            $xml    .=  (is_array($val) || is_object($val)) ? self::data_to_xml($val)  : self::xmlSafeStr($val);
            list($key, ) = explode(' ', $key);
            $xml    .=  "</$key>";
        }
        return $xml;
    }
}