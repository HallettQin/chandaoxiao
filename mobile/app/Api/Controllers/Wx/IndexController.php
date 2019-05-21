<?php

namespace App\Api\Controllers\Wx;

use App\Services\IndexService;
use App\Api\Controllers\Controller;

/**
 * Class IndexController
 * @package App\Api\Controllers\Wx
 */
class IndexController extends Controller
{


    /** @var IndexService  */
    private $indexService;

    /**
     * IndexController constructor.
     * @param IndexService $indexService
     */
    public function __construct(IndexService $indexService)
    {
        $this->indexService = $indexService;
    }

    /**
     * 首页
     * @return mixed
     */
    public function index()
    {

        // 获取banner
        $banners = $this->indexService->getBanners();
        $data['banner'] = $banners;
        // 获取广告位
        $adsense = $this->indexService->getAdsense();
        $data['adsense'] = $adsense;
        // 获取推荐商品列表
        $goodsList = $this->indexService->bestGoodsList();
        $data['goods_list'] = $goodsList;

        return $this->apiReturn($data);
    }
}
