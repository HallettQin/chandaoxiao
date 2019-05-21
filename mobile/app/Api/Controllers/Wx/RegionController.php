<?php

namespace App\Api\Controllers\Wx;

use Illuminate\Http\Request;
use App\Services\AuthService;
use App\Services\RegionService;
use App\Api\Controllers\Controller;

/**
 * Class RegionController
 * @package App\Api\Controllers\Wx
 */
class RegionController extends Controller
{
    private $authService;
    private $regionService;

    public function __construct(AuthService $authService, RegionService $regionService)
    {
        $this->authService = $authService;
        $this->regionService = $regionService;
    }

    /**
     * 获取下级地区列表
     * @param Request $request
     * @return mixed
     */
    public function regionList(Request $request)
    {
        $this->validate($request, [
            'id' => 'integer'
        ]);


        //
        $args = $request->all();
        $list = $this->regionService->regionList($args);

        return $this->apiReturn($list);
    }
}
