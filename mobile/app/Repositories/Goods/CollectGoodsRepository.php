<?php

namespace App\Repositories\Goods;

use App\Models\CollectGoods;
use App\Contracts\Repositories\Goods\CollectGoodsRepositoryInterface;

class CollectGoodsRepository implements CollectGoodsRepositoryInterface
{

    /**
     * 用户收藏的商品
     * @param $userId
     * @param $page
     * @param $size
     * @return mixed
     */
    public function findByUserId($userId, $page, $size)
    {
        $start = ($page - 1) * $size;

        return CollectGoods::where('user_id', $userId)
            ->offset($start)
            ->limit($size)
            ->get()
            ->toArray();
    }

    /**
     * 查找我的收藏商品
     * @param $goodsId
     * @param $uid
     * @return array
     */
    public function findOne($goodsId, $uid)
    {
        $cg = CollectGoods::where('goods_id', $goodsId)
            ->where('user_id', $uid)
            ->first();

        if ($cg === null) {
            return [];
        }
        return $cg->toArray();
    }

    /**
     * 添加我的收藏
     * @param $goodsId
     * @param $uid
     * @return boolean
     */
    public function addCollectGoods($goodsId, $uid)
    {
        $model = new CollectGoods();

        $model->user_id = $uid;
        $model->goods_id = $goodsId;
        $model->add_time = gmtime();
        $model->is_attention = 0;

        return $model->save();
    }

    /**
     * 删除我的收藏
     * @param $goodsId
     * @param $uid
     */
    public function deleteCollectGoods($goodsId, $uid)
    {
        return CollectGoods::where('goods_id', $goodsId)
            ->where('user_id', $uid)
            ->delete();
    }
}
