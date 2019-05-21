<?php

namespace App\Repositories\Order;

use App\Models\Goods;
use App\Models\Products;
use App\Models\OrderInfo;
use App\Models\OrderGoods;
use Illuminate\Support\Facades\DB;
use App\Repositories\Cart\CartRepository;
use App\Repositories\Bonus\BonusTypeRepository;
use App\Repositories\Shipping\ShippingRepository;
use App\Contracts\Repositories\Order\OrderRepositoryInterface;

class OrderRepository implements OrderRepositoryInterface
{
    private $cartRepository;
    private $bonusTypeRepository;
    private $shippingRepository;

    /**
     * OrderRepository constructor.
     * @param CartRepository $cartRepository
     * @param BonusTypeRepository $bonusTypeRepository
     * @param ShippingRepository $shippingRepository
     */
    public function __construct(
        CartRepository $cartRepository,
        BonusTypeRepository $bonusTypeRepository,
        ShippingRepository $shippingRepository
    ) {
        $this->cartRepository = $cartRepository;
        $this->bonusTypeRepository = $bonusTypeRepository;
        $this->shippingRepository = $shippingRepository;
    }

    /**
     * 订单数量
     * @param $id
     * @param int $status  0 待付款 1 已付款  （2 发货中） 3 已收货 待评价
     * @return mixed
     */
    public function orderNum($id, $status = null)
    {
        $model = OrderInfo::select('*')
            ->where('user_id', $id)
            ->where('order_status', '<>', OrderInfo::OS_CANCELED)
            ->where('main_order_id', '<>', 0);

        // 全部订单
        if ($status === null) {
            $orderNum = $model->count();

            return $orderNum;
        }
        // 0 待付款
        if ($status === OrderInfo::STATUS_CREATED) {
            $model->wherein('pay_status', [OrderInfo::PS_UNPAYED]);
        }

        if (!empty($status)) {
            switch ($status) {

                // 1 已付款
                case OrderInfo::STATUS_PAID:
                    $model->wherein('pay_status', [OrderInfo::PS_PAYED]);
                    break;
                // 2 发货中
                case OrderInfo::STATUS_DELIVERING:
                    $model->wherein('shipping_status', [OrderInfo::SS_SHIPPED, OrderInfo::SS_SHIPPED_PART, OrderInfo::OS_SHIPPED_PART]);
                    break;

                // 3 已收货 待评价
                case OrderInfo::STATUS_DELIVERIED:
                    $model->wherein('shipping_status', [OrderInfo::SS_RECEIVED]);
                    break;
            }
        }

        $orderNum = $model->count();
        return $orderNum;
    }

    /**
     * 获取已收货(待评价)商品列表
     * @param $id
     * @return array
     */
    public function getReceived($id)
    {
        $sql =" select og.order_id, og.goods_id, og.goods_name, og.goods_attr, og.goods_price, g.goods_thumb, g.user_id, og.rec_id from dsc_order_info oi ";
        $sql .= " left join dsc_order_goods og on oi.order_id=og.order_id ";
        $sql .= " left join dsc_goods g on og.goods_id=g.goods_id ";
        $sql .= " where oi.user_id={$id} AND order_status <> 2 AND shipping_status = 2 AND not exists (select 1 from `dsc_comment` where dsc_comment.rec_id = og.rec_id) ";
        $list = DB::select($sql);
        return $list;
    }

    /**
     * 待评价详情
     * @param $id
     * @param $goodsId
     * @param $orderId
     * @return mixed
     */
    public function orderAppraiseDetail($id, $orderId, $goodsId)
    {
        $model = OrderInfo::select('order_id')
            ->with(['goods' => function ($query) use ($goodsId) {
                $query
                    ->leftjoin('goods', 'goods.goods_id', '=', 'order_goods.goods_id')
                    ->leftjoin('comment', 'comment.rec_id', '=', 'order_goods.rec_id')
                    ->where('goods.goods_id', $goodsId)
                    ->select('order_goods.order_id', 'order_goods.goods_id', 'order_goods.goods_name', 'order_goods.goods_attr', 'order_goods.goods_price', 'goods.goods_thumb', 'order_goods.goods_price', 'comment.rec_id');
            }])
            ->where('user_id', $id)
            ->where('order_id', $orderId)
            ->where('order_status', '<>', OrderInfo::OS_CANCELED);

        $model->wherein('shipping_status', [OrderInfo::SS_RECEIVED]);

        $list = $model->first();
        if ($list == null) {
            return [];
        }

        return $list->toArray();
    }

    /**
     * 订单详情
     * @param $uid
     * @param $orderId
     * @return mixed
     */
    public function orderDetail($uid, $orderId)
    {
        $order = OrderInfo::select('*')
            ->where('user_id', $uid)
            ->where('order_id', $orderId)
            ->first();

        if ($order == null) {
            return [];
        }

        return $order;
    }

    /**
     * 查找订单id
     * @param $orderId
     * @return array
     */
    public function find($orderId)
    {
        $order = OrderInfo::where('order_id', $orderId)
            ->first();

        if ($order == null) {
            return [];
        }

        return $order;
    }

    /**
     * 取消订单
     * @param $uid
     * @param $orderId
     */
    public function orderCancel($uid, $orderId)
    {
        $order = OrderInfo::where('user_id', $uid)
            ->where('order_id', $orderId)
            ->first();

        $order->order_status = 2;
        return $order->save();
    }

    /**
     * 确认订单
     * @param $uid
     * @param $orderId
     */
    public function orderConfirm($uid, $orderId)
    {
        $order = OrderInfo::where('user_id', $uid)
            ->where('order_id', $orderId)
            ->first();

        $order->order_status = 1;
        $order->shipping_status = OrderInfo::SS_RECEIVED;
        $order->confirm_take_time = gmtime();

        return $order->save();
    }

    /**
     * 将订单改为已支付状态 批量操作
     * @param $uid
     * @param $orderId
     * @return mixed
     */
    public function orderPay($uid, array $orderId)
    {
        $array = [
            'order_status' => OrderInfo::OS_CONFIRMED,
            'pay_status' => OrderInfo::PS_PAYED,
            'pay_time' => gmtime(),
            'money_paid' => DB::Raw('order_amount'),
            'order_amount' => 0
        ];

        return OrderInfo::where('user_id', $uid)
            ->wherein('order_id', $orderId)
            ->update($array);
    }

    /**
     * 获取订单商品
     * @param $orderId
     * @return mixed
     */
    public function getOrderGoods($orderId)
    {
        $goods = OrderGoods::where('order_id', $orderId)
            ->select('goods.goods_thumb', 'order_goods.goods_price', 'order_goods.goods_number', 'order_goods.goods_id', 'order_goods.goods_name', 'order_goods.goods_sn', 'order_goods.ru_id')
            ->join('goods', 'goods.goods_id', '=', 'order_goods.goods_id')
            ->get();

        if ($goods == null) {
            return [];
        }
        return $goods->toArray();
    }

    /**
     * 获取子订单
     * @param $orderId
     * @return mixed
     */
    public function getChildOrder($orderId)
    {
        return OrderInfo::where('main_order_id', $orderId)
            ->select('order_id')
            ->get()
            ->toArray();
    }

    /**
     * 根据用户ID查询订单
     * @param $id
     * @param $status
     * @param $page
     * @param $size
     * @return mixed
     */
    public function getOrderByUserId($id, $status = 0, $page=0, $size=10)
    {
        $model = OrderInfo::select('*')
            ->where('user_id', $id)
            ->where('main_order_id', '<>', 0)
            ->where('order_status', '<>', OrderInfo::OS_CANCELED);

        if (!empty($status)) {
            switch ($status) {
                case OrderInfo::STATUS_PAID:
                    $model->wherein('pay_status', [OrderInfo::PS_UNPAYED]);
                    break;

                case OrderInfo::STATUS_DELIVERING:
                    $model->wherein('shipping_status', [OrderInfo::SS_SHIPPED, OrderInfo::SS_SHIPPED_PART, OrderInfo::OS_SHIPPED_PART]);
                    break;
            }
        }

        $order = $model
            ->select(['order_id', 'order_sn', 'order_status', 'shipping_status', 'pay_status','goods_amount','order_amount','add_time','shipping_status','shipping_status', 'money_paid', 'shipping_fee'])
            ->with(['goods' => function ($query) {
                $query
                    ->leftjoin('goods', 'goods.goods_id', '=', 'order_goods.goods_id')
                    ->select('order_goods.order_id', 'order_goods.goods_number', 'order_goods.goods_id', 'order_goods.goods_name', 'order_goods.goods_attr', 'order_goods.goods_price', 'goods.goods_thumb', 'goods.user_id');
            }])
            ->orderBy('add_time', 'DESC')
            ->offset(($page - 1) * $size)
            ->limit($size)
            ->get()
            ->toArray();

        return $order;
    }

    /**
     * 插入订单
     * @param $order
     * @return bool|mixed
     */
    public function insertGetId($order)
    {
        $orderModel = new OrderInfo();

        foreach ($order as $k => $v) {
            $orderModel->$k = $v;
        }
        $res = $orderModel->save();

        if ($res) {
            return $orderModel->order_id;
        }
        return false;
    }


    /**
     * 改变订单中商品库存
     * @param   int     $order_id   订单号
     * @param   bool    $is_dec     是否减少库存
     * @param   bool    $storage     减库存的时机，1，下订单时；0，发货时；
     */
    public function changeOrderGoodsStorage($order_id, $is_dec = true, $storage = 0)
    {
        /* 查询订单商品信息 */
        switch ($storage) {
            case 0:
                $res = OrderGoods::where('order_id', $order_id)
                    ->where('is_real', 1)
                    ->groupBy('goods_id')
                    ->groupBy('product_id')
                    ->select(['sum(send_number) as num','goods_id,max(extension_code) as extension_code','product_id'])
                    ->get()
                    ->toArray();
                break;

            case 1:
                $res = OrderGoods::where(['order_id'=>$order_id])->where(['is_real'=>1])
                    ->groupBy('goods_id')
                    ->groupBy('product_id')
                    ->selectRaw('sum(goods_number) as num, goods_id,max(extension_code) as extension_code, product_id')
                    ->get()
                    ->toArray();
                break;
        }
        foreach ($res as $key => $row) {
            if ($row['extension_code'] != "package_buy") {
                if ($is_dec) {
                    $this->change_goods_storage($row['goods_id'], $row['product_id'], - $row['num']);
                } else {
                    $this->change_goods_storage($row['goods_id'], $row['product_id'], $row['num']);
                }
                // $GLOBALS['db']->query($sql);
            } else {   //package_buy

                // $sql = "SELECT goods_id, goods_number" .
                //        " FROM " . $GLOBALS['ecs']->table('package_goods') .
                //        " WHERE package_id = '" . $row['goods_id'] . "'";
                // $res_goods = $GLOBALS['db']->query($sql);
                // while ($row_goods = $GLOBALS['db']->fetchRow($res_goods))
                // {
                //     $sql = "SELECT is_real" .
                //        " FROM " . $GLOBALS['ecs']->table('goods') .
                //        " WHERE goods_id = '" . $row_goods['goods_id'] . "'";
                //     $real_goods = $GLOBALS['db']->query($sql);
                //     $is_goods = $GLOBALS['db']->fetchRow($real_goods);

                //     if ($is_dec)
                //     {
                //         self::change_goods_storage($row_goods['goods_id'], $row['product_id'], - ($row['num'] * $row_goods['goods_number']));
                //     }
                //     elseif ($is_goods['is_real'])
                //     {
                //         self::change_goods_storage($row_goods['goods_id'], $row['product_id'], ($row['num'] * $row_goods['goods_number']));
                //     }
                // }
            }
        }
        //
    }

    /**
     * 商品库存增与减 货品库存增与减
     *
     * @param   int    $good_id         商品ID
     * @param   int    $product_id      货品ID
     * @param   int    $number          增减数量，默认0；
     *
     * @return  bool               true，成功；false，失败；
     */
    public function change_goods_storage($good_id, $product_id, $number = 0)
    {
        if ($number == 0) {
            return true; // 值为0即不做、增减操作，返回true
        }

        if (empty($good_id) || empty($number)) {
            return false;
        }

        $number = ($number > 0) ? '+ ' . $number : $number;
        /* 处理货品库存 */
        $products_query = true;
        if (!empty($product_id)) {
            $products_query = Products::where('goods_id', $good_id)
                ->where('product_id', $product_id)
                ->first();
            $products_query->product_number += $number;
            $products_query->save();
        }

        /* 处理商品库存 */
        $query = Goods::where('goods_id', $good_id)
            ->first();
        $query->goods_number += $number;
        $query->save();
        if ($query && $products_query) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 获得订单中的费用信息
     *
     * @access  public
     * @param   array   $order
     * @param   array   $goods
     * @param   array   $consignee
     * @param   bool    $is_gb_deposit  是否团购保证金（如果是，应付款金额只计算商品总额和支付费用，可以获得的积分取 $gift_integral）
     * @return  array
     */
    public function order_fee($order, $goods, $consignee, $cart_good_id = 0, $shipping, $consignee_id)
    {
        /* 初始化订单的扩展code */
        if (!isset($order['extension_code'])) {
            $order['extension_code'] = '';
        }

        $total  = ['real_goods_count' => 0,
            'gift_amount'      => 0,
            'goods_price'      => 0,
            'market_price'     => 0,
            'discount'         => 0,
            'pack_fee'         => 0,
            'card_fee'         => 0,
            'shipping_fee'     => 0,
            'shipping_insure'  => 0,
            'integral_money'   => 0,
            'bonus'            => 0,
            'surplus'          => 0,
            'cod_fee'          => 0,
            'pay_fee'          => 0,
            'tax'              => 0];
        $weight = 0;
        $newGoodsList = [];
        /* 商品总价 */
        foreach ($goods as $val) {
            foreach ($val['goods'] as $v) {
                /* 统计实体商品的个数 */
                if (!empty($v['is_real'])) {
                    $total['real_goods_count']++;
                }

                $total['goods_price']  += $v['goods_price'] * $v['goods_number'];
                $total['market_price'] += $v['market_price'] * $v['goods_number'];

                //
                $newGoodsList[] = ['goods' => $v];
            }
            //
        }

        $total['saving']    = $total['market_price'] - $total['goods_price'];
        $total['save_rate'] = $total['market_price'] ? round($total['saving'] * 100 / $total['market_price']) . '%' : 0;

        $total['goods_price_formated']  = price_format($total['goods_price'], false);
        $total['market_price_formated'] = price_format($total['market_price'], false);
        $total['saving_formated']       = price_format($total['saving'], false);
        /* 折扣 */
        $total['discount'] = $this->cartRepository->computeDiscountCheck($goods);
        if ($total['discount'] > $total['goods_price']) {
            $total['discount'] = $total['goods_price'];
        }

        $total['discount_formated'] = price_format($total['discount'], false);

        /* 税额  暂时没有用 */
        if (!empty($order['need_inv']) && $order['inv_type'] != '') {
            /* 查税率 */
            $rate = 0;
            foreach ($GLOBALS['_CFG']['invoice_type']['type'] as $key => $type) {
                if ($type == $order['inv_type']) {
                    $rate = floatval($GLOBALS['_CFG']['invoice_type']['rate'][$key]) / 100;
                    break;
                }
            }
            if ($rate > 0) {
                $total['tax'] = $rate * $total['goods_price'];
            }
        }
        $total['tax_formated'] = price_format($total['tax'], false);

        /* 包装费用 */

        /* 贺卡费用 */

        /* 红包 */

        if (!empty($order['bonus_id'])) {
            $bonus          = $this->bonusTypeRepository->bonusInfo($order['bonus_id']);
            $total['bonus'] = $bonus['type_money'];
        }
        $total['bonus_formated'] = price_format($total['bonus'], false);

        /* 线下红包 */
        if (!empty($order['bonus_kill'])) {
            $total['bonus_kill'] = $order['bonus_kill'];
            $total['bonus_kill_formated'] = price_format($total['bonus_kill'], false);
        }

        /* 配送费用 */
        $shipping_cod_fee = null;
        $shippingArr = explode(',', $order['shipping_id']);
        if (count($shippingArr) > 0 && $total['real_goods_count'] > 0) {
            $region['country']  = $consignee['country'];
            $region['province'] = $consignee['province'];
            $region['city']     = $consignee['city'];
            $region['district'] = $consignee['district'];

            $shippingFee = 0;
            foreach ($shippingArr as $k => $v) {
                $temp = explode('|', $v);

                $shipFee = $this->shippingRepository->total_shipping_fee($consignee_id, $newGoodsList, $temp[1], $temp[0]);
                $newShipFee = strip_tags(preg_replace('/([\x80-\xff]*|[a-zA-Z])/i', '', $shipFee));

                if (floatval($newShipFee) > 0) {
                    $shippingFee += $newShipFee;
                    $total['shipping_fee_list'][$temp[0]] = $newShipFee;
                }
            }
            $total['shipping_fee'] = $shippingFee;
        }
        $total['shipping_fee_formated']    = price_format($total['shipping_fee'], false);

        // 购物车中的商品能享受红包支付的总额
        $bonus_amount = $this->cartRepository->computeDiscountCheck($goods);
        // 红包和积分最多能支付的金额为商品总额
        $max_amount = $total['goods_price'] == 0 ? $total['goods_price'] : $total['goods_price'] - $bonus_amount;

        /* 计算订单总额 */
        if ($order['extension_code'] == 'group_buy' && $group_buy['deposit'] > 0) {
            $total['amount'] = $total['goods_price'];
        } else {
            $total['amount'] = $total['goods_price'] - $total['discount'] + $total['tax'] + $total['pack_fee'] + $total['card_fee'] +$total['shipping_fee'] + $total['shipping_insure'] + $total['cod_fee'];

            // 减去红包金额
            $use_bonus        = min($total['bonus'], $max_amount); // 实际减去的红包金额
            if (isset($total['bonus_kill'])) {
                $use_bonus_kill   = min($total['bonus_kill'], $max_amount);
                $total['amount'] -=  $price = number_format($total['bonus_kill'], 2, '.', ''); // 还需要支付的订单金额
            }

            $total['bonus']   = $use_bonus;
            $total['bonus_formated'] = price_format($total['bonus'], false);

            $total['amount'] -= $use_bonus; // 还需要支付的订单金额
            $max_amount      -= $use_bonus; // 积分最多还能支付的金额
        }

        /* 积分 */
        $order['integral'] = $order['integral'] > 0 ? $order['integral'] : 0;
        if ($total['amount'] > 0 && $max_amount > 0 && $order['integral'] > 0) {
            $integral_money = self::value_of_integral($order['integral']);

            // 使用积分支付
            $use_integral            = min($total['amount'], $max_amount, $integral_money); // 实际使用积分支付的金额
            $total['amount']        -= $use_integral;
            $total['integral_money'] = $use_integral;
            $order['integral']       = self::integral_of_value($use_integral);
        } else {
            $total['integral_money'] = 0;
            $order['integral']       = 0;
        }
        $total['integral'] = $order['integral'];
        $total['integral_formated'] = price_format($total['integral_money'], false);

        /* 保存订单信息 */
        // $_SESSION['flow_order'] = $order;

        $se_flow_type = isset($_SESSION['flow_type']) ? $_SESSION['flow_type'] : '';

        /* 支付费用 */
        // if (!empty($order['pay_id']) && ($total['real_goods_count'] > 0 || $se_flow_type != CART_EXCHANGE_GOODS))
        // {
        //     $total['pay_fee']      = pay_fee($order['pay_id'], $total['amount'], $shipping_cod_fee);
        // }

        // $total['pay_fee_formated'] = price_format($total['pay_fee'], false);

        // $total['amount']           += $total['pay_fee']; // 订单总额累加上支付费用
        // $total['amount_formated']  = price_format($total['amount'], false);

        /* 取得可以得到的积分和红包 */
        if ($order['extension_code'] == 'group_buy') {
            $total['will_get_integral'] = $group_buy['gift_integral'];
        } elseif ($order['extension_code'] == 'exchange_goods') {
            $total['will_get_integral'] = 0;
        } else {
            $total['will_get_integral'] = $this->cartRepository->getGiveIntegral();
        }
        $total['will_get_bonus']        = 0;

        $total['formated_goods_price']  = price_format($total['goods_price'], false);
        $total['formated_market_price'] = price_format($total['market_price'], false);
        $total['formated_saving']       = price_format($total['saving'], false);

        return $total;
    }
}
