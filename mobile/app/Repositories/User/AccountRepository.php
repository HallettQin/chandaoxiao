<?php

namespace App\Repositories\User;

use App\Models\User;
use App\Models\AccountLog;
use App\Models\UserAccount;
use App\Contracts\Repositories\User\AccountRepositoryInterface;

class AccountRepository implements AccountRepositoryInterface
{
    const SURPLUS_SAVE = 0; // 为帐户冲值
     const SURPLUS_RETURN = 1; // 从帐户提款

    /**
     * 资金变动记录列表
     * @param $userId
     * @param $page
     * @param $size
     * @return mixed
     */
    public function accountList($userId, $page = 1, $size = 10)
    {
        $start = ($page - 1) * $size;

        return AccountLog::where('user_id', $userId)
            ->offset($start)
            ->limit($size)
            ->get()
            ->toArray();
    }

    /**
     * 提现记录  （ 充值  提现 ）
     * @param $userId
     * @param int $page
     * @param int $size
     * @return mixed
     * SURPLUS_SAVE    0// 为帐户冲值
     * SURPLUS_RETURN    1// 从帐户提款
     */
    public function accountLogList($userId, $page = 1, $size = 10)
    {
        $start = ($page - 1) * $size;

        return UserAccount::where('user_id', $userId)
            ->wherein('process_type', [0, 1])
            ->offset($start)
            ->limit($size)
            ->get()
            ->toArray();
    }

    /**
     * 充值操作
     * @param $arr
     * @return mixed
     */
    public function deposit($arr)
    {
        $model = new UserAccount();
        foreach ($arr as $k => $v) {
            $model->$k = $v;
        }

        return $model->save();
    }
    /**
     * 获取充值记录
     * @param id
     * @return array
     */
    public function getDepositInfo($id)
    {
        $model = UserAccount::where('id', $id)
            ->first();

        if ($model === null) {
            return [];
        }

        return $model->toArray();
    }

    /**
     * 记录帐户变动
     * @param   float   $user_money     可用余额变动
     * @param   float   $frozen_money   冻结余额变动
     * @param   int     $rank_points    等级积分变动
     * @param   int     $pay_points     消费积分变动
     * @param   string  $change_desc    变动说明
     * @param   int     $change_type    变动类型：系统
     * @param   int     $uid            用户ID
     * @return  boolean
     */
    public function logAccountChange($user_money = 0, $frozen_money = 0, $rank_points = 0, $pay_points = 0, $change_desc = '', $change_type = 99, $uid)
    {
        $flag = 0;
        /* 更新用户信息 */
        if ($member = User::where('user_id', $uid)->first()) {
            $member->user_money += $user_money;
            $member->frozen_money += $frozen_money;
            $member->rank_points += $rank_points;
            $member->pay_points += $pay_points;
            $flag = $member->save();
        }

        if ($flag) {
            /* 插入帐户变动记录 */
            $model = new AccountLog;
            $model->user_id             = $uid;
            $model->pay_points          = $pay_points;
            $model->change_desc         = $change_desc;
            $model->user_money          = $user_money;
            $model->rank_points         = $rank_points;
            $model->frozen_money        = $frozen_money;
            $model->change_type         = $change_type;
            $model->change_time         = gmtime();

            if ($model->save()) {
                return true;
            }
        }
        return false;
    }
}
