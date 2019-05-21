<?php

namespace App\Repositories\User;

use App\Models\UserAddress;
use Illuminate\Support\Facades\DB;
use App\Contracts\Repositories\User\AddressRepositoryInterface;

class AddressRepository implements AddressRepositoryInterface
{

    /**
     * 获取用户默认收货地址
     * @param $id
     * @return mixed
     */
    public function getDefaultByUserId($id)
    {
//        $userAddress = UserAddress::select('user_address.*')
//            ->select('user_address.address_id', 'user_address.address_name', 'user_address.consignee', 'user_address.email', 'user_address.mobile', 'user_address.country', 'user_address.province', 'user_address.city', 'user_address.district', 'user_address.street', 'user_address.address')
//            ->join('users as u', 'u.user_id', '=', 'user_address.user_id')
//            ->where('user_address.user_id', $id)
//            ->first();

        $prefix = app('config')->get('database.connections.mysql.prefix');

        $sql = "select * from `{$prefix}user_address` where user_id = {$id} and address_id = (select address_id from `{$prefix}users` where user_id = {$id})";

        $userAddress = DB::select($sql);
        if ($userAddress == null) {
            return [];
        }

        $userAddress = $userAddress[0];
        foreach ($userAddress as $k => $v) {
            $data[$k] = $v;
        }
        return $data;
    }

    /**
     * 根据用户ID获取收货地址
     * @param $id
     * @return mixed
     */
    public function addressListByUserId($id)
    {
        return UserAddress::select('address_id', 'address_name', 'consignee', 'email', 'mobile', 'country', 'province', 'city', 'district', 'street', 'address')
            ->where('user_id', $id)
            ->get()
            ->toArray();
    }

    /**
     * 添加收货地址
     * @param $args
     * @return int
     */
    public function addAddress($args)
    {
        $model = new UserAddress();
        foreach ($args as $k => $v) {
            $model->$k = $v;
        }
        $model->save();
        return $model->address_id;
    }

    /**
     * 编辑收货地址
     * @param int $id
     * @param array $args
     * @return array
     */
    public function updateAddress($id, array $args)
    {
        $model = UserAddress::where('user_id', $args['user_id'])
            ->where('address_id', $id)
            ->first();

        if ($model === null) {
            return [];
        }

        foreach ($args as $k => $v) {
            $model->$k = $v;
        }
        return $model->save();
    }

    /**
     * 删除收货地址
     * @param $id
     * @param $uid
     */
    public function deleteAddress($id, $uid)
    {
        return UserAddress::where('user_id', $uid)
            ->where('address_id', $id)
            ->delete();
    }

    /**
     * 获取用户地址信息
     * @param $address_id
     * @return array
     */
    public function find($address_id)
    {
        $address = UserAddress::where('address_id', $address_id)
            ->first();

        if ($address_id === null) {
            return [];
        }

        return $address;
    }

    /**
     * 获取用户收货地址 对应地区
     * @param $address_id
     * @return array
     */
    public function getRegionIdList($address_id)
    {
        $arr = [];
        if ($model = UserAddress::where(['address_id' => $address_id])->first()) {
            $arr['country'] = $model->country;
            $arr['province'] = $model->province;
            $arr['city'] = $model->city;
            $arr['district'] = $model->district;
        }
        return $arr;
    }
}
