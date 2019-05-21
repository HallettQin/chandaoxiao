<?php

namespace App\Repositories\Shop;

use App\Models\TouchAd;
use App\Models\SellerShopinfo;
use App\Contracts\Repositories\Shop\ShopRepositoryInterface;

class ShopRepository implements ShopRepositoryInterface
{

    /**
     * @param id
     * @return array
     */
    public function get($id)
    {
        return $this->findBY('id', $id);
    }

    /**
     * 根据其他值找店铺信息
     * @param $key
     * @param $val
     * @return array
     */
    public function findBY($key, $val)
    {
        $list = SellerShopinfo::select('ru_id', 'shop_name', 'shop_logo', 'shopname_audit')
            ->with(['MerchantsShopInformation' => function ($query) {
                $query->select('shoprz_brandName', 'user_id', 'shopNameSuffix', 'rz_shopName');
            }])
            ->where($key, $val)
            ->get()
            ->toArray();

        if (empty($list)) {
            $list = [];
            return $list;
        }

        //
        foreach ($list as $k=>$v) {
            $list[$k]['brandName'] = $v['merchants_shop_information']['shoprz_brandName'];
            $list[$k]['shopNameSuffix'] = $v['merchants_shop_information']['shopNameSuffix'];
            $list[$k]['rz_shopName'] = $v['merchants_shop_information']['rz_shopName'];

            unset($list[$k]['merchants_shop_information']);
        }

        return $list;
    }

    /**
     * 获取轮播图
     * @param $tc_type
     * @param $num
     * @return array
     */
    public function getPositions($tc_type = 'banner', $num = 3)
    {
        $time = local_gettime();

        $res = TouchAd::select('ad_id', 'touch_ad.position_id', 'media_type', 'ad_link', 'ad_code', 'ad_name')
                ->with(['position'])
                ->join('touch_ad_position', 'touch_ad_position.position_id', '=', 'touch_ad.position_id')
                ->where("start_time", '<=', $time)
                ->where("end_time", '>=', $time)
                ->where("enabled", 1)
                ->where("touch_ad_position.tc_type", $tc_type)
                ->orderby('ad_id', 'desc')
                ->limit($num)
                ->get()
                ->toArray();

        $res = array_map(function ($v) {
            if (!empty($v['position'])) {
                $temp = array_merge($v, $v['position']);
                unset($temp['position']);
                return $temp;
            }
        }, $res);

        return $res;
    }

    //end
}
