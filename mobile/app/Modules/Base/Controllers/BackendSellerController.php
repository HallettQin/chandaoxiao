<?php

namespace App\Modules\Base\Controllers;

use App\Libraries\Shop;
use App\Libraries\Mysql;

abstract class BackendSellerController extends FoundationController
{
    public function __construct()
    {
        parent::__construct();
        // 加载helper文件
        $helper_list = ['time', 'base', 'common', 'main', 'insert', 'goods', 'wechat'];
        $this->load_helper($helper_list);
        // 全局对象
        $this->ecs = $GLOBALS['ecs'] = new Shop(C('DB_NAME'), C('DB_PREFIX'));
        $this->db = $GLOBALS['db'] = new Mysql();
        // 商家后台登录
        if (!defined('INIT_NO_USERS')) {
            session(['name' => 'ECSCP_SELLER_ID']);
            session('[start]');
            $condition['sesskey'] = substr(cookie('ECSCP_SELLER_ID'), 0, 32);
            $session_seller = $this->model->table('sessions_data')->where($condition)->find();
            $_SESSION = unserialize($session_seller['data']);

            define('SESS_ID', substr($session_seller['sesskey'], 0, 32));
        }

        // 全局配置
        $GLOBALS['_CFG'] = load_ecsconfig();
        $GLOBALS['_CFG']['template'] = 'default';
        C('shop', $GLOBALS['_CFG']);

        // 验证商家
        $this->checkSellerLogin();

        // 全局语言包
        L(require(LANG_PATH . C('shop.lang') . '/common.php'));
        L('copyright', sprintf(L('copyright'), date('Y'))); // 后台版权语言包
    }

    /**
     * 操作成功之后跳转,默认三秒钟跳转
     *
     * @param unknown $msg
     * @param unknown $url
     * @param string $type
     * @param number $waitSecond
     */
    public function message($msg, $url = null, $type = '1', $seller = false, $waitSecond = 3)
    {
        if ($url == null) {
            $url = 'javascript:history.back();';
        }
        if ($type == '2') {
            $title = L('error_information');
        } else {
            $title = L('prompt_information');
        }
        $data['title'] = $title;
        $data['message'] = $msg;
        $data['type'] = $type;
        $data['url'] = $url;
        $data['second'] = $waitSecond;
        $this->assign('data', $data);
        $tpl = ($seller == true) ? 'admin/seller_message' : 'admin/message';
        $this->display($tpl);
        exit();
    }

    /**
     * 判断商家管理员登录
     * @return [type] [description]
     */
    private function checkSellerLogin()
    {
        $condition['user_id'] = isset($_SESSION['seller_id']) ? intval($_SESSION['seller_id']) : 0;
        $action_list = $this->model->table('admin_user')->where($condition)->getField('action_list');
        // 手机端登录商家后台权限校验
        if (empty($action_list)) {
            redirect('../' . SELLER_PATH . '/privilege.php?act=login');
        }
    }

    /**
     * 判断商家管理员对某一个操作是否有权限。
     *
     * 根据当前对应的action_code，然后再和用户session里面的action_list做匹配，以此来决定是否可以继续执行。
     * @param     string $priv_str 操作对应的priv_str
     * @param     string $msg_type 返回的类型
     * @return true/false
     */
    public function seller_admin_priv($priv_str)
    {
        $condition['user_id'] = isset($_SESSION['seller_id']) ? intval($_SESSION['seller_id']) : 0;
        $action_list = $this->model->table('admin_user')->where($condition)->getField('action_list');

        if ($action_list == 'all') {
            return true;
        }

        if (strpos(',' . $action_list . ',', ',' . $priv_str . ',') === false) {
            $this->message(L('priv_error'), null, 2, true);
            // redirect('../'.SELLER_PATH.'/privilege.php?act=login');
            return false;
        } else {
            return true;
        }
    }
}
