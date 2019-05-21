<?php

/**
 * 处理微信素材图片
 * @param  string $image
 * @param  string $no_path 例外不做oss处理的图片目录 默认 'public/assets/wechat'
 * @param  boolean $absolute_path = true 绝对路径
 * @param  boolean $is_mobile = true /mobile 手机端图片
 * @return
 */
function get_wechat_image_path($image = '', $absolute_path = true, $is_mobile = true, $no_path = 'public/assets/wechat')
{
    // 不做oss上传处理的图片
    $length = strlen($no_path);
    if (strtolower(substr($image, 0, $length)) == $no_path) {
        $url = ($absolute_path == true ? __HOST__ . '/' : '') . ($is_mobile == true ? ltrim(__ROOT__, '/') . '/' : '') . $image;
    } else {
        $image_url = get_image_path($image);
        // 处理绝对路径 http:// ,https://
        if (strtolower(substr($image_url, 0, 4)) == 'http') {
            $url = $image_url;
        } else {
            $url = ($absolute_path == true ? __HOST__ : '') . $image_url;
        }
    }
    return $url;
}

/**
 * 发送微信通模板消息
 * @param $code 模板标识
 * @param array $content 发送模板消息内容
 * @param $url 消息链接
 * @param $uid 发送人user_id
 * @return bool
 */
function push_template($code = '', $content = [], $url = '', $uid = 0)
{
    //公众号信息
    if (isset($_COOKIE['ectouch_ru_id'])) {
        $ru_id = $_COOKIE['ectouch_ru_id'];
        $where = ['ru_id' => $ru_id, 'status' => 1];
    } else {
        $where = ['default_wx' => 1, 'status' => 1];
    }
    $wechat_info = dao('wechat')->field('id, token, appid, appsecret')->where($where)->find();
    $config = [
        'driver' => 'wechat',
        'driverConfig' => [
            'token' => $wechat_info['token'],
            'appid' => $wechat_info['appid'],
            'appsecret' => $wechat_info['appsecret'],
        ]
    ];

    $wechat = new \App\Channels\Send($config);

    $data = ['url' => $url, 'wechat_id' => $wechat_info['id']];
    if ($uid == 0) {
        $uid = $_SESSION['user_id'];
    }
    if ($wechat->push($uid, $code, $content, $data) == true) {
        return true;
    } else {
        return $wechat->getError;
    }
}

/**
 *  获取微信用户信息数组
 * @access  public
 * @param
 * @return        $user       用户信息
 */
function get_wechat_user_info($id = 0)
{
    //微信 wechat_users
    if (is_wechat_browser() && is_dir(APP_WECHAT_PATH)) {
        $sql = "SELECT u.user_name, u.nick_name, u.user_picture, wu.headimgurl, wu.nickname FROM " . $GLOBALS['ecs']->table('users') . " AS u "
        . " LEFT JOIN " . $GLOBALS['ecs']->table('wechat_user') . " AS wu ON wu.ect_uid = u.user_id "
        . " WHERE u.user_id = '$id' ";
    } else {
        // users
        $sql = 'SELECT user_name, nick_name , user_picture FROM ' . $GLOBALS['ecs']->table('users') . " WHERE user_id = '$id' ";
    }
    $result = $GLOBALS['db']->getRow($sql);
    $user['nick_name'] =  !empty($result['nickname']) ? $result['nickname'] : (!empty($result['nick_name']) ? $result['nick_name'] : $result['user_name']) ;
    $user['user_picture'] = !empty($result['headimgurl']) ? $result['headimgurl'] : $result['user_picture'];
    return $user;
}

/**
 * 保存ru_id
 * @access  public
 * @param   void
 * @return void
 * @author yang
 * */
function set_ru_id($ru_id)
{
    if (is_dir(APP_WECHAT_PATH)) {
        $cookiekey = 'ectouch_ru_id';
        if ($ru_id > 0) {
            cookie($cookiekey, $ru_id, gmtime() + 3600 * 24); // 过期时间为 1 天
        } else {
            cookie($cookiekey, null);
        }
    }
}

/**
 * 获取ru_id
 * @access  public
 * @param   void
 * @return int
 * @author yang
 * */
function get_ru_id()
{
    if (is_dir(APP_WECHAT_PATH) && isset($_COOKIE['ectouch_ru_id'])) {
        $ru_id = $_COOKIE['ectouch_ru_id'];
        if ($GLOBALS['db']->getOne('SELECT ru_id FROM ' . $GLOBALS['ecs']->table('admin_user') . " WHERE ru_id = '$ru_id' ")) {
            return $ru_id;
        } else {
            cookie($cookiekey, null);
        }
    }
    return 0;
}

/**
 * 获得URL参数
 * @param string $url URL表达式，格式：'http://www.a.com/index.php?参数1=值1&参数2=值2...'
 *  或 参数1=值1&参数2=值2...
 * @return array
 */
function get_url_query($url = '')
{
    // 解析URL
    $info = parse_url($url);
    // 判断参数 是否为url 或 path
    if (false == strpos($url, '?')) {
        if (isset($info['path'])) {
            // 解析地址里面path参数
            parse_str($info['path'], $params);
        }
    } elseif (isset($info['query'])) {
        // 解析地址里面query参数
        parse_str($info['query'], $params);
    }

    return $params;
}

/**
 * 查询绑定用户信息
 * @param  string $unionid 开放平台唯一标识
 * @return array
 */
function get_connect_user($unionid)
{
    // 查询新用户
    $sql = "SELECT u.user_name, u.user_id, u.parent_id FROM {pre}users u, {pre}connect_user cu WHERE u.user_id = cu.user_id AND cu.open_id = '" . $unionid . "' ";
    $userinfo = $GLOBALS['db']->getRow($sql);
    return $userinfo;
}

/**
 * 更新社会化登录用户信息
 * @param  [type] $res, $type:qq,sina,wechat
 * @return
 */
function update_connnect_user($res, $type = '')
{
    // 组合数据
    $profile = [
        'nickname' => $res['nickname'],
        'sex' => $res['sex'],
        'province' => $res['province'],
        'city' => $res['city'],
        'country' => $res['country'],
        'headimgurl' => $res['headimgurl'],
    ];
    $data = [
        'connect_code' => 'sns_' . $type,
        'user_id' => $res['user_id'],
        'open_id' => $res['unionid'],
        'profile' => serialize($profile)
    ];
    if ($res['user_id'] > 0 && $res['unionid']) {
        // 查询
        $connect_userinfo = get_connect_user($res['unionid']);
        if (empty($connect_userinfo)) {
            // 新增记录
            $data['create_at'] = gmtime();
            dao('connect_user')->data($data)->add();
        } else {
            // 更新记录
            dao('connect_user')->data($data)->where(['open_id' => $res['unionid']])->save();
        }
    }
}

/**
 * 更新微信用户信息
 * @param array $info 微信用户信息
 * @param string $is_relation 是否关联
 * @return
 */
function update_wechat_user($info, $is_relation = 0)
{
    // 平台公众号id
    $wechat_id = dao('wechat')->where(['status' => 1, 'default_wx' => 1])->getField('id');
    // 组合数据
    $data = [
        'wechat_id' => $wechat_id,
        'openid' => $info['openid'],
        'nickname' => !empty($info['nickname']) ? $info['nickname'] : '',
        'sex' => !empty($info['sex']) ? $info['sex'] : 0,
        'language' => !empty($info['language']) ? $info['language'] : '',
        'city' => !empty($info['city']) ? $info['city'] : '',
        'province' => !empty($info['province']) ? $info['province'] : '',
        'country' => !empty($info['country']) ? $info['country'] : '',
        'headimgurl' => !empty($info['headimgurl']) ? $info['headimgurl'] : '',
        'unionid' => $info['unionid'],
        'ect_uid' => !empty($info['user_id']) ? $info['user_id'] : 0,
    ];
    // 帐号关联功能 不更新ect_uid
    if ($is_relation == 1) {
        unset($data['ect_uid']);
    }
    // unionid 微信开放平台唯一标识
    if (!empty($info['unionid'])) {
        // 查询
        $where = ['unionid' => $info['unionid'], 'wechat_id' => $wechat_id];
        $result = dao('wechat_user')->field('openid, unionid')->where($where)->find();
        if (empty($result)) {
            // 新增记录
            $data['from'] = 1; // 微信粉丝来源 1 授权登录注册
            dao('wechat_user')->data($data)->add();
        } else {
            // 更新记录
            dao('wechat_user')->data($data)->where($where)->save();
        }
    }
}

/**
 * 关联查询微信会员的会员ID 唯一条件unionid
 * @param  [string] $openid
 * @return [int]
 */
function get_wechat_user_id($openid)
{
    $unionid = dao('wechat_user')->where(['openid' => $openid])->getField('unionid');
    $result = get_connect_user($unionid);
    return $result;
}

/**
 * 兼容更新平台粉丝unionid 已经存在wechat_user 且 unionid 为空的情况 用openid 更新一下 unionid
 * @param
 * @return
 */
function update_wechat_unionid($info, $wechat_id = 0)
{
    //公众号id
    $wechat_id = !empty($wechat_id) ? $wechat_id : dao('wechat')->where(['status' => 1, 'default_wx' => 1])->getField('id');
    // 组合数据
    $data = [
        'wechat_id' => $wechat_id,
        'openid' => $info['openid'],
        'unionid' => $info['unionid']
    ];
    // unionid 微信开放平台唯一标识
    if (!empty($info['unionid'])) {
        // 兼容查询用户openid
        $where = ['openid' => $info['openid'], 'wechat_id' => $wechat_id];
        $res = dao('wechat_user')->field('unionid, ect_uid')->where($where)->find();
        if (empty($res['unionid'])) {
            dao('wechat_user')->data($data)->where($where)->save();
            if (!empty($res['ect_uid'])) {
                // 更新社会化登录用户信息
                $connect_userinfo = get_connect_user($info['unionid']);
                if (empty($connect_userinfo)) {
                    dao('connect_user')->data(['open_id' => $info['unionid']])->where(['open_id' => $info['openid']])->save();
                }
                $info['user_id'] = $res['ect_uid'];
                update_connnect_user($info, 'wechat');
            }
        }
    }
}

/**
 * 微信粉丝生成用户名规则
 * 长度最大15个字符 兼容UCenter用户名
 * @return
 */
function get_wechat_username($unionid, $type = '')
{
    switch ($type) {
        case 'wechat':
            $prefix = 'wx';
            break;
        case 'qq':
            $prefix = 'qq';
            break;
        case 'weibo':
            $prefix = 'wb';
            break;
        case 'facebook':
            $prefix = 'fb';
            break;
        default:
            $prefix = 'sc';
            break;
    }
    return $prefix . substr(md5($unionid), -5) . substr(time(), 0, 4) . mt_rand(1000, 9999);
}

/**
 * 是否开通微信h5配置
 * @param  string  $pay_code
 * @return
 */
function is_wxh5($pay_code = 'wxpay')
{
    $rs = dao('payment')->where(['pay_code' => $pay_code])->getField('pay_config');
    if (!empty($rs)) {
        $rs = unserialize($rs);
        foreach ($rs as $key => $value) {
            $config[$value['name']] = $value['value'];
        }
    }

    return isset($config) ? $config['is_h5'] : 0;
}

/**
 * 把多维数组的键值改为小写或大写  改变原数组
 * @param  [type] &$array 要转换的一维或多维数组
 * @param  [type] $case   小写 CASE_LOWER 大写 CASE_UPPER
 * @return [type]         没有返回值
 */
function array_key_case(&$array, $case = CASE_LOWER)
{
    $array = array_change_key_case($array, $case);
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            array_key_case($array[$key], $case);
        }
    }
}