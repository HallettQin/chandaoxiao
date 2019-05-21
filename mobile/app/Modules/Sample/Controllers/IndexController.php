<?php

namespace App\Modules\Sample\Controllers;

use App\Modules\Base\Controllers\FrontendController;

class IndexController extends FrontendController
{
    private $user_id = 0;
    private $goods_id = 0;
    private $region_id = 0;
    private $sampleid = 0;
    private $area_info = [];

    public function __construct()
    {
        parent::__construct();

        isset($_SESSION['user_id']) && $this->user_id = $_SESSION['user_id'];
        $this->init_params();//初始化位置信息

        $this->assign('user_id', $_SESSION['user_id']);
    }

    /**
     * 预售 商品详情
     *
     */
    public function actionDetail()
    {
        $act_id = I('id');
        if ($act_id <= 0) {
            ecs_header("Location: ./\n");
        }

        $this->sampleid = $act_id;

        //$presale = presale_info($this->preid); /* 取得预售活动信息 */

        $sample = sample_info($this->sampleid, 1, [], $this->region_id, $this->area_info['region_id']);
        if (empty($sample)) {
            ecs_header("Location: ./\n");
            exit;
        }

        //不是制造商
        if (!$_SESSION['seller_id'] || $_SESSION['user_id'] != $sample['user_id']) {
            //检查权限
            $this->purchasers_priv();
        }


        $this->assign('sample', $sample);

        /* 取得预售商品信息 */
        $this->goods_id = $sample['goods_id'];
        $goods = get_goods_info($this->goods_id, $this->region_id, $this->area_info['region_id']);
        if (empty($goods)) {
            ecs_header("Location: ./\n");
            exit;
        }

        //有效订单
        $this->assign('orderG_number', $sample['valid_goods']);

        // 该商铺的其他样品
        $merchant_group = get_merchant_group_goods($this->sampleid);
        $this->assign('merchant_group_goods', $merchant_group);

        // 样品销量
        $sql = "SELECT COUNT(*) as num FROM {pre}order_info WHERE extension_code = 'sample' AND extension_id = '$this->sampleid'";
        $res = $GLOBALS['db']->getOne($sql);
        if ($res) {
            $goods['sales_volume'] = $res;
        } else {
            $goods['sales_volume'] = 0;
        }

        // 检查是否已经存在于用户的收藏夹
        if ($_SESSION ['user_id']) {
            $where['user_id'] = $_SESSION ['user_id'];
            $where['goods_id'] = $this->goods_id;
            $rs = $this->db->table('collect_goods')->where($where)->count();
            if ($rs > 0) {
                $this->assign('goods_collect', 1);
            }
        }
        $this->assign('goods', $goods);
        $this->assign('type', 0);

        /* 商品关注信息 */
        $sql = "SELECT count(*) FROM " . $this->ecs->table('collect_store') . " WHERE ru_id = " . $goods['user_id'];
        $collect_number = $this->db->getOne($sql);
        $this->assign('collect_number', $collect_number ? $collect_number : 0);

        //评分 start
        $mc_all = ments_count_all($this->goods_id);       //总条数
        $mc_one = ments_count_rank_num($this->goods_id, 1);        //一颗星
        $mc_two = ments_count_rank_num($this->goods_id, 2);        //两颗星
        $mc_three = ments_count_rank_num($this->goods_id, 3);    //三颗星
        $mc_four = ments_count_rank_num($this->goods_id, 4);        //四颗星
        $mc_five = ments_count_rank_num($this->goods_id, 5);        //五颗星
        $comment_all = get_conments_stars($mc_all, $mc_one, $mc_two, $mc_three, $mc_four, $mc_five);
        if ($goods['user_id'] > 0) {
            //商家所有商品评分类型汇总
            $merchants_goods_comment = get_merchants_goods_comment($goods['user_id']);
            $this->assign('merch_cmt', $merchants_goods_comment);
        }
        $this->assign('comment_all', $comment_all);
        //查询一条好评
        $good_comment = get_good_comment($this->goods_id, 4, 1, 0, 1);
        $this->assign('good_comment', $good_comment);
        $this->assign('goods_id', $this->goods_id); //商品ID
        // 商家客服
        $sql = "select b.is_IM is_im, a.ru_id,a.province, a.city, a.kf_type, a.kf_ww, a.kf_qq, a.meiqia, a.shop_name, a.kf_appkey from {pre}seller_shopinfo as a left join {pre}merchants_shop_information as b on a.ru_id=b.user_id where ru_id='" . $goods['user_id'] . "' ";
        $basic_info = $this->db->getRow($sql);

        $info_ww = $basic_info['kf_ww'] ? explode("\r\n", $basic_info['kf_ww']) : '';
        $info_qq = $basic_info['kf_qq'] ? explode("\r\n", $basic_info['kf_qq']) : '';
        $kf_ww = $info_ww ? $info_ww[0] : '';
        $kf_qq = $info_qq ? $info_qq[0] : '';
        $basic_ww = $kf_ww ? explode('|', $kf_ww) : '';
        $basic_qq = $kf_qq ? explode('|', $kf_qq) : '';
        $basic_info['kf_ww'] = $basic_ww ? $basic_ww[1] : '';
        $basic_info['kf_qq'] = $basic_qq ? $basic_qq[1] : '';
        if (($basic_info['is_im'] == 1 || $basic_info['ru_id'] == 0) && !empty($basic_info['kf_appkey'])) {
            $basic_info['kf_appkey'] = $basic_info['kf_appkey'];
        } else {
            $basic_info['kf_appkey'] = '';
        }
        $basic_date = ['region_name'];
        $basic_info['province'] = get_table_date('region', "region_id = '" . $basic_info['province'] . "'", $basic_date, 2);
        $basic_info['city'] = get_table_date('region', "region_id= '" . $basic_info['city'] . "'", $basic_date, 2) . "市";
        $this->assign('basic_info', $basic_info);
        $properties = get_goods_properties($this->goods_id, $this->region_id, $this->area_info['region_id']);  // 获得商品的规格和属性
        $this->assign('properties', $properties['pro']);                              // 商品属性
        //默认选中的商品规格 by wanglu
        $default_spe = '';
        if ($properties['spe']) {
            foreach ($properties['spe'] as $k => $v) {
                if ($v['attr_type'] == 1) {
                    if ($v['is_checked'] > 0) {
                        foreach ($v['values'] as $key => $val) {
                            $default_spe .= $val['checked'] ? $val['label'] . '、' : '';
                        }
                    } else {
                        foreach ($v['values'] as $key => $val) {
                            if ($key == 0) {
                                $default_spe .= $val['label'] . '、';
                            }
                        }
                    }
                }
            }
        }
        $this->assign('default_spe', $default_spe);                              // 商品规格
        $this->assign('specification', $properties['spe']);                              // 商品规格
        //获取商品的相册
        $sql = "SELECT * FROM {pre}goods_gallery WHERE goods_id = " . $this->goods_id;
        $goods_img = $this->db->query($sql);
        foreach ($goods_img as $key => $val) {
            $goods_img[$key]['img_url'] = get_image_path($val['img_url']);
        }
        $this->assign('goods_img', $goods_img);
        //ecmoban模板堂 --zhuo 仓库 start
        $this->assign('province_row', get_region_name($this->province_id));
        $this->assign('city_row', get_region_name($this->city_id));
        $this->assign('district_row', get_region_name($this->district_id));

        $goods_region['country'] = 1;
        $goods_region['province'] = $this->province_id;
        $goods_region['city'] = $this->city_id;
        $goods_region['district'] = $this->district_id;
        $this->assign('goods_region', $goods_region);

        //$this->assign('guess_goods',     get_guess_goods($user_id, 1, $page=1, 7,$region_id, $area_info['region_id']));         //猜你喜欢
        $this->assign('best_goods', get_recommend_goods('best', '', $this->region_id, $this->area_info['region_id'], $goods['user_id'], 1, 'presale'));    // 推荐商品
        $this->assign('new_goods', get_recommend_goods('new', '', $this->region_id, $this->area_info['region_id'], $goods['user_id'], 1, 'presale'));     // 最新商品
        $this->assign('hot_goods', get_recommend_goods('hot', '', $this->region_id, $this->area_info['region_id'], $goods['user_id'], 1, 'presale'));     // 最新商品

        //ecmoban模板堂 --zhuo start
        $shop_info = get_merchants_shop_info('merchants_steps_fields', $goods['user_id']);
        $adress = get_license_comp_adress($shop_info['license_comp_adress']);

        $this->assign('shop_info', $shop_info);
        $this->assign('adress', $adress);
        //ecmoban模板堂 --zhuo end

        //ecmoban模板堂 --zhuo start 仓库
        $province_list = get_warehouse_province();
        $this->assign('province_list', $province_list); //省、直辖市

        $city_list = get_region_city_county($this->province_id);
        if ($city_list) {
            foreach ($city_list as $k => $v) {
                $city_list[$k]['district_list'] = get_region_city_county($v['region_id']);
            }
        }
        $this->assign('city_list', $city_list); //省下级市

        $district_list = get_region_city_county($this->city_id);
        $this->assign('district_list', $district_list);//市下级县

        $this->assign('goods_id', $this->goods_id); //商品ID

        $warehouse_list = get_warehouse_list_goods();
        $this->assign('warehouse_list', $warehouse_list); //仓库列

        $warehouse_name = get_warehouse_name_id($this->region_id);

        $this->assign('warehouse_name', $warehouse_name); //仓库名称
        $this->assign('region_id', $this->region_id); //商品仓库region_id
        $this->assign('user_id', $_SESSION['user_id']);
        $this->assign('shop_price_type', $goods['model_price']); //商品价格运营模式 0代表统一价格（默认） 1、代表仓库价格 2、代表地区价格
        $this->assign('area_id', $this->area_info['region_id']); //地区ID
        //ecmoban模板堂 --zhuo end 仓库
        $area = [
            'region_id' => $this->region_id,  //仓库ID
            'province_id' => $this->province_id,
            'city_id' => $this->city_id,
            'district_id' => $this->district_id,
            'goods_id' => $this->goods_id,
            'user_id' => $_SESSION['user_id'],
            'area_id' => $this->area_info['region_id'],  //地区ID
            'merchant_id' => $goods['user_id'],
        ];
        $this->assign('area', $area);

        /* 取得商品的规格 */
        $properties = get_goods_properties($this->goods_id);
        $this->assign('properties', $properties['pro']);    //商品属性
        $this->assign('specification', $properties['spe']); // 商品规格
        $this->assign('cfg', C('shop'));
        $position = assign_ur_here(0, $goods['goods_name']);
        $this->assign('page_title', $position['title']);

        // 微信JSSDK分享
        $share_data = [
            'title' => '预售商品_' . $goods['goods_name'],
            'desc' => $sample['act_name'],
            'link' => '',
            'img' => $goods['goods_img'],
        ];
        $this->assign('share_data', $this->get_wechat_share_content($share_data));

        $this->display();
    }


    /**
     * 改变属性、数量时重新计算商品价格
     */
    public function actionPrice()
    {
        $res = ['err_msg' => '', 'err_no' => 0, 'result' => '', 'qty' => 1];
        $attr = I('attr');
        $this->goods_id = (isset($_REQUEST['gid'])) ? intval($_REQUEST['gid']) : 0;

        $this->sampleid = (isset($_REQUEST['id'])) ? intval($_REQUEST['id']) : 0;
        $attr_id = !empty($attr) ? explode(',', $attr) : [];
        $warehouse_id = I('request.warehouse_id', 0, 'intval');
        $area_id = I('request.area_id', 0, 'intval'); //仓库管理的地区ID
        $attr_id = !empty($attr) ? explode(',', $attr) : [];

        $onload = I('request.onload', '', 'trim');
        ; //仓库管理的地区ID

        $goods = get_goods_info($this->goods_id, $warehouse_id, $area_id);
        if ($this->goods_id == 0) {
            $res['err_msg'] = L('err_change_attr');
            $res['err_no'] = 1;
        } else {
            $sample = sample_info($this->sampleid);
            if (empty($sample))
            {
                exit(json_encode($res));
            }

            if ($sample['goods_id'] != $this->goods_id) {
                exit(json_encode($res));
            }

            $price_ladder = $sample['price_ladder'];

            $prices = array_column($price_ladder, 'formated_price');
            $presale['goods_price_formatted'] = max($prices);
            $this->assign('goods', $presale);

            //ecmoban模板堂 --zhuo start
            $products = get_warehouse_id_attr_number($this->goods_id, $_REQUEST['attr'], $goods['user_id'], $warehouse_id, $area_id);
            $attr_number = $products['product_number'];

            $main_attr_list = get_main_attr_list($this->goods_id, $attr_id);

            $this->assign('main_attr_list', $main_attr_list);
            $res['main_attr_list'] = $this->fetch();
        }
        die(json_encode($res));
    }


    public function actionGetSelectRecord() {
        if (IS_AJAX || true) {
            $res = array('error' => '', 'message' => 0, 'content' => '');

            $act_id = I('get.act_id', 0,'intval');
            if ($act_id <= 0)
            {
                exit(json_encode($res));
            }

            $sample = sample_info($act_id);
            if (empty($sample))
            {
                exit(json_encode($res));
            }

            $goods_id     = $sample['goods_id']; //仓库管理的地区ID
            $this->goods_id = $goods_id;

            //by zxk 获取商品规格
            $properties = get_goods_properties($goods_id, $this->region_id, $this->area_id);
            $specscount = count($properties['spe']);

            if (0 < $specscount) {
                $attr_array = (empty($_REQUEST['attr_array']) ? array() : $_REQUEST['attr_array']);
                $num_array = (empty($_REQUEST['num_array']) ? array() : $_REQUEST['num_array']);
                $result['total_number'] = array_sum($num_array);

                $attr_num_array = array();
                foreach ($attr_array as $key => $val )
                {
                    $arr = array();
                    $arr['attr'] = $val;
                    $arr['num'] = $num_array[$key];
                    $attr_num_array[] = $arr;
                }

                $record_data = get_select_record_data($this->goods_id, $attr_num_array);
                $this->assign('record_data', $record_data);
                $result['record_data'] = $this->fetch('select_record_data');
            } else {
                $goods_number = (empty($_REQUEST['goods_number']) ? 0 : intval($_REQUEST['goods_number']));
                $result['total_number'] = $goods_number;
            }
        }

        $data = calculate_goods_price($sample['act_id'], $result['total_number'], 'sample');
        $result['data'] = $data;
        exit(json_encode($result));
    }

    /**
     * 预售商品 --> 购买
     *
     */
    public function actionBuy()
    {
        $this->check_login();

        //检查权限
        $this->purchasers_priv();

        $warehouse_id = I('request.warehouse_id', 0, 'intval');
        $area_id = (isset($_REQUEST['area_id'])) ? intval($_REQUEST['area_id']) : 0; //仓库管理的地区ID
        /* 取得参数：预售活动id */
        $sample_id = I('request.sample_id', 0, 'intval');
        ;
        if ($sample_id <= 0) {
            show_message("链接无效", '', '', 'error');
            exit;
        }

        //获取团购信息
        $goods_id = get_table_date('sample_activity', 'act_id=\'' . $sample_id . '\'', array('goods_id'), 2);
        $properties = get_goods_properties($goods_id, $this->region_id, $this->area_id);  // 获得商品的规格和属性
        $specscount = count($properties['spe']);

        //获取商品总数
        if (0 < $specscount) {
            $attr_array = (empty($_REQUEST['attr_array']) ? array() : $_REQUEST['attr_array']);
            $total_number = array_sum(array_values($attr_array));
        }  else
        {
            $goods_number = (empty($_REQUEST['goods_number']) ? 0 : intval($_REQUEST['goods_number']));
            $total_number = $goods_number;
        }

        /* 查询：取得预售活动信息 */
        $sample = sample_info($sample_id, $total_number);
        if (empty($sample)) {
            show_message("链接无效", '', '', 'error');
            exit;
        }

        /* 查询：检查预售活动是否是进行中 */
        if ($sample['review_status'] != 3) {
            show_message('活动不存在', '', '', 'error');
        }

        /* 查询：取得预售商品信息 */
        $goods = goods_info($sample['goods_id'], $warehouse_id, $area_id);
        if (empty($goods)) {
            show_message("链接无效", '', '', 'error');
            exit;
        }

        $restrict_amount = $total_number + $sample['valid_goods'];
        /* 查询：判断数量是否足够 */
        if ($sample['restrict_amount'] > 0 && $restrict_amount > $sample['restrict_amount']) {
            show_message(L('error_restrict_amount'), '', '', 'error');
        } elseif ($sample['restrict_amount'] > 0 && ($total_number > ($sample['restrict_amount'] - $sample['valid_goods']))) {
            show_message(L('error_goods_lacking'), '', '', 'error');
        }

        //起订量
        if ($sample['moq'] && $sample['moq'] > $total_number) {
            show_message('您订购的商品少于最小起订量', '', '', 'error');
        }

        //ecmoban模板堂 --zhuo start
        $area_info = get_area_info($this->province_id);
        $this->area_id = $area_info['region_id'];

        $where = "regionId = '$this->province_id'";
        $date = ['parent_id'];
        $this->region_id = get_table_date('region_warehouse', $where, $date, 2);

        if (!empty($_SESSION['user_id'])) {
            $sess = "";
        } else {
            $sess = real_cart_mac_ip();
        }
        //ecmoban模板堂 --zhuo end

        $common_cart = [
            'user_id' => $_SESSION['user_id'],
            'session_id' => $sess,
            'goods_id' => $sample['goods_id'],
            'goods_sn' => addslashes($goods['goods_sn']),
            'goods_name' => addslashes($goods['goods_name']),
            'market_price' => $goods['market_price'],

            //ecmoban模板堂 --zhuo start
            'ru_id' => $goods['user_id'],
            'warehouse_id' => $this->region_id,
            'area_id' => $area_id,
            //ecmoban模板堂 --zhuo end
            'is_real' => $goods['is_real'],
            'extension_code' => 'sample',
            'extension_id' => $sample['act_id'],
            'parent_id' => 0,
            'rec_type' => 0,
            'is_gift' => 0,
            'freight' => $goods['freight'],
            'tid' => $goods['tid'],
        ];

        $sess_id = ' user_id = \'' . $_SESSION['user_id'] . '\' ';
        if (0 < $specscount) {
            //商品规格存在
            foreach ($attr_array as $key => $val ) {
                $specs = $key;
                $_specs = explode(',', $key);

                if ($val > 1) {
                    $this->error('每个规格最多只能选择一件');
                }

                $product_info = get_products_info($goods['goods_id'], $_specs, $warehouse_id, $this->area_id);
                empty($product_info) ? $product_info = array('product_number' => 0, 'product_id' => 0) : '';

                if($goods['model_attr'] == 1){
                    $table_products = "products_warehouse";
                    $type_files = " and warehouse_id = '$this->warehouse_id'";
                }elseif($goods['model_attr'] == 2){
                    $table_products = "products_area";
                    $type_files = " and area_id = '$this->area_id'";
                }else{
                    $table_products = "products";
                    $type_files = "";
                }

                $sql = "SELECT * FROM " .$GLOBALS['ecs']->table($table_products). " WHERE goods_id = '" .$goods['goods_id']. "'" .$type_files. " LIMIT 0, 1";
                $prod = $GLOBALS['db']->getRow($sql);

                $number = $val;

                /* 查询：查询规格名称和值，不考虑价格 */
                $attr_list = array();
                $sql = "SELECT a.attr_name, g.attr_value " .
                    "FROM " . $GLOBALS['ecs']->table('goods_attr') . " AS g, " .
                    $GLOBALS['ecs']->table('attribute') . " AS a " .
                    "WHERE g.attr_id = a.attr_id " .
                    "AND g.goods_attr_id " . db_create_in($specs) . " ORDER BY a.sort_order, a.attr_id, g.goods_attr_id";
                $res = $GLOBALS['db']->query($sql);
                foreach ($res as $row) {
                    $attr_list[] = $row['attr_name'] . ': ' . $row['attr_value'];
                }

                $goods_attr = join(chr(13) . chr(10), $attr_list);

                $innerJoin = 'inner join '.$GLOBALS['ecs']->table('order_goods') . " AS og on oi.order_id = og.order_id ";

                //是否已经购买过
                $sql = "SELECT count(*) " .
                    "FROM " . $GLOBALS['ecs']->table('order_info') . " AS oi " . $innerJoin.
                    "WHERE og.goods_id = ".$goods_id . ' AND order_status != 2 AND user_id = '.$_SESSION['user_id'] ." AND goods_attr_id = '$specs' ";
                $count =  $GLOBALS['db']->getOne($sql);
                if ($count > 0) {
                    show_message('样品商品('.addslashes($goods_attr).')已经购买过了', '', '', 'error');
                }

                /* 更新：加入进货单 */
                $goods_price = $sample['cur_price'];

                /* 更新：加入进货单 */
                $cart = $common_cart;
                $cart['product_id'] = $product_info['product_id'];
                $cart['goods_price'] = $goods_price;
                $cart['goods_number'] = $number;
                $cart['goods_attr'] = addslashes($goods_attr);
                $cart['goods_attr_id'] = $specs;

                $set = get_find_in_set(array_filter($_specs), 'goods_attr_id', ',');
                $sql = ' SELECT rec_id FROM ' . $GLOBALS['ecs']->table('cart') . ' WHERE ' . $sess_id . ' AND goods_id = \'' . $goods_id . '\' ' . $set . ' ';
                $rec_id = $GLOBALS['db']->getOne($sql);

                if (!(empty($rec_id)))
                {
                    $this->db->autoExecute($this->ecs->table('cart'), $cart, 'UPDATE', 'rec_id=\'' . $rec_id . '\'');
                }
                else
                {
                    $this->db->autoExecute($this->ecs->table('cart'), $cart, 'INSERT');
                }

            }
        } else {
            if ($goods_number > 1) {
                $this->error('每个规格最多只能选择一件');
            }

            $innerJoin = 'inner join '.$GLOBALS['ecs']->table('order_goods') . " AS og on oi.order_id = og.order_id ";

            //是否已经购买过
            $sql = "SELECT count(*) " .
                "FROM " . $GLOBALS['ecs']->table('order_info') . " AS oi " . $innerJoin.
                "WHERE og.goods_id = ".$goods_id . ' AND order_status != 2 AND user_id = '.$_SESSION['user_id'] ;
            $count =  $GLOBALS['db']->getOne($sql);
            if ($count > 0) {
                show_message('样品商品已经购买过了', '', '', 'error');
            }

            $product_info = array('product_number' => 0, 'product_id' => 0);

            if($goods['model_attr'] == 1){
                $table_products = "products_warehouse";
                $type_files = " and warehouse_id = '$warehouse_id'";
            }elseif($goods['model_attr'] == 2){
                $table_products = "products_area";
                $type_files = " and area_id = '$this->area_id'";
            }else{
                $table_products = "products";
                $type_files = "";
            }

            $sql = "SELECT * FROM " .$GLOBALS['ecs']->table($table_products). " WHERE goods_id = '" .$goods['goods_id']. "'" .$type_files. " LIMIT 0, 1";
            $prod = $GLOBALS['db']->getRow($sql);


            /* 更新：加入进货单 */
            $goods_price =  $sample['cur_price'];
            /* 更新：加入进货单 */
            $cart = $common_cart;
            $cart['product_id'] = $product_info['product_id'];
            $cart['goods_price'] = $goods_price;
            $cart['goods_number'] = $goods_number;
            $cart['goods_attr'] = '';
            $cart['goods_attr_id'] = '';

            $sql = ' SELECT rec_id FROM ' . $GLOBALS['ecs']->table('cart') . ' WHERE ' . $sess_id . ' AND goods_id = \'' . $goods_id . '\' ';
            $rec_id = $GLOBALS['db']->getOne($sql);
            if (!(empty($rec_id)))
            {
                $this->db->autoExecute($this->ecs->table('cart'), $cart, 'UPDATE', 'rec_id=\'' . $rec_id . '\'');
            }
            else
            {
                $this->db->autoExecute($this->ecs->table('cart'), $cart, 'INSERT');
            }
        }

        /* 更新：记录购物流程类型：预售 */
        calculate_cart_goods_price($goods_id, '', 'sample', $sample['act_id']);
        show_message('已添加成功');
    }


    /**
     * 验证是否登录
     */
    private function check_login()
    {
        if (!$_SESSION['user_id']) {
            $url = urlencode(__HOST__ . $_SERVER['REQUEST_URI']);
            if (IS_POST) {
                $url = urlencode($_SERVER['HTTP_REFERER']);
            }
            ecs_header("Location: " . url('user/login/index', ['back_act' => $url]));
            exit;
        }
    }

    /**
     * 初始化参数
     */
    private function init_params()
    {
        #需要查询的IP start
        if (!isset($_COOKIE['province'])) {
            $area_array = get_ip_area_name();
            if ($area_array['county_level'] == 2) {
                $date = ['region_id', 'parent_id', 'region_name'];
                $where = "region_name = '" . $area_array['area_name'] . "' AND region_type = 2";
                $city_info = get_table_date('region', $where, $date, 1);

                $date = ['region_id', 'region_name'];
                $where = "region_id = '" . $city_info[0]['parent_id'] . "'";
                $province_info = get_table_date('region', $where, $date);

                $where = "parent_id = '" . $city_info[0]['region_id'] . "' order by region_id asc limit 0, 1";
                $district_info = get_table_date('region', $where, $date, 1);
            } elseif ($area_array['county_level'] == 1) {
                $area_name = $area_array['area_name'];
                $date = ['region_id', 'region_name'];
                $where = "region_name = '$area_name'";
                $province_info = get_table_date('region', $where, $date);
                $where = "parent_id = '" . $province_info['region_id'] . "' order by region_id asc limit 0, 1";
                $city_info = get_table_date('region', $where, $date, 1);
                $where = "parent_id = '" . $city_info[0]['region_id'] . "' order by region_id asc limit 0, 1";
                $district_info = get_table_date('region', $where, $date, 1);
            }
        }
        #需要查询的IP end
        $order_area = get_user_order_area($this->user_id);
        $user_area = get_user_area_reg($this->user_id); //2014-02-25
        if ($order_area['province'] && $this->user_id > 0) {
            $this->province_id = $order_area['province'];
            $this->city_id = $order_area['city'];
            $this->district_id = $order_area['district'];
        } else {
            //省
            if ($user_area['province'] > 0) {
                $this->province_id = $user_area['province'];
                cookie('province', $user_area['province']);
                $this->region_id = get_province_id_warehouse($this->province_id);
            } else {
                $sql = "select region_name from " . $this->ecs->table('region_warehouse') . " where regionId = '" . $province_info['region_id'] . "'";
                $warehouse_name = $this->db->getOne($sql);

                $this->province_id = $province_info['region_id'];
                $cangku_name = $warehouse_name;
                $this->region_id = get_warehouse_name_id(0, $cangku_name);
            }
            //市
            if ($user_area['city'] > 0) {
                $this->city_id = $user_area['city'];
                cookie('city', $user_area['city']);
            } else {
                $this->city_id = $city_info[0]['region_id'];
            }
            //区
            if ($user_area['district'] > 0) {
                $this->district_id = $user_area['district'];
                cookie('district', $user_area['district']);
            } else {
                $this->district_id = $district_info[0]['region_id'];
            }
        }
        $this->province_id = isset($_COOKIE['province']) ? $_COOKIE['province'] : $this->province_id;
        $child_num = get_region_child_num($this->province_id);
        if ($child_num > 0) {
            $this->city_id = isset($_COOKIE['city']) ? $_COOKIE['city'] : $this->city_id;
        } else {
            $this->city_id = '';
        }
        $child_num = get_region_child_num($this->city_id);
        if ($child_num > 0) {
            $this->district_id = isset($_COOKIE['district']) ? $_COOKIE['district'] : $this->district_id;
        } else {
            $this->district_id = '';
        }
        $this->region_id = !isset($_COOKIE['region_id']) ? $this->region_id : $_COOKIE['region_id'];
        $goods_warehouse = get_warehouse_goods_region($this->province_id); //查询用户选择的配送地址所属仓库
        if ($goods_warehouse) {
            $this->regionId = $goods_warehouse['region_id'];
            if ($_COOKIE['region_id'] && $_COOKIE['regionid']) {
                $gw = 0;
            } else {
                $gw = 1;
            }
        }
        if ($gw) {
            $this->region_id = $this->regionId;
            cookie('area_region', $this->region_id);
        }
        cookie('goodsId', $this->goods_id);
        $sellerInfo = get_seller_info_area();
        if (empty($this->province_id)) {
            $this->province_id = $sellerInfo['province'];
            $this->city_id = $sellerInfo['city'];
            $this->district_id = 0;
            cookie('province', $this->province_id);
            cookie('city', $this->city_id);
            cookie('district', $this->district_id);

            $this->region_id = get_warehouse_goods_region($this->province_id);
        }
        //ecmoban模板堂 --zhuo end 仓库
        $this->area_info = get_area_info($this->province_id);
    }

    /**
     * 取得某页的所有预售商品
     *
     */
    private function get_pre_goods($cat_id, $status = 0, $sort = 'cat_id', $order = 'DESC', $page = 1, $size = 10, $keyword = '')
    {
        $now = gmtime();
        $where = '';
        if ($cat_id > 0) {
            $where = "AND a.cat_id = '$cat_id' ";
        }
        //1未开始，2进行中，3结束
        if ($status == 1) {
            $where .= " AND a.start_time > $now ";
        } elseif ($status == 2) {
            $where .= " AND a.start_time < $now AND $now < a.end_time ";
        } elseif ($status == 3) {
            $where .= " AND $now > a.end_time ";
        }
        if ($sort == 'shop_price') {
            $sort = "g.$sort";
        } else {
            $sort = "a.$sort";
        }
        if ($keyword) {
            $where .= " AND g.goods_name like '%$keyword%' ";
        }
        $sql = "SELECT COUNT(*) as total FROM " .
            $GLOBALS['ecs']->table('presale_activity') . " AS a " .
            " LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON a.goods_id = g.goods_id " .
            " WHERE g.goods_id > 0 AND a.review_status = 3 and g.is_on_sale = 0 $where";
        $total = $GLOBALS['db']->getOne($sql);
        $total ? $total : 0;

        $sql = "SELECT a.*, g.goods_thumb, g.goods_img, g.goods_name, g.shop_price, g.market_price, g.sales_volume FROM " .
            $GLOBALS['ecs']->table('presale_activity') . " AS a " .
            " LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON a.goods_id = g.goods_id " .
            " WHERE g.goods_id > 0 $where AND g.is_on_sale = 0 AND a.review_status > 2 ORDER BY $sort $order LIMIT " . ($page - 1) * $size . ",  $size";
        $res = $GLOBALS['db']->getAll($sql);
        foreach ($res as $key => $row) {
            $res[$key]['thumb'] = get_image_path($row['goods_thumb']);
            $res[$key]['goods_img'] = get_image_path($row['goods_img']);
            $res[$key]['url'] = build_uri('presale', ['r' => 'index/detail', 'id' => $row['act_id']]);

            if ($row['start_time'] >= $now) {
                $res[$key]['status'] = 1;
                $res[$key]['short_format_date'] = short_format_date($row['start_time']);
            } elseif ($row['end_time'] < $now) {
                $res[$key]['status'] = 3;
            } else {
                $res[$key]['short_format_date'] = short_format_date($row['end_time']);
            }
        }
        return ['total' => $total, 'list' => $res];
    }


    /**
     * 商品详情
     */
    public function actionInfo()
    {
        $this->act_id = I('id');
        if (!$this->act_id) {
            ecs_header("Location: ./\n");
        }

        $this->assign('act_id', $this->act_id);

        $goods_id = get_table_date('sample_activity', 'act_id=\'' . $this->act_id . '\'', array('goods_id'), 2);
        $this->goods_id = $goods_id;

        $info = $this->db->table('goods')->field('goods_desc,desc_mobile')->where(['goods_id' => $this->goods_id])->find();
        $properties = get_goods_properties($this->goods_id, $this->region_id, $this->area_info['region_id']);  // 获得商品的规格和属性
        // 查询关联商品描述
        $sql = "SELECT ld.goods_desc FROM {pre}link_desc_goodsid AS dg, {pre}link_goods_desc AS ld WHERE dg.goods_id = {$this->goods_id}  AND dg.d_id = ld.id AND ld.review_status > 2";
        $link_desc = $this->db->getOne($sql);
        if (!empty($info['desc_mobile'])) {
            // 处理手机端商品详情 图片（手机相册图） data/gallery_album/
            if (C('shop.open_oss') == 1) {
                $bucket_info = get_bucket_info();
                $bucket_info['endpoint'] = empty($bucket_info['endpoint']) ? $bucket_info['outside_site'] : $bucket_info['endpoint'];
                $desc_preg = get_goods_desc_images_preg($bucket_info['endpoint'], $info['desc_mobile'], 'desc_mobile');
                $goods_desc = preg_replace('/<div[^>]*(tools)[^>]*>(.*?)<\/div>(.*?)<\/div>/is', '', $desc_preg['desc_mobile']);
            } else {
                $goods_desc = preg_replace('/<div[^>]*(tools)[^>]*>(.*?)<\/div>(.*?)<\/div>/is', '', $info['desc_mobile']);
            }
        }

        if (empty($info['desc_mobile']) && !empty($info['goods_desc'])) {
            if (C('shop.open_oss') == 1) {
                $bucket_info = get_bucket_info();
                $bucket_info['endpoint'] = empty($bucket_info['endpoint']) ? $bucket_info['outside_site'] : $bucket_info['endpoint'];
                $goods_desc = str_replace(['src="/images/upload', 'src="images/upload'], 'src="' . $bucket_info['endpoint'] . 'images/upload', $info['goods_desc']);

                // $desc_preg = get_goods_desc_images_preg($bucket_info['endpoint'], $info['goods_desc']);
                // $goods_desc = $desc_preg['goods_desc'];
            } else {
                $goods_desc = str_replace(['src="/images/upload', 'src="images/upload'], 'src="' . __STATIC__ . '/images/upload', $info['goods_desc']);
            }
        }
        if (empty($info['desc_mobile']) && empty($info['goods_desc'])) {
            $goods_desc = $link_desc;
        }
        $goods_desc = preg_replace("/height\=\"[0-9]+?\"/", "", $goods_desc);
        $goods_desc = preg_replace("/width\=\"[0-9]+?\"/", "", $goods_desc);
        $goods_desc = preg_replace("/style=.+?[*|\"]/i", "", $goods_desc);
        $this->assign('goods_desc', $goods_desc);
        // 商品属性
        $this->assign('properties', $properties['pro']);
        $this->assign('page_title', L('goods_detail'));
        $this->display();
    }

    /**
     * 商品评论
     */
    public function actionComment($rank = '')
    {

        $this->act_id = I('id');
        if (!$this->act_id) {
            ecs_header("Location: ./\n");
        }

        $this->assign('act_id', $this->act_id);

        $goods_id = get_table_date('sample_activity', 'act_id=\'' . $this->act_id . '\'', array('goods_id'), 2);
        $this->goods_id = $goods_id;

        if (IS_AJAX) {
            $rank = I('rank', 'all', 'trim');
            $page = I('page', 0, 'intval');
            $start = $page > 0 ? ($page - 1) * $this->size : 1;

            $arr = get_good_comment_as($this->goods_id, $rank, 1, $start, $this->size);
            $comments = $arr['arr'];
            $totalPage = $arr['max'];
            if ($rank == 'img') {
                foreach ($comments as $key => $val) {
                    if ($val['comment_img'] == '') {
                        unset($comments[$key]);
                    }
                }
                $totalPage = $arr['img_max'];
            }
            // dd($comments);
            $reset = $start > 0 ? 0 : 1;
            die(json_encode(['comments' => $comments, 'rank' => $rank, 'reset' => $reset, 'totalPage' => $totalPage, 'top' => 1]));
        }
        // 兼容原有图评论
        if ($rank == 'img') {
            $rank = $rank;
        } else {
            $rank = I('rank', 'all', 'trim');
        }
        $this->assign('rank', $rank); // 评论
        $this->assign('comment_count', commentCol($this->goods_id)); // 评论数量
        $this->assign('goods_id', $this->goods_id);
        $this->assign('page_title', L('goods_comment'));
        $this->display('comment');
    }

    /**
     * 有图评论
     * @return
     */
    public function actionInfoimg()
    {
        $rank = 'img';
        $this->actionComment($rank);
    }
}
