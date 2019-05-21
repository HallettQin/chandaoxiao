<?php

namespace App\Api\Controllers\Wx;

use Illuminate\Http\Request;
use App\Services\AuthService;
use App\Services\PaymentService;
use App\Api\Controllers\Controller;

class PaymentController extends Controller
{
    private $paymentService;
    private $authService;

    public function __construct(PaymentService $paymentService, AuthService $authService)
    {
        $this->paymentService = $paymentService;
        $this->authService = $authService;
    }

    /**
     * 微信支付
     * @param Request $request
     * @return mixed
     */
    public function pay(Request $request)
    {

        // 验证数据
        $this->validate($request, [
            'id' => 'required|integer',
            'open_id' => 'required|string',
            'code' => 'string',
        ]);

        //验证用户
        $uid = $this->authService->authorization();   //返回用户ID
        if (isset($uid['error']) && $uid['error'] > 0) {
            return $this->apiReturn($uid, 1);
        }

        $args = $request->all();
        $args['uid'] = $uid;

        $res = $this->paymentService->payment($args);

        return $this->apiReturn($res);
    }

    /**
     * 支付回调
     * @param Request $request
     * @return mixed
     */
    public function notify(Request $request)
    {
        // 验证数据
        $this->validate($request, [
            'id' => 'required|integer',
            'code' => 'string',
        ]);

        //验证用户
        $uid = $this->authService->authorization();   //返回用户ID
        if (isset($uid['error']) && $uid['error'] > 0) {
            return $this->apiReturn($uid, 1);
        }

        $args = $request->all();
        $args['uid'] = $uid;

        $res = $this->paymentService->notify($args);
        //
        if ($res['code'] > 0) {
            return $this->apiReturn($res['msg'], 1);
        }

        return $this->apiReturn($res);
    }
}
