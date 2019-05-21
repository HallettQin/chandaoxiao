<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/1/5
 * Time: 18:03
 */
class Wechat{
    //=======【基本信息设置】=====================================
    //微信公众号身份的唯一标识。审核通过后，在微信发送的邮件中查看
    public $APPID;
    //JSAPI接口中获取openid，审核后在公众平台开启开发模式后可查看
    public $APPSECRET;

    //=======【curl超时设置】===================================
    //本例程通过curl使用HTTP POST方法，此处可修改其超时时间，默认为30秒
    public $CURL_TIMEOUT = 30;

    function __construct($config) {
        if(isset($config['appid'])){
            $this->APPID = $config['appid'];
            $this->APPSECRET = $config['appsecret'];
        }
    }

    function getConfig($name){
        return $this->$name;
    }

    function getaccesstoken(){
        if (!isset($_GET['code'])) {
            $request = Request::instance();
            $appid = $this->APPID;
            $scope = 'snsapi_userinfo';
            $redirect_uri =urlencode($request->scheme().'://'.$request->host().$request->url());
            $url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid='.$appid.'&redirect_uri='.urlencode($redirect_uri).'&response_type=code&scope='.$scope.'&state=STATE#wechat_redirect';
            header('Location:'.$url);
            die();
        } else {
            $posturl = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid='.$this->APPID;
            $posturl .= '&secret='.$this->APPSECRET;
            $posturl .= '&code='.$_GET['code'];
            $posturl .= '&grant_type=authorization_code';

            return $this->curl($posturl);
        }
    }

    function getAccess_token() {
        $posturl = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential';
        $posturl .= '&appid='.$this->APPID;
        $posturl .= '&secret='.$this->APPSECRET;
        return $this->curl($posturl);
    }

    function templesend($access_token, $openid) {
        $posturl = 'https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token='.$access_token;
        $data = $this->getDataArray($openid);
        $data = json_encode($data);
        return $this->curl($posturl, $data);
    }

    //获取发送数据数组
    function getDataArray($value)
    {
        $data = array(
            'touser' => $value, //要发送给用户的openid
            'template_id' => "MVBm6TVJr_Puec-CsGhN_748Bxl7OqqfcrgswRFRu5A",//改成自己的模板id，在微信后台模板消息里查看
            'form_id' => '8d3ea03e01e4cb58d08cba15b4bd05a5',
            'data' => array(
                'keyword1' => array(
                    'value' => "亲爱的同学，您有考试提醒，请查阅。",
                    'color' => "#000"
                ),
                'keyword2' => array(
                    'value' => "6月22日 23:00:00",
                    'color' => "#000"
                )
            )
        );
        return $data;
    }

    function jscode2session($code) {
        $posturl = "https://api.weixin.qq.com/sns/jscode2session";
        $posturl .= '?appid='. $this->APPID.'&secret='. $this->APPSECRET.'&js_code='. $code.'&grant_type=authorization_code';
        return $this->curl($posturl);
    }

    function getwxuserinfo($access_token = '', $openid = ''){
        $posturl = 'https://api.weixin.qq.com/sns/userinfo?access_token='.$access_token;
        $posturl .= '&openid='.$openid.'&lang=zh_CN';
        return $this->curl($posturl);
    }

    private function curl($url = '', $data = null){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $data = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        return json_decode($data, true);
    }
}
