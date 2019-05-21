<?php

namespace App\Modules\Region\Controllers;

use App\Modules\Base\Controllers\FrontendController;

class IndexController extends FrontendController
{

    /*    public function actionIndex()
        {
            $type = I('type', 0);
            $parent = I('parent', 0);

            $arr['regions'] = get_regions($type, $parent);
            $arr['type'] = $type;
            $arr['target'] = I('target', '');
            exit(json_encode($arr));
        }*/

    public function __construct()
    {
        parent::__construct();
        //初始化位置信息
        //$this->init_params();
        L(require(LANG_PATH . C('shop.lang') . '/other.php'));
    }

    /**
     * 地区筛选
     */
    public function actionIndex()
    {
        $type = I('get.type', 0, 'intval');
        $parent = I('get.parent', 0, 'intval');
        $user_id = I('get.user_id', 0, 'intval');

        $regions = get_regions($type, $parent);
        //查询省下级，市区
        if ($type == 2 && !empty($regions)) {
            //查询市区下级,县
            foreach ($regions as $k => $v) {
                $regions[$k]['district'] = get_regions(3, $v['region_id']);
            }
        }
        $arr['regions'] = $regions;
        $arr['type'] = $type;
        $arr['user_id'] = $user_id;

        if ($user_id) {
            $user_address = get_user_address_region($user_id);
            $user_address = explode(",", $user_address['region_address']);

            if (in_array($parent, $user_address)) {
                $arr['isRegion'] = 1;
            } else {
                $arr['isRegion'] = 88; //原为0
                $arr['message'] = L('input_dispatch_addr');
                $arr['province'] = $_COOKIE['province'];
                $arr['city'] = $_COOKIE['city'];
            }
        }

        if (empty($arr['regions'])) {
            $arr['empty_type'] = 1;
        }
        echo json_encode($arr);
    }

    /**
     * 商品列表筛选城市切换
     */
    public function actionSelectRegionChild()
    {
        if (IS_AJAX) {
            $result = ['error' => 0, 'message' => '', 'content' => ''];
            clear_cache_files();
            $cat_id = I('get.cat_id', 0, 'intval');
            $province = I('get.province', 1, 'intval');
            $city = I('get.city', 0, 'intval');
            $district = I('get.district', 0, 'intval');
            $street = I('get.street', 0, 'intval');

            setcookie('province', $province, gmtime() + 3600 * 24 * 30);
            setcookie('city', $city, gmtime() + 3600 * 24 * 30);
            setcookie('district', $district, gmtime() + 3600 * 24 * 30);
            setcookie('street', $street, gmtime() + 3600 * 24 * 30);
            setcookie('regionId', $regionId, gmtime() + 3600 * 24 * 30);

            //清空
            setcookie('type_province', 0, gmtime() + 3600 * 24 * 30);
            setcookie('type_city', 0, gmtime() + 3600 * 24 * 30);
            setcookie('type_district', 0, gmtime() + 3600 * 24 * 30);
            setcookie('type_street', 0, gmtime() + 3600 * 24 * 30);

            $result['cat_id'] = $cat_id;

            die(json_encode($result));
        }
    }

    /**
     * 商品列表筛选城市切换
     */
    public function actionSelectDistrictList()
    {
        if (IS_AJAX) {
            $result = ['error' => 0, 'message' => '', 'content' => '', 'ra_id' => '', 'region_id' => ''];
            $region_id = I('get.region_id', 0, 'intval');
            $type = I('get.type', 0, 'intval');

            $where = "region_id = '" . $region_id . "'";
            $date = ['parent_id'];
            $parent_id = get_table_date('region', $where, $date, 2);
            if ($type == 0) {
                //市区筛选

                cookie('province', $parent_id);
                cookie('city', $region_id);

                $where = "parent_id = '" . $region_id . "' order by region_id asc limit 0, 1";
                $date = ['region_id', 'region_name'];
                $district_list = get_table_date('region', $where, $date, 1);
                if (count($district_list) > 0) {
                    cookie('district', $district_list[0]['region_id']);
                } else {
                    cookie('district', 0);
                }

                //清空
                cookie('type_province', 0);
                cookie('type_city', 0);
                cookie('type_district', 0);
            } else {
                $where = "region_id = '" . $parent_id . "'";
                $date = ['parent_id'];
                $province = get_table_date('region', $where, $date, 2);
                cookie('type_province', $province);
                cookie('type_city', $parent_id);
                cookie('type_district', $region_id);
            }

            die(json_encode($result));
        }
    }

    public function actionAddress()
    {
        $pid = input('parent_id', 1, 'intval');
        $list = $this->model->table('region')->field('region_id,region_name')->where(['parent_id' => $pid])->cache(true, 12 * 3600)->select();
        $res = [];
        foreach ($list as $key => $v) {
            $res[$key]['name'] = $v['region_name'];
            $res[$key]['id'] = $v['region_id'];
        }
        $addresslist = ["addressList" => $res];
        exit(json_encode($addresslist));
    }
}
