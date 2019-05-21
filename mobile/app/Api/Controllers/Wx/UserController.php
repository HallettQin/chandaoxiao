<?php

namespace App\Api\Controllers\Wx;

use Illuminate\Http\Request;
use App\Services\AuthService;
use App\Services\UserService;
use App\Api\Controllers\Controller;

class UserController extends Controller
{
    private $userService;
    private $authService;

    /**
     * User constructor.
     * @param UserService $userService
     * @param AuthService $authService
     */
    public function __construct(UserService $userService, AuthService $authService)
    {
        $this->userService = $userService;
        $this->authService = $authService;
    }

    /**
     * 验证用户
     * @param Request $request
     * @return array
     */
    public function login(Request $request)
    {
        //数据验证
        $this->validate($request, [
            'userinfo' => 'required',
            'code' => 'required'
        ]);

        // 用户登录
        if (false === ($result = $this->authService->loginMiddleWare($request->all()))) {
            return ['登录失败', 1];
        }

        //登录成功
        return $this->apiReturn($result);
    }

    /**
     * 用户中心
     * @param $request
     * @return array
     */
    public function index(Request $request)
    {
        //数据验证
        $this->validate($request, []);

        $uid = $this->authService->authorization();   //返回用户ID
        if (isset($uid['error']) && $uid['error'] > 0) {
            return $this->apiReturn($uid, 1);
        }

        $args['uid'] = $uid;

        $userCenterData = $this->userService->userCenter($args);

        return $this->apiReturn($userCenterData);
    }

    /**
     * 订单列表
     * @param Request $request
     * @return array
     */
    public function orderList(Request $request)
    {

        //数据验证
        $this->validate($request, [
            'page' => "required|integer",
            'size' => "required|integer",
            'status' => "required|integer"
        ]);

        $uid = $this->authService->authorization();   //返回用户ID
        if (isset($uid['error']) && $uid['error'] > 0) {
            return $this->apiReturn($uid, 1);
        }

        $orderList = $this->userService->orderList(array_merge(['uid' => $uid], $request->all()));

        return $this->apiReturn($orderList);
    }

    /**
     * 订单详情
     * @param Request $request
     * @return mixed
     */
    public function orderDetail(Request $request)
    {
        //数据验证
        $this->validate($request, [
            'id' => "required|integer",
        ]);

        $args['order_id'] = $request->get('id');
        $uid = $this->authService->authorization();   //返回用户ID
        if (isset($uid['error']) && $uid['error'] > 0) {
            return $this->apiReturn($uid, 1);
        }
        $args['uid'] = $uid;

        $order = $this->userService->orderDetail($args);

        return $this->apiReturn($order);
    }

    /**
     * 评价订单列表
     * @param Request $request
     * @return mixed
     */
    public function orderAppraise(Request $request)
    {
        //数据验证
        $this->validate($request, [
//            'id' => "required|integer",
        ]);

        $args = $request->all();
        $uid = $this->authService->authorization();   //返回用户ID
        if (isset($uid['error']) && $uid['error'] > 0) {
            return $this->apiReturn($uid, 1);
        }
        $args['uid'] = $uid;

        $order = $this->userService->orderAppraise($args);

        return $this->apiReturn($order);
    }

    /**
     * 待评价订单详情
     * @param Request $request
     * @return mixed
     */
    public function orderAppraiseDetail(Request $request)
    {
        //数据验证
        $this->validate($request, [
            'oid' => "required|integer",
            'gid' => "required|integer",
        ]);

        $args = $request->all();
        $uid = $this->authService->authorization();   //返回用户ID
        if (isset($uid['error']) && $uid['error'] > 0) {
            return $this->apiReturn($uid, 1);
        }
        $args['uid'] = $uid;
        $order = $this->userService->orderAppraiseDetail($args);

        return $this->apiReturn($order);
    }

    /**
     * 提交评价
     * @param Request $request
     * @return mixed
     */
    public function orderAppraiseAdd(Request $request)
    {
        //数据验证
        $this->validate($request, [
            'gid' => "required|integer",
            'oid' => "required|integer",
            'content' => "required",
            'rank' => "required|integer",
        ]);

        $args = $request->all();
        $uid = $this->authService->authorization();   //返回用户ID
        if (isset($uid['error']) && $uid['error'] > 0) {
            return $this->apiReturn($uid, 1);
        }
        $args['uid'] = $uid;

        $res = $this->userService->orderAppraiseAdd($args);

        return $this->apiReturn($res);
    }

    /**
     * 取消订单
     * @param Request $request
     * @return mixed
     */
    public function orderCancel(Request $request)
    {
        //数据验证
        $this->validate($request, [
            'id' => "required|integer",
        ]);

        $args['order_id'] = $request->get('id');
        $uid = $this->authService->authorization();   //返回用户ID
        if (isset($uid['error']) && $uid['error'] > 0) {
            return $this->apiReturn($uid, 1);
        }
        $args['uid'] = $uid;

        $res = $this->userService->orderCancel($args);
        // 判断
        if ($res['error'] > 0) {
            return $this->apiReturn($res['msg'], 1);
        }

        return $this->apiReturn($res);
    }

    /**
     * 订单确认
     * @param Request $request
     * @return mixed
     */
    public function orderConfirm(Request $request)
    {
        //数据验证
        $this->validate($request, [
            'id' => "required|integer",
        ]);

        $args['order_id'] = $request->get('id');
        $uid = $this->authService->authorization();   //返回用户ID
        if (isset($uid['error']) && $uid['error'] > 0) {
            return $this->apiReturn($uid, 1);
        }
        $args['uid'] = $uid;

        $res = $this->userService->orderConfirm($args);

        return $this->apiReturn($res);
    }

    /**
     * 用户中心 获取地址列表
     * @param Request $request
     * @return array
     */
    public function addressList(Request $request)
    {

        //数据验证
        $this->validate($request, []);

        $uid = $this->authService->authorization();   //返回用户ID
        if (isset($uid['error']) && $uid['error'] > 0) {
            return $this->apiReturn($uid, 1);
        }

        $address = $this->userService->userAddressList($uid);

        return $this->apiReturn($address);
    }

    /**
     * 选择默认收货地址
     * @param Request $request
     * @return mixed
     */
    public function addressChoice(Request $request)
    {
        //数据验证
        $this->validate($request, [
            'id' => 'required|integer'
        ]);

        $uid = $this->authService->authorization();   //返回用户ID
        if (isset($uid['error']) && $uid['error'] > 0) {
            return $this->apiReturn($uid, 1);
        }

        $args = $request->all();
        $args['uid'] = $uid;

        $res = $this->userService->addressChoice($args);

        return $this->apiReturn($res, ($res ? 0 : 1));
    }

    /**
     * 添加收货地址
     * @param Request $request
     * @return array
     */
    public function addressAdd(Request $request)
    {
        //数据验证
        $this->validate($request, [
            'consignee' => 'required|string',
            'province' => 'required|integer',
            'city' => 'required|integer',
            'district' => 'required|integer',
            'address' => 'required|string',
            'mobile' => 'required|size:11'
        ]);

        $uid = $this->authService->authorization();   //返回用户ID
        if (isset($uid['error']) && $uid['error'] > 0) {
            return $this->apiReturn($uid, 1);
        }

        $args = $request->all();
        $args['uid'] = $uid;

        $address = $this->userService->addressAdd($args);

        return $this->apiReturn($address);
    }

    /**
     * 编辑收货地址  显示数据
     * @param Request $request
     * @return array
     */
    public function addressDetail(Request $request)
    {
        //数据验证
        $this->validate($request, [
            'id' => 'required|integer'
        ]);

        $uid = $this->authService->authorization();   //返回用户ID
        if (isset($uid['error']) && $uid['error'] > 0) {
            return $this->apiReturn($uid, 1);
        }

        $args = $request->all();
        $args['uid'] = $uid;

        $address = $this->userService->addressDetail($args);

        return $this->apiReturn($address);
    }

    /**
     * 更新收货地址
     * @param Request $request
     * @return array
     */
    public function addressUpdate(Request $request)
    {
        //数据验证
        $this->validate($request, [
            'id' => 'required|integer',
            'consignee' => 'required|string',
            'province' => 'required|integer',
            'city' => 'required|integer',
            'district' => 'required|integer',
            'address' => 'required|string',
            'mobile' => 'required|size:11'
        ]);

        $uid = $this->authService->authorization();   //返回用户ID
        if (isset($uid['error']) && $uid['error'] > 0) {
            return $this->apiReturn($uid, 1);
        }

        $args = $request->all();
        $args['uid'] = $uid;

        $address = $this->userService->addressUpdate($args);

        return $this->apiReturn($address);
    }

    /**
     * 删除收货地址
     * @param Request $request
     * @return array
     */
    public function addressDelete(Request $request)
    {
        //数据验证
        $this->validate($request, [
            'id' => 'required|integer'
        ]);

        $uid = $this->authService->authorization();   //返回用户ID
        if (isset($uid['error']) && $uid['error'] > 0) {
            return $this->apiReturn($uid, 1);
        }

        $args = $request->all();
        $args['uid'] = $uid;
        $address = $this->userService->addressDelete($args);

        return $this->apiReturn($address);
    }

    /**
     * 用户资金管理
     * @param Request $request
     * @return array
     */
    public function account(Request $request)
    {

        //数据验证
        $this->validate($request, []);

        $uid = $this->authService->authorization();   //返回用户ID
        if (isset($uid['error']) && $uid['error'] > 0) {
            return $this->apiReturn($uid, 1);
        }

        $userInfo = $this->userService->userAccount($uid);

        return $this->apiReturn($userInfo);
    }

    /**
     * 账户明细列表
     * @param Request $request
     * @return array
     */
    public function accountDetail(Request $request)
    {

        //数据验证
        $this->validate($request, [
            'page' => "required|integer",
            'size' => "required|integer"
        ]);

        $uid = $this->authService->authorization();   //返回用户ID
        if (isset($uid['error']) && $uid['error'] > 0) {
            return $this->apiReturn($uid, 1);
        }

        $args = $request->all();
        $args['user_id'] = $uid;
        $list = $this->userService->accountDetail($args);

        return $this->apiReturn($list);
    }

    /**
     * 充值 操作
     * @param Request $request
     * @return array
     */
    public function deposit(Request $request)
    {

        //数据验证
        $this->validate($request, [
            'amount' => "required|integer",
            'user_note' => "required|string"
        ]);

        $uid = $this->authService->authorization();   //返回用户ID
        if (isset($uid['error']) && $uid['error'] > 0) {
            return $this->apiReturn($uid, 1);
        }

        $args = $request->all();
        $args['uid'] = $uid;
        $args['payment'] = "微信";
        $res = $this->userService->deposit($args);

        return $this->apiReturn($res);
    }

    /**
     * 提现记录（充值  提现）
     * @param Request $request
     * @return array
     */
    public function accountLog(Request $request)
    {

        //数据验证
        $this->validate($request, [
            'page' => "required|integer",
            'size' => "required|integer"
        ]);

        $uid = $this->authService->authorization();   //返回用户ID
        if (isset($uid['error']) && $uid['error'] > 0) {
            return $this->apiReturn($uid, 1);
        }

        $args = $request->all();
        $args['user_id'] = $uid;
        $list = $this->userService->accountLog($args);

        return $this->apiReturn($list);
    }

    /**
     * 我的收藏
     * @param Request $request
     * @return array
     */
    public function collectGoods(Request $request)
    {

        //数据验证
        $this->validate($request, [
            'page' => "required|integer",
            'size' => "required|integer"
        ]);

        $uid = $this->authService->authorization();   //返回用户ID
        if (isset($uid['error']) && $uid['error'] > 0) {
            return $this->apiReturn($uid, 1);
        }

        $args = $request->all();
        $args['user_id'] = $uid;

        $list = $this->userService->collectGoods($args);

        return $this->apiReturn($list);
    }

    /**
     * 添加收藏
     * @param Request $request
     * @return mixed
     */
    public function collectAdd(Request $request)
    {
        //数据验证
        $this->validate($request, [
            'id' => "required|integer",
        ]);

        $uid = $this->authService->authorization();   //返回用户ID
        if (isset($uid['error']) && $uid['error'] > 0) {
            return $this->apiReturn($uid, 1);
        }

        $args = $request->all();
        $args['uid'] = $uid;

        $res = $this->userService->collectAdd($args);

        return $this->apiReturn($res, ($res == 1) ? 0 : 1);
    }

    /**
     * 关注
     * @param Request $request
     * @return mixed
     */
    public function collectStore(Request $request)
    {
        //数据验证
        $this->validate($request, [
            'id' => "required|integer",
        ]);

        $uid = $this->authService->authorization();   //返回用户ID
        if (isset($uid['error']) && $uid['error'] > 0) {
            return $this->apiReturn($uid, 1);
        }

        $res = $this->userService->collectStore($uid);

        return $res;
    }

    /**
     * 优惠券
     * @param Request $request
     * @return array
     */
    public function conpont(Request $request)
    {

        //数据验证
        $this->validate($request, [
            'page' => "required|integer",
            'size' => "required|integer",
            'type' => "required|integer"
        ]);

        $uid = $this->authService->authorization();   //返回用户ID
        if (isset($uid['error']) && $uid['error'] > 0) {
            return $this->apiReturn($uid, 1);
        }

        $args = $request->all();
        $args['user_id'] = $uid;
        $list = $this->userService->myConpont($args);

        return $this->apiReturn($list);
    }

    /**
     * 上传图片
     * @param Request $request
     * @return mixed
     */
    public function uploadFile(Request $request)
    {
        $file = $request->file('file');

        $destinationPath = '../resources/uploads';
        $path = $file->move($destinationPath, $file->getClientOriginalName());
        //        $res = $request->allFiles($destinationPath);

        return $this->apiReturn(json_encode($path));
    }


    /**
     * 增值发票详情
     * @param Request $request
     * @return array
     */
    public function invoiceDetail(Request $request)
    {
        //数据验证
        $this->validate($request, [
        ]);
        $uid = $this->authService->authorization();   //返回用户ID
        if (isset($uid['error']) && $uid['error'] > 0) {
            return $this->apiReturn($uid, 1);
        }

        $args['uid'] = $uid;
        $invoice = $this->userService->invoiceDetail($args);
        return $this->apiReturn($invoice);
    }

    /**
     * 添加增值发票
     * @param Request $request
     * @return array
     */
    public function invoiceAdd(Request $request)
    {
        //数据验证
        $this->validate($request, [
            'company_name' => 'required|string',
            'company_address' => 'required|string',
            'tax_id' => 'required|string',
            'company_telephone' => 'required|size:11',
            'bank_of_deposit' => 'required|string',
            'bank_account' => 'required|string',
            'consignee_name' => 'required|string',
            'consignee_mobile_phone' => 'required|size:11',
            'province' => 'required|integer',
            'city' => 'required|integer',
            'district' => 'required|integer',
            'country' => 'required|integer',
            'consignee_address' => 'required|string',
        ]);
        $uid = $this->authService->authorization();   //返回用户ID
        if (isset($uid['error']) && $uid['error'] > 0) {
            return $this->apiReturn($uid, 1);
        }

        $args = $request->all();
        $args['uid'] = $uid;
        $invoice = $this->userService->invoiceAdd($args);
        return $this->apiReturn($invoice);
    }

    /**
     * 编辑增值发票  显示数据
     * @param Request $request
     * @return array
     */
    public function invoiceUpdate(Request $request)
    {
        //数据验证
        $this->validate($request, [
            'id' => 'required|integer'
        ]);

        $uid = $this->authService->authorization();   //返回用户ID
        if (isset($uid['error']) && $uid['error'] > 0) {
            return $this->apiReturn($uid, 1);
        }

        $args = $request->all();
        $args['uid'] = $uid;
        $invoice = $this->userService->invoiceUpdate($args);

        return $this->apiReturn($invoice);
    }

    /**
     * 删除增值发票
     * @param Request $request
     * @return array
     */
    public function invoiceDelete(Request $request)
    {
        //数据验证
        $this->validate($request, [
            'id' => 'required|integer'
        ]);

        $uid = $this->authService->authorization();   //返回用户ID
        if (isset($uid['error']) && $uid['error'] > 0) {
            return $this->apiReturn($uid, 1);
        }

        $args = $request->all();
        $args['uid'] = $uid;
        $invoice = $this->userService->invoiceDelete($args);

        return $this->apiReturn($invoice);
    }
}
