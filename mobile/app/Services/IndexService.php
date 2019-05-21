<?php

namespace App\Services;

use Illuminate\Http\Request;
use App\Repositories\Shop\ShopRepository;
use App\Repositories\Goods\GoodsRepository;

/**
 * 商店首页服务
 * Class IndexService
 * @package App\Services
 */
class IndexService
{
    private $goodsRepository;
    private $shopRepository;
    private $root_url;

    /**
     * IndexService constructor.
     * @param GoodsRepository $goodsRepository
     * @param ShopRepository $shopRepository
     * @param Request $request
     */
    public function __construct(GoodsRepository $goodsRepository, ShopRepository $shopRepository, Request $request)
    {
        $this->goodsRepository = $goodsRepository;
        $this->shopRepository = $shopRepository;
        $this->root_url = dirname(dirname($request->root())) . '/';
    }

    /**
     * 微信小程序 首页推荐商品
     * @return array
     */
    public function bestGoodsList()
    {
        $arr = [
            'goods_id',   //商品id
            'goods_name',   //商品名
            'shop_price',   //商品价格
            'goods_thumb',   //商品图片
            'goods_link',    //商品链接
            'goods_number',   //商品销量
            'market_price',   //商品原价格
            'sales_volume',   //商品库存
        ];
        $goodsList = $this->goodsRepository->findByType('best');  //获取推荐商品

        $data = array_map(function ($v) use ($arr) {
            foreach ($v as $ck => $cv) {
                if (!in_array($ck, $arr)) {
                    unset($v[$ck]);
                }
            }

            $v['goods_thumb'] = get_image_path($v['goods_thumb']);
            $v['goods_sales'] = $v['sales_volume'];
            $v['goods_stock'] = $v['goods_number'];
            $v['market_price_formated'] = price_format($v['market_price'], false);
            $v['shop_price_formated'] = price_format($v['shop_price'], false);
            unset($v['goods_number'], $v['sales_volume']);
            return $v;
        }, $goodsList);

        return $data;
    }

    /**
     * 获取banner
     * @return array
     */
    public function getBanners()
    {
        $res = $this->shopRepository->getPositions('banner', 3);  //获取banner

        $ads = [];

        foreach ($res as $row) {
            if (!empty($row['position_id'])) {
                $src = (strpos($row['ad_code'], 'http://') === false && strpos($row['ad_code'], 'https://') === false) ?
                    "data/afficheimg/$row[ad_code]" : $row['ad_code'];
                $ads[] = [
                    'pic' => get_image_path($src),
                    'banner_id' => $row['ad_id']
                ];
            }
        }

        return $ads;
    }

    /**
     * 获取广告位
     * @return array
     */
    public function getAdsense()
    {
        $shopconfig = app('App\Repositories\ShopConfig\ShopConfigRepository');
        $number = $shopconfig->getShopConfigByCode('wx_index_show_number');
        if (empty($number)) {
            $number = 3;
        }

        $adsense = $this->shopRepository->getPositions('', $number);  //获取广告位

        $ads = [];
        foreach ($adsense as $row) {
            if (!empty($row['position_id'])) {
                $src = (strpos($row['ad_code'], 'http://') === false && strpos($row['ad_code'], 'https://') === false) ?
                    "data/afficheimg/$row[ad_code]" : $row['ad_code'];
                $ads[] = [
                    'pic' => get_image_path($src),
                    'adsense_id' => $row['ad_id']
                ];
            }
        }
        return $ads;
    }
}
