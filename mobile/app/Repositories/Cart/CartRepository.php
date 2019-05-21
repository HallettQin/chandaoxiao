<?php

namespace App\Repositories\Cart;

use App\Models\Cart;
use App\Models\ShippingArea;
use App\Services\AuthService;
use App\Models\FavourableActivity;
use App\Repositories\Shop\ShopRepository;
use App\Repositories\Goods\GoodsRepository;
use App\Repositories\User\UserRankRepository;
use App\Repositories\ShopConfig\ShopConfigRepository;
use App\Contracts\Repositories\Cart\CartRepositoryInterface;

/**
 * Class CartRepository
 * @package App\Repositories\cart
 */
class CartRepository implements CartRepositoryInterface
{
    private $model;
    private $shopConfigRepository;
    private $userRankRepository;
    private $authService;
    private $goodsRepository;
    private $shopRepository;

    /**
     * CartRepository constructor.
     * @param ShopConfigRepository $shopConfigRepository
     * @param UserRankRepository $userRankRepository
     * @param AuthService $authService
     * @param GoodsRepository $goodsRepository
     * @param ShopRepository $shopRepository
     */
    public function __construct(
        ShopConfigRepository $shopConfigRepository,
        UserRankRepository $userRankRepository,
        AuthService $authService,
        GoodsRepository $goodsRepository,
        ShopRepository $shopRepository
    ) {
        $this->shopConfigRepository = $shopConfigRepository;
        $this->userRankRepository = $userRankRepository;
        $this->authService = $authService;
        $this->goodsRepository = $goodsRepository;
        $this->shopRepository = $shopRepository;
        $this->model = Cart::where('rec_id', '<>', 0);
    }


    /**
     * 添加字段
     * @param $columns
     * @return Cart
     */
    public function field($columns)
    {
        $this->model->select($columns);
        return $this;
    }

    /**
     * 根据ID返回购物商品
     * @param $rec_id
     * @return mixed
     */
    public function find($rec_id)
    {
        $cart = $this->model->where('rec_id', $rec_id)
            ->first();
        if ($cart === null) {
            return [];
        }

        return $cart->toArray();
    }

    /**
     * 商品添加到进货单
     * @param $params
     * @return bool
     */
    public function addGoodsToCart($params)
    {
        $model = new Cart();

        foreach ($params as $k => $v) {
            $model->{$k} = $v;
        }

        $res = $model->save();
        if ($res) {
            return $model->goods_number;
        }
        return false;
    }

    /**
     * 获取进货单所有商品
     * 暂时没有用
     * @return mixed
     */
    public function getAllCartGoods()
    {
        $cart = Cart::select('rec_id', 'user_id', 'goods_id', 'goods_name', 'market_price', 'goods_price', 'goods_number', 'goods_attr', 'ru_id')
            ->get()
            ->toArray();

        return $cart;
    }

    /**
     * 根据  商品ID 商品属性 用户ID 获取进货单记录
     * @param $uid
     * @param $goodsId
     * @param $goodsAttr
     * @return mixed
     */
    public function getCartByGoods($uid, $goodsId, $goodsAttr = '')
    {
        $cart = Cart::where('user_id', $uid)
            ->where('goods_id', $goodsId)
            ->where('goods_attr_id', $goodsAttr)
            ->first();

        if ($cart === null) {
            return [];
        }

        return $cart->toArray();
    }

    /**
     * 根据用户ID查询  进货单商品数量
     * @param $id  用户ID
     * @return mixed
     */
    public function goodsNumInCartByUser($id)
    {
        return Cart::where('user_id', $id)
            ->sum('goods_number');
    }

    /**
     * 根据用户ID获取进货单商品列表
     * @param $id
     * @return mixed
     */
    public function getGoodsInCartByUser($id)
    {
        $cart = Cart::select('cart.*')
            ->with(['goods' => function ($query) {
                $query->select('goods_id', 'goods_name', 'goods_thumb');
            }])
            ->where('user_id', $id)
            ->where('rec_type', 0)//普通商品
            ->orderby('rec_id', 'desc')
            ->get()
            ->toArray();

        $total = ['goods_price' => 0, 'market_price' => 0, 'goods_number' => 0];
        $goods_list = [];
        /* 用于统计进货单中实体商品和虚拟商品的个数 */
        $virtual_goods_count = 0;
        $real_goods_count = 0;

        foreach ($cart as $v) {

            // 计算总价
            $total['goods_price'] += $v["goods_price"] * $v['goods_number'];
            $total['market_price'] += $v["market_price"] * $v['goods_number'];
            $total['goods_number'] += $v["goods_number"];

            $v['subtotal'] = price_format($v['goods_price'] * $v['goods_number'], false);
            $v['goods_price_format'] = price_format($v['goods_price'], false);
            $v['market_price_format'] = price_format($v['market_price'], false);

            /* 统计实体商品和虚拟商品的个数 */

            if ($v['is_real']) {
                $real_goods_count++;
            } else {
                $virtual_goods_count++;
            }

            //店铺名称
            $shopInfo = $this->shopRepository->findBY('ru_id', $v['ru_id']);

            if (count($shopInfo) > 0) {
                $shopInfo = $shopInfo[0];
                if ($shopInfo['shopname_audit'] == 1) {
                    if ($v['ru_id'] > 0) {
                        $v['shop_name'] = $shopInfo['brandName'] . $shopInfo['shopNameSuffix'];
                    } else {
                        $v['shop_name'] = $shopInfo['shop_name'];
                    }
                } else {
                    $v['shop_name'] = $shopInfo['rz_shopName'];
                }
            } else {
                $v['shop_name'] = "";
            }

            $goods_list[] = $v;
        }

        $tmpArray = [];
        $goodslist = [];
        foreach ($goods_list as $key => $row) {
            $row['goods']['rec_id'] = $row['rec_id'];
            $row['goods']['market_price'] = $row['market_price'];
            $row['goods']['goods_price'] = $row['goods_price'];
            $row['goods']['goods_number'] = $row['goods_number'];
            $row['goods']['goods_attr'] = $row['goods_attr'];
            $row['goods']['is_real'] = $row['is_real'];
            $row['goods']['goods_attr_id'] = $row['goods_attr_id'];
            $row['goods']['is_shipping'] = $row['is_shipping'];
            $row['goods']['shopping_fee'] = $row['shopping_fee'];
            $row['goods']['ru_id'] = $row['ru_id'];
            $row['goods']['warehouse_id'] = $row['warehouse_id'];
            $row['goods']['stages_qishu'] = $row['stages_qishu'];
            $row['goods']['add_time'] = $row['add_time'];
            $row['goods']['goods_sn'] = $row['goods_sn'];
            $row['goods']['product_id'] = $row['product_id'];
            $row['goods']['extension_code'] = $row['extension_code'];
            $row['goods']['parent_id'] = $row['parent_id'];
            $row['goods']['is_gift'] = $row['is_gift'];
            $row['goods']['model_attr'] = $row['model_attr'];
            $row['goods']['area_id'] = $row['area_id'];
            $a = $row['ru_id'];
            $tmpArray[$a]['shop_name'] = $row['shop_name'];
            $tmpArray[$a]['user_id'] = $row['user_id'];
            $tmpArray[$a]['ru_id'] = $row['ru_id'];

            $tmpArray[$a]['goods'][] = $row['goods'];
            $goodslist[$key]['goods'] = $row['goods'];
        }

        foreach ($tmpArray as $key => $value) {
            //商家配送方式
            $shipping = ShippingArea::select('shipping_area.*')
                ->with(['shipping' => function ($query) {
                    $query->select('shipping_id', 'shipping_name', 'insure');
                }])
                ->where('ru_id', $value['ru_id'])
                ->get()
                ->toArray();

            $ship = [];
            foreach ($shipping as $k => $val) {
                if ($val['ru_id'] == $value['ru_id']) {
                    $val['shipping']['ru_id'] = $val['ru_id'];
                    $val['shipping']['configure'] = $val['configure'];
                    $ship[] = $val['shipping'];
                }
            }
            $tmpArray[$key]['shop_info'] = $ship;
        }

//        $total['goods_amount'] = $total['goods_price'];
        $total['saving'] = $total['market_price'] - $total['goods_price'];
        $total['saving_formated'] = price_format($total['market_price'] - $total['goods_price'], false);
        if ($total['market_price'] > 0) {
            $total['save_rate'] = $total['market_price'] ? round(($total['market_price'] - $total['goods_price']) *
                    100 / $total['market_price']) . '%' : 0;
        }

        $total['goods_price_formated'] = price_format($total['goods_price'], false);
        $total['market_price_formated'] = price_format($total['market_price'], false);
        $total['real_goods_count'] = $real_goods_count;
        $total['virtual_goods_count'] = $virtual_goods_count;

        return ['goods_list' => $tmpArray, 'total' => $total, 'product' => $goodslist];
    }

    /**
     * 更新进货单
     * @param $uid
     * @param $id 进货单ID
     * @param $num
     * @param array $attr
     * @return boolean
     */
    public function update($uid, $id, $num, $attr = [])
    {
        $cart = Cart::where('user_id', $uid)
            ->where('rec_id', $id)
            ->first();
        if ($cart === null) {
            return false;
        }

        $cart->goods_number = $num;
        return $cart->save();
    }

    /**
     * 删除进货单商品
     * @param $id
     * @param $uid
     */
    public function deleteOne($id, $uid)
    {
        return Cart::where('rec_id', $id)
            ->where('user_id', $uid)
            ->delete();
    }

    /**
     * 删除全部
     * @param $arr
     */
    public function deleteAll($arr)
    {
        $cartModel = new Cart();

        foreach ($arr as $k => $v) {
            if (count($v) == 3 && $v[0] == 'in') {
                $cartModel = $cartModel->whereIn($v[1], $v[2]);
            } elseif (count($v) == 2) {
                $cartModel = $cartModel->where($v[0], $v[1]);
            }
        }

        $cartModel->delete();
    }

    /**
     * 计算进货单中的商品能享受红包支付的总额
     * @param $order_products
     * @return  float   享受红包支付的总额
     */
    public function computeDiscountCheck($order_products)
    {
        /* 查询优惠活动 */
        $now = local_gettime();
        $user_rank = $this->userRankRepository->getUserRankByUid();
        $user_rank = ',' . $user_rank['rank_id'] . ',';

        $favourable_list = FavourableActivity::where('start_time', '<=', $now)
            ->where('end_time', '>=', $now)
            ->whereraw("CONCAT(',', user_rank, ',') LIKE '%" . $user_rank . "%'")
            ->wherein('act_type', [FavourableActivity::FAT_DISCOUNT, FavourableActivity::FAT_PRICE])
            ->get()
            ->toArray();

        if (!$favourable_list) {
            return 0;
        }
        $goods_list = $order_products;
        foreach ($goods_list as $key => $good) {
            foreach ($good['goods'] as $k => $v) {
                $good_property = [];
                if ($v['goods_attr_id']) {
                    $good_property = explode(',', $v['goods_attr_id']);
                }
                $goods_list[$key]['price'] = $this->goodsRepository->getFinalPrice($v['goods_id'], $v['goods_number'], true, $good_property);
                $goods_list[$key]['amount'] = $v['goods_number'];
            }
        }
        if (!$goods_list) {
            return 0;
        }

        /* 初始化折扣 */
        $discount = 0;
        $favourable_name = [];

        /* 循环计算每个优惠活动的折扣 */
        foreach ($favourable_list as $favourable) {
            $total_amount = 0;
            if ($favourable['act_range'] == FavourableActivity::FAR_ALL) {
                foreach ($goods_list as $goods) {
                    $total_amount += $goods['goods_price'] * $goods['goods_number'];
                }
            } elseif ($favourable['act_range'] == FavourableActivity::FAR_CATEGORY) {
                // /* 找出分类id的子分类id */
                // $id_list = array();
                // $raw_id_list = explode(',', $favourable['act_range_ext']);
                // foreach ($raw_id_list as $id)
                // {
                //     $id_list = array_merge($id_list, array_keys(cat_list($id, 0, false)));
                // }
                // $ids = join(',', array_unique($id_list));

                // foreach ($goods_list as $goods)
                // {
                //     if (strpos(',' . $ids . ',', ',' . $goods['cat_id'] . ',') !== false)
                //     {
                //         $total_amount += $goods['price'] * $goods['amount'];
                //     }
                // }
            } elseif ($favourable['act_range'] == FavourableActivity::FAR_BRAND) {
                foreach ($goods_list as $goods) {
                    $brand_id = $this->goodsRepository->getBrandIdByGoodsId($goods['goods_id']);
                    if (strpos(',' . $favourable['act_range_ext'] . ',', ',' . $brand_id . ',') !== false) {
                        $total_amount += $goods['goods_price'] * $goods['goods_number'];
                    }
                }
            } elseif ($favourable['act_range'] == FavourableActivity::FAR_GOODS) {
                foreach ($goods_list as $goods) {
                    foreach ($goods['goods'] as $v) {
                        if (strpos(',' . $favourable['act_range_ext'] . ',', ',' . $v['goods_id'] . ',') !== false) {
                            $total_amount += $v['goods_price'] * $v['goods_number'];
                        }
                    }
                }
            } else {
                continue;
            }
            if ($total_amount > 0 && $total_amount >= $favourable['min_amount'] && ($total_amount <= $favourable['max_amount'] || $favourable['max_amount'] == 0)) {
                if ($favourable['act_type'] == FavourableActivity::FAT_DISCOUNT) {
                    $discount += $total_amount * (1 - $favourable['act_type_ext'] / 100);
                } elseif ($favourable['act_type'] == FavourableActivity::FAT_PRICE) {
                    $discount += $favourable['act_type_ext'];
                }
            }
        }

        return $discount;
    }

    /**
     * 取得进货单该赠送的积分数
     * @return  int     积分数
     */
    public function getGiveIntegral()
    {
        $uid = $this->authService->authorization();

        $allIntegral = Cart::from("cart as c")
            ->select(['c.*', 'g.give_integral as give_integral'])
            ->leftjoin('goods as g', 'c.goods_id', '=', 'g.goods_id')
            ->where('c.goods_id', '>', 0)
            ->where('c.parent_id', 0)
            ->where('c.rec_type', 0)
            ->where('c.is_gift', 0)
            ->where('c.user_id', $uid)
            ->get()
            ->toArray();

        $sum = 0;
        foreach ($allIntegral as $key => $value) {
            $giveIntegral = empty($value['give_integral']) ? 0 : $value['give_integral'];
            if ($giveIntegral > -1) {
                $sum += $giveIntegral * $value['goods_number'];
            } else {
                $sum += $value['goods_price'] * $value['goods_number'];
            }
        }

        return $sum;
    }
}
