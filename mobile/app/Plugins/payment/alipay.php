<?php

defined('IN_ECTOUCH') or die('Deny Access');

use Payment\Config;
use Payment\Client\Query;
use Payment\Client\Charge;
use Payment\Client\Notify;
use Payment\Common\PayException;
use Payment\Notify\PayNotifyInterface;

class alipay
{
    /**
     * 生成支付代码
     * @param $order 订单信息
     * @param $payment 支付方式
     * @return string
     */
    public function get_code($order, $payment)
    {
        // 订单信息
        $payData = [
            'body' => $order['order_sn'],
            'subject' => $order['order_sn'],
            'order_no' => $order['order_sn'] . 'O' . $order['log_id'],
            'timeout_express' => time() + 3600 * 24,// 表示必须 24h 内付款
            'amount' => $order['order_amount'],// 单位为元 ,最小为0.01
            'return_param' => (string) $order['log_id'],// 一定不要传入汉字，只能是 字母 数字组合
            'client_ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1',// 客户地址
            'goods_type' => 1,
            'store_id' => '',
        ];

        try {
            $payUrl = Charge::run(Config::ALI_CHANNEL_WAP, $this->getConfig(), $payData);
        } catch (PayException $e) {
            // 异常处理
            exit($e->getMessage());
        }

        /* 生成支付按钮 */
        return '<a  type="button" class="box-flex btn-submit min-two-btn" onclick="javascript:_AP.pay(\'' . $payUrl . '\')">支付宝支付</a>';
    }

    /**
     * 同步通知
     * @param $data
     * @return mixed
     */
    public function callback($data)
    {
        if (!empty($_GET)) {
            try {
                $order = [];
                list($order['order_sn'], $order['log_id']) = explode('O', $_GET['out_trade_no']);
                return $this->query($order);
            } catch (PayException $e) {
                logResult($e->getMessage());
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * 异步通知
     * @param $data
     * @return mixed
     */
    public function notify($data)
    {
        if (!empty($_POST)) {
            try {
                $callback = new OrderPaidNotify();
                $ret = Notify::run(Config::ALI_CHARGE, $this->getConfig(), $callback);// 处理回调，内部进行了签名检查
                exit($ret);
            } catch (PayException $e) {
                logResult($e->getMessage());
                exit('fail');
            }
        } else {
            exit("fail");
        }
    }

    /**
     * 订单查询
     * @return mixed
     */
    public function query($order)
    {
        $data = [
            'out_trade_no' => $order['order_sn'] . 'O' . $order['log_id'],
        ];

        try {
            $ret = Query::run(Config::ALI_CHARGE, $this->getConfig(), $data);

            if ($ret['response']['trade_state'] === Config::TRADE_STATUS_SUCC) {
                order_paid($order['log_id'], 2);
                return true;
            }
        } catch (PayException $e) {
            logResult($e->getMessage());
        }

        return false;
    }

    /**
     * 获取配置
     * @return array
     */
    private function getConfig()
    {
        include_once(BASE_PATH . 'Helpers/payment_helper.php');
        $payment = get_payment(basename(__FILE__, '.php'));

        return [
            'use_sandbox' => (bool)$payment['use_sandbox'],
            'partner' => $payment['alipay_partner'],
            'app_id' => $payment['app_id'],
            'sign_type' => $payment['sign_type'],
            // 可以填写文件路径，或者密钥字符串  当前字符串是 rsa2 的支付宝公钥(开放平台获取)
            'ali_public_key' => $payment['ali_public_key'],
            // 可以填写文件路径，或者密钥字符串  我的沙箱模式，rsa与rsa2的私钥相同，为了方便测试
            'rsa_private_key' => $payment['rsa_private_key'],
            'notify_url' => notify_url(basename(__FILE__, '.php')),
            'return_url' => return_url(basename(__FILE__, '.php')),
            'return_raw' => false,
        ];
    }
}

/**
 * 客户端需要继承该接口，并实现这个方法，在其中实现对应的业务逻辑
 * Class OrderPaidNotify
 */
class OrderPaidNotify implements PayNotifyInterface
{
    public function notifyProcess(array $data)
    {
        /**
         * 改变订单状态
         */
        $log_id = $data['return_param']; // 订单号log_id
        order_paid($log_id, 2);
        return true;
    }
}
