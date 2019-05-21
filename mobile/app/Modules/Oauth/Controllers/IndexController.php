<?php

namespace App\Modules\Oauth\Controllers;

use App\Extensions\Form;
use App\Extensions\Wechat;
use App\Modules\Base\Controllers\FrontendController;

class IndexController extends FrontendController
{
    public function __construct()
    {
        parent::__construct();
        L(require(LANG_PATH . C('shop.lang') . '/other.php'));
        $this->assign('lang', array_change_key_case(L()));
        $this->load_helper('passport');
    }

    public function actionIndex()
    {
        $type = I('get.type');
        $back_url = I('get.back_url', '', 'urldecode');
        // 会员中心授权管理绑定
        $user_id = input('get.user_id', 0, 'intval');
        $file = ADDONS_PATH . 'connect/' . $type . '.php';
        if (file_exists($file)) {
            include_once($file);
        } else {
            show_message(L('msg_plug_notapply'), L('msg_go_back'), url('user/login/index'));
        }
        // 处理url
        $url = url('/', [], false, true);
        $param = [
            'm' => 'oauth',
            'type' => $type,
            'back_url' => empty($back_url) ? url('user/index/index') : $back_url
        ];
        $url .= '?' . http_build_query($param, '', '&');
        $config = $this->getOauthConfig($type);
        // 判断是否安装
        if (!$config) {
            show_message(L('msg_plug_notapply'), L('msg_go_back'), url('user/login/index'));
        }
        $obj = new $type($config);

        // 授权回调
        if (isset($_GET['code']) && $_GET['code'] != '') {
            if ($res = $obj->callback($url, $_GET['code'])) {
                $param = get_url_query($back_url);
                $is_drp = false;
                // 处理推荐u参数
                if (isset($param['u'])) {
                    $up_uid = get_affiliate();  // 获得推荐uid
                    $res['parent_id'] = (!empty($param['u']) && $param['u'] == $up_uid) ? intval($param['u']) : 0;
                }
                // 处理分销商d参数
                if (isset($param['d'])) {
                    $up_drpid = get_drp_affiliate();  // 获得分销商d参数
                    $res['drp_parent_id'] = (!empty($param['d']) && $param['d'] == $up_drpid) ? intval($param['d']) : 0;
                    $is_drp = true;
                }

                $res['unionid'] = $res['openid'];
                session('unionid', $res['unionid']);
                session('parent_id', $res['parent_id']);
                session('drp_parent_id', $res['drp_parent_id']);
                redirect($back_url);
                // 会员中心授权管理绑定
                if (isset($_SESSION['user_id']) && $user_id > 0 && $_SESSION['user_id'] == $user_id && !empty($res['unionid'])) {
                    $this->UserBind($res, $user_id, $type);
                }

                // 授权登录
                if ($this->oauthLogin($res, $type) === true) {
                    redirect($back_url);
                }

                // 自动注册
                if (!empty($_SESSION['unionid']) && isset($_SESSION['unionid']) || $res['unionid']) {
                    $res['unionid'] = !empty($_SESSION['unionid']) ? $_SESSION['unionid'] : $res['unionid'];
                    $res['parent_id'] = !empty($_SESSION['parent_id']) ? $_SESSION['parent_id'] : $res['parent_id'];
                    $res['drp_parent_id'] = !empty($_SESSION['drp_parent_id']) ? $_SESSION['drp_parent_id'] : $res['drp_parent_id'];
                    $res['nickname'] = session('nickname');
                    $res['headimgurl'] = session('headimgurl');
                    $this->doRegister($res, $type, $back_url, $is_drp);
                } else {
                    show_message(L('msg_author_register_error'), L('msg_go_back'), url('user/login/index'), 'error');
                }
                return;
            } else {
                show_message(L('msg_authoriza_error'), L('msg_go_back'), url('user/login/index'), 'error');
            }
            return;
        }
        // 授权开始
        $url = $obj->redirect($url);
        redirect($url);
    }

    /**
     * 会员中心授权管理绑定帐号
     * @param
     */
    private function UserBind($res, $user_id, $type)
    {
        // 查询users用户是否存在
        $users = dao('users')->field('user_id, user_name')->where(['user_id' => $user_id])->find();
        if ($users && !empty($res['unionid'])) {
            // 查询users用户是否被其他人绑定
            $connect_user_id = dao('connect_user')->where(['open_id' => $res['unionid'], 'connect_code' => 'sns_' . $type])->getField('user_id');
            if ($connect_user_id > 0 && $connect_user_id != $users['user_id']) {
                show_message(L('msg_account_bound'), L('msg_rebound'), '', 'error');
            }
            // 更新社会化登录用户信息
            $res['user_id'] = $users['user_id'];
            update_connnect_user($res, $type);

            // 更新微信用户信息
            if (is_dir(APP_WECHAT_PATH) && is_wechat_browser() && $type == 'wechat') {
                $res['openid'] = session('openid');
                update_wechat_user($res, 1); // 1 不更新ect_uid
            }

            // 重新登录
            $this->doLogin($users['username']);
            $back_url = empty($back_url) ? url('user/index/index') : $back_url;
            redirect($back_url);
        } else {
            show_message('用户不存在', L('msg_go_back'), '', 'error');
        }
        return;
    }

    // 重新绑定合并帐号
    public function actionMergeUsers()
    {
        if ($_SESSION['user_id']) {
            if (IS_POST) {
                $username = I('username', '', 'trim');
                // 验证
                $form = new Form();
                // 验证手机号并通过手机号查找用户名
                if ($form->isMobile($username, 1)) {
                    $user_name = dao('users')->field('user_name')->where(['mobile_phone' => $username])->find();
                    $username = $user_name['user_name'];
                }
                // 验证邮箱并通过邮箱查找用户名
                if ($form->isEmail($username, 1)) {
                    $user_name = dao('users')->field('user_name')->where(['email' => $username])->find();
                    $username = $user_name['user_name'];
                }
                $password = I('password', '', 'trim');
                $back_url = I('back_url', '', 'urldecode');
                // 数据验证
                if (!$form->isEmpty($username, 1) || !$form->isEmpty($password, 1)) {
                    show_message(L('msg_input_namepwd'), L('msg_go_back'), '', 'error');
                }
                $from_user_id = $_SESSION['user_id'];
                // 查询users用户是否存在
                $new_user_id = $this->users->check_user($username, $password);
                if ($new_user_id > 0) {
                    // 同步社会化登录用户信息
                    $from_connect_user = dao('connect_user')->field('user_id')->where(['user_id' => $from_user_id])->select();
                    if (!empty($from_connect_user)) {
                        foreach ($from_connect_user as $key => $value) {
                            dao('connect_user')->where('user_id = ' . $value['user_id'])->setField('user_id', $new_user_id);
                        }
                    }
                    if (is_dir(APP_WECHAT_PATH)) {
                        // 微信用户
                        $from_wechat_user = dao('wechat_user')->field('ect_uid')->where(['ect_uid' => $from_user_id])->find();
                        if (!empty($from_wechat_user)) {
                            dao('wechat_user')->where('ect_uid = ' . $from_wechat_user['ect_uid'])->setField('ect_uid', $new_user_id);
                        }
                    }

                    // 合并绑定会员数据 $from_user_id  $new_user_id
                    $res = merge_user($from_user_id, $new_user_id);
                    if ($res == true) {
                        // 退出重新登录
                        $this->users->logout();
                        $back_url = empty($back_url) ? url('user/index/index') : $back_url;
                        show_message(L('logout'), [L('back_up_page'), "返回首页"], [$back_url, url('/')], 'success');
                    }
                    return;
                } else {
                    show_message(L('msg_account_bound_fail'), L('msg_rebound'), '', 'error');
                }
                return;
            }
            $back_url = I('back_url', '', 'urldecode');
            $this->assign('back_url', $back_url);
            $this->assign('page_title', "重新绑定帐号");
            $this->display();
        } else {
            show_message("请登录", L('msg_go_back'), url('user/login/index'), 'error');
        }
    }

    /**
     * 获取第三方登录配置信息
     *
     * @param type $type
     * @return type
     */
    private function getOauthConfig($type)
    {
        if ($type == 'wechat') {
            $sql = 'SELECT auth_config FROM {pre}touch_auth WHERE `type` = \'' . $type . '\'';
        } else {
            $sql = 'SELECT auth_config FROM {pre}touch_auth WHERE `type` = \'' . $type . '\' AND `status` = 1';

        }
        
        $info = $this->db->getRow($sql);
        if ($info) {
            $res = unserialize($info['auth_config']);
            $config = [];
            foreach ($res as $key => $value) {
                $config[$value['name']] = $value['value'];
            }
            return $config;
        }
        return false;
    }

    /**
     * 授权自动登录
     * @param  $res
     */
    private function oauthLogin($res, $type)
    {
        // 兼容老用户
        $older_user = dao('users')->field('user_name, user_id')->where(['aite_id' => $type . '_' . $res['unionid']])->find();
        if (!empty($older_user)) {
            // 清空aite_id
            dao('users')->data(['aite_id' => ''])->where(['user_id' => $older_user['user_id']])->save();
            // 同步社会化登录用户信息表
            $res['user_id'] = $older_user['user_id'];
            update_connnect_user($res, $type);
        }

        // 查询新用户
        $userinfo = get_connect_user($res['unionid']);

        // 已经绑定过的 授权自动登录
        if ($userinfo) {
            $this->doLogin($userinfo['user_name']);
            // 更新会员表信息
            $user_data = [
                'nick_name' => $res['nickname'],
                'sex' => $res['sex'],
                'user_picture' => $res['headimgurl'],
            ];
            dao('users')->data($user_data)->where(['user_id' => $userinfo['user_id']])->save();
            // 更新社会化登录用户信息
            $res['user_id'] = !empty($userinfo['user_id']) ? $userinfo['user_id'] : $_SESSION['user_id'];
            update_connnect_user($res, $type);
            // 更新微信授权用户信息
            if (is_dir(APP_WECHAT_PATH) && is_wechat_browser() && $type == 'wechat') {
                $res['openid'] = session('openid');
                update_wechat_user($res, 1); // 1 不更新ect_uid
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * 设置成登录状态
     * @param  $username
     */
    private function doLogin($username)
    {
        $this->users->set_session($username);
        $this->users->set_cookie($username);
        update_user_info();
        recalculate_price();
    }

    /**
     * 授权注册
     * @param        $res
     * @param string $back_url
     */
    private function doRegister($res, $type = '', $back_url = '', $is_drp = false)
    {
        $username = get_wechat_username($res['unionid'], $type);
        $password = mt_rand(100000, 999999);
        $email = $username . '@qq.com';
        $extends = [
            'nick_name' => $res['nickname'],
            'sex' => !empty($res['sex']) ? $res['sex'] : 0,
            'user_picture' => $res['headimgurl'],
        ];
        if (is_dir(APP_DRP_PATH) && $is_drp == true) {
            $extends['drp_parent_id'] = $res['drp_parent_id'];
        } else {
            $extends['parent_id'] = $res['parent_id'];
        }

        // 查询是否绑定
        $userinfo = get_connect_user($res['unionid']);
        if (empty($userinfo)) {
            if (register($username, $password, $email, $extends) !== false) {
                // 更新社会化登录用户信息
                $res['user_id'] = session('user_id');
                update_connnect_user($res, $type);

                // 更新微信用户信息
                if (is_dir(APP_WECHAT_PATH) && is_wechat_browser() && $type == 'wechat') {
                    $res['openid'] = session('openid');
                    update_wechat_user($res);
                    //关注送红包
                    $this->sendBonus();
                }

                // 跳转链接
                $back_url = empty($back_url) ? url('user/index/index') : $back_url;
                redirect($back_url);
            } else {
                show_message(L('msg_author_register_error'), L('msg_re_registration'), '', 'error');
            }
        } else {
            show_message(L('msg_account_bound'), L('msg_go_back'), url('user/index/index'), 'error');
        }
        return;
    }

    /**
     * 关注送红包
     */
    private function sendBonus()
    {
        // 查询平台微信配置信息
        $wxinfo = dao('wechat')->field('id, token, appid, appsecret, encodingaeskey')->where(['default_wx' => 1, 'status' => 1])->find();
        if ($wxinfo) {
            // 查询功能扩展 是否安装
            $rs = $this->db->query("SELECT name, keywords, command, config FROM {pre}wechat_extend WHERE command = 'bonus' and enable = 1 and wechat_id = " . $wxinfo['id'] . " ORDER BY id ASC");
            $addons = reset($rs);
            $file = APP_PATH . 'Wechat/Plugins/' . ucfirst($addons['command']) . '/' . ucfirst($addons['command']) . '.php';
            if (file_exists($file)) {
                require_once($file);
                $new_command = '\\App\\Modules\\Wechat\\Plugins\\' . ucfirst($addons['command']) . '\\' . ucfirst($addons['command']);
                $wechat = new $new_command();
                $data = $wechat->returnData($_SESSION['openid'], $addons);
                if (!empty($data)) {
                    $config['token'] = $wxinfo['token'];
                    $config['appid'] = $wxinfo['appid'];
                    $config['appsecret'] = $wxinfo['appsecret'];
                    $config['encodingaeskey'] = $wxinfo['encodingaeskey'];
                    $weObj = new Wechat($config);
                    $weObj->sendCustomMessage($data['content']);
                }
            }
        }
    }
}
