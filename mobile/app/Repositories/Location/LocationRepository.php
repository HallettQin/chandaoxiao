<?php
namespace App\Repositories\Location;

use App\Models\Region;
use App\Models\MerchantsRegionArea;
use App\Contracts\Repositories\Location\LocationRepositoryInterface;

/**
 * Class LocationRepository
 * @package App\Repositories\Location
 */
class LocationRepository implements LocationRepositoryInterface
{
    /**
     * 城市列表
     */
    public function Index($region_id =0)
    {
        // 获取省份地区
        if ($region_id > 0) {
            $list = Region::select('region.region_name', 'region.region_id')
            ->where('region_id', $region_id)
            ->get()
            ->toArray();
            foreach ($list as $key => $v) {
                $list2[$key] = Region::select('region.region_name', 'region.region_id')
                    ->where('parent_id', $v['region_id'])
                    ->get()
                    ->toArray();
                foreach ($list2 as $key2 => $val) {
                    $list2[$key2] = Region::select('region.region_name', 'region.region_id')
                    ->where('parent_id', $v['region_id'])
                    ->get()
                    ->toArray();
                }
                $list[$key]['tree'] =$list2;
                return $list;
            }
        }

        $list = Region::select('region.region_name', 'region.region_id')
                ->where('region_type', 2)
                ->get()
                ->toArray();

        return $list;
    }


    /**
     * 设置最近访问城市
     * @param int $city_id
     */
    public function SetCity($data = [])
    {
        $_SESSION['recent_city_history'][$data['region_id']] = $data['region_name'];
    }

    /**
     * 返回仓库信息
     */
    public function Info($region_id = 0, $region_type = '')
    {
        //获取仓库
        $city = RegionWarehouse::select('region_warehouse.region_name')
                ->where('region_id', $region_type)
                ->get()
                ->toArray();

        $area = MerchantsRegionArea::from('merchants_region_area as mra')
                ->select('mra.*')
                ->leftjoin('merchants_region_info as mri', 'mra.ra_id', '=', 'mri.ra_id')
                ->where('mri.region_id', $region_id)
                ->get()
                ->toArray();

        $msg['region_name'] = $city['0']['region_name'];
        $msg['ra_id'] = $area['0']['ra_id'];
        $msg['ra_name'] = $area['0']['ra_name'];
        $msg['region_id'] = $region_id;

        return $msg;
    }

    /**
     * 对应返回地区
     */
    public function contrast($region_name)
    {
        $name = Region::select('region.region_name', 'region.region_id', 'region.parent_id')
            ->where('region_name', 'like', '%'.$region_name.'%')
            ->where('region_type', 2)
            ->get()
            ->toArray();

        setcookie('lbs_city_name', $name['0']['region_name'].'市');
        setcookie('lbs_city', $name['0']['region_id']);
        setcookie('province', $name['0']['parent_id']);
        setcookie('city', $name['0']['region_id']);

        return $name;
    }
}
