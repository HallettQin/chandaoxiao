<?php

namespace App\Api\Controllers\Wx;

use Illuminate\Http\Request;
use App\Services\AuthService;
use App\Services\StoreService;
use App\Api\Controllers\Controller;
use App\Repositories\Store\StoreRepository;

/**
 * Class StoreController
 * @package App\Api\Controllers\Wx
 */
class StoreController extends Controller
{
    private $storeService;
    private $authService;

    /**
     * Store constructor.
     * @param StoreRepository $store
     */
    public function __construct(StoreService $storeService, AuthService $authService)
    {
        $this->storeService = $storeService;
        $this->authService = $authService;
    }

    /**
     * 类别列表
     * @return mixed
     */
    public function index()
    {
        return $this->storeService->storeList();
    }

    /**
     * 类别详情
     * @param $id
     * @return mixed
     */
    public function detail(Request $request)
    {
        $this->validate($request, [
            'id' => 'required|int',
            'page' => 'required|int',
            'per_page' => 'required|int',
            'cate_key' => 'required|string',
            'sort' => 'required|string',
            'order' => 'required|string',
            'cat_id' => 'required|int',
        ]);
        $uid = $this->authService->authorization();   //返回用户ID
        if (isset($uid['error']) && $uid['error'] > 0) {
                return $this->apiReturn($uid, 1);
        }
        return $this->storeService->detail($request->get('id'), $request->get('page'), $request->get('per_page'), $request->get('cate_key'), $request->get('sort'), $request->get('order'), $request->get('cat_id'), $uid);
    }

    /**
     * 关注店铺
     * @param $id
     * @return mixed
     */
    public function attention(Request $request)
    {
        $this->validate($request, [
            'id' => 'required|int',
        ]);
        $uid = $this->authService->authorization();   //返回用户ID
        if (isset($uid['error']) && $uid['error'] > 0) {
                return $this->apiReturn($uid, 1);
        }
         return $this->storeService->attention($request->get('id'), $uid);
    }

}
