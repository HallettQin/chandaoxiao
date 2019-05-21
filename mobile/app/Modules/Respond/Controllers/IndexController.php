<?php

namespace App\Modules\Respond\Controllers;

use App\Modules\Base\Controllers\FrontendController;

class IndexController extends FrontendController
{
    private $data = [];

    public function __construct()
    {
        parent::__construct();
        // 获取参数
        $this->data = [
            'code' => I('get.code')
        ];
        if (isset($_GET['code'])) {
            unset($_GET['code']);
        }
    }

    /**
     * 处理支付同步通知
     */
    public function actionIndex()
    {
        // 提示类型
        $msg_type = 2;
        $payment = $this->getPayment();
        if ($payment === false) {
            $msg = L('pay_disabled');
        } else {
            if ($payment->callback($this->data)) {
                $msg = L('pay_success');
                $msg_type = 0;
            } else {
                $msg = L('pay_fail');
                $msg_type = 1;
            }
        }
        // 显示页面
        $this->assign('message', $msg);
        $this->assign('msg_type', $msg_type);
        $this->assign('page_title', L('pay_status'));
        $this->display();
    }

    /**
     * 处理支付异步通知
     */
    public function actionNotify()
    {
        $payment = $this->getPayment();
        if ($payment === false) {
            exit('plugin load fail');
        }
        $payment->notify($this->data);
    }

    /**
     * 获得支付信息
     */
    private function getPayment()
    {
        /* 判断启用状态 */
        $condition = [
            'pay_code' => $this->data['code'],
            'enabled' => 1
        ];
        $enabled = $this->db->table('payment')->where($condition)->count();
        $plugin = ADDONS_PATH . 'payment/' . $this->data['code'] . '.php';
        if (!is_file($plugin) || $enabled == 0) {
            return false;
        }
        /* 实例化插件 */
        require_cache($plugin);
        $payment = new $this->data['code']();
        return $payment;
    }

    /**
     * 微信支付h5同步通知中间页面
     * @return
     */
    public function actionWxh5()
    {
        //显示页面
        if (isset($_GET) && isset($_GET['order_id'])) {
            $order = [];
            $order['order_id']= intval($_GET['order_id']);
            $order_url = url('user/order/detail', ['order_id' => $order['order_id']]);

            $repond_url = __URL__ . "/respond.php?code=" .$this->data['code']. "&status=1&order_id=".$order['order_id'];
        } else {
            $repond_url = __URL__ . "/respond.php?code=" .$this->data['code']. "&status=0";
        }
        $is_wxh5 = ($this->data['code'] == 'wxpay' && !is_wechat_browser()) ? 1 : 0;
        $this->assign('is_wxh5', $is_wxh5);
        $this->assign('repond_url', $repond_url);
        $this->assign('order_url', $order_url);
        $this->assign('page_title', '确认支付');
        $this->display();
    }
}
