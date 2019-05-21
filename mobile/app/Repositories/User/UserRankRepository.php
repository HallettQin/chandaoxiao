<?php

namespace App\Repositories\User;

use App\Models\User;
use App\Models\Goods;
use App\Models\UserRank;
use App\Services\AuthService;
use App\Contracts\Repositories\User\UserRankRepositoryInterface;

class UserRankRepository implements UserRankRepositoryInterface
{
    private $authService;
    private $memberPriceRepository;

    public function __construct(AuthService $authService, MemberPriceRepository $memberPriceRepository)
    {
        $this->authService = $authService;
        $this->memberPriceRepository = $memberPriceRepository;
    }

    /**
     *
     * @return null
     */
    public function getUserRankByUid()
    {
        $uid = $this->authService->authorization();

        if (empty($uid)) {
            $data = null;
        } else {
            $user = User::where(['user_id'=>$uid])->first();
            if (!$user) {
                $data = null;
            } else {
                $user_rank = UserRank::where('special_rank', 0)
                    ->where('min_points', '<=', $user->rank_points)
                    ->where('max_points', '>', $user->rank_points)
                    ->first();
                $data['rank_id'] = $user_rank->rank_id;
                $data['discount'] = $user_rank->discount * 0.01;
            }
        }
        return $data;
    }

    /**
     * 获取用户等级价格
     * @param $goods_id
     * @return mixed
     */
    public function getMemberRankPriceByGid($goods_id)
    {
        $user_rank = $this->getUserRankByUid();

        $shop_price = Goods::where('goods_id', $goods_id)->pluck('shop_price');
        $shop_price = $shop_price[0];

        if ($user_rank) {
            if ($price = $this->memberPriceRepository->getMemberPriceByUid($user_rank['rank_id'], $goods_id)) {
                return $price;
            }
            if ($user_rank['discount']) {
                $member_price = $shop_price * $user_rank['discount'];
            } else {
                $member_price = $shop_price;
            }
            return $member_price;
        } else {
            return $shop_price;
        }
    }
}
