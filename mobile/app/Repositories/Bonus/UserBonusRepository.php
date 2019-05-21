<?php

namespace App\Repositories\Bonus;

use App\Models\UserBonus;
use App\Contracts\Repositories\Bonus\UserBonusRepositoryInterface;

class UserBonusRepository implements UserBonusRepositoryInterface
{

    /**
     * 返回用户红包数量
     * @param $userId
     * @return mixed
     */
    public function getUserBonusCount($userId)
    {
        return UserBonus::where('user_id', $userId)
            ->count();
    }
}
