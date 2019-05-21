<?php

namespace App\Repositories\Store;

use App\Models\CollectStore;
use App\Contracts\Repositories\Store\CollectStoreRepositoryInterface;

class CollectStoreRepository implements CollectStoreRepositoryInterface
{

    /**
     * 用户关注的店铺
     * @param $uid
     * @param $page
     * @param $size
     * @return mixed
     */
    public function findByUserId($uid)
    {
        $list = CollectStore::select('ru_id')
            ->where('user_id', $uid)
            ->get()
            ->toArray();
        return $list;
    }

    /**
     * 查找关注店铺
     * @param $ruId
     * @param $uid
     * @return array
     */
    public function findOne($ruId, $uid)
    {
        $cg = CollectStore::where('ru_id', $ruId)
            ->where('user_id', $uid)
            ->first();

        if ($cg === null) {
            return [];
        }
        return $cg->toArray();
    }

    /**
     * 添加关注
     * @param $ruId
     * @param $uid
     * @return boolean
     */
    public function addCollectStore($ruId, $uid)
    {
        $model = new CollectStore();

        $model->user_id = $uid;
        $model->ru_id = $ruId;
        $model->add_time = gmtime();
        $model->is_attention = 0;

        return $model->save();
    }

    /**
     * 删除关注
     * @param $ruId
     * @param $uid
     */
    public function deleteCollectStore($ruId, $uid)
    {
        return CollectStore::where('ru_id', $ruId)
            ->where('user_id', $uid)
            ->delete();
    }
}
