<?php

namespace App\Modules\User\Controllers;

use Think\Verify;
use App\Extensions\Form;
use App\Modules\Base\Controllers\FrontendController;

class ProfileController extends FrontendController
{
    public $user_id;

    public $email;

    public $mobile;

    public $sex;

    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct();
        L(require(LANG_PATH . C('shop.lang') . '/user.php'));
        $file = [
            'passport',
            'clips'
        ];
        $this->load_helper($file);
        // 属性赋值
        $this->user_id = $_SESSION['user_id'];
        // 验证登录
        $this->actionchecklogin();
        $this->assign('lang', L());
    }

    /**
     * 频道页
     */
    public function actionIndex()
    {
        $this->parameter();
        $sql = "SELECT user_id,user_name,sex FROM {pre}users WHERE user_id = " . $this->user_id;
        $user_info = $this->db->getRow($sql);
        // 查询是否实名认证
        $user_real = dao('users_real')->where(['user_id' => $this->user_id])->count();
        $this->assign("user_real", $user_real);
        $this->assign("user_info", $user_info);
        $this->assign("user_name", $user_info['user_name']);
        $this->assign("user_sex", $user_info['sex']);
        $this->assign("page_title", L('profile'));
        $this->display();
    }

    /**
     * 上传头像
     */
    public function actionChangeHeader()
    {
        $result = $this->upload('data/images_user', false, 2);
        $imagePath = '';
        if ($result['error'] <= 0) {
            $imagePath = $result['url']['img']['url'];

            //存入数据库
            $sql = "UPDATE {pre}users SET user_picture = '" . $imagePath . "' WHERE user_id = " . $this->user_id;
            $update = $this->db->query($sql);

            if (!$update) {
                $data = ['error' => 0, 'msg' => '头像替换失败'];
            } else {
                $sql = "SELECT user_picture FROM {pre}users WHERE user_id = " . $this->user_id;
                $users = $this->db->getRow($sql);
                $data = ['error' => 0, 'msg' => '头像替换成功', 'path' => $imagePath];
            }
        } else {
            $data = ['error' => 1, 'msg' => '头像替换失败'];
        }

        $this->ajaxReturn($data);
    }

    // 异步修改个人资料
    public function actionEditProfile()
    {
        $this->parameter();
        if (IS_POST) {
            $data = [];

            $sex = I('sex');
            $nick_name = I('nick_name');
            $birthday = I('birthday');

            if ($sex) {
                $data = ['sex' => $sex];
            }
            if ($nick_name) {
                $data = ['nick_name' => $nick_name];
            }
            if ($birthday) {
                $data = ['birthday' => $birthday];
            }
            dao('users')->data($data)->where(['user_id' => $this->user_id])->save();

            $info = get_user_default($this->user_id);
            echo json_encode($info);
            exit();
        }
    }

    /**
     * 修改手机
     */
    public function actionUserEditMobile()
    {
        $this->parameter();
        $sql = "SELECT user_id,user_name,mobile_phone FROM {pre}users WHERE user_id = " . $this->user_id;
        $user_info = $this->db->getRow($sql);
        // 验证提交
        if (IS_POST) {
            $mobile = I('mobile');
            $sms_code = I('sms_code');
            if (empty($mobile)) {
                show_message(L('msg_input_mobile'), '返回上一页', '', 'error');
            }
            // 验证手机号格式
            if (is_mobile($mobile) == false) {
                show_message(L('msg_mobile_format_error'), '返回上一页', '', 'error');
            }
            if (C('shop.sms_signin') == 1) {
                // 验证手机号、短信验证码
                if ($mobile != $_SESSION['sms_mobile']) {
                    show_message('手机号不正确', '返回上一页', '', 'error');
                }
                if ($sms_code != $_SESSION['sms_mobile_code']) {
                    show_message(L('msg_auth_code_error'), '返回上一页', '', 'error');
                }
            }

            $sql = "SELECT user_id FROM {pre}users WHERE mobile_phone='" . $mobile . "'AND user_id !=" . $_SESSION['user_id'];
            $mobile_phone = $this->db->getOne($sql);

            $sql = "SELECT user_id FROM {pre}users WHERE user_name='" . $mobile . "'AND user_id !=" . $_SESSION['user_id'];
            $user_name = $this->db->getOne($sql);
            if (!empty($mobile_phone) || !empty($user_name)) {
                show_message(L('msg_mobile_exist'), '返回上一页', '', 'error');
            }

            if (!empty($user_info)) {
                $sql = "UPDATE {pre}users SET mobile_phone = '" . $mobile . "' WHERE user_id = '" . $this->user_id . "'";
                $this->db->query($sql);
                unset($_SESSION['sms_mobile']);
                unset($_SESSION['sms_mobile_code']);
                show_message('修改成功', '', url('user/profile/accountsafe'), 'success');
            }
        }

        $this->assign("mobile", $user_info['mobile_phone']);
        $this->assign("sms_signin", C('shop.sms_signin'));
        $this->assign("page_title", L('edit_mobile'));
        $this->display();
    }

    /**
     * 修改验证邮箱
     */
    public function actionUserEditEmail()
    {
        $this->parameter();
        if (IS_POST) {
            $email = I('email');
            $type = I('type', '', 'trim');

            if (empty($email)) {
                show_message('邮箱不能为空', '返回上一页', '', 'error');
            }
            // 验证邮箱格式
            if (is_email($email) == false) {
                show_message('邮箱格式不正确，请重新填写', '返回上一页', '', 'error');
            }

            // 开启短信验证手机
            if (C('shop.sms_signin') == 1 && $type == 'phone') {
                $sms_code = I('sms_code');
                $mobile = dao('users')->where(['user_id' => $this->user_id])->getField('mobile_phone');
                if (empty($mobile)) {
                    show_message('请先填写并验证手机号', '返回上一页', url('user/profile/user_edit_mobile'), 'error');
                }
                // 验证发送的手机号、短信验证码
                if ($mobile != $_SESSION['sms_mobile']) {
                    show_message('手机号不正确', '返回上一页', '', 'error');
                }
                if ($sms_code != $_SESSION['sms_mobile_code']) {
                    show_message(L('msg_auth_code_error'), '返回上一页', '', 'error');
                }
                unset($_SESSION['sms_mobile']);
                unset($_SESSION['sms_mobile_code']);
            } else {
                $sms_email_code = I('sms_email_code');
                if (empty($sms_email_code)) {
                    show_message('邮箱验证码不能为空', '返回上一页', '', 'error');
                }
                // 验证发送的邮箱、邮箱验证码
                if ($email != $_SESSION['sms_email']) {
                    show_message('发送邮箱不正确', '返回上一页', '', 'error');
                }
                if ($sms_email_code != $_SESSION['sms_email_code']) {
                    show_message('邮箱验证码填写不正确', '返回上一页', '', 'error');
                }
                unset($_SESSION['sms_email']);
                unset($_SESSION['sms_email_code']);
            }

            // 查询邮箱是否被他人使用
            $sql = "SELECT user_id FROM {pre}users WHERE email = '" . $email . "' AND user_id !=" . $this->user_id;
            $other_email = $this->db->getOne($sql);

            $sql = "SELECT user_id FROM {pre}users WHERE user_name = '" . $email . "' AND user_id !=" . $this->user_id;
            $user_email = $this->db->getOne($sql);

            if (!empty($user_email) || !empty($other_email)) {
                show_message(L('msg_email_registered'), '返回上一页', '', 'error');
            }
            // 修改并验证通过
            if (!empty($email)) {
                $data = ['email' => $email, 'is_validated' => 1];
                dao('users')->data($data)->where(['user_id' => $this->user_id])->save();
                show_message('修改成功', '', url('user/profile/accountsafe'), 'success');
            }
        }
        // 验证类型 默认邮箱
        $type = I('type', 'email', 'trim');

        $sql = "SELECT user_id, email, mobile_phone FROM {pre}users WHERE user_id = " . $this->user_id;
        $user_info = $this->db->getRow($sql);
        $_SESSION['hash_code'] = md5($user_info['email'] . rand(1000, 9999));

        if ($type == 'phone') {
            $this->assign('change_email', 1); // 可切换使用邮箱方式验证
        }
        $this->assign('type', $type);
        $this->assign('hash_code', $_SESSION['hash_code']);
        $this->assign('user_info', $user_info);
        $this->assign("sms_signin", C('shop.sms_signin'));
        $this->assign("page_title", L('edit_email'));
        $this->display();
    }

    /**
     * 发送验证码, 短信或邮件
     */
    public function actionSendSms()
    {
        if (IS_POST) {
            $result = ['error' => 0, 'content' => ''];
            $type = I('post.type');

            // 验证hash值
            $hash_code = I('post.hash_code');
            if ($hash_code != $_SESSION['hash_code']) {
                $result['error'] = 1;
                $result['content'] = '发送有误';
                exit(json_encode($result));
            }

            if ($type == 'email') {
                $email = I('post.email');
                // 生成code
                $code = rand(1000, 9999);

                $sql = "SELECT user_id, user_name, email FROM {pre}users WHERE user_id = " . $this->user_id;
                $user_info = $this->db->getRow($sql);

                if (!empty($user_info)) {

                    // 验证邮箱格式
                    if (is_email($email) == false) {
                        $result['error'] = 1;
                        $result['content'] = '邮箱格式错误';
                        exit(json_encode($result));
                    }
                    if (empty(C('shop.smtp_user'))) {
                        $result['error'] = 1;
                        $result['content'] = '邮件服务器未配置';
                        exit(json_encode($result));
                    }

                    // 发送邮件
                    if (send_pwd_email($user_info['user_id'], $user_info['user_name'], $email, $code)) {
                        $_SESSION['sms_email'] = $email;
                        $_SESSION['sms_email_code'] = $code;

                        $result['error'] = 0;
                        $result['content'] = L('send_success');
                        exit(json_encode($result));
                    } else {
                        // 发送邮件出错
                        $result['error'] = 1;
                        $result['content'] = L('fail_send_password');
                        exit(json_encode($result));
                    }
                } else {
                    // 用户不存在
                    $result['error'] = 1;
                    $result['content'] = '用户不存在';
                    exit(json_encode($result));
                }
            } elseif ($type == 'phone') {
                $mobile = I('post.mobile');
                $code = rand(1000, 9999);//生成验证码

                // 验证手机号格式
                if (is_mobile($mobile) == false) {
                    $result['error'] = 1;
                    $result['content'] = '手机号码格式错误';
                    exit(json_encode($result));
                }
                // 组装数据
                $message = [
                    'code' => $code
                ];
                //发送短信
                if (send_sms($mobile, 'sms_code', $message) === true) {
                    $_SESSION['sms_mobile'] = $mobile;
                    $_SESSION['sms_mobile_code'] = $code;

                    $result['error'] = 0;
                    $result['content'] = '短信发送成功';
                    exit(json_encode($result));
                } else {
                    $result['error'] = 1;
                    $result['content'] = '短信发送失败';
                    exit(json_encode($result));
                }
            } else {
                $result['error'] = 1;
                $result['content'] = '操作有误';
                exit(json_encode($result));
            }
        }
    }

    private function parameter()
    {
        $this->user_id = $_SESSION['user_id'];
        if (empty($this->user_id)) {
            ecs_header("Location: ./\n");
        }
        $this->mobile = I('mobile');
        $this->sex = I('sex');
        $this->email = I('email');
        $this->postbox = I('postbox');
        $this->assign('info', get_user_default($this->user_id)); // 自定义导航栏
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

    /**
     * 账户安全
     * @return
     */
    public function actionAccountSafe()
    {
        $this->parameter();
        // 是否验证邮箱
        $is_validated = dao('users')->where(['user_id' => $this->user_id])->getField('is_validated');
        $this->assign("is_validated", $is_validated);

        // 是否启用支付密码
        $users_paypwd = dao('users_paypwd')->where(['user_id' => $this->user_id])->count();
        $this->assign("users_paypwd", $users_paypwd);

        // 是否是授权登录用户 如果是 则不显示修改密码
        $is_connect_user = is_connect_user($this->user_id);
        $this->assign('is_connect_user', $is_connect_user);

        $this->assign('page_title', '账户安全');
        $this->display();
    }

    /**
     * 修改启用支付密码
     */
    public function actionUserEditPaypwd()
    {
        // 验证提交
        if (IS_POST) {
            $paypwd_id = I('paypwd_id', 0, 'intval');
            $pay_paypwd = I('pay_paypwd', '', 'trim');

            $pay_online = I('pay_online', 0, 'intval');
            $user_surplus = I('user_surplus', 0, 'intval');
            $user_point = I('user_point', 0, 'intval');
            $baitiao = I('baitiao', 0, 'intval');
            $gift_card = I('gift_card', 0, 'intval');

            $type = I('type', '', 'trim');

            // 开启短信验证手机
            if (C('shop.sms_signin') == 1 && $type == 'phone') {
                $sms_code = I('sms_code');
                $mobile = dao('users')->where(['user_id' => $this->user_id])->getField('mobile_phone');
                if (empty($mobile)) {
                    show_message('请先填写并验证手机号', '返回上一页', url('user/profile/user_edit_mobile'), 'error');
                }
                // 验证发送的手机号、短信验证码
                if ($mobile != $_SESSION['sms_mobile']) {
                    show_message('手机号不正确', '返回上一页', '', 'error');
                }
                if ($sms_code != $_SESSION['sms_mobile_code']) {
                    show_message(L('msg_auth_code_error'), '返回上一页', '', 'error');
                }
                unset($_SESSION['sms_mobile']);
                unset($_SESSION['sms_mobile_code']);
            } else {
                $sms_email_code = I('sms_email_code');
                // 验证邮箱
                $users = dao('users')->field('email, is_validated')->where(['user_id' => $this->user_id])->find();
                if (empty($users['email'])) {
                    show_message('请先填写并验证邮箱', '返回上一页', url('user/profile/user_edit_email'), 'error');
                }
                // 验证发送的邮箱、邮箱验证码
                if ($users['email'] != $_SESSION['sms_email']) {
                    show_message('发送邮箱不正确', '返回上一页', '', 'error');
                }
                if ($sms_email_code != $_SESSION['sms_email_code']) {
                    show_message('邮箱验证码填写不正确', '返回上一页', '', 'error');
                }
                unset($_SESSION['sms_email']);
                unset($_SESSION['sms_email_code']);
            }

            if (empty($pay_paypwd)) {
                show_message('支付密码不能为空', '返回上一页', '', 'error');
            }

            $data = [
                'user_id' => $this->user_id,
                'pay_online' => $pay_online,
                'user_surplus' => $user_surplus,
                'user_point' => $user_point,
                'baitiao' => $baitiao,
                'gift_card' => $gift_card
            ];
            // 加密
            $ec_salt = rand(1, 9999);
            $new_password = md5(md5($pay_paypwd) . $ec_salt);

            $data['pay_password'] = $new_password;
            $data['ec_salt'] = $ec_salt;
            if ($paypwd_id) {
                // 更新支付密码，验证原支付密码
                $old_pay_paypwd = I('old_pay_paypwd', '', 'trim');

                $res = dao('users_paypwd')->field('pay_password,ec_salt')->where(['paypwd_id' => $paypwd_id])->find();
                $new_password = md5(md5($old_pay_paypwd) . $res['ec_salt']);
                if ($new_password != $res['pay_password']) {
                    show_message('原支付密码不正确', '返回上一页', '', 'error');
                }
                dao('users_paypwd')->data($data)->where(['paypwd_id' => $paypwd_id])->save();
                show_message('修改成功', '', url('user/profile/accountsafe'), 'success');
            } else {
                // 启用支付密码
                dao('users_paypwd')->data($data)->add();
                show_message('启用成功', '', url('user/profile/accountsafe'), 'success');
            }
        }

        // 判断是否启用支付密码
        $users_paypwd = dao('users_paypwd')->field('paypwd_id, pay_online, user_surplus,user_point')->where(['user_id' => $this->user_id])->find();
        if (!empty($users_paypwd)) {
            $page_title = '修改支付密码';
        } else {
            $page_title = '启用支付密码';
        }
        $users_paypwd['user_surplus'] = !empty($users_paypwd['user_surplus']) ? $users_paypwd['user_surplus'] : 1; // 默认选择使用积分
        $this->assign("users_paypwd", $users_paypwd);

        // 验证类型 默认邮箱
        $type = I('type', 'email', 'trim');
        if ($type == 'phone') {
            $this->assign('change_email', 1); // 可切换使用邮箱方式验证
        }
        $this->assign('type', $type);

        $user_info = dao('users')->field('mobile_phone, email, is_validated')->where(['user_id' => $this->user_id])->find();
        $this->assign("user_info", $user_info);
        $_SESSION['hash_code'] = md5($user_info['email'] . rand(1000, 9999));
        $this->assign('hash_code', $_SESSION['hash_code']);
        $this->assign("sms_signin", C('shop.sms_signin'));
        $this->assign("page_title", $page_title);
        $this->display();
    }

    /**
     * 实名认证页面
     */
    public function actionRealname()
    {
        // 提交数据
        if (IS_POST) {
            $step = I('step', '', 'trim');
            $real_id = I('real_id', 0, 'intval');
            // 获取实名信息
            $real_user = I('post.data', '', 'trim');
            $real_user['user_id'] = $this->user_id;
            $real_user['bank_mobile'] = I('mobile_phone', '');

            if (empty($real_user['real_name'])) {
                exit(json_encode(['status' => 1, 'msg' => '真实姓名不可为空']));
            }
            if (empty($real_user['self_num'])) {
                exit(json_encode(['status' => 1, 'msg' => '身份证号不可为空']));
            }
            if (empty($real_user['bank_name'])) {
                exit(json_encode(['status' => 1, 'msg' => '银行不可为空']));
            }
            if (empty($real_user['bank_card'])) {
                exit(json_encode(['status' => 1, 'msg' => '银行卡号不可为空']));
            }
            if (empty($real_user['bank_mobile'])) {
                exit(json_encode(['status' => 1, 'msg' => '手机号不可为空']));
            }
            // 验证格式
            $form = new Form();
            if (!$form->isMobile($real_user['bank_mobile'], 1)) {
                exit(json_encode(['status' => 1, 'msg' => '手机号码格式不正确']));
            }
            // 编辑包含 * 数据时 跳过不验证
            if (strpos($real_user['self_num'], '*') == false) {
                if (!$form->isCreditNo($real_user['self_num'], 1)) {
                    exit(json_encode(['status' => 1, 'msg' => '身份证号码格式不正确']));
                }
            }
            if (strpos($real_user['bank_card'], '*') == false) {
                if (is_numeric($real_user['bank_card']) == false) {
                    exit(json_encode(['status' => 1, 'msg' => '银行卡号格式不正确']));
                }
            }

            // 验证手机号是否为本人 验证短信
            $mobile_code = I('mobile_code', '');
            if (!empty($real_user['bank_mobile'])) {
                if (!empty($mobile_code)) {
                    if ($real_user['bank_mobile'] != $_SESSION['sms_mobile'] || $mobile_code != $_SESSION['sms_mobile_code']) {
                        exit(json_encode(['status' => 1, 'msg' => '手机号或短信验证码错误']));
                    }
                } else {
                    exit(json_encode(['status' => 1, 'msg' => '短信验证码不可为空']));
                }
            }
            $count_user = dao('users_real')->where(['user_id' => $this->user_id])->count();
            if ($count_user == 0 && $step == 'first') {
                if (empty($_FILES['front_of_id_card']['name']) || empty($_FILES['reverse_of_id_card']['name'])) {
                    exit(json_encode(['status' => 1, 'msg' => '身份证正反面不可为空']));
                }
            }
            /*身份证上传*/
            if (!empty($_FILES['front_of_id_card']['name']) || !empty($_FILES['reverse_of_id_card']['name'])) {
                $result = $this->upload('data/attached/iduser_image', false);
                if ($result['error'] > 0) {
                    exit(json_encode(['status' => 1, 'msg' => $result['message']]));
                }
                if (!empty($_FILES['front_of_id_card']['name'])) {
                    $real_user['front_of_id_card'] = $result['url']['front_of_id_card']['url'];
                }
                if (!empty($_FILES['reverse_of_id_card']['name'])) {
                    $real_user['reverse_of_id_card'] = $result['url']['reverse_of_id_card']['url'];
                }
            }
            //有新的上传图片，删除原图
            $row = dao('users_real')->where(['user_id' => $this->user_id])->find();
            if ($count_user == 1 && $step == 'edit') {
                $file = dirname(ROOT_PATH);
                if ($real_user['front_of_id_card'] != '' && $row['front_of_id_card']) {
                    @unlink($file . '/' . $row['front_of_id_card']);
                }
                if ($real_user['reverse_of_id_card'] != '' && $row['reverse_of_id_card']) {
                    @unlink($file . '/' . $row['reverse_of_id_card']);
                }
                $real_user['review_status']=0;
            }
            if ($real_id && $count_user == 1) {
                // 编辑包含 * 数据时 跳过不保存数据库
                if (strpos($real_user['self_num'], '*') == true) {
                    unset($real_user['self_num']);
                }
                if (strpos($real_user['bank_card'], '*') == true) {
                    unset($real_user['bank_card']);
                }

                dao('users_real')->data($real_user)->where(['real_id' => $real_id, 'user_id' => $this->user_id])->save();
                exit(json_encode(['status' => 0, 'msg' => '修改成功']));
            } elseif ($count_user == 0 && $step == 'first') {
                $real_user['add_time'] = gmtime();
                dao('users_real')->data($real_user)->where(['user_id' => $this->user_id])->add();
                exit(json_encode(['status' => 0, 'msg' => '实名认证申请成功，请等待管理员审核！']));
            } else {
                exit(json_encode(['status' => 1, 'msg' => '您已经实名认证过了，不需要重复认证']));
            }
        }
        // 显示实名认证信息
        $step = I('step', '', 'trim'); // 实名验证阶段 first 申请  edit 修改

        $count_user = dao('users_real')->where(['user_id' => $this->user_id])->count();
        if ($count_user == 1 && $step != 'edit') {
            $this->redirect('user/profile/realnameok');
        }
        $step = $count_user == 0 ? 'first' : 'edit';
        $real_user = dao('users_real')->where(['user_id' => $this->user_id])->find();

        if (!empty($real_user)) {
            // 默认用会员中心绑定手机号
            if (empty($real_user['bank_mobile'])) {
                $mobile_phone = dao('users')->where(['user_id' => $this->user_id])->getField('mobile_phone');
                if ($mobile_phone) {
                    $real_user['bank_mobile'] = $mobile_phone;
                }
            }
            // 身份证 银行卡号 用 * 替换字符显示
            $real_user['self_num'] = string_to_star($real_user['self_num'], 4);
            $real_user['bank_card'] = string_to_star($real_user['bank_card'], 4);
            $real_user['front_of_id_card'] = get_image_path($real_user['front_of_id_card']);//身份证正面
            $real_user['reverse_of_id_card'] = get_image_path($real_user['reverse_of_id_card']);//身份证反面
        }
        $this->assign('real_user', $real_user);
        $this->assign('step', $step);
        $this->assign('page_title', '实名认证');
        $this->display();
    }

    /**
     * 实名短信验证码发送
     */
    public function actionRealnameSend()
    {
        if (IS_AJAX) {
            $_SESSION['sms_mobile'] = I('mobile');
            $_SESSION['sms_mobile_code'] = rand(1000, 9999);
            // 验证手机号码格式
            $form = new Form();
            if (!$form->isMobile($_SESSION['sms_mobile'], 1)) {
                $result['error'] = 1;
                $result['content'] = '手机号码格式不正确';
                exit(json_encode($result));
            }
            // 组装数据
            $message = [
                'code' => $_SESSION['sms_mobile_code']
            ];
            $send_result = send_sms($_SESSION['sms_mobile'], 'sms_code', $message);
            if ($send_result === true) {
                $result['error'] = 0;
                $result['content'] = '发送短信成功';
            } else {
                $result['error'] = 1;
                $result['content'] = '发送短信失败';
            }
            exit(json_encode($result));
        }
    }

    /**
     * 实名认证成功页面
     */
    public function actionRealnameOk()
    {
        $real_user = dao('users_real')->where(['user_id' => $this->user_id])->find();
        if (!$real_user) {
            $this->redirect('user/profile/realname');
        }
        $real_user['validate_time'] = local_date('Y-m-d H:i:s', $real_user['add_time']); // 认证时间
        // 身份证 银行卡号 用*替换字符显示
        $real_user['self_num'] = string_to_star($real_user['self_num'], 4);
        $real_user['bank_card'] = string_to_star($real_user['bank_card'], 4);
        $real_user['front_of_id_card'] = get_image_path($real_user['front_of_id_card']);//身份证正面
        $real_user['reverse_of_id_card'] = get_image_path($real_user['reverse_of_id_card']);//身份证反面
        $this->assign('real_user', $real_user);
        $this->assign('page_title', '实名认证信息');
        $this->display();
    }

    /**
     * 授权管理
     * @return
     */
    public function actionAccountBind()
    {
        if (IS_AJAX) {
            $json_result = ['error' => 0, 'msg' => '', 'url' => ''];

            $id = I('id', 0, 'intval');
            if ($id) {
                // 查询是否绑定 并且需填写验证手机号
                $sql = "SELECT cu.user_id, cu.open_id, u.mobile_phone FROM {pre}connect_user cu, {pre}users u WHERE u.user_id = cu.user_id AND u.user_id = '" . $this->user_id . "' and cu.id = '" . $id . "' ";
                $users = $this->db->getRow($sql);

                if (!empty($users)) {
                    if (!empty($users['mobile_phone'])) {
                        $connect_where = ['user_id' => $this->user_id, 'open_id' => $users['open_id']];
                        dao('connect_user')->where($connect_where)->delete();
                        // 兼容PC
                        $auth_where = ['user_id' => $this->user_id, 'identifier' => $users['open_id']];
                        dao('users_auth')->where($auth_where)->delete();

                        $json_result = ['error' => 0, 'msg' => '解绑成功'];
                        exit(json_encode($json_result));
                    } else {
                        $json_result = ['error' => 1, 'msg' => '请先填写并验证手机号', 'url' => url('user/profile/user_edit_mobile')];
                        exit(json_encode($json_result));
                    }
                } else {
                    $json_result = ['error' => 1, 'msg' => '帐号未绑定'];
                    exit(json_encode($json_result));
                }
            }
        }

        // 查询绑定信息
        $sql = "SELECT cu.id, cu.user_id, cu.connect_code, cu.create_at FROM {pre}connect_user cu, {pre}users u WHERE u.user_id = cu.user_id AND u.user_id = '" . $this->user_id . "' ";
        $connect_user = $GLOBALS['db']->getAll($sql);

        // 显示已经安装的社会化登录插件
        $oauth_list = dao('touch_auth')->field('type, status')->order('sort asc, id asc')->select();

        $list = [];
        foreach ($oauth_list as $key => $vo) {
            $list[$vo['type']]['type'] = $vo['type'];
            $list[$vo['type']]['install'] = $vo['status'];

            if ($vo['type'] == 'wechat' && !is_wechat_browser()) {
                unset($list[$vo['type']]); // 过滤微信登录
            }
        }

        foreach ($connect_user as $key => $value) {
            $type = substr($value['connect_code'], 4);
            $list[$type]['user_id'] = $value['user_id'];
            $list[$type]['id'] = $value['id'];
        }

        $back_url = __HOST__ . $_SERVER['REQUEST_URI'];
        $this->assign('back_url', $back_url);
        $this->assign('user_id', $this->user_id);
        $this->assign('list', $list);
        $this->assign('page_title', '授权管理');
        $this->display();
    }


    /**
     * 账号关联
     * @return
     */
    public function actionAccountRelation()
    {
        // 提交
        if (IS_POST) {
            $username = I('username', '', 'trim'); // 用户名/手机号
            $verify_code = I('verify');  // 图形验证码

            $password = I('password', '', 'trim');

            // 验证
            $form = new Form();
            $verify = new Verify();

            // 数据验证
            if (!$form->isEmpty($username, 1)) {
                $json_result = ['status' => 'n', 'info' => '用户名不能为空'];
                exit(json_encode($json_result));
            }
            if (!$form->isEmpty($password, 1)) {
                $json_result = ['status' => 'n', 'info' => '密码不能为空'];
                exit(json_encode($json_result));
            }

            // 验证手机号并通过手机号查找用户名
            if ($form->isMobile($username, 1)) {
                $condition['mobile_phone'] = $username;
                $condition['user_name'] = $username;
                $condition['_logic'] = 'OR';
                $username = dao('users')->where($condition)->getField('user_name');
            }

            // 校验图片验证码
            if (!$verify->check($verify_code)) {
                $json_result = ['status' => 'n', 'info' => '图片验证码不正确'];
                exit(json_encode($json_result));
            }

            $bind_user_id = $this->users->check_user($username, $password);
            if ($bind_user_id > 0 && !empty($_SESSION['unionid'])) {
                // 查询users用户是否被其他人绑定
                $connect_user_id = dao('connect_user')->where(['user_id' => $bind_user_id, 'connect_code' => 'sns_wechat'])->count();
                if ($connect_user_id == 0 && $bind_user_id != $this->user_id) {
                    // 更新关联表用户ID
                    $res = dao('connect_user')->data(['user_id' => $bind_user_id])->where(['user_id' => $this->user_id, 'connect_code' => 'sns_wechat'])->save();
                    // 重新登录
                    if (!empty($username) && $res) {
                        unset($_SESSION['user_id']);
                        unset($_SESSION['user_name']);

                        $this->users->set_session($username);
                        $this->users->set_cookie($username);
                        update_user_info();
                        recalculate_price();
                    }

                    $json_result = ['status' => 'y', 'info' => '已关联账号' . $username, 'url' => url('user/index/index')];
                    exit(json_encode($json_result));
                } else {
                    $json_result = ['status' => 'n', 'info' => '该账号已被关联！'];
                    exit(json_encode($json_result));
                }
            } else {
                $json_result = ['status' => 'n', 'info' => '账号不存在或密码错误，请重新输入！'];
                exit(json_encode($json_result));
            }
        }

        // 显示
        if (!empty($_SESSION['unionid'])) {
            // 默认账号(主账号) 即首次自动注册分配的账号
            $wechat_id = dao('wechat')->where(['default_wx' => 1, 'status' => 1])->getField('id');
            $main_user_id = dao('wechat_user')->where(['unionid' => $_SESSION['unionid'], 'wechat_id' => $wechat_id])->getField('ect_uid');

            $main_user_info = get_users($main_user_id);
            if (!empty($main_user_info)) {
                $main_user_info['user_name'] = $main_user_info['user_name'] . '(系统默认分配账号)';
                $main_user_info['user_picture'] = dao('wechat_user')->where(['unionid' => $_SESSION['unionid'], 'wechat_id' => $wechat_id])->getField('headimgurl');
            }
            $this->assign('main_user_info', $main_user_info); // 主会员信息

            // 关联会员信息
            $relation_user_info = get_connect_user($_SESSION['unionid']);
            $relation_users = get_users($relation_user_info['user_id']);
            if (!empty($relation_users)) {
                $relation_user_info['user_picture'] = $relation_users['user_picture'];
                $relation_user_info['mobile_phone'] = $relation_users['mobile_phone'];
            }
            $this->assign('relation_user_info', $relation_user_info); // 已关联会员

            // 当前登录会员
            $now_user_info = get_users($this->user_id);

            if (!empty($main_user_info) && !empty($now_user_info) && $main_user_info['user_id'] == $now_user_info['user_id']) {
                $now_user_info['user_name'] = $main_user_info['user_name'];
                $now_user_info['user_picture'] = $main_user_info['user_picture'];
            }
            $this->assign('now_user_info', $now_user_info);

            // 是否已经关联
            if (!empty($main_user_info) && !empty($relation_user_info) && $main_user_info['user_id'] == $relation_user_info['user_id']) {
                $is_relation = 0;
            } elseif (!empty($main_user_info) && !empty($relation_user_info) && $main_user_info['user_id'] != $relation_user_info['user_id']) {
                $is_relation = 1;
            }
            $this->assign('is_relation', $is_relation);

            // 是否可以解除关联
            if (!empty($main_user_info) && !empty($now_user_info) && $main_user_info['user_id'] == $now_user_info['user_id']) {
                $is_remove_relation = 1;
            } else {
                $is_remove_relation = 0;
            }
            $this->assign('is_remove_relation', $is_remove_relation);

            // 是否可以切换登录
            if (!empty($main_user_info) && !empty($relation_user_info) && $main_user_info['user_id'] != $relation_user_info['user_id']) {
                $is_change_login = 1;
            } else {
                $is_change_login = 0;
            }

            if ($is_change_login == 1) {
                // 用关联会员登录 否则用默认账号
                if ($now_user_info && $relation_user_info && $now_user_info['user_id'] != $relation_user_info['user_id']) {
                    $this->assign('change_user_info', $relation_user_info);
                } else {
                    $this->assign('change_user_info', $main_user_info);
                }
            }

            $this->assign('is_change_login', $is_change_login);
        } else {
            $back_url = __HOST__ . $_SERVER['REQUEST_URI'];
            $this->redirect('oauth/index/index', ['type' => 'wechat', 'back_url' => urlencode($back_url)]);
        }

        $this->assign('page_title', '账号关联');
        $this->display();
    }

    /**
     * 解除账号关联
     * @return
     */
    public function actionRemoveRelation()
    {
        // 异步
        if (IS_AJAX) {
            $json_result = ['error' => 0, 'msg' => '', 'url' => ''];

            $relation_user_id = I('relation_user_id', 0, 'intval');

            if (!empty($relation_user_id)) {
                if ($_SESSION['relation_times'] > 1) {
                    $json_result = ['error' => 1, 'msg' => '请不要频繁操作！每次登录只能解除关联一次'];
                    exit(json_encode($json_result));
                }

                $wechat_id = dao('wechat')->where(['default_wx' => 1, 'status' => 1])->getField('id');
                $main_user_id = dao('wechat_user')->where(['unionid' => $_SESSION['unionid'], 'wechat_id' => $wechat_id])->getField('ect_uid');

                $userinfo = dao('users')->field('user_name')->where(['user_id' => $main_user_id])->find();
                if (!empty($userinfo)) {
                    // 更新关联表记录
                    $data = ['user_id' => $main_user_id];
                    dao('connect_user')->data($data)->where(['user_id' => $relation_user_id, 'connect_code' => 'sns_wechat'])->save();

                    unset($_SESSION['user_id']);
                    unset($_SESSION['user_name']);
                    // 重新登录
                    $this->users->set_session($userinfo['user_name']);
                    $this->users->set_cookie($userinfo['user_name']);
                    update_user_info();
                    recalculate_price();
                    $_SESSION['relation_times']++; // 每次登录只能解除关联一次

                    $json_result = ['error' => 0, 'msg' => '解除关联成功', 'url' => url('user/profile/index')];
                    exit(json_encode($json_result));
                } else {
                    $json_result = ['error' => 1, 'msg' => '账号不存在'];
                    exit(json_encode($json_result));
                }
            }
            $json_result = ['error' => 1, 'msg' => '错误'];
            exit(json_encode($json_result));
        }
    }

    /**
     * 切换登录
     * @return
     */
    public function actionChangeLogin()
    {
        // 异步
        if (IS_AJAX) {
            $json_result = ['error' => 0, 'msg' => '', 'url' => ''];

            $change_user_id = I('change_user_id', 0, 'intval');

            if (!empty($change_user_id)) {
                $userinfo = dao('users')->field('user_name')->where(['user_id' => $change_user_id])->find();

                if (!empty($userinfo)) {
                    unset($_SESSION['user_id']);
                    unset($_SESSION['user_name']);
                    // 重新登录
                    $this->users->set_session($userinfo['user_name']);
                    $this->users->set_cookie($userinfo['user_name']);
                    update_user_info();
                    recalculate_price();

                    $json_result = ['error' => 0, 'msg' => '切换登录成功', 'url' => url('user/index/index')];
                    exit(json_encode($json_result));
                } else {
                    $json_result = ['error' => 1, 'msg' => '账号不存在'];
                    exit(json_encode($json_result));
                }
            }
            $json_result = ['error' => 1, 'msg' => '错误'];
            exit(json_encode($json_result));
        }
    }

    //更新缓存
    function actionUpdatesession() {
        unset($_SESSION['company_type']);

        $user_id = $_SESSION['user_id'];
        $sql = "SELECT user_id, password, email, is_purchasers FROM " . $GLOBALS['ecs']->table('users') . " WHERE user_id='$user_id' LIMIT 1";
        $row = $GLOBALS['db']->getRow($sql);
        if ($row) {
            $_SESSION['is_purchasers'] = $row['is_purchasers'];
            if (!$row['is_purchasers']) {
                //不是采购商 查询是否是制造商
                $sql = "SELECT user_id, ru_id, user_name, password, last_login, action_list, last_login,suppliers_id,ec_salt".
                    " FROM " . $GLOBALS['ecs']->table('admin_user') .
                    " WHERE ru_id = ". $_SESSION['user_id'];
                $row2 = $GLOBALS['db']->getRow($sql);
                if ($row2) {
                    //查找到
                    $_SESSION['is_suppliers'] = 1;
                }
            }
        }

        show_message('更新成功', '', url('user/index/index'), 'success');
    }
}
