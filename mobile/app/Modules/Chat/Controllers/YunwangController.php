<?php

namespace App\Modules\Chat\Controllers;

use App\Modules\Base\Controllers\FrontendController;

class YunwangController extends FrontendController
{
    private $user_id = 0;
    private $userinfo = [];

    public function __construct()
    {
        parent::__construct();
        $this->load_helper('order');
//        $this->user_id = $_SESSION['user_id'];
//        $this->userinfo = user_info($this->user_id);
//
//        $this->userinfo['user_picture'] = isset($_SESSION['avatar']) ? $_SESSION['avatar'] : $this->userinfo['user_picture'];
//        if (!$this->user_id) {
//            $this->redirect('user/login/index');
//        }
    }

    /**
     * 客服首页
     */
    public function actionIndex()
    {

//        $goods_id = I('goods_id', 0, 'intval');
//        $ru_id = I('ru_id', 0, 'intval');
//        if (!empty($goods_id)) {
//            $data = get_goods_info($goods_id);
//            $ru_id = $data['user_id'];
//        }
//        $config = $this->model->table('seller_shopinfo')->field('kf_appkey, kf_secretkey, kf_touid, kf_logo, kf_welcomeMsg')->where(['ru_id' => $ru_id])->find();
//        $user_id = $ru_id;
//        if ($user_id) {
//            $user_info = user_info($_SESSION['user_id']);
//            $user_info['user_id'] = 'dsc' . $_SESSION['user_id'];
//
//            if (empty($user_info['user_picture'])) {
//                $user_logo = 'http://dsc-kf.oss-cn-shanghai.aliyuncs.com/dsc_kf/dsc_kf_user_logo.jpg';
//            }
//            else {
//                $user_logo = $GLOBALS['ecs']->get_domain() . '/' . $user_info['user_picture'];
//            }
//        }
//        else {
//            $user_info['user_id'] = $_SESSION['user_ni_id'];
//            $user_logo = 'http://dsc-kf.oss-cn-shanghai.aliyuncs.com/dsc_kf/dsc_kf_user_logo.jpg';
//        }
//
//        // 创建客服用户
//        $this->createImUser($config['kf_appkey'], $config['kf_secretkey']);
//        // 参数赋值
//        $this->assign('user_logo', $user_logo);
//        $data['goods_id'] = $goods_id;
//        $data['avatar'] = get_image_path($this->userinfo['user_picture']);
//        $data['appkey'] = $config['kf_appkey'];
//        $data['secretkey'] = $config['kf_secretkey'];
//        $data['touid'] = $config['kf_touid'];
//        $data['uid'] = 'dsc'.$this->userinfo['user_id'];
//        $data['credential'] = md5($this->userinfo['user_id']);
//        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 创建用户
     */
    private function createImUser($appkey = '', $secretkey = '')
    {
        require dirname(ROOT_PATH) . '/plugins/aliyunim/TopSdk.php';
        $c = new \TopClient;
        $c->appkey = $appkey;
        $c->secretKey = $secretkey;
        $req = new \OpenimUsersAddRequest();
        $userinfos = new \Userinfos;
        $userinfos->nick = $this->userinfo['user_name'];
        $userinfos->userid = $this->userinfo['user_id'];
        $userinfos->password = md5($this->userinfo['user_id']);
        $req->setUserinfos(json_encode($userinfos));
        $resp = $c->execute($req);
    }
}
