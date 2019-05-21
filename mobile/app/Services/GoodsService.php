<?php

namespace App\Services;

use App\Repositories\Cart\CartRepository;
use App\Repositories\Goods\GoodsRepository;
use App\Repositories\Store\StoreRepository;
use App\Repositories\Goods\GoodsAttrRepository;
use App\Repositories\Goods\CollectGoodsRepository;

class GoodsService
{
    private $goodsRepository;
    private $goodsAttrRepository;
    private $collectGoodsRepository;
    private $shopService;
    private $cartRepository;
    private $StoreRepository;
    public function __construct(
        GoodsRepository $goodsRepository,
        GoodsAttrRepository $goodsAttrRepository,
        CollectGoodsRepository $collectGoodsRepository,
        ShopService $shopService,
        CartRepository $cartRepository,
        StoreRepository $StoreRepository
    ) {
        $this->goodsRepository = $goodsRepository;
        $this->goodsAttrRepository = $goodsAttrRepository;
        $this->collectGoodsRepository = $collectGoodsRepository;
        $this->shopService = $shopService;
        $this->cartRepository = $cartRepository;
        $this->StoreRepository = $StoreRepository;
    }

    /**
     * 商品列表
     * @param int $categoryId
     * @param $keywords
     * @param int $page
     * @param int $size
     * @param $sortKey
     * @param $sortVal
     * @return mixed
     */
    public function getGoodsList($categoryId = 0, $keywords = '', $page = 1, $size = 10, $sortKey = '', $sortVal = '')
    {
        $page = empty($page) ? 1 : $page;

        $field = [
           "goods_id",    //商品id
           "goods_name", //商品名称
           "shop_price",  //商品价格
           "goods_thumb", //商品图片
           "goods_number",   //商品销量
           "market_price",   //商品原价格
           "sales_volume"  //商品库存
        ];

        $list = $this->goodsRepository->findBy('category', $categoryId, $page, $size, $field, $keywords, $sortKey, $sortVal);

        foreach ($list as $k => $v) {
            $list[$k]['goods_thumb'] = get_image_path($v['goods_thumb']);
            $list[$k]['market_price_formated'] = price_format($v['market_price'], false);
            $list[$k]['shop_price_formated'] = price_format($v['shop_price'], false);
        }

        return $list;
    }

    /**
     * 商品详情
     * @param $id
     * @param $uid
     * @return array
     */
    public function goodsDetail($id, $uid)
    {
        $result = [
            'error' => 0,
            'goods_img' => '',
            'goods_info' => '',
            'goods_comment' => '',
            'goods_properties' => ''
        ];
        $rootPath = app('request')->root();
        $rootPath = dirname(dirname($rootPath)) . '/';
        $shopconfig = app('App\Repositories\ShopConfig\ShopConfigRepository');
        $timeFormat = $shopconfig->getShopConfigByCode('time_format');

        //
        $collect = $this->collectGoodsRepository->findOne($id, $uid);
        $goodsComment = $this->goodsRepository->goodsComment($id);

        foreach ($goodsComment as $k => $v) {
            $goodsComment[$k]['add_time'] = local_date('Y-m-d', $v['add_time']);
            $goodsComment[$k]['user_name'] = $this->goodsRepository->getGoodsCommentUser($v['user_id']);
        }

        $result['goods_comment'] = $goodsComment;
        $result['total_comment_number'] = count($result['goods_comment']);
        // 商品信息
        $goodsInfo = $this->goodsRepository->goodsInfo($id);
        if ($goodsInfo['is_on_sale'] == 0) {
            return ['error' => 1, 'msg' => '商品已下架'];
        }
        $goodsInfo['goods_thumb'] = get_image_path($goodsInfo['goods_thumb']);
        $goodsInfo['goods_price_formated'] = price_format($goodsInfo['goods_price'], true);
        $goodsInfo['market_price_formated'] = price_format($goodsInfo['market_price'], true);
        $result['goods_info'] = array_merge($goodsInfo, ['is_collect' => (empty($collect)) ? 0 : 1]);

        // 商家信息
        $ruId = $goodsInfo['user_id'];
        unset($result['goods_info']['user_id']);
        if ($ruId > 0) {
            $result['shop_name'] = $this->shopService->getShopName($ruId);

            $result['coll_num'] = $this->StoreRepository->collnum($ruId);

            $detail = $this->StoreRepository->detail($ruId);
            $result['detail'] = $detail['0'];
            $result['detail']['sellershopinfo']['logo_thumb'] = get_image_path(str_replace('../', '', $detail['0']['sellershopinfo']['logo_thumb']));
        }

        // 商品相册
        $goodsGallery = $this->goodsRepository->goodsGallery($id);
        foreach ($goodsGallery as $k => $v) {
            $goodsGallery[$k] = get_image_path($v['thumb_url']);
        }
        $result['goods_img'] = $goodsGallery;

        // 商品属性 规格
        $result['goods_properties'] = $this->goodsRepository->goodsProperties($id);

        // 进货单商品数量
        $result['cart_number'] = $this->cartRepository->goodsNumInCartByUser($uid);
        $result['root_path'] = $rootPath;

        return $result;
    }

    /**
     * 商品属性价格
     * @param int $goodsId
     * @param int $attr_id
     * @param int $num
     * @return array
     * todo
     */
    public function goodsPropertiesPrice($goods_id, $attr_id, $num = 1, $warehouse_id = 0, $area_id = 0)
    {
        $result = [
            'stock' => '',       //库存
            'market_price' => '',      //市场价
            'qty' => '',               //数量
            'spec_price' => '',        //属性价格
            'goods_price' => '' ,           //商品价格(最终使用价格)
            'attr_img' => ''           //商品属性图片
        ];
        $goods = $this->goodsRepository->goodsInfo($goods_id);//商品详情

        $result['stock'] = $this->goodsRepository->goodsAttrNumber($goods_id, $attr_id, $warehouse_id, $area_id);
        $result['market_price'] = $goods['market_price'];
        $result['market_price_formated'] = price_format($goods['market_price'], true);
        $result['qty'] = $num;
        $result['spec_price'] = $this->goodsRepository->goodsPropertyPrice($goods_id, $attr_id, $warehouse_id, $area_id);
        $result['spec_price_formated'] = price_format($result['spec_price'], true);
        $result['goods_price'] = $this->goodsRepository->getFinalPrice($goods_id, $num, true, $attr_id, $warehouse_id, $area_id);
        $result['goods_price_formated'] = price_format($result['goods_price'], true);
        $attr_img = $this->goodsRepository->getAttrImgFlie($goods_id, $attr_id);
        if (!empty($attr_img)) {
            $result['attr_img'] = get_image_path($attr_img['attr_img_flie']);
        }

        return  $result;
    }
}
