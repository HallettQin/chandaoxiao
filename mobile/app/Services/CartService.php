<?php

namespace App\Services;

use App\Repositories\Cart\CartRepository;
use App\Repositories\Goods\GoodsRepository;
use App\Repositories\Goods\GoodsAttrRepository;

/**
 * Class CartService
 * @package App\Services
 */
class CartService
{
    private $cartRepository;
    private $goodsRepository;
    private $authService;
    private $goodsAttrRepository;

    /**
     * CartService constructor.
     * @param CartRepository $cartRepository
     * @param GoodsRepository $goodsRepository
     * @param AuthService $authService
     * @param GoodsAttrRepository $goodsAttrRepository
     */
    public function __construct(
        CartRepository $cartRepository,
        GoodsRepository $goodsRepository,
        AuthService $authService,
        GoodsAttrRepository $goodsAttrRepository
    ) {
        $this->cartRepository = $cartRepository;
        $this->goodsRepository = $goodsRepository;
        $this->authService = $authService;
        $this->goodsAttrRepository = $goodsAttrRepository;
    }

    /**
     * 进货单页面数据
     * @return mixed
     */
    public function getCart()
    {
        $cart = $this->getCartGoods();
        $result = [];

        foreach ($cart['goods_list'] as  $v) {
            foreach ($v['goods'] as $key => $value) {
                $result['cart_list'][$v['ru_id']][] = [
                    'rec_id' => $value['rec_id'],
                    'user_id' => $v['user_id'],
                    'ru_id' => $value['ru_id'],
                    'shop_name' => $v['shop_name'],
                    'goods_id' => $value['goods_id'],
                    'goods_name' => $value['goods_name'],
                    'market_price' => $value['market_price'],
                    'market_price_formated' => price_format($value['market_price'], false),
                    'goods_price' => $value['goods_price'],
                    'goods_price_formated' => price_format($value['goods_price'], false),
                    'goods_number' => $value['goods_number'],
                    'goods_attr' => $value['goods_attr'],
                    'goods_attr_id' => $value['goods_attr_id'],
                    'goods_thumb' => get_image_path($value['goods_thumb'])
                ];
            }
        }

        $result['total'] = array_map('strip_tags', $cart['total']);

        $result['best_goods'] = $this->getBestGoods();

        return $result;
    }

    /**
     * 进货单商品列表
     * @return mixed
     */
    private function getCartGoods()
    {

        // 用户ID
        $userId = $this->authService->authorization();

        $list = $this->cartRepository->getGoodsInCartByUser($userId);

        return $list;
    }

    /**
     * 推荐商品
     * @return mixed
     */
    private function getBestGoods()
    {
        $list =$this->goodsRepository->findByType('best');

        $bestGoods = array_map(function ($v) {
            return [
                'goods_id' => $v['goods_id'],
                'goods_name' => $v['goods_name'],
                'market_price' => $v['market_price'],
                'market_price_formated' => price_format($v['market_price'], false),
                'shop_price' => $v['shop_price'],
                'shop_price_formated' => price_format($v['shop_price'], false),
                'goods_thumb' => get_image_path($v['goods_thumb']),
            ];
        }, $list);

        return $bestGoods;
    }

    /**
     * 添加商品到进货单
     * @param $params
     * @return bool
     */
    public function addGoodsToCart($params)
    {
        $result = [
            'code' => 0,
            'goods_number' => 0,
            'total_number' => 0,
        ];

        $goods = $this->goodsRepository->find($params['id']);   //查找商品
        if ($goods['is_on_sale'] != 1) {
            return '商品已下架';
        }
        // 货品
        $goodsAttr = empty($params['attr_id']) ? '' : json_decode($params['attr_id'], 1);
        $goodsAttrId = implode(',', $goodsAttr);
        $product = $this->goodsRepository->getProductByGoods($params['id'], implode('|', $goodsAttr));
        if (empty($product)) {
            $product['id'] = 0;
        }
        // 商品属性文字输出
        $attrName = $this->goodsAttrRepository->getAttrNameById($goodsAttr);

        $attrNameStr = '';
        foreach ($attrName as $v) {
            $attrNameStr .= $v['attr_name'] . ':'. $v['attr_value'] . " \n";
        }

        // 计算商品价格
        $goodsPrice = $this->goodsRepository->getFinalPrice($params['id'], $params['num'], 1, $goodsAttr);

        // 判断进货单是否已经添加
        $cart = $this->cartRepository->getCartByGoods($params['uid'], $params['id'], $goodsAttrId);
        if (!empty($cart)) {
            // 已有商品  则更新商品数量
            $goodsNumber = $params['num']+$cart['goods_number'];
            $res = $this->cartRepository->update($params['uid'], $cart['rec_id'], $goodsNumber);
            if ($res) {
                $number = $this->cartRepository->goodsNumInCartByUser($params['uid']);
                $result['goods_number'] = $goodsNumber;
                $result['total_number'] = $number;
            }
        } else {
            // 添加参数
            $arguments = [
                'goods_id' => $params['id'],
                'user_id' => $params['uid'],
                'goods_sn' => $goods['goods_sn'],
                'product_id' => empty($product['id']) ? '' : $product['id'],
                'group_id' => '',
                'goods_name' => $goods['goods_name'],
                'market_price' => $goods['market_price'],
                'goods_price' => $goodsPrice,
                'goods_number' => $params['num'],
                'goods_attr' => $attrNameStr,
                'is_real' => $goods['is_real'],
                'extension_code' => empty($params['extension_code']) ? '' : $params['extension_code'],
                'parent_id' => 0,
                'rec_type' => 0,  // 普通商品
                'is_gift' => 0,
                'is_shipping' => $goods['is_shipping'],
                'can_handsel' => '',
                'model_attr' => $goods['model_attr'],
                'goods_attr_id' => $goodsAttrId,
                'ru_id' => $goods['user_id'],
                'shopping_fee' => '',
                'warehouse_id' => '',
                'area_id' => '',
                'add_time' => gmtime(),
                'stages_qishu' => '',
                'store_id' => '',
                'freight' => '',
                'tid' => '',
                'shipping_fee' => '',
                'store_mobile' => '',
                'take_time' => '',
                'is_checked' => '',
            ];

            $goodsNumber = $this->cartRepository->addGoodsToCart($arguments);
            $number = $this->cartRepository->goodsNumInCartByUser($params['uid']);

            $result['goods_number'] = $goodsNumber;
            $result['total_number'] = $number;
        }

        return $result;
    }

    /**
     * 更新进货单商品
     * @param $args
     * @return array
     */
    public function updateCartGoods($args)
    {
        $res = $this->cartRepository->update($args['uid'], $args['id'], $args['amount']);
        if ($res) {
            //成功
            return ['code' => 0, 'msg' => '添加成功'];
        }
        return ['code' => 1, 'msg' => '添加失败'];
    }

    /**
     * 删除进货单商品
     * @param $args
     * @return array
     */
    public function deleteCartGoods($args)
    {
        $res = $this->cartRepository->deleteOne($args['id'], $args['uid']);

        $result = [];
        switch ($res) {
            case 0:
                $result['code'] = 1;
                $result['msg'] = '进货单中没有该商品';
                break;
            case 1:
                $result['code'] = 0;
                $result['msg'] = '删除一个商品';
                break;
            default:
                $result['code'] = 1;
                $result['msg'] = '删除失败';
                break;
        }

        return $result;
    }
}
