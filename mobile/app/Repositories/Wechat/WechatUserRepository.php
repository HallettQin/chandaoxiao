<?php

namespace App\Repositories\Wechat;

use App\Models\Wechat;
use App\Models\WechatUser;
use App\Contracts\Repositories\Wechat\WechatUserRepositoryInterface;

class WechatUserRepository implements WechatUserRepositoryInterface
{
    public function all($columns = ['*'])
    {
    }

    public function paginate($perPage = 15, $columns = ['*'])
    {
    }

    public function create(array $data)
    {
    }

    public function update(array $data, $id)
    {
    }

    public function delete($id)
    {
    }

    public function find($id, $columns = ['*'])
    {
    }

    public function findBy($field, $value, $columns = ['*'])
    {
    }

    /**
     * 获取微信通配置信息
     * @param  integer $ru_id
     * @return
     */
    public function getWechatConfig($ru_id = 0)
    {
        if ($ru_id > 0) {
            $where = ['ru_id' => $ru_id];
        } else {
            $where = ['default_wx' => 1];
        }
        $wechat = Wechat::where($where)
            ->select('id', 'name', 'orgid', 'weixin', 'token', 'appid', 'appsecret', 'type', 'status')
            ->first();

        if ($wechat === null) {
            return [];
        }

        return $wechat->toArray();
    }

    /**
     * 新增微信用户信息
     * @param $res
     * @return
     */
    public function addWechatUser($res)
    {
        $model = new WechatUser();

        $wechat = $this->getWechatConfig();

        $res['wechat_id'] = $wechat['id'];
        $res['from'] = 3; // 小程序注册来源

        $model->fill($res);
        $model->save();

        return $model->uid;
    }

    /**
     * 更新微信用户信息
     * @param array $info 微信用户信息
     * @return
     */
    public function updateWechatUser($res)
    {
        $model = new WechatUser();

        $wechat = $this->getWechatConfig();

        // 更新记录
        $model = WechatUser::where('unionid', $res['unionid'])
            ->where('wechat_id', $wechat['id'])
            ->first();

        if ($model === null) {
            return [];
        }

        $model->fill($res);

        return $model->save();
    }

    /**
     * 查询微信用户信息
     * @param $unionid
     * @return
     */
    public function getWechatUserInfo($unionid)
    {
        $wechat = $this->getWechatConfig();

        $wechatuser = WechatUser::where('unionid', $unionid)
            ->where('wechat_id', $wechat['id'])
            ->select('subscribe', 'openid', 'nickname', 'sex', 'city', 'country', 'province', 'headimgurl', 'unionid', 'ect_uid')
            ->first();

        if ($wechatuser === null) {
            return [];
        }

        return $wechatuser->toArray();
    }
}
