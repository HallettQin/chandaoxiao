<?php

namespace App\Services;

use App\Repositories\Store\StoreRepository;
use App\Repositories\Store\CollectStoreRepository;

class StoreService
{
    private $storeRepository;
    private $collectStoreRepository;

    /**
     * StoreService constructor.
     * @param StoreRepository $storeRepository
     * @param CollectStoreRepository $collectStoreRepository
     */
    public function __construct(
        StoreRepository $storeRepository,
        CollectStoreRepository $collectStoreRepository
        )
    {
        $this->storeRepository = $storeRepository;
        $this->collectStoreRepository = $collectStoreRepository;
    }

    /**
     * 店铺列表
     * @return array
     */
    public function storeList()
    {
        $list = $this->storeRepository->all();

        return $list;
    }

    /**
     * 店铺详情
     * @return array
     */
    public function detail($id, $page, $per_page = 10, $cate_key, $sort, $order = 'ASC', $cat_id = 0, $uid)
    {
        $rootPath = app('request')->root();
        $rootPath = dirname(dirname($rootPath)) . '/';
        $detail = $this->storeRepository->detail($id);
        $detail['0']['sellershopinfo']['logo_thumb'] = get_image_path(str_replace('../', '', $detail['0']['sellershopinfo']['logo_thumb']));

        $goods = $this->storeRepository->goods($id, $page, $per_page, $cate_key, $sort, $order, $cat_id);

        $category = $this->storeRepository->store_category($id);
        foreach ($goods as $key => $value) {
            $goods[$key]['goods_name'] = $value['goods_name'];
            $goods[$key]['goods_thumb'] = get_image_path($value['goods_thumb']);
            $goods[$key]['shop_price'] = price_format($value['shop_price'], true);
            $goods[$key]['yuan_shop'] = $value['shop_price'];
            $goods[$key]['cat_id'] = $value['cat_id'];
            $goods[$key]['market_price'] = price_format($value['market_price'], true);
            $goods[$key]['yuan_market'] = $value['market_price'];
            $goods[$key]['goods_number'] = $value['goods_number'];
        }
        $collnum = $this->storeRepository->collnum($id);
        $collect = $this->storeRepository->collect($id, $uid);
        $list['detail'] = $detail['0'];
        $list['goods'] = $goods;
        $list['category'] = $category;
        $list['collnum'] = $collnum;//关注人数
        $list['collect'] = $collect;//关注状态
        $list['root_path'] = $rootPath;
        return $list;
    }

    /**
     * 关注店铺
     * @return array
     */
    public function attention($id, $uid)
    {
        $collectStore = $this->collectStoreRepository->findOne($id, $uid);
        if (empty($collectStore)) {
            $result = $this->collectStoreRepository->addCollectStore($id, $uid);
            $result = [
                    'collect' => 'true',
                    'collnum' => $this->storeRepository->collnum($id)
            ];
        } else {
            $result = $this->collectStoreRepository->deleteCollectStore($id, $uid);
            $result = [
                    'collect' => '0',
                    'collnum' => $this->storeRepository->collnum($id)
            ];
        }
        return $result;
    }
}
