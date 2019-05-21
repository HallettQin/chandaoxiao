<?php

namespace App\Services;

use App\Services\Wxpay\WxPay;
use Illuminate\Support\Facades\URL;
use App\Repositories\Order\OrderRepository;
use App\Repositories\User\AccountRepository;
use App\Repositories\Wechat\WxappConfigRepository;
use App\Repositories\ShopConfig\ShopConfigRepository;

class PaymentService
{
    public $payList;
    private $orderRepository;
    private $shopConfigRepository;
    private $accountRepository;
    private $shopService;
    private $WxappConfigRepository;

    public function __construct(
        OrderRepository $orderRepository,
        ShopConfigRepository $shopConfigRepository,
        AccountRepository $accountRepository,
        ShopService $shopService,
        WxappConfigRepository $WxappConfigRepository
    ) {
        $this->orderRepository = $orderRepository;
        $this->shopConfigRepository = $shopConfigRepository;
        $this->accountRepository = $accountRepository;
        $this->shopService = $shopService;
        $this->WxappConfigRepository = $WxappConfigRepository;

        //  订单支付   或  充值支付
        $this->payList = [
            'order' => 'order.pay',   // 订单支付
            'account' => 'account.pay',   // 充值
        ];
    }

    /**
     * 支付工厂
     * @param $args
     * @return mixed
     */
    public function payment($args)
    {
        $shopName = $this->shopConfigRepository->getShopConfigByCode('shop_name');  //平台名称
        $order = $this->orderRepository->find($args['id']);
        $orderGoods = $this->orderRepository->getOrderGoods($args['id']);
        $ruName = $this->shopService->getShopName($orderGoods[0]['ru_id']);  // 店铺名称

        switch ($args['code']) {

            case $this->payList['order']:
                $new = [
                    'open_id' => $args['open_id'],
                    'body' => $ruName . '-订单编号' . $order['order_sn'],
                    'out_trade_no' => $order['order_sn'],
                    'total_fee' => $order['order_amount'] * 100, // 分
                ];
                break;
            case $this->payList['account']:
                $account = $this->accountRepository->getDepositInfo($args['id']);

                $new = [
                    'open_id' => $args['open_id'],
                    'body' => $shopName . '-订单编号' . $order['order_sn'],
                    'out_trade_no' => date('Ymd') . 'A' . str_pad($account['id'], 6, '0', STR_PAD_LEFT),
                    'total_fee' => $account['amount'] * 100,  // 分
                ];
                break;
            default:
                $new = [
                    'open_id' => $args['open_id'],
                    'body' => $shopName . '-订单编号' . $order['order_sn'],
                    'out_trade_no' => 'out_trade_no',
                    'total_fee' => 'total_fee',
                ];
                break;
        }

        return $this->WxPay($new);
    }

    /**
     * 微信小程序支付接口
     * @param $args
     * @return mixed
     */
    private function WxPay($args)
    {
        $wxpay = new WxPay();

        $code = 'wxpay';

        $config = [
            'app_id' => $this->WxappConfigRepository->getWxappConfigByCode('wx_appid'),
            'app_secret' => $this->WxappConfigRepository->getWxappConfigByCode('wx_appsecret'),
            'mch_key' => $this->WxappConfigRepository->getWxappConfigByCode('wx_mch_key'),
            'mch_id' => $this->WxappConfigRepository->getWxappConfigByCode('wx_mch_id'),
        ];
        $wxpay->init($config['app_id'], $config['app_secret'], $config['mch_key']);
        $nonce_str = 'ibuaiVcKdpRxkhJA';
        $time_stamp = (string)gmtime();

        $inputParams = [

            //公众账号ID
            'appid' => $config['app_id'],

            //商户号
            'mch_id' => $config['mch_id'],

            //openid
            'openid' => $args['open_id'],

            'device_info' => '1000',

            //随机字符串
            'nonce_str' => $nonce_str,

            //商品描述
            'body' => $args['body'],

            'attach' => $args['body'],

            //商户订单号
            'out_trade_no' => $args['out_trade_no'],

            //总金额
            'total_fee' => $args['total_fee'],

            //终端IP
            'spbill_create_ip' => app('request')->getClientIp(),

            //接受微信支付异步通知回调地址
            'notify_url' => Url::to('api/wx/payment/notify', ['code', $code]),

            //交易类型:JSAPI,NATIVE,APP
            'trade_type' => 'JSAPI'
        ];

        $inputParams['sign'] = $wxpay->createMd5Sign($inputParams);

        //获取prepayid
        $prepayid = $wxpay->sendPrepay($inputParams);

        $pack = 'prepay_id=' . $prepayid;

        $prePayParams = [
            'appId' => $config['app_id'],
            'timeStamp' => $time_stamp,
            'package' => $pack,
            'nonceStr' => $nonce_str,
            'signType' => 'MD5'
        ];

        //生成签名
        $sign = $wxpay->createMd5Sign($prePayParams);

        $body = [
            'appid' => $config['app_id'],
            'mch_id' => $config['mch_id'],
            'prepay_id' => $prepayid,
            'nonce_str' => $nonce_str,
            'timestamp' => $time_stamp,
            'packages' => $pack,
            'sign' => $sign,
        ];

        return ['wxpay' => $body];
    }

    /**
     * 回调通知
     * @param $args
     * @return mixed
     */
    public function notify($args)
    {
        $uid = $args['uid'];
        $orderId = $args['id'];
        $idsArr = [];
        // 判断子订单
        $order = $this->orderRepository->find($orderId);
        if (empty($order['user_id']) || $order['user_id'] != $uid) {
            return ['code' => 1, 'msg' => '不是你的订单'];
        }
        array_unshift($idsArr, $orderId);

        if ($order['main_order_id'] == 0) {
            // 订单为主订单  查出子订单ID

            $ids = $this->orderRepository->getChildOrder($order['order_id']);
            $idsArr = array_column($ids, 'order_id');
        }

        // 修改 订单状态
        $res = $this->orderRepository->orderPay($uid, $idsArr);
        if ($res === 0) {
            return ['code' => 1, 'msg' => '没有任何修改'];
        }

        return $res;
    }
}
