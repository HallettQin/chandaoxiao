<?php

namespace App\Repositories\Region;

use App\Models\Region;
use Illuminate\Support\Facades\Cache;
use App\Contracts\Repositories\Region\RegionRepositoryInterface;

class RegionRepository implements RegionRepositoryInterface
{

    /**
     * 获取地区名称
     * @param $regionId
     * @return mixed
     */
    public function getRegionName($regionId)
    {
        $regionName = Region::where('region_id', $regionId)
            ->pluck('region_name')
            ->toArray();
        if (empty($regionName)) {
            return '';
        }

        return $regionName[0];
    }

    /**
     * 获取某一级 地区列表
     * @param $type
     * @return array
     */
    public function regionListByType($type)
    {
        $reginList = Cache::get('region_list_'.$type);

        // 缓存一小时
        if (empty($reginList)) {
            $reginList = Region::where('region_type', $type)
                ->get()
                ->toArray();
            Cache::put('region_list_'.$type, $reginList, 60);
        }

        return $reginList;
    }

    /**
     * 根据ID获取地区类型
     * @param $regionId
     * @return string
     */
    public function getRegionTypeById($regionId)
    {
        $regionType = Region::where('region_id', $regionId)
            ->pluck('region_type')
            ->toArray();

        if (empty($regionType)) {
            return '';
        }

        return $regionType[0];
    }

    /**
     * 根据父ID查找出地区列表
     * @param $regionId
     */
    public function getRegionByParentId($regionId = 1)
    {
        $regionList = Region::where('parent_id', $regionId)
            ->get()
            ->toArray();

        return $regionList;
    }
}
