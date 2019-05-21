<?php

namespace App\Services;

use App\Models\OrderInfo;
use App\Models\CollectGoods;
use App\Repositories\User\UserRepository;
use App\Repositories\Goods\GoodsRepository;
use App\Repositories\Order\OrderRepository;
use App\Repositories\Store\StoreRepository;
use App\Repositories\User\AccountRepository;
use App\Repositories\User\AddressRepository;
use App\Repositories\User\InvoiceRepository;
use App\Repositories\Region\RegionRepository;
use App\Repositories\Bonus\UserBonusRepository;
use App\Repositories\Comment\CommentRepository;
use App\Repositories\Order\OrderGoodsRepository;
use App\Repositories\Goods\CollectGoodsRepository;
use App\Repositories\Store\CollectStoreRepository;

class UserService
{
    private $orderRepository;
    private $goodsRepository;
    private $storeRepository;
    private $collectStoreRepository;
    private $userRepository;
    private $addressRepository;
    private $invoiceRepository;
    private $regionRepository;
    private $userBonusRepository;
    private $accountRepository;
    private $collectGoodsRepository;
    private $shopService;
    private $commentRepository;
    private $orderGoodsRepository;

    /**
     * UserService constructor.
     * @param OrderRepository $orderRepository
     * @param GoodsRepository $goodsRepository
     * @param UserRepository $userRepository
     * @param AddressRepository $addressRepository
     * @param InvoiceRepository $invoiceRepository
     * @param RegionRepository $regionRepository
     * @param UserBonusRepository $userBonusRepository
     * @param AccountRepository $accountRepository
     * @param CollectGoodsRepository $collectGoodsRepository
     * @param ShopService $shopService
     * @param CommentRepository $commentRepository
     * @param OrderGoodsRepository $orderGoodsRepository
     */
    public function __construct(
        OrderRepository $orderRepository,
        GoodsRepository $goodsRepository,
        UserRepository $userRepository,
        StoreRepository $storeRepository,
        CollectStoreRepository $collectStoreRepository,
        AddressRepository $addressRepository,
        InvoiceRepository $invoiceRepository,
        RegionRepository $regionRepository,
        UserBonusRepository $userBonusRepository,
        AccountRepository $accountRepository,
        CollectGoodsRepository $collectGoodsRepository,
        ShopService $shopService,
        CommentRepository $commentRepository,
        OrderGoodsRepository $orderGoodsRepository
    ) {
        $this->orderRepository = $orderRepository;
        $this->goodsRepository = $goodsRepository;
        $this->userRepository = $userRepository;
        $this->addressRepository = $addressRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->regionRepository = $regionRepository;
        $this->userBonusRepository = $userBonusRepository;
        $this->accountRepository = $accountRepository;
        $this->collectGoodsRepository = $collectGoodsRepository;
        $this->storeRepository = $storeRepository;
        $this->collectStoreRepository = $collectStoreRepository;
        $this->shopService = $shopService;
        $this->commentRepository = $commentRepository;
        $this->orderGoodsRepository = $orderGoodsRepository;
    }

    /**
     * 用户中心数据
     * @param array $args
     * @return mixed
     */
    public function userCenter(array $args)
    {
        $userId = $args['uid'];

        /** 用户信息 */

        /**
         * 待付款
         * 待收货
         * 待评价
         */
        $result['order']['all_num'] = $this->orderRepository->orderNum($userId);   //所有订单数量
        $result['order']['no_paid_num'] = $this->orderRepository->orderNum($userId, 0);   //待付款订单数量
        $result['order']['no_received_num'] = $this->orderRepository->orderNum($userId, 2);   //待收货订单数量
        $result['order']['no_evaluation_num'] = $this->orderRepository->orderNum($userId, 3);   //待评价订单数量

        /**
         * 我的钱包
         * 余额
         * 红包
         * 积分
         * 优惠券
         */

        $result['userInfo'] = $this->userRepository->userInfo($userId);

        $bestGoods = $this->goodsRepository->findByType('best');
        $result['best_goods'] = array_map(function ($v) {
            return [
                'goods_id' => $v['goods_id'],
                'goods_name' => $v['goods_name'],
                'market_price' => $v['market_price'],
                'shop_price' => $v['shop_price'],
                'goods_thumb' => get_image_path($v['goods_thumb']),
            ];
        }, $bestGoods);

        return $result;
    }

    /**
     * 订单列表
     * @param $args
     * @return mixed
     */
    public function orderList($args)
    {
        $orderList = $this->orderRepository->getOrderByUserId($args['uid'], $args['status']);
        $shopconfig = app('App\Repositories\ShopConfig\ShopConfigRepository');
        $timeFormat = $shopconfig->getShopConfigByCode('time_format');

        foreach ($orderList as $k => $v) {
            $orderList[$k]['add_time'] = local_date($timeFormat, $v['add_time']); // 时间
            $orderList[$k]['order_status'] = $this->orderStatus($v['order_status']); // 订单状态
            $orderList[$k]['pay_status'] = $this->payStatus($v['pay_status']); // 支付状态
            $orderList[$k]['shipping_status'] = $this->shipStatus($v['shipping_status']); // 配送状态
            $dataTotalNumber = 0;

            foreach ($v['goods'] as $gk => $gv) {
                $dataTotalNumber += $gv['goods_number'];
                $orderList[$k]['goods'][$gk]['goods_thumb'] = get_image_path($gv['goods_thumb']);
                $orderList[$k]['goods'][$gk]['goods_price_formated'] = price_format($gv['goods_price'], false);
                if (empty($orderList[$k]['shop_name'])) {
                    $orderList[$k]['shop_name'] = $this->shopService->getShopName($gv['user_id']);
                    unset($orderList[$k]['goods'][$gk]['user_id']);
                }
            }
            $orderList[$k]['goods'] = array_slice($orderList[$k]['goods'], 0, 3);

            $orderList[$k]['total_number'] = $dataTotalNumber; // 配送状态
            $orderList[$k]['goods_amount_formated'] = price_format($v['goods_amount']);
            $orderList[$k]['money_paid_formated'] = price_format($v['money_paid']);
            $orderList[$k]['order_amount_formated'] = price_format($v['order_amount']);
            $orderList[$k]['shipping_fee_formated'] = price_format($v['shipping_fee']);

            $orderList[$k]['total_amount'] = $v['money_paid'] + $v['order_amount'] + $v['shipping_fee']; // 总金额
            $orderList[$k]['total_amount_formated'] = price_format($orderList[$k]['total_amount']);
        }
        return $orderList;
    }

    /**
     * 订单详情
     * @param $args
     * @return mixed
     */
    public function orderDetail($args)
    {
        $order = $this->orderRepository->orderDetail($args['uid'], $args['order_id']);
        if (empty($order)) {
            return [];
        }

        $address = $this->regionRepository->getRegionName($order['country']);
        $address .= $this->regionRepository->getRegionName($order['province']);
        $address .= $this->regionRepository->getRegionName($order['city']);
        $address .= $this->regionRepository->getRegionName($order['district']);
        $address .= $order['address'];
        $shopconfig = app('App\Repositories\ShopConfig\ShopConfigRepository');
        $timeFormat = $shopconfig->getShopConfigByCode('time_format');
        $list = [
            'add_time' =>  local_date($timeFormat, $order['add_time']),
//            'shipping_time' => date('Y-m-d H:i', $order['shipping_time']),
            'address' => $address,
            'consignee' => $order['consignee'],
            'mobile' => $order['mobile'],
            'money_paid' => $order['money_paid'],
            'goods_amount' => $order['goods_amount'],
            'goods_amount_formated' => price_format($order['goods_amount'], false),
            'order_amount' => $order['order_amount'],
            'order_amount_formated' => price_format($order['order_amount'], false),
            'order_id' => $order['order_id'],
            'order_sn' => $order['order_sn'],
            'tax_id' => $order['tax_id'], //纳税人识别码
            'inv_payee' => $order['inv_payee'],   //个人还是公司名称 ，增值发票时此值为空
            'inv_content' => $order['inv_content'] ,//发票明细
            'vat_id' => $order['vat_id'],//增值发票对应的id
            'invoice_type' => $order['invoice_type'],// 0普通发票，1增值发票
            'order_status' => $this->orderStatus($order['order_status']),
            'pay_status' => $this->payStatus($order['pay_status']),
            'shipping_status' => $this->shipStatus($order['shipping_status']),
            'pay_time' => $order['pay_time'],
            'pay_fee' => $order['pay_fee'],
            'pay_fee_formated' => price_format($order['pay_fee'], false),
            'pay_name' => $order['pay_name'],
            'shipping_fee' => $order['shipping_fee'],
            'shipping_fee_formated' => price_format($order['shipping_fee'], false),
            'shipping_id' => $order['shipping_id'],
            'shipping_name' => $order['shipping_name'],
            'total_amount' => $order['order_amount'] + $order['money_paid'] + $order['shipping_fee'],
            'total_amount_formated' => price_format($order['order_amount'] + $order['money_paid'] + $order['shipping_fee'], false),
        ];

        if (!empty($list)) {
            $orderGoods = $this->orderRepository->getOrderGoods($args['order_id']);
            $goodsList = [];
            $total_number = 0;
            foreach ($orderGoods as $k => $v) {
                $goodsList[$k]['goods_id'] = $v['goods_id'];
                $goodsList[$k]['goods_name'] = $v['goods_name'];
                $goodsList[$k]['goods_number'] = $v['goods_number'];
                $goodsList[$k]['goods_thumb'] = get_image_path($v['goods_thumb']);
                $goodsList[$k]['goods_price'] = $v['goods_price'];
                $goodsList[$k]['goods_price_formated'] = price_format($v['goods_price'], false);
                $goodsList[$k]['goods_sn'] = $v['goods_sn'];
                $goodsList[$k]['shop_name'] = $this->shopService->getShopName($v['ru_id']);

                $total_number += $v['goods_number'];
            }
            $list['goods'] = $goodsList;
            $list['total_number'] = $total_number;
        }

        return $list;
    }

    /**
     * 待评价订单列表
     * @param $args
     * @return mixed
     */
    public function orderAppraise($args)
    {
        $list = $this->orderRepository->getReceived($args['uid']);

        $orders = [];
        foreach ($list as $k => $v) {
            if (empty($v->rec_id)) {
                continue;
            }
            $orders[] = [
                'id'  => $v->goods_id,
                'oid' => $v->order_id,
                'goods_name' => $v->goods_name,
                'shop_price' => $v->goods_price,
                'goods_thumb' => get_image_path($v->goods_thumb),
            ];
        }

        return $orders;
    }

    /**
     * 待评价订单详情
     * @param $args
     * @return array
     */
    public function orderAppraiseDetail($args)
    {
        $list = $this->orderRepository->orderAppraiseDetail($args['uid'], $args['oid'], $args['gid']);
        if (empty($list)) {
            return [];
        }

        $arr = $list['goods'][0];
        $arr['goods_thumb'] = get_image_path($arr['goods_thumb']);
        return $arr;
    }

    /**
     * 添加评价
     * @param $args
     * @return boolean
     */
    public function orderAppraiseAdd($args)
    {
        $orderGoods = $this->orderGoodsRepository->orderGoodsByOidGid($args['oid'], $args['gid']);
        $userInfo = $this->userRepository->userInfo($args['uid']);
        $arr = [
            'comment_type' => 0,
            'id_value' => $args['gid'],
            'email' => 'email',
            'user_name' => $userInfo['user_name'],
            'content' => $args['content'],
            'comment_rank' => $args['rank'],
//            'comment_server' => $rank_server,
//            'comment_delivery' => $rank_delivery,
            'add_time' => gmtime(),
            'ip_address' => app('request')->ip(),
            'status' => (1 - app('config')->get('shop.comment_check')),
            'parent_id' => 0,
            'user_id' => $args['uid'],
            'single_id' => 0,
            'order_id' => $args['oid'],
            'rec_id' => empty($orderGoods) ? 0 : $orderGoods['rec_id'],
//            'goods_tag' => $goods_tag,
            'ru_id' => empty($orderGoods) ? 0 : $orderGoods['ru_id']
        ];
        return $this->commentRepository->orderAppraiseAdd($arr);
    }

    /**
     * 订单确认
     * @param $args
     * @return mixed
     */
    public function orderConfirm($args)
    {

        // 判断订单状态
        $order = $this->orderRepository->find($args['order_id']);
        if ($order['user_id'] != $args['uid']) {
            return ['code' => 1, 'msg' => '该订单不是本人'];
        } elseif ($order['order_status'] == OrderInfo::OS_CONFIRMED) {
            return ['code' => 1, 'msg' => '订单已确认'];
        } elseif ($order['shipping_status'] == OrderInfo::SS_RECEIVED) {
            return ['code' => 1, 'msg' => '已收货'];
        } elseif ($order['shipping_status'] != OrderInfo::SS_SHIPPED) {
            return ['code' => 1, 'msg' => '订单未发货，不能确认'];
        }

        // 确认订单 - 修改订单状态
        return $this->orderRepository->orderConfirm($args['uid'], $args['order_id']);
    }

    /**
     * 取消订单
     * @param $args
     * @return mixed
     * 订单状态只能是“未确认”或“已确认”
     * 发货状态只能是“未发货”
     * 如果付款状态是“已付款”、“付款中”，不允许取消，要取消和商家联系
     */
    public function orderCancel($args)
    {
        $order = $this->orderRepository->find($args['order_id']);

        if ($order['user_id'] != $args['uid']) {
            return ['error' => 1, 'msg' => '不是本人订单'];
        }

        // 订单状态只能是“未确认”或“已确认”
        if ($order['order_status'] !=  OrderInfo::OS_UNCONFIRMED && $order['order_status'] !=  OrderInfo::OS_CONFIRMED) {
            return ['error' => 1, 'msg' => '订单不能取消'];
        }
        // 发货状态只能是“未发货”
        if ($order['shipping_status'] !=  OrderInfo::SS_UNSHIPPED) {
            return ['error' => 1, 'msg' => '订单已确认'];
        }
        // 如果付款状态是“已付款”、“付款中”，不允许取消，要取消和商家联系
        if ($order['pay_status'] !=  OrderInfo::PS_UNPAYED) {
            return ['error' => 1, 'msg' => '订单已付款，请与商家联系'];
        }

        $res = $this->orderRepository->orderCancel($args['uid'], $args['order_id']);

        return $res;
    }

    private function orderStatus($num)
    {
        $array = ['未确认', '已确认', '已取消', '无效', '退货', '已分单', '部分分单'];  //订单状态

        return $array[$num];
    }
    private function payStatus($num)
    {
        $array = ['未付款', '付款中', '已付款']; //支付状态

        return $array[$num];
    }
    private function shipStatus($num)
    {
        $array = ['未发货', '已发货', '已收货', '备货中', '已发货(部分商品)', '发货中(处理分单)', '已发货(部分商品)']; //配送状态

        return $array[$num];
    }

    /**
     * 用户中心地址
     * @param $userId
     * @return mixed
     */
    public function userAddressList($userId)
    {
        $userInfo = $this->userRepository->userInfo($userId);
        $res = $this->addressRepository->addressListByUserId($userId);
        $default = empty($userInfo['address_id']) ? 0: $userInfo['address_id'];

        $list = array_map(function ($v) use ($default) {
            $v['country_name'] = $this->regionRepository->getRegionName($v['country']);
            $v['province_name'] = $this->regionRepository->getRegionName($v['province']);
            $v['city_name'] = $this->regionRepository->getRegionName($v['city']);
            $v['district_name'] = $this->regionRepository->getRegionName($v['district']);
            $v['street_name'] = $this->regionRepository->getRegionName($v['street']);
            $v['id'] = $v['address_id'];
            $v['default'] = ($v['address_id'] == $default) ? 1 : 0;

            unset($v['country'], $v['province'], $v['city'], $v['district'], $v['street'], $v['address_id'], $v['email'], $v['address_name']);

            $v['address'] = $v['country_name'] .' '. $v['province_name'] .' '. $v['city_name'] .' '. $v['district_name'] .' '. $v['street_name'] . ' ' . $v['address'];

            return $v;
        }, $res);

        return $list;
    }

    /**
     * 选择默认地址
     * @param array $args
     * @return mixed
     */
    public function addressChoice(array $args)
    {

        //确认ID是否为本人
        $res = $this->addressRepository->find($args['id']);
        if (empty($res) || $args['uid'] != $res['user_id']) {
            return false;
        }

        //修改操作
        return $this->userRepository->setDefaultAddress($args['id'], $args['uid']);
    }

    /**
     * 添加收货地址
     * @param array $args
     * @return int
     */
    public function addressAdd(array $args)
    {
        $arr = [
            'user_id' => $args['uid'],
            'consignee' => $args['consignee'],
            'email' => '',
            'country' => !empty($args['country']) ? $args['country'] : '',
            'province' => !empty($args['province']) ? $args['province'] : '',
            'city' => !empty($args['city']) ? $args['city'] : '',
            'district' => !empty($args['district']) ? $args['district'] : '',
            'address' => $args['address'],
            'mobile' => isset($args['mobile']) ? $args['mobile'] : '',
            'address_name' => '',
            'sign_building' => '',
            'best_time' => '',
        ];

        $res = $this->addressRepository->addAddress($arr);

        return $res;
    }

    /**
     * 收货地址详情
     * @param $args
     * @return mixed
     */
    public function addressDetail($args)
    {
        $res = $this->addressRepository->find($args['id']);
        if (empty($res) || $args['uid'] != $res['user_id']) {
            return false;
        }

        $address = [
            'id' => $res->address_id,
            'consignee' => $res->consignee,
            'province_id' => $res->province,
            'city_id' => $res->city,
            'district_id' => $res->district,
//            'country' => $this->regionRepository->getRegionName($res->country),
//            'province' => $this->regionRepository->getRegionName($res->province),
//            'city' => $this->regionRepository->getRegionName($res->city),
//            'district' => $this->regionRepository->getRegionName($res->district),
            'address' => $res->address,
            'mobile' => $res->mobile,
        ];

        //  地区列表 查找出所在地区的  列表
        $provinceList = $this->regionRepository->getRegionByParentId();  // 省列表
        $cityList = $this->regionRepository->getRegionByParentId($address['province_id']);  // 市列表
        $districtList = $this->regionRepository->getRegionByParentId($address['city_id']);  // 区列表

        return [
            'address' => $address,
            'province' => $provinceList,
            'city' => $cityList,
            'district' => $districtList,
        ];
    }
    /**
     * 编辑收货地址
     * @param $args
     * @return mixed
     */
    public function addressUpdate($args)
    {
        $arr = [
            'user_id' => $args['uid'],
            'consignee' => $args['consignee'],
            'email' => '',
//            'country' => !empty( $args['country'] ) ? $args['country'] : '',
            'province' => !empty($args['province']) ? $args['province'] : '',
            'city' => !empty($args['city']) ? $args['city'] : '',
            'district' => !empty($args['district']) ? $args['district'] : '',
            'address' => $args['address'],
            'mobile' => isset($args['mobile']) ? $args['mobile'] : '',
            'address_name' => '',
            'sign_building' => '',
            'best_time' => '',
        ];

        $res = $this->addressRepository->updateAddress($args['id'], $arr);
        return (int)$res;
    }

    /**
     * 删除收货地址
     * @param $args
     */
    public function addressDelete($args)
    {
        $res = $this->addressRepository->deleteAddress($args['id'], $args['uid']);
        return $res;
    }

    /**
     * 用户账户
     * @param $userId
     * @return array
     */
    public function userAccount($userId)
    {
        $userInfo = $this->userRepository->userInfo($userId);

        if (empty($userInfo)) {
            return [];
        }

        $result['user_money'] = $userInfo['user_money'];   //用户资金
        $result['frozen_money'] = $userInfo['frozen_money'];   // 冻结资金

        $result['pay_points'] = $userInfo['pay_points'];

        $result['bonus_num'] = $this->userBonusRepository->getUserBonusCount($userId);

        return $result;
    }

    /**
     * 账户明细
     * @param $args
     * @return mixed
     */
    public function accountDetail($args)
    {
        $list = $this->accountRepository->accountList($args['user_id'], $args['page'], $args['size']);

        $accountList = array_map(function ($v) {
            return [
                'log_sn' => $v['log_id'],
                'money' => $v['user_money'],
                'time' => $v['change_time']
            ];
        }, $list);

        return $accountList;
    }

    /**
     * 提现记录  （充值  退款）
     * @param $args
     * @return mixed
     */
    public function accountLog($args)
    {
        $list = $this->accountRepository->accountLogList($args['user_id'], $args['page'], $args['size']);


        $logList = array_map(function ($v) {
            return [
                'log_sn' => $v['id'],
                'money'  => $v['amount'],
                'time'   => $v['add_time'],
                'type'   => ($v['process_type'] == 0) ? '充值' : '提现',
                'status' => ($v['is_paid'] == 0) ? '未支付' : '已支付'
            ];
        }, $list);
        return $logList;
    }

    /**
     * 充值操作
     * @param $args
     * @return mixed
     */
    public function deposit($args)
    {
        $arr = [
            'user_id' => $args['uid'],
            'amount' => $args['amount'],
            'add_time' => gmtime(),
            'user_note' => $args['user_note'],
            'payment' => $args['payment'],
        ];

        return $this->accountRepository->deposit($arr);
    }

    /**
     * 我的收藏
     * @param $args
     * @return array
     */
    public function collectGoods($args)
    {
        $list = $this->collectGoodsRepository->findByUserId($args['user_id'], $args['page'], $args['size']);

        $collect = array_map(function ($v) {
            $goodsInfo = $this->goodsRepository->goodsInfo($v['goods_id']);

            return [
                'goods_name' => $goodsInfo['goods_name'],
                'shop_price'  => $goodsInfo['goods_price'],
                'goods_thumb'   => get_image_path($goodsInfo['goods_thumb']),
                'goods_stock'   => $goodsInfo['stock'],
                'time'     => $v['add_time'],
                'goods_id' => $v['goods_id']
            ];
        }, $list);


        return $collect;
    }

    /**
     * 添加收藏
     * @param $args
     * @return array
     */
    public function collectAdd($args)
    {
        $collectGoods = $this->collectGoodsRepository->findOne($args['id'], $args['uid']);
        if (empty($collectGoods)) {
            $result = $this->collectGoodsRepository->addCollectGoods($args['id'], $args['uid']);
        } else {
            $result = $this->collectGoodsRepository->deleteCollectGoods($args['id'], $args['uid']);
        }
        return $result;
    }

    /**
     * 店铺收藏
     * @param $args
     * @return array
     */
    public function collectStore($uid)
    {
        $list = $this->collectStoreRepository->findByUserId($uid);
        foreach ($list as $key => $value) {
            $collectnum = $this->storeRepository->collnum($value['ru_id']);
            $store = $this->storeRepository->detail($value['ru_id']);
            $list[$key]['logo_thumb'] = get_image_path(str_replace('../', '', $store['0']['sellershopinfo']['logo_thumb']));
            $list[$key]['store_name'] = $store['0']['sellershopinfo']['shop_title'];
            $list[$key]['store_id'] = $store['0']['sellershopinfo']['ru_id'];
            $list[$key]['collectnum'] = $collectnum;
        }
        return $list;
    }

    /**
     * 我的优惠券
     * @param $args
     * @return array
     */
    public function myConpont($args)
    {
        $list = [];

        return $list;
    }


    /**
     * 添加增值发票
     * @param array $args
     * @return int
     */
    public function invoiceAdd(array $args)
    {
        $invoice= $this->invoiceRepository->find($args['uid']);
        if (!empty($invoice)) {
            return false;
        }
        $arr = [
            'user_id' => $args['uid'],
            'company_name' => $args['company_name'],
            'company_address' => $args['company_address'],
            'tax_id' => $args['tax_id'],
            'company_telephone' => $args['company_telephone'] ,
            'bank_of_deposit' => $args['bank_of_deposit'],
            'bank_account' => $args['bank_account'],
            'consignee_name' => $args['consignee_name'],
            'consignee_mobile_phone' => $args['consignee_mobile_phone'],
            'country' => !empty($args['country']) ? $args['country'] : '',
            'province' => !empty($args['province']) ? $args['province'] : '',
            'city' => !empty($args['city']) ? $args['city'] : '',
            'district' => !empty($args['district']) ? $args['district'] : '',
            'consignee_address' => $args['consignee_address'],
            'audit_status' => 0,
            'add_time'=>gmtime()
        ];
        $res = $this->invoiceRepository->addInvoice($arr);

        return $res;
    }


    /**
     * 编辑增值发票
     * @param $args
     * @return mixed
     */
    public function invoiceUpdate($args)
    {
        $arr = [
            'user_id' => $args['uid'],
            'company_name' => $args['company_name'],
            'company_address' => $args['company_address'],
            'tax_id' => $args['tax_id'],
            'company_telephone' => $args['company_telephone'] ,
            'bank_of_deposit' => $args['bank_of_deposit'],
            'bank_account' => $args['bank_account'],
            'consignee_name' => $args['consignee_name'],
            'consignee_mobile_phone' => $args['consignee_mobile_phone'],
            'country' => !empty($args['country']) ? $args['country'] : '',
            'province' => !empty($args['province']) ? $args['province'] : '',
            'city' => !empty($args['city']) ? $args['city'] : '',
            'district' => !empty($args['district']) ? $args['district'] : '',
            'consignee_address' => $args['consignee_address'],
            'audit_status' => 0,
            'add_time'=>gmtime()
        ];

        $res = $this->invoiceRepository->updateInvoice($args['id'], $arr);
        return (int)$res;
    }

    /**
     * 增值发票详情
     * @param $args
     * @return mixed
     */
    public function invoiceDetail(array $args)
    {
        $res = $this->invoiceRepository->find($args['uid']);

        if (empty($res) || $args['uid'] != $res['user_id']) {
            return false;
        }
        $invoice = [
            'id' => $res->id,
            'company_name' => $res->company_name,
            'company_address' => $res->company_address,
            'tax_id' => $res->tax_id,
            'company_telephone' => $res->company_telephone,
            'bank_of_deposit' => $res->bank_of_deposit,
            'bank_account' => $res->bank_account,
            'consignee_name' => $res->consignee_name,
            'consignee_mobile_phone' => $res->consignee_mobile_phone,
            'consignee_address' => $res->consignee_address,
            'country' => $res->country,
            'province_name' => $this->regionRepository->getRegionName($res->province),
            'city_name' => $this->regionRepository->getRegionName($res->city),
            'district_name' => $this->regionRepository->getRegionName($res->district),
            'province' => $res->province,
            'city' => $res->city,
            'district' => $res->district,
            'audit_status' => $res->audit_status,
        ];

        //  地区列表 查找出所在地区的  列表
        $provinceList = $this->regionRepository->getRegionByParentId();  // 省列表
        $cityList = $this->regionRepository->getRegionByParentId($invoice['province']);  // 市列表
        $districtList = $this->regionRepository->getRegionByParentId($invoice['city']);  // 区列表

        return [
            'invoice' => $invoice,
            'province' => $provinceList,
            'city' => $cityList,
            'district' => $districtList,
            'country'=>1,
        ];
    }

    /**
     * 删除增值发票
     * @param $args
     */
    public function invoiceDelete($args)
    {
        $res = $this->invoiceRepository->deleteInvoice($args['id'], $args['uid']);
        return $res;
    }
}
