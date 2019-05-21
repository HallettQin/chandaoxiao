<?php

namespace App\Services;

use App\Models\OrderInfo;
use App\Repositories\Cart\CartRepository;
use App\Repositories\Goods\GoodsRepository;
use App\Repositories\Order\OrderRepository;
use App\Repositories\User\AccountRepository;
use App\Repositories\User\AddressRepository;
use App\Repositories\User\InvoiceRepository;
use App\Repositories\Region\RegionRepository;
use App\Repositories\Payment\PayLogRepository;
use App\Repositories\Payment\PaymentRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Order\OrderGoodsRepository;
use App\Repositories\Shipping\ShippingRepository;
use App\Repositories\Order\OrderInvoiceRepository;
use App\Repositories\ShopConfig\ShopConfigRepository;

class FlowService
{
    /* 进货单商品类型 */
    const CART_GENERAL_GOODS = 0; // 普通商品

    /* 减库存时机 */
    const SDT_SHIP = 0; // 发货时
    const SDT_PLACE = 1; // 下订单时

    ///
    private $cartRepository;
    private $addressRepository;
    private $invoiceRepository;
    private $paymentRepository;
    private $shippingRepository;
    private $shopConfigRepository;
    private $goodsRepository;
    private $productRepository;
    private $orderRepository;
    private $orderGoodsRepository;
    private $orderInvoiceRepository;
    private $accountRepository;
    private $payLogRepository;
    private $regionRepository;

    public function __construct(
        CartRepository $cartRepository,
        AddressRepository $addressRepository,
        InvoiceRepository $invoiceRepository,
        PaymentRepository $paymentRepository,
        ShippingRepository $shippingRepository,
        ShopConfigRepository $shopConfigRepository,
        GoodsRepository $goodsRepository,
        OrderInvoiceRepository $orderInvoiceRepository,
        ProductRepository $productRepository,
        OrderRepository $orderRepository,
        OrderGoodsRepository $orderGoodsRepository,
        AccountRepository $accountRepository,
        PayLogRepository $payLogRepository,
        RegionRepository $regionRepository
    ) {
        $this->cartRepository = $cartRepository;
        $this->addressRepository = $addressRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->paymentRepository = $paymentRepository;
        $this->shippingRepository = $shippingRepository;
        $this->shopConfigRepository = $shopConfigRepository;
        $this->goodsRepository = $goodsRepository;
        $this->productRepository = $productRepository;
        $this->orderRepository = $orderRepository;
        $this->orderGoodsRepository = $orderGoodsRepository;
        $this->orderInvoiceRepository = $orderInvoiceRepository;
        $this->accountRepository = $accountRepository;
        $this->payLogRepository = $payLogRepository;
        $this->regionRepository = $regionRepository;
    }

    /**
     * 订单确认信息
     * @param $userId
     * @return array
     */
    public function flowInfo($userId)
    {
        $result = [];

        $result['cart_goods_list'] = $this->arrangeCartGoods($userId);  //进货单商品
        // 发票
        if ($this->shopConfigRepository->getShopConfigByCode('can_invoice') == '1') {
            $result['invoice_content'] = explode("\n", str_replace("\r", '', $this->shopConfigRepository->getShopConfigByCode('invoice_content')));
            if (empty($this->invoiceRepository->find($userId))) {
                $result['vat_invoice'] = '';
            } else {
                $result['vat_invoice'] = $this->invoiceRepository->find($userId);//增值发票
            }
            $result['can_invoice'] = 1;
        } else {
            $result['can_invoice'] = 0;
        }
        // 收货地址
        $defaultAddress = $this->addressRepository->getDefaultByUserId($userId);
        if (empty($defaultAddress['province']) || empty($defaultAddress['city'])) {
            $result['default_address'] = '';
        } else {
            $result['default_address'] = [
                'country'    => $this->regionRepository->getRegionName($defaultAddress['country']),
                'province'   => $this->regionRepository->getRegionName($defaultAddress['province']),
                'city'       => $this->regionRepository->getRegionName($defaultAddress['city']),
                'district'   => $this->regionRepository->getRegionName($defaultAddress['district']),
                'address'    => $defaultAddress['address'],
                'address_id' => $defaultAddress['address_id'],
                'consignee'  => $defaultAddress['consignee'],
                'mobile'     => $defaultAddress['mobile'],
                'user_id'    => $defaultAddress['user_id'],
            ];
        }
        // 收货地址end

//        $result['payment_list'] = $this->paymentRepository->paymentList();   //支付方式列表

        return $result;
    }

    /**
     * 整理进货单商品数据
     * @param $userId
     * @return array
     */
    private function arrangeCartGoods($userId)
    {
        $cartGoodsList = $this->cartRepository->getGoodsInCartByUser($userId);  //进货单商品
        $list = [];

        $totalAmount = $cartGoodsList['total']['goods_price'];   //订单总价

        foreach ($cartGoodsList['goods_list'] as $k => $v) {
            if (!isset($total[$v['ru_id']])) {
                $total[$v['ru_id']] = 0;
            }

            $totalPrice = empty($total[$v['ru_id']]['price']) ? 0 : $total[$v['ru_id']]['price'];
            $totalNumber = empty($total[$v['ru_id']]['number']) ? 0 : $total[$v['ru_id']]['number'];
            foreach ($v['goods'] as $key => $value) {
                $totalPrice += $value["goods_price"] * $value['goods_number'];
                $totalNumber += $value["goods_number"];

                $list[$v['ru_id']]['shop_list'][$key] = [
                    'rec_id' => $value['rec_id'],
                    'user_id' => $v['user_id'],
                    'goods_id' => $value['goods_id'],
                    'goods_name' => $value['goods_name'],
                    'ru_id' => $v['ru_id'],
                    'shop_name' => $v['shop_name'],
                    'market_price' => strip_tags($value['market_price']),
                    'market_price_formated' => price_format($value['market_price'], false),
                    'goods_price' => strip_tags($value['goods_price']),
                    'goods_price_formated' => price_format($value['goods_price'], false),
                    'goods_number' => $value['goods_number'],
                    'goods_thumb' => get_image_path($value['goods_thumb']),
                    'goods_attr' => $value['goods_attr']
                ];
            }
            foreach ($v['shop_info'] as $key => $value) {
                $list[$v['ru_id']]['shop_info'][$key] = [
                    'shipping_id' => $value['shipping_id'],
                    'shipping_name' => $value['shipping_name'],
                    'ru_id' => $value['ru_id'],
                ];
            }
            $list[$v['ru_id']]['total'] = [
                'price' => $totalPrice,
                'price_formated' => price_format($totalPrice, false),
                'number' => $totalNumber
            ];
        }
        unset($cartGoodsList);
        $totalAmount = strip_tags(preg_replace('/([\x80-\xff]*|[a-zA-Z])/i', '', $totalAmount));  //格式化总价
        //
        sort($list);

        return ['list' => $list, 'order_total' => $totalAmount, 'order_total_formated' => price_format($totalAmount, false)];
    }

    /**
     * 提交订单
     * @param $args
     * @return array
     */
    public function submitOrder($args)
    {
        // 检查登录状态
        $userId = $args['uid'];
        app('config')->set('uid', $userId);

        //商品类型
        $flow_type = self::CART_GENERAL_GOODS;

        // 检查进货单商品
        $goodsNum = $this->cartRepository->goodsNumInCartByUser($userId);

        if (empty($goodsNum)) {
            return ['error' => 1, 'msg' => '进货单没有商品'];
        }

        /**
         * 检查商品库存
         * 如果使用库存，且下订单时减库存，则减少库存
         */
        if ($this->shopConfigRepository->getShopConfigByCode('use_storage') == 1 && $this->shopConfigRepository->getShopConfigByCode('stock_dec_time') == 1) {
            $cart_goods = $this->cartRepository->getGoodsInCartByUser($userId);
            $_cart_goods_stock = [];
            foreach ($cart_goods['goods_list'] as $value) {
                foreach ($value['goods'] as $goodsValue) {
                    $_cart_goods_stock[$goodsValue['rec_id']] = $goodsValue['goods_number'];
                }
            }

            // 检查库存
            if (!$this->flow_cart_stock($_cart_goods_stock)) {
                return ['error' => 1, 'msg' => '库存不足'];
            }
            unset($cart_goods_stock, $_cart_goods_stock);
        }
        // 查询收货人信息
        $consignee = $args['consignee'];

        $consignee_info = $this->addressRepository->find($consignee);
        if (empty($consignee_info)) {
            return ['error' => 1, 'msg' => 'not find consignee'];
        }

        // 配送方式
        $shipping = $this->generateShipping($args['shipping']);
        // *****
        // 预定单
        $order = [
            'shipping_id' => empty($shipping['shipping_id']) ? 0 : $shipping['shipping_id'],
            'pay_id' => intval(0),
            'surplus' => isset($args['surplus']) ? floatval($args['surplus']) : 0.00,
            'integral' => isset($score) ? intval($score) : 0,//使用的积分的数量,取用户使用积分,商品可用积分,用户拥有积分中最小者
            'tax_id' => empty($args['postdata']['tax_id']) ? 0 : $args['postdata']['tax_id'], //纳税人识别码
            'inv_payee' => trim($args['postdata']['inv_payee']),   //个人还是公司名称 ，增值发票时此值为空
            'inv_content' => empty(trim($args['postdata']['inv_content'])) ? 0 :trim($args['postdata']['inv_content']) ,//发票明细
            'vat_id' => empty($args['postdata']['vat_id']) ? 0 : $args['postdata']['vat_id'],//增值发票对应的id
            'invoice_type' => empty($args['postdata']['invoice_type']) ? 0 : $args['postdata']['invoice_type'],// 0普通发票，1增值发票
            'froms' => '微信小程序',
            'postscript' => @trim($args['postscript']),
            'how_oos' => '',//缺货处理
            'user_id' => $userId,
            'add_time' => gmtime(),
            'order_status' => OrderInfo::OS_UNCONFIRMED,
            'shipping_status' => OrderInfo::SS_UNSHIPPED,
            'pay_status' => OrderInfo::PS_UNPAYED,
            'agency_id' => 0,//办事处的id
        ];

        /** 扩展信息 */
        $order['extension_code'] = '';
        $order['extension_id'] = 0;

        /** 订单中的商品 */
        if (!isset($cart_goods)) {
            $cart_goods = $this->cartRepository->getGoodsInCartByUser($userId);
        }
        $cartGoods = $cart_goods['goods_list'];   //进货单列表

        $cart_good_ids = [];   //进货单ID集合
        foreach ($cartGoods as $k => $v) {
            foreach ($v['goods'] as $goodsValue) {
                array_push($cart_good_ids, $goodsValue['rec_id']);
            }
        }

        if (empty($cart_goods)) {
            return ['error' => 1, 'msg' => '进货单没有商品'];
        }
        /** 检查积分余额是否合法 */
        /** 检查红包是否存在 */
        /** 收货人信息 */
        $order['consignee'] = $consignee_info->consignee;
        $order['country'] = $consignee_info->country;
        $order['province'] = $consignee_info->province;
        $order['city'] = $consignee_info->city;
        $order['mobile'] = $consignee_info->mobile;
        $order['tel'] = $consignee_info->tel;
        $order['zipcode'] = $consignee_info->zipcode;
        $order['district'] = $consignee_info->district;
        $order['address'] = $consignee_info->address;

        /** 判断是不是实体商品 */
        foreach ($cartGoods as $val) {
            foreach ($val['goods'] as $v) {
                /* 统计实体商品的个数 */
                if ($v['is_real']) {
                    $is_real_good = 1;
                }
            }
        }
        //        if(isset($is_real_good))
        //        {
        //            $shipping_is_real = $this->shippingRepository->find($order['shipping_id']);
        //            if(!$shipping_is_real)
        //            {
        //                return ['error' => 1, 'msg' => '�        �送方式不正确'];
        //            }
        //        }

        /** 订单中的总额 */
        $total = $this->orderRepository->order_fee($order, $cart_goods['goods_list'], $consignee_info, $cart_good_ids, $order['shipping_id'], $consignee);

        $order['bonus'] = isset($bonus) ? $bonus['type_money'] : '';

        $order['goods_amount'] = $total['goods_price'];
        $order['discount'] = $total['discount'];
        $order['surplus'] = $total['surplus'];
        $order['tax'] = $total['tax'];

        /** 配送方式 */
        if (!empty($order['shipping_id'])) {
            $order['shipping_name'] = addslashes($shipping['shipping_name']);
        }
        $order['shipping_fee'] = $total['shipping_fee'];
        $order['insure_fee'] = 0;

        /** 支付方式 */
        if ($order['pay_id'] > 0) {
            $order['pay_name'] = '微信支付';
        }
        $order['pay_name'] = '微信支付';
        $order['pay_fee'] = $total['pay_fee'];
        $order['cod_fee'] = $total['cod_fee'];

        /** 如果全部使用余额支付，检查余额是否足够 没有余额支付*/
        $order['order_amount'] = number_format($total['amount'], 2, '.', '');

        /** 如果订单金额为0（使用余额或积分或红包支付），修改订单状态为已确认、已付款 */
        if ($order['order_amount'] <= 0) {
            $order['order_status'] = OrderInfo::OS_CONFIRMED;
            $order['confirm_time'] = gmtime();
            $order['pay_status'] = OrderInfo::PS_PAYED;
            $order['pay_time'] = gmtime();
            $order['order_amount'] = 0;
        }

        $order['integral_money'] = $total['integral_money'];
        $order['integral'] = $total['integral'];

        $order['parent_id'] = 0;
        $order['order_sn'] = $this->getOrderSn(); //获取新订单号


        /** 插入订单表 */
        unset($order['timestamps']);
        unset($order['perPage']);
        unset($order['incrementing']);
        unset($order['dateFormat']);
        unset($order['morphClass']);
        unset($order['exists']);
        unset($order['wasRecentlyCreated']);
        unset($order['cod_fee']);
        $order['bonus'] = !empty($order['bonus']) ? $order['bonus'] : (!empty($order['bonus_id']) ? $order['bonus_id'] : 0);

        $new_order_id = $this->orderRepository->insertGetId($order);
        $order['order_id'] = $new_order_id;   //订单ID

        /** 插入订单商品 */
        $newGoodsList = [];
        foreach ($cartGoods as $v) {
            foreach ($v['goods'] as $gv) {
                $gv['ru_id'] = $v['ru_id'];
                $gv['user_id'] = $v['user_id'];
                $gv['shop_name'] = $v['shop_name'];
                $newGoodsList[] = $gv;
            }
        }
        $this->orderGoodsRepository->insertOrderGoods($newGoodsList, $order['order_id']);

        /** 处理余额、积分、红包 */
        if ($order['user_id'] > 0 && $order['integral'] > 0) {
            $this->accountRepository->logAccountChange(0, 0, 0, $order['integral'] * (-1), trans('message.score.pay'), $order['order_sn'], $userId);
        }

        /** 如果使用库存，且下订单时减库存，则减少库存 */
        if ($this->shopConfigRepository->getShopConfigByCode('use_storage') == '1' && $this->shopConfigRepository->getShopConfigByCode('stock_dec_time') == self::SDT_PLACE) {
            $this->orderRepository->changeOrderGoodsStorage($order['order_id'], true, self::SDT_PLACE);
        }

        /** 清空进货单 */
        $this->clear_cart_ids($cart_good_ids, $flow_type);

        /** 清除缓存，否则买了商品，但是前台页面读取缓存，商品数量不减少 */
        // clear_all_files();
        /** 插入支付日志 */

        $order['log_id'] = $this->payLogRepository->insert_pay_log($new_order_id, $order['order_amount'], 0);   //订单支付
        /** 当前用户是否已经填写过发票信息 */
        $user_invoice= $this->orderInvoiceRepository->find($userId);
        $invoice_info = [
            'tax_id' => $order['tax_id'],    // 纳税人识别码
            'inv_payee' => $order['inv_payee'],   // 公司名称
            'user_id' => $userId,
        ];
        if (!empty($user_invoice)) {
            $this->orderInvoiceRepository->updateInvoice($user_invoice['invoice_id'], $invoice_info);
        } else {
            $this->orderInvoiceRepository->addInvoice($invoice_info);
        }

        /** 主订单ID */
        $order_id = $order['order_id'];

        /** 生成子订单 */
        $shipping = [
            'shipping' => $args['shipping'],    // 配送ID 列表
            'shipping_fee_list' => isset($total['shipping_fee_list']) ? $total['shipping_fee_list'] : '',   // 配送费用列表
        ];

        $this->childOrder($cart_goods, $order, $consignee_info, $shipping);

        return $order_id;
    }

    /**
     * 组装配送方式
     * @param $arr
     * @return array
     */
    private function generateShipping($arr)
    {
        $return = [];
        $str = [];
        foreach ($arr as $k => $v) {
            $return[] = implode('|', array_values($v));

            $shippingId = $v['shipping_id'];
            $shipping = $this->shippingRepository->find($shippingId);

            $str[] = implode('|', [$v['ru_id'], $shipping['shipping_name']]);
        }
        return ['shipping_id' => implode(',', $return), 'shipping_name' => implode(',', $str)];
    }

    /**
     * 生成子订单
     * @param $cartGoods
     * @param $order
     * @param $consigneeInfo
     * @param $shipping
     */
    private function childOrder($cartGoods, $order, $consigneeInfo, $shipping)
    {
        $goodsList = $cartGoods['goods_list'];   //商品列表
        $total = $cartGoods['total'];  //商品总价信息
        $orderGoods = [];   //添加子订单商品
        $ruIds = $this->getRuIds($goodsList);

        if (count($ruIds) <= 0) {
            return;
        }

        // 商品配送方式
        $newShippingArr = [];
        foreach ($shipping['shipping'] as $v) {
            $newShippingArr[$v['ru_id']] = $v['shipping_id'];
        }

        // 商品配送费用
        $newShippingFeeArr = [];
        if (isset($shipping['shipping_fee_list']) && !empty($shipping['shipping_fee_list'])) {
            foreach ($shipping['shipping_fee_list'] as $k => $v) {
                $newShippingFeeArr[$k] = $v;
            }
        }

        // 配送方式名称
        $newShippingName = explode(',', $order['shipping_name']);
        $newShippingNameArr = [];
        foreach ($newShippingName as $v) {
            $temp = explode('|', $v);
            $newShippingNameArr[$temp[0]] = $temp[1];
        }

        //
        foreach ($goodsList as $key => $value) {
            $userId = 0;
            $goodsAmount = 0;
            $orderAmount = 0;
            $newOrder = [];
            $orderGoods = [];

            foreach ($value['goods'] as $v) {
                if ($v['ru_id'] != $value['ru_id']) {
                    continue;
                }
                $userId = $value['user_id'];
                $goodsAmount += $v['goods_number'] * $v['goods_price'];
                $orderAmount += $v['goods_number'] * $v['goods_price'];
            }
            $newOrder = [
                'main_order_id' => $order['order_id'],
                'order_sn' => $this->getOrderSn(), //获取新订单号
                'user_id' => $userId,
                'shipping_id' => $newShippingArr[$value['ru_id']],
                'shipping_name' => $newShippingNameArr[$value['ru_id']],
                'shipping_fee' => (empty($newShippingFeeArr[$value['ru_id']]) || !isset($newShippingFeeArr[$value['ru_id']])) ? 0 : $newShippingFeeArr[$value['ru_id']],
                'pay_id' => $order['pay_id'],
                'pay_name' => '微信支付',
                'goods_amount' => $goodsAmount,
                'order_amount' => $orderAmount,
                'add_time' => gmtime(),
                'order_status' => $order['order_status'],
                'shipping_status' => $order['shipping_status'],
                'pay_status' => $order['pay_status'],
                'tax_id' => $order['tax_id'], //纳税人识别码
                'inv_payee' => $order['inv_payee'],   //个人还是公司名称 ，增值发票时此值为空
                'inv_content' => $order['inv_content'] ,//发票明细
                'vat_id' => $order['vat_id'],//增值发票对应的id
                'invoice_type' => $order['invoice_type'],// 0普通发票，1增值发票
                'froms' => '微信小程序',
                'consignee' => $consigneeInfo->consignee,
                'country' => $consigneeInfo->country,
                'province' => $consigneeInfo->province,
                'city' => $consigneeInfo->city,
                'mobile' => $consigneeInfo->mobile,
                'tel' => $consigneeInfo->tel,
                'zipcode' => $consigneeInfo->zipcode,
                'district' => $consigneeInfo->district,
                'address' => $consigneeInfo->address,
            ];

            $new_order_id = $this->orderRepository->insertGetId($newOrder);   //插入订单
            foreach ($value['goods'] as $v) {
                if ($v['ru_id'] != $value['ru_id']) {
                    continue;
                }
                // 订单商品
                $orderGoods[] = [
                    'order_id' => $new_order_id,
                    'goods_id' => $v['goods_id'],
                    'goods_name' => $v['goods_name'],
                    'goods_sn' => $v['goods_sn'],
                    'product_id' => $v['product_id'],
                    'goods_number' => $v['goods_number'],
                    'market_price' => $v['market_price'],
                    'goods_price' => $v['goods_price'],
                    'goods_attr' => $v['goods_attr'],
                    'is_real' => $v['is_real'],
                    'extension_code' => $v['extension_code'],
                    'parent_id' => $v['parent_id'],
                    'is_gift' => $v['is_gift'],
                    'model_attr' => $v['model_attr'],
                    'goods_attr_id' => $v['goods_attr_id'],
                    'ru_id' => $v['ru_id'],
                    'shopping_fee' => $v['shopping_fee'],
                    'warehouse_id' => $v['warehouse_id'],
                    'area_id' => $v['area_id'],
                ];
            }
            $this->orderGoodsRepository->insertOrderGoods($orderGoods);    //添加子订单商品
        }
    }

    /**
     * 获取商品中商家ID
     * @param $cartGoods
     * @return array
     */
    private function getRuIds($cartGoods)
    {
        $arr = [];
        foreach ($cartGoods as $v) {
            if (in_array($v['ru_id'], $arr)) {
                continue;
            }
            $arr[] = $v['ru_id'];
        }

        return $arr;
    }

    /**
     * 检查订单中商品库存
     *
     * @access  public
     * @param   array $arr
     *
     * @return  void
     */
    public function flow_cart_stock($arr)
    {
        foreach ($arr as $key => $val) {
            $val = intval(make_semiangle($val));
            if ($val <= 0 || !is_numeric($key)) {
                continue;
            }

            // 根据进货单ID 找到商品
            $goods = $this->cartRepository->field(['goods_id', 'goods_attr_id', 'extension_code'])->find($key);   //

            // 商品 、 货品 信息
            $row = $this->goodsRepository->cartGoods($key);

            //系统启用了库存，检查输入的商品数量是否有效
            $goodsExtendsionCode = (empty($goods['extension_code'])) ? "" : $goods['extension_code'];
            if (intval($this->shopConfigRepository->getShopConfigByCode('use_storage')) > 0 && $goodsExtendsionCode != 'package_buy') {
                if ($row['goods_number'] < $val) {
                    return false;
                }

                /* 是货品 */
                $row['product_id'] = trim($row['product_id']);
                if (!empty($row['product_id'])) {
                    @$product_number = $this->productRepository
                        ->findBy(['goods_id' => $goods['goods_id'], 'product_id' => $row['product_id']])
                        ->column('product_number');

                    if ($product_number < $val) {
                        return false;
                    }
                }
            }
        }
        return true;
    }

    /**
     * 得到新订单号
     * @return  string
     */
    public function getOrderSn()
    {
        /* 选择一个随机的方案 */
        mt_srand((double)microtime() * 1000000);

        return date('Ymd') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
    }

    /**
     * 清空指定进货单
     * @param   arr $arr 进货单id
     * @param   int $type 类型：默认普通商品
     */
    private function clear_cart_ids($arr, $type = self::CART_GENERAL_GOODS)
    {
        $uid = app('config')->get('uid');

        $this->cartRepository->deleteAll([
            ['in', 'rec_id', $arr],
            ['rec_type', $type],
            ['user_id', $uid],
        ]);
    }

    /**
     * 运费计算
     * @param $args
     * @return int
     */
    public function shippingFee($args)
    {
        $result = ['error' => 0, 'message' => ''];
        /* 配送方式 */
        $shippingId = isset($args['id']) ? intval($args['id']) : 0;
        $ruId = isset($args['ru_id']) ? intval($args['ru_id']) : 0;
        $address = isset($args['address']) ? intval($args['address']) : 0;

        /* 对商品信息赋值 */
        $cart_goods = $this->cartRepository->getGoodsInCartByUser($args['uid']);

        // 切换配送方式
        $cart_goods_list = $cart_goods['product'];
        if (empty($cart_goods_list)) {
            $result['error'] = 1;
            $result['message'] = '进货单没有商品';
            return $result;
        }

        foreach ($cart_goods_list as $key => $val) {
            if ($shippingId > 0 && $val['goods']['ru_id'] == $ruId) {
                $cart_goods_list[$key]['goods']['tmp_shipping_id'] = $shippingId;
            }
        }
        /* 计算订单的费用 */
        // 收货地址、商品列表、配送方式ID
        $shipFee = $this->shippingRepository->total_shipping_fee($address, $cart_goods_list, $shippingId, $ruId);
        if ($shipFee) {
            $newShipFee = strip_tags(preg_replace('/([\x80-\xff]*|[a-zA-Z])/i', '', $shipFee));
            $result['fee'] = "0";
            if (floatval($newShipFee) > 0) {
                $result['fee'] = $newShipFee;
            }
        } else {
            $result['error'] = 1;
            $result['message'] = '该地区不支持配送';
        }

        $result['fee_formated'] = $shipFee;
        return $result;
    }
}
