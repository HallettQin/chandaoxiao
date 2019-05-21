<?php

namespace App\Repositories\Brand;

use App\Models\Brand;
use App\Models\Goods;
use App\Contracts\Repositories\Brand\BrandRepositoryInterface;

class BrandRepository implements BrandRepositoryInterface
{
    /**
     * 获取品牌列表
     */
    public function getAllBrands()
    {
        $brand_list = S('brand_list');
        //缓存一小时
        if (!empty($brand_list)) {
            return $brand_list;
        }

        $res = Brand::select('brand_id', 'brand_name', 'brand_logo', 'brand_desc')
            ->where('is_show', 1)
            ->groupby('brand_id')
            ->groupby('sort_order')
            ->orderby('sort_order', 'ASC')
            ->get()
            ->toArray();
        $res = array_values($res);
        $arr = [];

        foreach ($res as $key=>$row) {
            if ($key == 0) {
                $arr['top'][$row['brand_id']]['brand_id']   =   $row['brand_id'];
                $arr['top'][$row['brand_id']]['brand_name'] =   $row['brand_name'];
                //                $arr['top'][$row['brand_id']]['url']    =  build_uri('brand', array('bid' => $row['brand_id']));
                $arr['top'][$row['brand_id']]['brand_logo'] =  get_data_path($row['brand_logo'], 'brandlogo/');
                $arr['top'][$row['brand_id']]['goods_num']  =   $this->goodsCountByBrand($row['brand_id']);
                $arr['top'][$row['brand_id']]['brand_desc'] =   htmlspecialchars($row['brand_desc'], ENT_QUOTES);
            } elseif ($key > 0 && $key < 4) {
                $arr['center'][$row['brand_id']]['brand_id']   =   $row['brand_id'];
                $arr['center'][$row['brand_id']]['brand_name'] =   $row['brand_name'];
                //                $arr['center'][$row['brand_id']]['url']    =  build_uri('brand', array('bid' => $row['brand_id']));
                $arr['center'][$row['brand_id']]['brand_logo'] =   get_data_path($row['brand_logo'], 'brandlogo/');
                $arr['center'][$row['brand_id']]['goods_num']  =  $this->goodsCountByBrand($row['brand_id']);
                $arr['center'][$row['brand_id']]['brand_desc'] =   htmlspecialchars($row['brand_desc'], ENT_QUOTES);
            } elseif ($key > 4 && $key < 4) {
                $arr['list1'][$row['brand_id']]['brand_id']   =   $row['brand_id'];
                $arr['list1'][$row['brand_id']]['brand_name'] =   $row['brand_name'];
                //                $arr['list1'][$row['brand_id']]['url']    =  build_uri('brand', array('bid' => $row['brand_id']));
                $arr['list1'][$row['brand_id']]['brand_logo'] =   get_data_path($row['brand_logo'], 'brandlogo/');
                $arr['list1'][$row['brand_id']]['goods_num']  =   $this->goodsCountByBrand($row['brand_id']);
                $arr['list1'][$row['brand_id']]['brand_desc'] =   htmlspecialchars($row['brand_desc'], ENT_QUOTES);
            } else {
                $arr['list2'][$row['brand_id']]['brand_id']   =   $row['brand_id'];
                $arr['list2'][$row['brand_id']]['brand_name'] =   $row['brand_name'];
                //                $arr['list2'][$row['brand_id']]['url']    =      build_uri('brand', array('bid' => $row['brand_id']));
                $arr['list2'][$row['brand_id']]['brand_logo'] =  get_data_path($row['brand_logo'], 'brandlogo/');
                $arr['list2'][$row['brand_id']]['goods_num']  =   $this->goodsCountByBrand($row['brand_id']);
                $arr['list2'][$row['brand_id']]['brand_desc'] =   htmlspecialchars($row['brand_desc'], ENT_QUOTES);
            }
        }

        S('brand_list', $arr, ['expire'=>3600]);

        return $arr;
    }

    /**
     * 获取单个品牌
     * @param id
     * @return array
     */
    public function getBrandDetail($id)
    {
        $brand = Brand::select('brand_id', 'brand_name')
            ->where('is_show', 1)
            ->where('is_delete', 0)
            ->where('brand_id', $id)
            ->first()
            ->toArray();

        return $brand;
    }

    /**
     * @param $brand_id
     * @return integer
     */
    private function goodsCountByBrand($brand_id)
    {
        $goodsNum = Goods::select()
            ->where('brand_id', $brand_id)
            ->where('is_on_sale', 1)
            ->where('is_alone_sale', 1)
            ->where('is_delete', 0)
            ->count();

        return $goodsNum;
    }
}
