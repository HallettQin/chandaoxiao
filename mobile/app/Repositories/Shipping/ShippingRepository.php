<?php

namespace App\Repositories\Shipping;

use App\Models\Goods;
use App\Models\Shipping;
use App\Models\ShippingArea;
use App\Repositories\Goods\GoodsRepository;
use App\Repositories\User\AddressRepository;
use App\Contracts\Repositories\Shipping\ShippingRepositoryInterface;

class ShippingRepository implements ShippingRepositoryInterface
{
    private $goodsRepository;
    private $addressRepository;

    public function __construct(GoodsRepository $goodsRepository, AddressRepository $addressRepository)
    {
        $this->goodsRepository = $goodsRepository;
        $this->addressRepository = $addressRepository;
    }

    public function shippingList()
    {
        $shippingList = Shipping::select('*')
//            ->where()
            ->get()
            ->toArray();

        return $shippingList;
    }

    /**
     * 查找配送方式
     * @param $id
     * @return array
     */
    public function find($id)
    {
        $shipping = Shipping::select('*')
            ->where('shipping_id', $id)
            ->where('enabled', 1)
            ->first();

        if ($shipping === null) {
            return [];
        }
        return $shipping->toArray();
    }

    /**
     * 计算配送费用
     * @param $address
     * @param $products
     * @param $shipping_id
     * @param $ruId
     * @return bool|int|string
     */
    public function total_shipping_fee($address, $products, $shipping_id, $ruId = 0)
    {
        $weight = 0;
        $amount = 0;
        $number = 0;

        foreach ($products as $key => $value) {
            $pro[$key]['goods_id'] = $value['goods']['goods_id'];
            $pro[$key]['goods_number'] = $value['goods']['goods_number'];
            $pro[$key]['is_shipping'] = $value['goods']['is_shipping'];
        }

        //如果传products对象 json后数组

        $IsShippingFree = true;

        if (isset($pro)) {
            foreach ($pro as $product) {
                $goods_weight = Goods::where(['goods_id' => $product['goods_id']])
                    ->pluck('goods_weight')
                    ->toArray();

                $goods_weight = $goods_weight[0];

                $goods_weight = (count($goods_weight) > 0) ? $goods_weight[0] : 0;

                if ($goods_weight) {
                    $weight += $goods_weight * $product['goods_number'];
                }

                $amount += $this->goodsRepository->getFinalPrice($product['goods_id'], $product['goods_number']);
                $number += $product['goods_number'];

                if (!intval($product['is_shipping'])) {
                    $IsShippingFree = false;
                }
            }
        }

        // 查看进货单中是否全为免运费商品，若是则把运费赋为零
        if ($IsShippingFree) {
            return 0;
        }

        //商家配送方式
        $result = ShippingArea::select('shipping_area.*')
            ->with(['shipping' => function ($query) {
                $query->select('shipping_id', 'shipping_name', 'insure', 'shipping_code');
            }])
            ->where('ru_id', $ruId)
            ->where('shipping_id', $shipping_id)
            ->first();

        if ($result === null) {
            $result = [];
        } else {
            $result = $result->toArray();
        }

        if (!empty($result['configure'])) {
            $configure = $this->getConfigure($result['configure']);
            $fee = $this->calculate($configure, $result['shipping']['shipping_code'], $weight, $amount, $number);
            return price_format($fee, false);
        }
        return false;
    }


    /**
     * 计算订单的配送费用的函数
     *
     */
    private function calculate($configure, $shipping_code, $goods_weight, $goods_amount, $goods_number)
    {
        $fee = 0;
        if ($configure['free_money'] > 0 && $goods_amount >= $configure['free_money']) {
            return $fee;
        }

        switch ($shipping_code) {
            case 'city_express':
            case 'flat':
                $fee = isset($configure['base_fee']) ? $configure['base_fee'] : 0;
                break;

            case 'ems':
                $fee = isset($configure['base_fee']) ? $configure['base_fee'] : 0;
                $configure['fee_compute_mode'] = !empty($configure['fee_compute_mode']) ? $configure['fee_compute_mode'] : 'by_weight';

                if ($configure['fee_compute_mode'] == 'by_number') {
                    $fee = $goods_number * $configure['item_fee'];
                } else {
                    if ($goods_weight > 0.5) {
                        $fee += (ceil(($goods_weight - 0.5) / 0.5)) * $configure['step_fee'];
                    }
                }
                break;

            case 'post_express':
                $fee = isset($configure['base_fee']) ? $configure['base_fee'] : 0;
                $configure['fee_compute_mode'] = !empty($configure['fee_compute_mode']) ? $configure['fee_compute_mode'] : 'by_weight';

                if ($configure['fee_compute_mode'] == 'by_number') {
                    $fee = $goods_number * $configure['item_fee'];
                } else {
                    if ($goods_weight > 5) {
                        $fee += 8 * $configure['step_fee'];
                        $fee += (ceil(($goods_weight - 5) / 0.5)) * $configure['step_fee1'];
                    } else {
                        if ($goods_weight > 1) {
                            $fee += (ceil(($goods_weight - 1) / 0.5)) * $configure['step_fee'];
                        }
                    }
                }
                break;

            case 'post_mail':
                $fee = $configure['base_fee'] + $configure['pack_fee'];
                $configure['fee_compute_mode'] = !empty($configure['fee_compute_mode']) ? $configure['fee_compute_mode'] : 'by_weight';

                if ($configure['fee_compute_mode'] == 'by_number') {
                    $fee = $goods_number * ($configure['item_fee'] + $configure['pack_fee']);
                } else {
                    if ($goods_weight > 5) {
                        $fee += 4 * $configure['step_fee'];
                        $fee += (ceil(($goods_weight - 5))) * $configure['step_fee1'];
                    } else {
                        if ($goods_weight > 1) {
                            $fee += (ceil(($goods_weight - 1))) * $configure['step_fee'];
                        }
                    }
                }
                break;
            case 'presswork':
                $fee = $goods_weight * 4 + 3.4;

                if ($goods_weight > 0.1) {
                    $fee += (ceil(($goods_weight - 0.1) / 0.1)) * 0.4;
                }
                break;

            case 'sf_express':
            case 'sto_express':
            case 'yto':
                if ($configure['free_money'] > 0 && $goods_amount >= $configure['free_money']) {
                    return 0;
                } else {
                    $fee = isset($configure['base_fee']) ? $configure['base_fee'] : 0;
                    $configure['fee_compute_mode'] = !empty($configure['fee_compute_mode']) ? $configure['fee_compute_mode'] : 'by_weight';

                    if ($configure['fee_compute_mode'] == 'by_number') {
                        $fee = $goods_number * $configure['item_fee'];
                    } else {
                        if ($goods_weight > 1) {
                            $fee += (ceil(($goods_weight - 1))) * $configure['step_fee'];
                        }
                    }
                }
                break;
            case 'zto':
                $fee = isset($configure['base_fee']) ? $configure['base_fee'] : 0;
                $configure['fee_compute_mode'] = !empty($configure['fee_compute_mode']) ? $configure['fee_compute_mode'] : 'by_weight';

                if ($configure['fee_compute_mode'] == 'by_number') {
                    $fee = $goods_number * $configure['item_fee'];
                } else {
                    if ($goods_weight > 1) {
                        $fee += (ceil(($goods_weight - 1))) * $configure['step_fee'];
                    }
                }
                break;

            default:
                $fee = 0;
                break;
        }
        $fee = floatval($fee);

        return $fee;
    }

    /**
     * 取得某配送方式对应于某收货地址的区域配置信息
     * @param $configure
     */
    private function getConfigure($configure)
    {
        $data = [];
        $configure = unserialize($configure);
        foreach ($configure as $key => $val) {
            $data[$val['name']] = $val['value'];
        }

        return $data;
    }
}
