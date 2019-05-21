<?php

namespace App\Modules\OfflineStore\Controllers;

use App\Extensions\QRcode;
use App\Modules\Base\Controllers\FrontendController;

class IndexController extends FrontendController
{
    private $goods_id = 0;
    private $page = 1;
    private $size = 10;


    public function __construct()
    {
        parent::__construct();

        $this->size = I('request.size', 10);
        $this->page = I('request.page', 1, 'intval');
    }

    /**
     * 选择门店地址
     */
    public function actionStoreList()
    {
        if (IS_AJAX) {
            $province = I('province', 0, 'intval');
            $city = I('city', 0, 'intval');
            $district = I('district', 0, 'intval');
            $street = I('street', 0, 'intval');
            $type = I('type', '', 'trim');

            $where = '1';
            if($province > 0){
                $where .= " AND o.province = ".$province;
            }
            if($city > 0){
                $where .= " AND o.city = ".$city;
            }
            if($district > 0){
                $where .= " AND o.district = ".$district;
            }
            if($street > 0){
                $where .= " AND o.street = ".$street;
            }

            $goods_id = I('request.goods_id', 0, 'intval'); //商品ID
            $spec_arr = isset($_REQUEST['spec_arr']) ? $_REQUEST['spec_arr'] : '';//商品属性

            $userId = !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
            $store_id = getStoreIdByGoodsId($goods_id); // 商品所在门店ID

            /*获取该商品有货门店*/
            $sql = "SELECT o.id,o.stores_name,s.goods_id,o.stores_address,o.stores_traffic_line,o.ru_id ,p.region_name as province ,s.goods_number ,o.stores_address, o.stores_tel, o.stores_opening_hours, "
                    . "c.region_name as city ,d.region_name as district FROM {pre}offline_store AS o "
                    . "LEFT JOIN {pre}store_goods  AS s ON o.id = s.store_id "
                    . "LEFT JOIN {pre}region AS p ON p.region_id = o.province "
                    . "LEFT JOIN {pre}region AS c ON c.region_id = o.city "
                    . "LEFT JOIN {pre}region AS d ON d.region_id = o.district "
                    . "WHERE $where  AND o.is_confirm = 1 GROUP BY o.id";

            // $store_list = $this->db->getAll($sql);
            $stores = $GLOBALS['db']->query($sql);
            $total = is_array($stores) ? count($stores) : 0;
            $store_list = $GLOBALS['db']->selectLimit($sql, $this->size, ($this->page - 1) * $this->size);

            $is_spec = explode(',', $spec_arr);
            if (!empty($store_list)) {
                foreach ($store_list as $k => $v) {
                    if (is_spec($is_spec) == true) {
                        $products = get_warehouse_id_attr_number($v['goods_id'], $spec_arr, $v['ru_id'], 0, 0, '', $v['id']);//获取属性库存
                        $v['goods_number'] = $products['product_number'];
                        if ($products['product_number'] == 0) {
                            unset($store_list[$k]);
                        }
                    }
                    if ($v['id'] == $store_id) {
                        $store_list[$k]['checked'] = 1;
                    }
                }
            }

            exit(json_encode(['store_list' => $store_list, 'totalPage' => ceil($total / $this->size) ]));
        }
    }

    /**
     * 订单详情页====门店详情页
     */
    public function actionOfflineStoreDetail()
    {
        $this->actionCheckLogin();

        $storeId = !empty($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
        $order_id = I('order_id', 0 ,'intval');

        $pay_status = dao('order_info')->where(['order_id' => $order_id])->getField('pay_status');
        if ($pay_status != PS_PAYED) {
            show_message('订单支付完成，才能查看提货码！');
        }

        /*获取订单门店信息start*/
        $sql = "SELECT store_id,pick_code,take_time  FROM" . $this->ecs->table("store_order") . " WHERE id = '$storeId'";
        $stores = $this->db->getRow($sql);
        $this->assign('store', $stores);
        if ($stores['store_id'] > 0) {
            $sql = "SELECT o.*,p.region_name as province,c.region_name as city,d.region_name as district FROM" . $this->ecs->table('offline_store') . " AS o "
                . "LEFT JOIN " . $this->ecs->table('region') . " AS p ON p.region_id = o.province "
                . "LEFT JOIN " . $this->ecs->table('region') . " AS c ON c.region_id = o.city "
                . "LEFT JOIN " . $this->ecs->table('region') . " AS d ON d.region_id = o.district WHERE o.id = '" . $stores['store_id'] . "'";
            $offline_store = $this->db->getRow($sql);
            if ($offline_store) {
                $offline_store['stores_img'] = get_image_path($offline_store['stores_img']);
            }
            $this->assign('offline_store', $offline_store);
        }
        /*获取订单门店信息 end*/
        $this->assign('page_title', '订单提货码');
        $this->display();
    }

    public function actionCreateQrcode()
    {
        $value = I('get.value', '');

        // 纠错级别：L、M、Q、H
        $errorCorrectionLevel = 'M';
        // 点的大小：1到10
        $matrixPointSize = 8;
        @QRcode::png($value, false, $errorCorrectionLevel, $matrixPointSize, 2);
    }

    //地图
    public function actionMap()
    {
        $address = I('get.address', '');
        if (empty($address)) {
            $address = C('shop.shop_address');
        }
        $url = 'http://apis.map.qq.com/tools/routeplan/eword=' . $address . '?referer=myapp&key=' . C('shop.tengxun_key') . '';
        redirect($url);
    }

    /**
     * 验证是否登录
     */
    public function actionCheckLogin()
    {
        if (IS_AJAX) {
            if (empty($_SESSION['user_id'])) {
                exit(json_encode(['error' => '1', 'url' => url('user/login/index', ['back_act' => urlencode($back_act)]) ]));
            } else {
                exit(json_encode(['error' => 0]));
            }
        } else {
            if (empty($_SESSION['user_id'])) {
                $back_act = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : __HOST__ . $_SERVER['REQUEST_URI'];
                $this->redirect('user/login/index', ['back_act' => urlencode($back_act)]);
            }
        }
    }

}
