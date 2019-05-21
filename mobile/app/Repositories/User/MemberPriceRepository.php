<?php

namespace App\Repositories\User;

use App\Models\MemberPrice;
use App\Contracts\Repositories\User\MemberPriceRepositoryInterface;

class MemberPriceRepository implements MemberPriceRepositoryInterface
{

    /**
     * 根据用户ID获取会员价格
     * @param $rank
     * @param $goods_id
     * @return mixed
     */
    public function getMemberPriceByUid($rank, $goods_id)
    {
        $price = MemberPrice::where('user_rank', $rank)->where('goods_id', $goods_id)->pluck('user_price')->toArray();

        if (!empty($price)) {
            $price = $price[0];
        }

        return $price;
    }
}
