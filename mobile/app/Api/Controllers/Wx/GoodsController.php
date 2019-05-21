<?php

namespace App\Api\Controllers\Wx;

use Illuminate\Http\Request;
use App\Services\AuthService;
use App\Services\GoodsService;
use App\Api\Controllers\Controller;

/**
 * Class GoodsController
 * @package App\Api\Controllers\Wx
 */
class GoodsController extends Controller
{
    private $goodsService;
    private $authService;

    public function __construct(GoodsService $goodsService, AuthService $authService)
    {
        $this->goodsService = $goodsService;
        $this->authService = $authService;
    }

    /**
     * 商品列表
     * @param Request $request
     * @return array
     */
    public function goodsList(Request $request)
    {
        //验证参数
        $this->validate($request, [
            'page' => 'required|int',
        ]);

        //验证通过
        $list = $this->goodsService->getGoodsList($request->get('id'), $request->get('keyword'), $request->get('page'), $request->get('per_page'), $request->get('sort_key'), $request->get('sort_value'));

        return $this->apiReturn($list);
    }

    /**
     * 商品详情
     * @param Request $request
     * @return array
     */
    public function goodsDetail(Request $request)
    {
        //验证参数
        $this->validate($request, [
            'id' => 'required|integer'
        ]);

        //验证通过  @param  商品ID
        $uid = $this->authService->authorization();
        if (isset($uid['error']) && $uid['error'] > 0) {
            $uid = 0;
        }

        $list = $this->goodsService->goodsDetail($request->get('id'), $uid);

        return $this->apiReturn($list, $list['error']);
    }

    /**
     * 改变属性、数量时重新计算商品价格
     * @param Request $request
     * @return array
     */
    public function property(Request $request)
    {

        //验证参数
        $this->validate($request, [
            'id' => 'required|integer',
//            'attr_id' => 'required',
            'num' => 'required|integer',
            'warehouse_id' => 'required|integer',    //仓库id
            'area_id' => 'required|integer'          //地区id
        ]);

        $price = $this->goodsService->goodsPropertiesPrice($request->get('id'), $request->get('attr_id'), $request->get('num'), $request->get('warehouse_id'), $request->get('area_id'));

        return $this->apiReturn($price);
    }
}
