<?php

namespace App\Modules\User\Controllers;

use Think\Verify;
use App\Extensions\Form;
use App\Modules\Base\Controllers\FrontendController;

class LoginController extends FrontendController
{
    public $user;
    public $user_id;

    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct();
        L(require(LANG_PATH . C('shop.lang') . '/user.php'));
        $file = [
            'passport',
            'clips',
        ];
        $this->load_helper($file);
        // 属性赋值
        $this->user_id = $_SESSION['user_id'];
    }

    /**
     * 用户登录
     */
    public function actionIndex()
    {
        // 是否为post提交
        if (IS_POST) {
            $username = isset($_POST['username']) ? trim($_POST['username']) : '';
            $password = isset($_POST['password']) ? trim($_POST['password']) : '';
            $back_act = isset($_POST['back_act']) ? trim($_POST['back_act']) : '';
            $back_act = empty($back_act) ? url('user/index/index') : $back_act;

            $apply = isset($_POST['apply']) ? trim($_POST['apply']) : '';

            // 验证
            $form = new Form();
            if ($form->isEmail($username, 1)) {
                $login = $this->db->getOne("SELECT user_name FROM {pre}users WHERE email='$username'");
                if ($login) {
                    $username = $login;
                }
            } elseif ($form->isMobile($username, 1)) {
                $login = $this->db->getOne("SELECT user_name FROM {pre}users WHERE mobile_phone='$username'");
                if ($login) {
                    $username = $login;
                }
            }
            if ($this->users->login($username, $password)) {
                update_user_info();
                recalculate_price();
                if ($apply == 'yes') {
                    $back_act = url('user/index/'.$apply);
                }

                $info = '';

                if ($this->check_purchasers()) {
                    $info = '您目前是零售商，欢迎采购！';
                } else if($this->is_supplier()) {
                    $info = '您目前是制造商，仅拥有浏览网站首页的权限';
                } else {
                    $info = '您目前是普通会员，仅拥有浏览网站首页的权限';
                }

                exit(json_encode(['status' => 'y', 'info' => $info, 'url' => $back_act]));
            } else {
                $_SESSION['login_fail']++;
                exit(json_encode(['status' => 'n', 'info' => L('login_failure')]));
            }
        }

        if ($this->user_id > 0) {
            $this->redirect('/user');
        }
        $back_act = input('back_act', '', 'urldecode');
        $apply = input('apply', '', 'trim');

        if (empty($back_act)) {
            if (empty($back_act) && isset($GLOBALS['_SERVER']['HTTP_REFERER'])) {
                $back_act = strpos($GLOBALS['_SERVER']['HTTP_REFERER'], url('user/index/index')) ? url('user/index/index') : $GLOBALS['_SERVER']['HTTP_REFERER'];
            } else {
                $back_act = url('user/index/index');
            }
        }
        //来源是退出地址时 默认会员中心
        $back_act = strpos($back_act, url('user/login/logout')) ? url('user/index/index') : $back_act;
        // 显示社会化登录插件
        $condition = [
            'status' => 1
        ];
        $oauth_list = $this->model->table('touch_auth')->where($condition)->order('sort asc, id asc')->select();
        foreach ($oauth_list as $key => $vo) {
            if ($vo['type'] == 'wechat' && !is_wechat_browser()) {
                unset($oauth_list[$key]);
            }
        }
        $this->assign('oauth_list', $oauth_list);
        $this->assign('sms_signin', C('shop.sms_signin'));
        $this->assign('back_act', $back_act);
        $this->assign('apply', $apply);
        $this->assign('page_title', L('log_user'));
        $this->assign('passport_js', L('passport_js'));
        $this->display();
    }

    /**
     * 手机号快捷登录 验证短信验证码登录
     * @return
     */
    public function actionMobileQuick()
    {
        // 提交
        if (IS_POST) {
            $mobile = input('mobile', '');
            $sms_code = input('mobile_code', '');
            $back_act = input('back_act', '', 'urldecode');
            $back_act = empty($back_act) ? url('user/index/index') : $back_act;

            // 验证短信验证码
            if ($mobile != $_SESSION['sms_mobile'] || $sms_code != $_SESSION['sms_mobile_code']) {
                exit(json_encode(['status' => 'n', 'info' => L('log_mobile_verify_error')]));
            }

            // 验证手机号格式
            if (is_mobile($mobile) == false) {
                exit(json_encode(['status' => 'n', 'info' => '手机号码格式错误']));
            }

            // 验证手机号是否存在
            $condition['mobile_phone'] = $mobile;
            $condition['user_name'] = $mobile;
            $condition['_logic'] = 'OR';
            $users = dao('users')->field('user_name, mobile_phone')->where($condition)->find();
            if (!empty($users)) {
                // 设置登录
                $this->users->set_session($users['user_name']);
                $this->users->set_cookie($users['user_name']);
                update_user_info();
                recalculate_price();
                exit(json_encode(['status' => 'y', 'info' => L('login_success'), 'url' => $back_act]));
            } else {
                // 手机号自动注册
                $username = $mobile;
                $password = $sms_code; //验证码做为密码
                $email = $mobile . '@qq.com';
                $other = ['mobile_phone' => $mobile, 'nick_name' => $username];

                if (register($username, $password, $email, $other) !== false) {
                    $message_tips = '手机号 %s 注册成功';
                    exit(json_encode(['status' => 'y', 'info' => sprintf($message_tips, $username), 'url' => $back_act]));
                } else {
                    exit(json_encode(['status' => 'n', 'info' => '注册失败']));
                }
            }
            exit;
        }
        // 须开启注册发送验证码
        if (C('shop.sms_signin') == 0) {
            $this->redirect('user/login/index');
        }
        $back_act = input('back_act', '', 'urldecode');
        if (empty($back_act)) {
            if (empty($back_act) && isset($GLOBALS['_SERVER']['HTTP_REFERER'])) {
                $back_act = strpos($GLOBALS['_SERVER']['HTTP_REFERER'], url('user/index/index')) ? url('user/index/index') : $GLOBALS['_SERVER']['HTTP_REFERER'];
            } else {
                $back_act = url('user/index/index');
            }
        }
        //来源是退出地址时 默认会员中心
        $back_act = strpos($back_act, url('user/login/logout')) ? url('user/index/index') : $back_act;

        $this->assign('back_act', $back_act);
        $this->assign('page_title', '手机号快捷登录');
        $this->display();
    }

    /**
     * 找回密码 填写用户信息页面  处理用户提交
     */
    public function actionGetPassword()
    {
        if (IS_POST) {
            $username = I('post.username');
            $result = ['error' => 0, 'content' => ''];
            if (empty($username)) {
                $result['error'] = 1;
                $result['content'] = '没有找到用户信息';
                echo json_encode($result);
                exit;
            }

            $userInfo = $this->getUserInfo($username);//获取用户信息

            if (empty($userInfo)) {
                $result['error'] = 1;
                $result['content'] = '没有找到用户信息';
            } else {
                session('forget_user_data', [
                    'user_id' => $userInfo['user_id'],
                    'email' => $userInfo['email'],
                    'user_name' => $userInfo['user_name'],
                    'phone' => $userInfo['mobile_phone'],
                    'reg_time' => $userInfo['reg_time'],
                ]);

                if (empty($userInfo['email']) && empty($userInfo['mobile_phone'])) {
                    $result['error'] = 1;
                    $result['content'] = '没有找到用户信息';
                } else {
                    $result['mail_or_phone'] = (($userInfo['email'] == $username) ? 'email' : (($userInfo['mobile_phone'] == $username) ? 'phone' : ((empty($userInfo['mobile_phone'])) ? 'email' : 'phone')));
                }
            }

            echo json_encode($result);
            exit;
        }
        $this->assign('page_title', L('get_password'));
        $this->display();
    }

    /** 获取用户信息 */
    private function getUserInfo($username)
    {
        $userInfo = $this->db->getRow("SELECT user_id, email, user_name, mobile_phone, reg_time FROM {pre}users WHERE email = '" . $username . "' OR user_name = '" . $username . "' OR mobile_phone = '" . $username . "'");

        return $userInfo;
    }

    /**
     * 忘记密码
     * 显示手机页面
     * 处理验证码验证
     * 通过则跳转
     */
    public function actionGetPasswordShow()
    {
        if (IS_POST) {
            $result = ['error' => 0, 'content' => ''];
            $code = I('code', '');

            if (empty($code)) {
                $result['error'] = 1;
                $result['content'] = '验证码不能为空';
            }

            if (session('forget_user_data.verify_str') == md5($code . session('forget_user_data.user_id') . session('forget_user_data.reg_time'))) {
                $result['error'] = 0;
                $result['content'] = '验证通过';
            } else {
                $result['error'] = 1;
                $result['content'] = '验证码错误，请重新输入';
            }

            echo json_encode($result);
            exit;
        }
        $type = I('type');
        $this->assign('page_title', L('get_password'));
        $this->assign('type', $type);
        $this->assign('user_name', session('forget_user_data.user_name'));
        $this->assign('mobile_phone', session('forget_user_data.phone'));
        $this->assign('email', session('forget_user_data.email'));
        $this->display();
    }

    /**
     * 发送验证码
     * 短信或邮件
     */
    public function actionSendSms()
    {
        $result = ['error' => 0, 'content' => ''];
        $number = I('post.number'); //手机号码或邮箱
        $type = I('post.type');

        if ($type == 'email') {
            // 初始化会员用户名和邮件地址
            $user_name = $this->db->getOne("SELECT user_name FROM {pre}users WHERE email='$number'");
            // 用户信息
            $user_info = $this->users->get_user_info($user_name);

            if ($user_info['user_name'] == $user_name && $user_info['email'] == $number) {
                // 生成code
                $code = $this->generateCodeString();
                // 发送邮件的函数
                if (send_pwd_email($user_info['user_id'], $user_name, $number, $code)) {
                    $result['content'] = L('send_success');
                } else {
                    // 发送邮件出错
                    $result['error'] = 1;
                    $result['content'] = L('fail_send_password');
                    exit(json_encode($result));
                }
            } else {
                // 用户名与邮件地址不匹配
                $result['error'] = 1;
                $result['content'] = L('username_no_email');
                exit(json_encode($result));
            }
        } elseif ($type == 'phone') {
            $code = $this->generateCodeString();//生成验证码

            //发送短信
            $template = L('you_auth_code') . $code . L('please_protect_authcode');
            if (is_mobile($number) == false) {
                $result['error'] = 1;
                $result['content'] = '手机号码格式错误';
                exit(json_encode($result));
            }
            // 组装数据
            $message = [
                'code' => $code
            ];
            if (send_sms($number, 'sms_code', $message) === true) {
                $result['error'] = 0;
                $result['content'] = '短信发送成功';
                $_SESSION['sms_mobile'] = $number;
                $_SESSION['sms_mobile_code'] = $code;
                exit(json_encode($result));
            } else {
                $result['error'] = 1;
                $result['content'] = '短信发送失败';
                exit(json_encode($result));
            }
            //发送短信end
        } else {
            $result['error'] = 1;
            $result['content'] = '操作有误';
            exit(json_encode($result));
        }
        exit(json_encode($result));
    }

    /**
     * 生成短信邮箱验证码加密串
     */
    private function generateCodeString()
    {
        $code = rand(1000, 9999);
        $verify_string = md5($code . session('forget_user_data.user_id') . session('forget_user_data.reg_time'));
        $forgetdata = session('forget_user_data');
        $forgetdata = array_merge($forgetdata, ['verify_str' => $verify_string]);
        session('forget_user_data', $forgetdata);
        return $code;
    }

    /**
     * 检验填写信息
     * 修改密码
     */
    public function actionEditForgetPassword()
    {
        if (IS_POST) {
            $password = I('password', '');
            $uid = session('forget_user_data.user_id');
            if (empty($password)) {
                show_message(L('log_pwd_notnull'));
            }
            if ($uid < 1) {
                show_message(L('log_opration_error'));
            }
            $sql = "SELECT user_name FROM {pre}users WHERE  user_id=" . $uid;
            $user_name = $this->db->getOne($sql);
            if ($this->users->edit_user(['username' => $user_name, 'old_password' => $password, 'password' => $password], 0)) {
                $sql = "UPDATE {pre}users SET `ec_salt`='0' WHERE user_id= '" . $uid . "'";
                $this->db->query($sql);
                unset($_SESSION['temp_user_id']);
                unset($_SESSION['user_name']);
                show_message(L('edit_sucsess'), L('back_login'), url('user/login/index'), 'success');
            }
            show_message(L('edit_error'), L('retrieve_password'), url('user/login/get_password_phone', ['enabled_sms' => 2]), 'info');
        }
        $this->display();
    }

    /**
     * 修改密码
     */
    public function actionEditPassword()
    {
        // 修改密码处理
        if (IS_POST) {
            $old_password = I('old_password', null);
            $new_password = I('userpassword2', '');
            $user_id = I('uid', $this->user_id);
            $code = I('code', ''); // 邮件code
            $mobile = I('mobile', ''); // 手机号
            if (strlen($new_password) < 6) {
                // show_message("密码不能小于6位");
                show_message(L('log_pwd_six'));
            }
            $user_info = $this->users->get_profile_by_id($user_id); // 论坛记录
            // 短信找回，邮件找回，问题找回，登录修改密码
            if ((!empty($mobile) && base64_encode($user_info['mobiles']) == $mobile) || ($user_info && (!empty($code) && md5($user_info['user_id'] . C('hash_code') . $user_info['reg_time']) == $code)) || ($_SESSION['user_id'] > 0 && $_SESSION['user_id'] == $user_id && $this->load->user->check_user($_SESSION['user_name'], $old_password))) {
                if ($this->load->user->edit_user([
                    'username' => ((empty($code) && empty($mobile) && empty($question)) ? $_SESSION['user_name'] : $user_info['user_name']),
                    'old_password' => $old_password,
                    'password' => $new_password
                ], empty($code) ? 0 : 1)
                ) {
                    $data['ec_salt'] = 0;
                    $where['user_id'] = $user_id;
                    $this->db->table('users')
                        ->data($data)
                        ->where($where)
                        ->save();
                    $this->users->logout();
                    show_message(L('edit_password_success'), L('relogin_lnk'), url('login'), 'info');
                } else {
                    show_message(L('edit_password_failure'), L('back_page_up'), '', 'info');
                }
            } else {
                show_message(L('edit_password_failure'), L('back_page_up'), '', 'info');
            }
        }
        // 显示修改密码页面
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
            $this->assign('title', L('edit_password'));
            // 判断登录方式
            if ($this->is_third_user($_SESSION['user_id'])) {
                $this->assign('is_third', 1);
            }
            $this->assign('page_title', L('edit_password'));
            $this->display();
        } else {
            $this->redirect('login', ['referer' => urlencode(url($this->action))]);
        }
    }


    /**
     * 退出
     */
    public function actionLogout()
    {
        if ((!isset($this->back_act) || empty($this->back_act)) && isset($_SERVER['HTTP_REFERER'])) {
            $this->back_act = stripos($_SERVER['HTTP_REFERER'], 'profile') ? url('user/index/index') : $_SERVER['HTTP_REFERER'];
        } else {
            $this->back_act = url('user/login/index');
        }
        $this->users->logout();
        show_message(L('logout'), [L('back_up_page'), L('back_home_lnk')], [$this->back_act, url('/')], 'success');
    }

    /**
     * 清空浏览历史
     */
    public function clear_history()
    {
        // ajax请求
        if (IS_AJAX) {
            cookie('ECS[history]', '');
            echo json_encode(['status' => 1]);
        } else {
            echo json_encode(['status' => 0]);
        }
    }


    /**
     * 用户注册
     */
    public function actionRegister()
    {
        if (IS_POST) {
            $back_act = isset($_POST['back_act']) ? trim($_POST['back_act']) : url('user/index/index');
            $passport_js = L("passport_js");
            if (I('enabled_sms') == 1) {
                //$username = isset($_POST['username']) ? trim($_POST['username']) : '';
                $username = isset($_POST['mobile']) ? trim($_POST['mobile']) : '';
                $mobile = isset($_POST['mobile']) ? trim($_POST['mobile']) : '';
                $password = isset($_POST['smspassword']) ? trim($_POST['smspassword']) : '';
                $sms_code = isset($_POST['mobile_code']) ? trim($_POST['mobile_code']) : '';
                //扩展
                $extend_field7 = isset($_POST['extend_field7']) ? trim($_POST['extend_field7']) : '';
                $extend_field8 = isset($_POST['extend_field8']) ? intval($_POST['extend_field8']) : '';

                if ($mobile != $_SESSION['sms_mobile'] or $sms_code != $_SESSION['sms_mobile_code']) {
                    exit(json_encode(['status' => 'n', 'info' => L('log_mobile_verify_error')]));
                }
                if (is_mobile($mobile) == false) {
                    exit(json_encode(['status' => 'n', 'info' => '手机号码格式错误']));
                }
                // 验证手机号格式
                if (is_mobile($mobile) == false) {
                    exit(json_encode(['status' => 'n', 'info' => '手机号码格式错误']));
                }

                if (!$extend_field7) {
                    exit(json_encode(['status' => 'n', 'info' => '请选择公司类型']));
                }

                if (!$extend_field8) {
                    exit(json_encode(['status' => 'n', 'info' => '请选择行业']));
                }

                if (strlen($password) < 6) {
                    exit(json_encode(['status' => 'n', 'info' => $passport_js['password_shorter']]));
                }
                if (strpos($password, ' ') > 0) {
                    exit(json_encode(['status' => 'n', 'info' => L('passwd_balnk')]));
                }
                $email = $username . '@qq.com';
                $other = [
                    'mobile_phone' => $mobile,
                    'nick_name' => $mobile,
                    'extend_field7' => $extend_field7,
                    'extend_field8' => $extend_field8,
                ];
            } elseif (I('enabled_sms') == 2) {
                $username = isset($_POST['username']) ? trim($_POST['username']) : '';
                $email = isset($_POST['email']) ? trim($_POST['email']) : '';
                $password = isset($_POST['password']) ? trim($_POST['password']) : '';
                $repassword = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';

                if (new_strlen($username) == false) {
                    exit(json_encode(['status' => 'n', 'info' => $passport_js['msg_un_format']]));
                }
                if (new_strlen($username) < 4) {
                    exit(json_encode(['status' => 'n', 'info' => $passport_js['username_shorter']]));
                }
                if (new_strlen($username) > 15) {
                    exit(json_encode(['status' => 'n', 'info' => $passport_js['username_longest']]));
                }
                if (!is_email($email)) {
                    exit(json_encode(['status' => 'n', 'info' => $passport_js['email_invalid']]));
                }
                if (strlen($password) < 6) {
                    exit(json_encode(['status' => 'n', 'info' => $passport_js['password_shorter']]));
                }
                if (strpos($password, ' ') > 0) {
                    exit(json_encode(['status' => 'n', 'info' => L('passwd_balnk')]));
                }
                if ($password != $repassword) {
                    exit(json_encode(['status' => 'n', 'info' => L('both_password_error')]));
                }

                /* 验证码验证 */
                if ((intval(C('shop.captcha')) & CAPTCHA_REGISTER) && gd_version() > 0) {
                    if (empty($_POST['captcha'])) {
                        exit(json_encode(['status' => 'n', 'info' => L('invalid_captcha')]));
                    }
                    $validator = new Verify();
                    if (!$validator->check($_POST['captcha'])) {
                        exit(json_encode(['status' => 'n', 'info' => L('invalid_captcha')]));
                    }
                }

                $other = ['nick_name' => $username];
            }

            if (register($username, $password, $email, $other) !== false) {
                /*把新注册用户的扩展信息插入数据库*/
                $sql = 'SELECT id FROM ' . $GLOBALS['ecs']->table('reg_fields') . ' WHERE type = 0 AND display = 1 ORDER BY dis_order, id';   //读出所有自定义扩展字段的id
                $fields_arr = $GLOBALS['db']->getAll($sql);

                $extend_field_str = '';    //生成扩展字段的内容字符串
                foreach ($fields_arr AS $val)
                {
                    $extend_field_index = 'extend_field' . $val['id'];
                    if(!empty($_POST[$extend_field_index]))
                    {
                        $temp_field_content = strlen($_POST[$extend_field_index]) > 100 ? mb_substr($_POST[$extend_field_index], 0, 99) : $_POST[$extend_field_index];
                        $extend_field_str .= " ('" . $_SESSION['user_id'] . "', '" . $val['id'] . "', '" . compile_str($temp_field_content) . "'),";
                    }
                }
                $extend_field_str = substr($extend_field_str, 0, -1);

                if ($extend_field_str)      //插入注册扩展数据
                {
                    $sql = 'INSERT INTO '.  $GLOBALS['ecs']->table('reg_extend_info') . ' (`user_id`, `reg_field_id`, `content`) VALUES' . $extend_field_str;
                    $GLOBALS['db']->query($sql);
                }

                //注册成功给平台发送短信
                /* 如果需要，发短信 */
                $sms_shop_mobile = $GLOBALS['_CFG']['sms_shop_mobile']; //手机

                $smsParams = array(
                    'username' => $username,
                    'mobile_phone' => $sms_shop_mobile,
                    'addtime' => date('Y-m-d H:i:s', gmtime()),
                    'reason' => ''
                );
                $send_result = send_sms($sms_shop_mobile, 'users_register', $smsParams);
//                if ($GLOBALS['_CFG']['sms_type'] == 0) {
//                    $sms = huyi_sms($smsParams, 'users_register');
//
//                } elseif ($GLOBALS['_CFG']['sms_type'] >=1) {
//                    $result = sms_ali($smsParams, 'users_register'); //阿里大鱼短信变量传值，发送时机传值
//
//                    if ($result) {
//                        $resp = $GLOBALS['ecs']->ali_yu($result);
//                    } else {
//                        sys_msg('阿里大鱼短信配置异常', 1);
//                    }
//                }

                /* 判断是否需要自动发送注册邮件 */
                if (C('member_email_validate') && C('send_verify_email')) {
                    send_regiter_hash($_SESSION['user_id']);
                }
                exit(json_encode(['status' => 'y', 'info' => sprintf(L('register_success'), $username), 'url' => $back_act]));
            } else {
                $ec_error = $GLOBALS['err']->last_message();
                exit(json_encode(['status' => 'n', 'info' => $ec_error[0]]));
            }
        }
        // 显示
        $back_act = input('back_act', '', 'urldecode');
        if (empty($back_act)) {
            if (empty($back_act) && isset($GLOBALS['_SERVER']['HTTP_REFERER'])) {
                $back_act = strpos($GLOBALS['_SERVER']['HTTP_REFERER'], url('user/index/index')) ? url('user/index/index') : $GLOBALS['_SERVER']['HTTP_REFERER'];
            } else {
                $back_act = url('user/index/index');
            }
        }
        //来源是退出地址时 默认会员中心
        $back_act = strpos($back_act, url('user/login/logout')) ? url('user/index/index') : $back_act;

        /* 验证码相关设置 */
        if ((intval(C('shop.captcha')) & CAPTCHA_REGISTER) && gd_version() > 0) {
            $this->assign('enabled_captcha', 1);
            $this->assign('rand', mt_rand());
        }

        /* 取出注册扩展字段 */
        $sql = 'SELECT * FROM ' .  $GLOBALS['ecs']->table('reg_fields') . ' WHERE type < 2 AND display = 1 ORDER BY dis_order, id';
        $extend_info_list =  $GLOBALS['db']->getAll($sql);
        $this->assign('extend_info_list', $extend_info_list);

        $parent_id = 0;
        $sql = "select cat_id, cat_name from {pre}category where parent_id = '$parent_id'";
        $category = $this->db->getAll($sql);
        $this->assign('category', $category);

        $this->assign('flag', 'register');
        $this->assign('back_act', $back_act);
        $this->assign('page_title', L('registered_user'));
        $this->assign('show', C('shop.sms_signin'));
        $this->display();
    }

    /**
     * 验证验证码
     */
    public function actionCheckcode()
    {
        if (IS_AJAX) {
            $verify = new Verify();
            $code = I('code');
            $code = $verify->check($code);
            if ($code == true) {
                $code = 1;
                echo json_encode($code);
            } else {
                $code = 0;
                echo json_encode($code);
            }
        }
    }

    /**
     * 生成验证码
     */
    public function actionVerify()
    {
        $verify = new Verify();
        $this->assign('code', $verify->entry());
    }

    /**
     * 验证是否登录
     */
    public function actionchecklogin()
    {
        if (!$this->user_id) {
            $back_act = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : __HOST__ . $_SERVER['REQUEST_URI'];
            $this->redirect('user/login/index', ['back_act' => urlencode($back_act)]);
        }
    }
}
