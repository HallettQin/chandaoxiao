<?php
/**
 * ECTouch Open Source Project
 * ============================================================================
 * Copyright (c) 2012-2015 http://ectouch.cn All rights reserved.
 * ----------------------------------------------------------------------------
 * 文件名称：wechat.php
 * ----------------------------------------------------------------------------
 * 功能描述：wechat登录插件
 * ----------------------------------------------------------------------------
 * Licensed ( http://www.ectouch.cn/docs/license.txt )
 * ----------------------------------------------------------------------------
 */

/* 访问控制 */
defined('IN_ECTOUCH') or die('Deny Access');

$payment_lang = LANG_PATH . C('shop.lang') . '/connect/' . basename(__FILE__);

if (file_exists($payment_lang)) {
    include_once($payment_lang);
    L($_LANG);
}
/* 模块的基本信息 */
if (isset($set_modules) && $set_modules == true) {
    $i = isset($modules) ? count($modules) : 0;
    /* 类名 */
    $modules[$i]['name'] = 'Wechat';
    // 文件名，不包含后缀
    $modules[$i]['type'] = 'wechat';

    $modules[$i]['className'] = 'wechat';
    // 作者信息
    $modules[$i]['author'] = 'ECTouch';

    // 作者QQ
    $modules[$i]['qq'] = '800007167';

    // 作者邮箱
    $modules[$i]['email'] = 'support@ecmoban.com';

    // 申请网址
    $modules[$i]['website'] = 'http://open.weixin.qq.com';

    // 版本号
    $modules[$i]['version'] = '2.0';

    // 更新日期
    $modules[$i]['date'] = '2017-03-22';

    /* 配置信息 */
    $modules[$i]['config'] = [
        ['type' => 'text', 'name' => 'app_id', 'value' => ''],
        ['type' => 'text', 'name' => 'app_secret', 'value' => ''],
    ];
    return;
}

use App\Extensions\Wechat as Weixin;

class wechat
{
    private $wechat = '';
    private $options = [];

    /**
     * 构造函数
     *
     * @param unknown $config
     */
    public function __construct($config)
    {
        $options = [
            'appid' => $config['app_id'],
            'appsecret' => $config['app_secret'],
        ];
        $this->wechat = new Weixin($options);
    }

    /**
     * 获取授权地址
     */
    public function redirect($callback_url, $state = 'wechat_oauth', $snsapi = 'snsapi_userinfo')
    {
        if (is_dir(APP_WECHAT_PATH) && is_wechat_browser() && isset($_COOKIE['ectouch_ru_id'])) {
            $snsapi = 'snsapi_base'; // 静默授权
            $state = 'repeat'; // state 标识
        }
        $_SESSION['state'] = $state;
        return $this->wechat->getOauthRedirect($callback_url, $state, $snsapi);
    }

    /**
     * 回调用户数据
     */
    public function callback($callback_url, $code)
    {
        if (!empty($code)) {
            if ($_REQUEST['state'] != $_SESSION['state']) {
                return false;
            }
            $token = $this->wechat->getOauthAccessToken();
            $userinfo = $this->wechat->getOauthUserinfo($token['access_token'], $token['openid']);
            if (!empty($userinfo) && !empty($userinfo['openid'])) {
                // 对昵称有特殊字符进行替换
                include('emoji.php');
                $userinfo['nickname'] = strip_tags(emoji_unified_to_html($userinfo['nickname']));//过滤emoji表情产生的html标签
                $_SESSION['openid'] = $userinfo['openid'];
                $_SESSION['nickname'] = $userinfo['nickname'];
                $_SESSION['headimgurl'] = $userinfo['headimgurl'];
                $data = [
                    'openid' => $userinfo['unionid'],
                    'nickname' => $userinfo['nickname'],
                    'sex' => $userinfo['sex'],
                    'headimgurl' => $userinfo['headimgurl'],
                    'city' => $userinfo['city'],
                    'province' => $userinfo['province'],
                    'country' => $userinfo['country'],
                ];
                if (is_dir(APP_WECHAT_PATH) && is_wechat_browser()) {
                    update_wechat_unionid($userinfo);
                }
                return $data;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
}
