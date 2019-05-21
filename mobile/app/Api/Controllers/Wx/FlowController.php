<?php

namespace App\Api\Controllers\Wx;

use Illuminate\Http\Request;
use App\Services\AuthService;
use App\Services\FlowService;
use App\Api\Controllers\Controller;

/**
 * Class FlowController
 * @package App\Api\Controllers\Wx
 */
class FlowController extends Controller
{
    private $flowService;
    private $authService;

    public function __construct(FlowService $flowService, AuthService $authService)
    {
        $this->flowService = $flowService;
        $this->authService = $authService;
    }

    /**
     * 订单确认页面 数据
     * @param Request $request
     * @return array
     */
    public function index(Request $request)
    {
        $this->validate($request, []);

        //  用户ID
        $uid = $this->authService->authorization();
        if (isset($uid['error']) && $uid['error'] > 0) {
            return $this->apiReturn($uid, 1);
        }

        $flowInfo = $this->flowService->flowInfo($uid);

        return $this->apiReturn($flowInfo);
    }

    /**
     * 提交订单
     * @param Request $request
     * @return mixed
     */
    public function down(Request $request)
    {
        $this->validate($request, [
            'consignee' => 'required|integer'
        ]);

        //  用户ID
        $uid = $this->authService->authorization();
        if (isset($uid['error']) && $uid['error'] > 0) {
            return $this->apiReturn($uid, 1);
        }

        $args = $request->all();
        $args['uid'] = $uid;
        $res = $this->flowService->submitOrder($args);
        if ($res['error'] == 1) {
            return $this->apiReturn($res['msg'], 1);
        }

        return $this->apiReturn($res);
    }

    /**
     * 配送费
     * @param Request $request
     * @return mixed
     */
    public function shipping(Request $request)
    {
        $this->validate($request, [
            'id' => 'required|integer',
            'ru_id' => 'required|integer',
            'address' => 'required|integer'
        ]);

        //  用户ID
        $uid = $this->authService->authorization();
        if (isset($uid['error']) && $uid['error'] > 0) {
            return $this->apiReturn($uid, 1);
        }

        $args = $request->all();
        $args['uid'] = $uid;
        $res = $this->flowService->shippingFee($args);

        if ($res['error'] == 0) {
            unset($res['error'], $res['message']);
            return $this->apiReturn($res);
        } else {
            return $this->apiReturn($res['message'], 1);
        }
    }
}
