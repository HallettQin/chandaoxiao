<?php

namespace App\Modules\Location\Controllers;

use App\Modules\Base\Controllers\FrontendController;

class IndexController extends FrontendController
{
    public function __construct()
    {
        parent::__construct();
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Headers: X-HTTP-Method-Override, Content-Type, x-requested-with, Authorization');
        $this->load_helper(['function', 'ecmoban']);
    }

    /**
     * 城市列表
     */
    public function actionIndex()
    {
        if (IS_POST) {
            $city = [
                'region_id' => I('city_id', 0),
                'region_name' => I('city_name', '')
            ];
            // 记录最近访问城市
            $this->setRecentCity($city);

            // 获取省份
            $sql = "select `parent_id` from " . $GLOBALS['ecs']->table('region') . " where region_type = 2 and region_id = '{$city['region_id']}'";
            $city['parent_id'] = $GLOBALS['db']->getOne($sql);

            // 保存cookie
            cookie('lbs_city_name', escape(rtrim($city['region_name'], '市')));
            cookie('lbs_city', $city['region_id']);
            cookie('province', $city['parent_id']);
            cookie('city', $city['region_id']);
            cookie('district', 0);

            //清空
            cookie('type_province', 0);
            cookie('type_city', 0);
            cookie('type_district', 0);

            return;
        }
        $keywords = input('keywords', '', 'htmlspecialchars');
        // 最近访问城市
        $this->assign('recent_city', $this->getRecentCity());
        // 城市列表
        $this->assign('city_list', $this->getCity($keywords));
        $this->assign('page_title', '城市选择');
        $this->display();
    }

    /**
     * 返回城市信息
     */
    public function actionInfo()
    {
        $current_city_id = cookie('lbs_city');
        $current_city_info = get_region_name(intval($current_city_id));
        // 位置选择页面需要强制显示当前城市信息，标记为force
        if (!empty($current_city_info) && !isset($_GET['force'])) {
            exit(json_encode($current_city_info));
        }
        // first query
        $city_name = I('city_name');
        $city_name = rtrim($city_name, '市');
        $city_group = $this->getCity($city_name);
        if (is_array($city_group)) {
            foreach ($city_group as $key => $city_list) {
                $city_list = end($city_list);
                // 设置cookie
                cookie('lbs_city_name', escape(rtrim($city_list['region_name'], '市')));
                cookie('lbs_city', $city_list['region_id']);
                exit(json_encode($city_list));
            }
        }
    }

    /**
     * 获取最近访问城市
     */
    private function getRecentCity()
    {
        return isset($_SESSION['recent_city_history']) ? $_SESSION['recent_city_history'] : [];
    }

    /**
     * 设置最近访问城市
     * @param int $city_id
     */
    private function setRecentCity($data = [])
    {
        $_SESSION['recent_city_history'][$data['region_id']] = $data['region_name'];
    }

    /**
     * 获取城市
     * @return array
     */
    private function getCity($keywords = '')
    {
        $data = [];
        $cacheFile = dirname(ROOT_PATH) . '/data/sc_file/pin_regions.php';
        if (file_exists_case($cacheFile)) {
            require $cacheFile;
            ksort($data);
        }
        // 搜索处理
        if (!empty($keywords)) {
            foreach ($data as $key => $val) {
                foreach ($val as $k => $vo) {
                    if (strpos($vo['region_name'], $keywords) === false) {
                        unset($data[$key][$k]);
                    }
                }
                if (empty($data[$key])) {
                    unset($data[$key]);
                }
            }
        }
        return $data;
    }
}
