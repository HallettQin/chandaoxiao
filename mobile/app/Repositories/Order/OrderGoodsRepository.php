<?php

namespace App\Repositories\Order;

use App\Models\OrderGoods;
use App\Contracts\Repositories\Order\OrderGoodsRepositoryInterface;

class OrderGoodsRepository implements OrderGoodsRepositoryInterface
{

    /**
     * 添加订单对应商品
     * @param $goods
     * @param $orderId
     */
    public function insertOrderGoods($goods, $orderId = 0)
    {
        foreach ($goods as $v) {
            if (empty($orderId)) {
                $newOrderId = $v['order_id'];
            } else {
                $newOrderId = $orderId;
            }
            $orderGoods                 = new OrderGoods;
            $orderGoods->order_id       = $newOrderId;
            $orderGoods->goods_id       = $v['goods_id'];
            $orderGoods->goods_name     = $v['goods_name'];
            $orderGoods->goods_sn       = $v['goods_sn'];
            $orderGoods->product_id     = $v['product_id'];
            $orderGoods->goods_number   = $v['goods_number'];
            $orderGoods->market_price   = $v['market_price'];
            $orderGoods->goods_price    = $v['goods_price'];
            $orderGoods->goods_attr     = $v['goods_attr'];
            $orderGoods->is_real        = $v['is_real'];
            $orderGoods->extension_code = $v['extension_code'];
            $orderGoods->parent_id      = $v['parent_id'];
            $orderGoods->is_gift        = $v['is_gift'];
            $orderGoods->ru_id          = $v['ru_id'];
            $orderGoods->goods_attr_id  = $v['goods_attr_id'];
            $orderGoods->save();
        }
    }

    /**
     * 根据 商品ID  订单ID  查找  orderGoods记录
     * @param $oid
     * @param $gid
     * @return mixed
     */
    public function orderGoodsByOidGid($oid, $gid)
    {
        $model = OrderGoods::where('order_id', $oid)
            ->where('goods_id', $gid)
            ->first();

        if ($model === null) {
            return [];
        }
        return $model->toArray();
    }
}
