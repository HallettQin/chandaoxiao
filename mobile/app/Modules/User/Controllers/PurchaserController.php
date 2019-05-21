<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/5
 * Time: 16:38
 */
namespace App\Modules\User\Controllers;

use App\Extensions\Form;
use App\Modules\Base\Controllers\FrontendController;

//零售商类
class PurchaserController extends FrontendController
{
    /**
     * 构造函数
     */
    public function __construct() {
        parent::__construct();

        L(require(LANG_PATH . C('shop.lang') . '/purchaser.php'));
        //检查是否登录
        $this->actionchecklogin();

        $this->user_id = $_SESSION['user_id'];
    }

    public function actionIndex() {
        $this->assign('page_title', L('purchaser_apply'));

        if (isset($_SESSION['is_purchasers']) && $_SESSION['is_purchasers']) {
            //已经是零售商 直接跳转
            $this->redirect('user/index/index');
        }
        $purchasers = dao('purchasers')->where(['user_id' => $this->user_id])->find();
        if ($purchasers && $purchasers['audit_status'] != 1) {
            show_message("您已申请过了",  '个人中心', url('user/index/index'), 'warning');
        }

        //获取完整地址
        if ($purchasers) {
            $address = '';
            if ($purchasers['province']) {
                $res = get_region_name($purchasers['province']);
                $address .= $res['region_name'];
            }
            if ($purchasers['city']) {
                $ress = get_region_name($purchasers['city']);
                $address .= $ress['region_name'];
            }
            if ($purchasers['district']) {
                $resss = get_region_name($purchasers['district']);
                $address .= $resss['region_name'];
            }
            if ($purchasers['street']) {
                $resss = get_region_name($purchasers['street']);
                $address .= $resss['region_name'];
            }
            $purchasers['region'] = $address;
            $purchasers['work_file'] = unserialize($purchasers['work_file']);

            //审核失败充填
            $this->assign('purchasers', $purchasers);
        }

        //获取所有行业
        $categorys = dao('category')->field('cat_id, cat_name')->where(['parent_id' => 0])->select();
        $this->assign('categorys', $categorys);

        $this->display();
    }


    //零售商申请
    public function actionApply() {
        if (IS_POST) {
            $purchasers = dao('purchasers')->where(['user_id' => $this->user_id])->find();
            if ($purchasers && $purchasers['audit_status'] != 1) {
                exit(json_encode(['status' => 1, 'msg' => '您已申请过了']));
            }

            $apply = I('post.data', '', 'trim');

            if (empty($apply['store_name'])) {
                exit(json_encode(['status' => 1, 'msg' => '店铺名不能为空']));
            }

            if (empty($apply['real_name'])) {
                exit(json_encode(['status' => 1, 'msg' => '零售商姓名不可为空']));
            }

            if (empty($apply['province_region_id'])) {
                exit(json_encode(['status' => 1, 'msg' => '请选择地区']));
            }

            if (empty($apply['address'])) {
                exit(json_encode(['status' => 1, 'msg' => '详细地址不可为空']));
            }

            if (empty($apply['store_type'])) {
                exit(json_encode(['status' => 1, 'msg' => '请选择店铺类型']));
            }

            if (empty($apply['store_area'])) {
                exit(json_encode(['status' => 1, 'msg' => '营业面积不可为空']));
            }

            if (empty($apply['store_category'])) {
                exit(json_encode(['status' => 1, 'msg' => '请选择行业']));
            }

            if (empty($apply['gate_file'])) {
                exit(json_encode(['status' => 1, 'msg' => '请上传店面正门照']));
            }

            if (empty($apply['work_file'])) {
                exit(json_encode(['status' => 1, 'msg' => '请上传店面内部照']));
            }

            if (empty($apply['self_num'])) {
                exit(json_encode(['status' => 1, 'msg' => '身份证号不可为空']));
            }

            if (empty($apply['front_of_id_card'])) {
                exit(json_encode(['status' => 1, 'msg' => '请上传身份证正面照']));
            }

            if (empty($apply['reverse_of_id_card'])) {
                exit(json_encode(['status' => 1, 'msg' => '请上传身份证反面照']));
            }

            if (empty($apply['mobile_phone'])) {
                exit(json_encode(['status' => 1, 'msg' => '请输入手机号码']));
            }

            if (!is_mobile($apply['mobile_phone'])) {
                exit(json_encode(['status' => 1, 'msg' => '请输入正确的手机号码']));
            }

            if (empty($apply['mobile_code'])) {
                exit(json_encode(['status' => 1, 'msg' => '请输入验证码']));
            }

            $mobile = $apply['mobile_phone'];
            $sms_code = $apply['mobile_code'];

            //验证手机号、短信验证码
            if (!$mobile || $mobile != $_SESSION['sms_mobile2']) {
                exit(json_encode(['status' => 1, 'msg' => '手机验证码不正确']));
            }
            if (!$sms_code || $sms_code != $_SESSION['sms_mobile_code2']) {
                exit(json_encode(['status' => 1, 'msg' => '手机验证码不正确']));
            }


            $apply['user_id'] = $this->user_id;
            $apply['country'] = 1;
            $apply['province'] = isset($apply['province_region_id'])?$apply['province_region_id']:0;
            $apply['city'] = isset($apply['city_region_id'])?$apply['city_region_id']:0;
            $apply['district'] = isset($apply['district_region_id'])?$apply['district_region_id']:0;
            $apply['street'] = isset($apply['town_region_id'])?$apply['town_region_id']:0;
            $apply['dateline'] = time();
            $apply['work_file'] = serialize($apply['work_file']);

            if ($purchasers) {
                //已经申请被拒绝 重复申请
                $apply['audit_status']  = 0;
                if (dao('purchasers')->data($apply)->where(['purchasers_id'=>$purchasers['purchasers_id']])->save()) {
                    exit(json_encode(['status' => 0, 'msg' => '申请成功']));
                }
            } else {
                //添加申请记录
                if (dao('purchasers')->data($apply)->add()) {
                    //注册成功给平台发送短信
                    /* 如果需要，发短信 */
                    $sms_shop_mobile = $GLOBALS['_CFG']['sms_shop_mobile']; //手机

                    $smsParams = array(
                        'username' =>  $_SESSION['user_name'],
                        'mobile_phone' => $sms_shop_mobile,
                        'addtime' => date('Y-m-d H:i:s', gmtime()),
                    );

                    $send_result = send_sms($sms_shop_mobile, 'purchasers_apply', $smsParams);

//                    if ($GLOBALS['_CFG']['sms_type'] == 0) {
//                        $sms = huyi_sms($smsParams, 'purchasers_apply');
//
//                    } elseif ($GLOBALS['_CFG']['sms_type'] >=1) {
//                        $result = sms_ali($smsParams, 'purchasers_apply'); //阿里大鱼短信变量传值，发送时机传值
//
//                        if ($result) {
//                            $resp = $GLOBALS['ecs']->ali_yu($result);
//                        } else {
//                            sys_msg('阿里大鱼短信配置异常', 1);
//                        }
//                    }

                    exit(json_encode(['status' => 0, 'msg' => '申请成功,等待审核吧']));
                }
            }

            exit(json_encode(['status' => 1, 'msg' => '申请失败']));
        }
    }

    //资质图片上传
    public function actionUpload() {
        $purchasers = dao('purchasers')->where(['user_id' => $this->user_id])->find();
        if ($purchasers && $purchasers['audit_status'] != 1) {
            $data = ['error' => 1, 'msg' => '您已申请过了'];
            exit(json_encode($data));
        }

        $result = $this->upload('data/purchaser', false, 2);
        $imagePath = '';
        if ($result['error'] <= 0) {
            $imagePath = $result['url']['img']['url'];
            $data = ['error' => 0, 'msg' => '上传成功', 'path' => $imagePath];
        } else {
            $data = ['error' => 1, 'msg' => '上传失败'];
        }
        echo json_encode($data);
    }

    public function actionApplyOk() {
        $this->redirect('user/index/index');
//        show_message('申请成功', L('back_retry_answer'), url('user/index/index'), 'success');
    }

    //注册零售商发送短信验证
    public function actionSend() {
        if (IS_AJAX || $_GET['ajax'] = 1) {
            $purchasers = dao('purchasers')->where(['user_id' => $this->user_id])->find();
            if ($purchasers && $purchasers['audit_status'] != 1) {
                $result['error'] = 1;
                $result['content'] = '您已申请过了';
                exit(json_encode($result));
            }

            $_SESSION['sms_mobile2'] = I('mobile');
            if (!$_SESSION['sms_mobile2'] || !is_mobile($_SESSION['sms_mobile2'])) {
                $result['error'] = 1;
                $result['content'] = '请输入正确的手机号';
                exit(json_encode($result));
            }

            //获取随机数
            $rand = rand(1000, 9999);


            // 验证手机号码格式
            $form = new Form();
            if (!$form->isMobile($_SESSION['sms_mobile2'], 1)) {
                $result['error'] = 1;
                $result['content'] = '手机号码格式不正确';
                exit(json_encode($result));
            }

            // 组装数据
            $message = [
                'code' => $rand
            ];

            $send_result = send_sms($_SESSION['sms_mobile2'], 'sms_code', $message);
            if ($send_result === true) {
                $result['error'] = 0;
                $result['msg'] = '发送短信成功';

                //赋予权限
                $_SESSION['sms_mobile_code2'] = $rand;
            } else {
                $result['error'] = 1;
                $result['msg'] = '发送短信失败';
            }
            exit(json_encode($result));
        }
    }
}
