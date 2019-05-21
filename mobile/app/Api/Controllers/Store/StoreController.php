<?php

namespace App\Api\Controllers\Store;

use App\Services\StoreService;
use App\Api\Controllers\Controller;
use App\Repositories\Store\StoreRepository;

/**
 * Class StoreController
 * @package App\Api\Controllers\Store
 */
class StoreController extends Controller
{
    protected $store;

    /**
     * Store constructor.
     * @param StoreRepository $store
     */
    public function __construct(StoreService $storeService)
    {
        $this->store = $storeService;
    }

    /**
     * 类别列表
     * @return mixed
     */
    public function index()
    {
        return $this->store->all();
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
        ]);
        return $this->store->detail($request->get('id'));
    }
}
