<?php

namespace App\Repositories\User;

use App\Models\User;
use App\Models\ConnectUser;
use App\Contracts\Repositories\User\UserRepositoryInterface;

class UserRepository implements UserRepositoryInterface
{
    /**
     * 获取用户信息
     * @param $uid
     * @return array
     */
    public function userInfo($uid)
    {
        $user = User::where('user_id', $uid)
            ->select('user_id as id', 'user_name', 'nick_name', 'sex', 'birthday', 'user_money', 'frozen_money', 'pay_points', 'rank_points', 'address_id', 'qq', 'mobile_phone', 'user_picture')
            ->first();
        if ($user === null) {
            return [];
        }
        return $user->toArray();
    }

    /**
     * 更新用户信息
     * @param  $res
     * @return
     */
    public function renewUser($res)
    {
        $model = new User();
        // 更新记录
        $model = User::where('user_id', $res['user_id'])->first();

        if ($model === null) {
            return [];
        }

        $model->fill($res);

        return $model->save();
    }

    /**
     * 查询绑定用户信息 getConnectUser
     * @param  $unionid
     * @return
     */
    public function getConnectUser($unionid)
    {
        $connectUser = User::select('users.user_id')
            ->leftjoin('connect_user', 'connect_user.user_id', '=', 'users.user_id')
            ->where('connect_user.open_id', $unionid)
            ->first();
        if ($connectUser === null) {
            return [];
        }
        return $connectUser->toArray();
    }

    /**
     * 新增社会化登录用户信息
     * @param $res
     * @return
     */
    public function addConnectUser($res)
    {
        $model = new ConnectUser();

        $model->fill($res);
        $model->save();

        return $model->id;
    }

    /**
     * 更新社会化登录用户信息
     * @param  $res
     * @return
     */
    public function updateConnnectUser($res)
    {
        $model = new ConnectUser();
        // 更新记录
        $model = ConnectUser::where('open_id', $res['open_id'])->first();

        if ($model === null) {
            return [];
        }

        $model->fill($res);

        return $model->save();
    }

    /**
     * @param $id
     * @param $uid
     * @return mixed
     */
    public function setDefaultAddress($id, $uid)
    {
        $model = User::where('user_id', $uid)
            ->first();
        if ($model == null) {
            return false;
        }

        $model->address_id = $id;
        return $model->save();
    }
}
