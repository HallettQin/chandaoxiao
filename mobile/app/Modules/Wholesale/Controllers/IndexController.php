<?php

namespace App\Modules\Wholesale\Controllers;

use App\Modules\Base\Controllers\FrontendController;

class IndexController extends FrontendController
{
    public function __construct()
    {
        parent::__construct();
        L(require(LANG_PATH . C('shop.lang') . '/wholesale.php'));

        $this->assign('user_id', $_SESSION['user_id']);
    }

    /**
     * 批发列表
     */
    public function actionIndex()
    {
        /* 模板赋值 */
        $category_list = cat_list(0, 1, 0, 'category', '', 2);
        foreach ($category_list as $key => $val) {
            if ($val['parent_id'] > 0 && $val['level'] > 0) {
                $category_list[$val['parent_id']]['children'][$key] = $val;
                unset($category_list[$key]);
            }
        }
        $this->assign('category_list', $category_list);

        $this->assign('page_title', L('wholesale_list'));    // 页面标题

        $this->display('wholesale_list');
    }

    /**
     * 获取批发列表  异步请求
     */
    public function actionWholeList()
    {
        $result = ['error' => 0, 'msg' => ''];
        $search_category = I('search_category', 0, 'intval');
        $search_keywords = I('search_keywords', '', 'trim');
        $sort = I('sort', '', 'trim');
        $sort = ($sort == 'ASC') ? 'ASC' : 'DESC';
        $order = I('order', '', 'trim');
        $param = []; // 翻页链接所带参数列表

        /* 查询条件：当前用户的会员等级（搜索关键字） */
        $where = " WHERE g.goods_id = w.goods_id
               AND w.enabled = 1
               AND CONCAT(',', w.rank_ids, ',') LIKE '" . '%,' . session('user_rank') . ',%' . "' ";

        /* 搜索 */
        /* 搜索类别 */
        if ($search_category) {
            $where .= " AND g.cat_id = '$search_category' ";
            $param['search_category'] = $search_category;
            $result['search_category'] = $search_category;
        }
        /* 搜索商品名称和关键字 */
        if ($search_keywords) {
            $where .= " AND (g.keywords LIKE '%$search_keywords%'
                    OR g.goods_name LIKE '%$search_keywords%') ";
            $param['search_keywords'] = $search_keywords;
            $result['search_keywords'] = $search_keywords;
        }

        /* 取得批发商品总数 */
        $sql = "SELECT COUNT(*) FROM " . $this->ecs->table('wholesale') . " AS w, " . $this->ecs->table('goods') . " AS g " . $where;
        $count = $this->db->getOne($sql);
        /* 排序 */
        $countSql = '';
        $where_sort = '';
        if ($order) {
            $where_sort .= " ORDER BY $order " . $sort;
            $countSql = " ,(SELECT COUNT(*) FROM " . $this->ecs->table('order_goods') . ' og WHERE g.goods_id = og.goods_id) AS sales_num';
            $param['sort'] = $search_keywords;
            $result['sort'] = $search_keywords;
        }

        if ($count > 0) {
            $default_display_type = C('show_order_type') == '0' ? 'list' : 'text';
            $display = (isset($_REQUEST['display']) && in_array(trim(strtolower($_REQUEST['display'])), ['list', 'text'])) ? trim($_REQUEST['display']) : (isset($_COOKIE['ECS']['display']) ? $_COOKIE['ECS']['display'] : $default_display_type);
            $display = in_array($display, ['list', 'text']) ? $display : 'text';
            setcookie('ECS[display]', $display, gmtime() + 86400 * 7, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);

            /* 取得每页记录数 */
            $size = isset($_CFG['page_size']) && intval($_CFG['page_size']) > 0 ? intval($_CFG['page_size']) : 10;

            /* 计算总页数 */
            $page_count = ceil($count / $size);

            /* 取得当前页 */
            $page = isset($_REQUEST['page']) && intval($_REQUEST['page']) > 0 ? intval($_REQUEST['page']) : 1;
            $page = $page > $page_count ? $page_count : $page;

            /* 取得当前页的批发商品 */
            $wholesale_list = wholesale_list($size, $page, $where, $where_sort, $countSql, $sort, $order);
            $result['wholesale_list'] = $wholesale_list;

            $param['act'] = 'list';
            $pager = get_pager('wholesale.php', array_reverse($param, true), $count, $page, $size);
            $pager['display'] = $display;
            $result['pager'] = $pager;

            /* 批发商品进货单 */
            $result['cart_goods'] = isset($_SESSION['wholesale_goods']) ? $_SESSION['wholesale_goods'] : [];
        } elseif (empty($_SESSION['user_id'])) {
            $result['error'] = 1;
            $result['msg'] = L('need_to_login');
        } else {
            $result['error'] = 2;
            $result['msg'] = L('no_goods');
        }
        $this->ajaxReturn($result);
    }

    /**
     * 批发详情
     */
    public function actionDetail()
    {
        $id = I('id', 0, 'intval');
        if ($id <= 0) {
            ecs_header("Location: ./\n");
        }
        $goods = wholesale_info($id);
        if (!$goods) {
            ecs_header("Location: ./\n");
            exit;
        }

        //不是制造商
        if (!$_SESSION['seller_id'] || $_SESSION['user_id'] != $goods['user_id']) {
            //检查权限
            $this->purchasers_priv();
        }

        $this->goods_id = $goods['goods_id'];

        $properties = get_wholesale_goods_properties($goods['goods_id'],$this->region_id, $this->area_info['region_id']);
        $this->assign('specification', $properties['spe']);                              // 商品规格

        //获取商品的相册
        $sql = "SELECT * FROM {pre}goods_gallery WHERE goods_id = " . $this->goods_id;
        $goods_img = $this->db->query($sql);
        foreach ($goods_img as $key => $val) {
            $goods_img[$key]['img_url'] = get_image_path($val['img_url']);
        }
        $this->assign('goods_img', $goods_img);

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


        // 该商铺的其他批发
        $merchant_group = get_merchant_group_goods($goods['act_id']);
        $this->assign('merchant_group_goods', $merchant_group);

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

        /* 商品关注信息 */
        $sql = "SELECT count(*) FROM " . $this->ecs->table('collect_store') . " WHERE ru_id = " . $goods['user_id'];
        $collect_number = $this->db->getOne($sql);
        $this->assign('collect_number', $collect_number ? $collect_number : 0);

        $stat = wholesale_stat($id);
        $this->assign('orderG_number', $stat['total_goods']); //购买的商品数量


        //ecmoban模板堂 --zhuo start
        $shop_info = get_merchants_shop_info('merchants_steps_fields', $goods['user_id']);
        $adress = get_license_comp_adress($shop_info['license_comp_adress']);

        $this->assign('shop_info', $shop_info);
        $this->assign('adress', $adress);
        //ecmoban模板堂 --zhuo end

        $goodsinfo = get_goods_info($this->goods_id, $this->region_id, $this->area_info['region_id']);
        if (empty($goodsinfo)) {
            ecs_header("Location: ./\n");
            exit;
        }

        $this->assign('goodsinfo', $goodsinfo);
        $this->assign('goods', $goods);
        $this->assign('page_title', L('wholesal_detail'));    // 页面标题
        $this->assign('act_id', $goods['act_id']); //活动id
        $this->display('wholesale_details');
    }

    //价格属性接口
    public function actionPrice() {
        $res = ['err_msg' => '', 'err_no' => 0, 'result' => '', 'qty' => 1];
        $attr = I('attr');

        $act_id = (isset($_REQUEST['act_id'])) ? intval($_REQUEST['act_id']) : 0;
        if ($act_id <= 0) {
            exit(json_encode($res));
        }

        $wholesale = wholesale_info($act_id);
        if (empty($wholesale))
        {
            exit(json_encode($res));
        }

        $goods_id = $wholesale['goods_id'];

        $attr_id = !empty($attr) ? explode(',', $attr) : [];

        $warehouse_id = I('request.warehouse_id', 0, 'intval');
        $this->area_id = I('request.area_id', 0, 'intval'); //仓库管理的地区ID

        $onload = I('request.onload', '', 'trim');
        //仓库管理的地区ID

        $goods = get_goods_info($goods_id, $warehouse_id, $this->area_id);
        if ($goods_id == 0) {
            $res['err_msg'] = L('err_change_attr');
            $res['err_no'] = 1;
        } else {
            //获取阶梯价格

            if ($wholesale['price_model'] == 1) {
                $price_ladder = get_wholesale_volume_price($goods_id);
                $prices = array_column($price_ladder, 'volume_price');
                $goods_price_formatted = price_format(max($prices), false);
            } else {
                $goods_price_formatted = price_format($goods['goods_price'], false);
            }

            //商品价格
            $this->assign('goods_price_formatted', $goods_price_formatted);

            $main_attr_list = get_wholesale_main_attr_list($goods_id, $attr_id);
            $this->assign('main_attr_list', $main_attr_list);

            $res['main_attr_list'] = $this->fetch();
        }
        exit(json_encode($res));

    }

    public function actionGetSelectRecord() {
        if (IS_AJAX || true) {
            $res = array('error' => '', 'message' => 0, 'content' => '');

            $act_id = I('get.act_id', 0,'intval');
            if ($act_id <= 0)
            {
                exit(json_encode($res));
            }

            $wholesale = wholesale_info($act_id);
            if (empty($wholesale))
            {
                exit(json_encode($res));
            }

            $goods_id     = $wholesale['goods_id']; //仓库管理的地区ID
            $this->goods_id = $goods_id;

            //by zxk 获取商品规格
            $properties = get_wholesale_goods_properties($goods_id, $this->region_id, $this->area_id);
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


                $record_data = get_wholesale_select_record_data($this->goods_id, $attr_num_array);
                $this->assign('record_data', $record_data);
                $result['record_data'] = $this->fetch('select_record_data');
            } else {
                $goods_number = (empty($_REQUEST['goods_number']) ? 0 : intval($_REQUEST['goods_number']));
                $result['total_number'] = $goods_number;
            }
        }

        $data = wholesale_calculate_goods_price($wholesale['act_id'], $result['total_number']);
        $result['data'] = $data;
        exit(json_encode($result));
    }

    /**
     * 添加进货单
     */
    public function actionAddToCart()
    {
        //检查权限
        $this->purchasers_priv();

        $act_id = I('act_id', 0, 'intval');
        if (empty($act_id)) {
            $this->ajaxReturn(['msg' => L('no_wholesale_goods')]);
        }

        /* 取批发相关数据 */
        $wholesale = wholesale_info($act_id);
        $wholeattr = [];
        foreach ($wholesale['price_list'] as $k => $v) {
            $wholeattr[$k] = $v['attr'];
        }

        //获取批发活动产品信息
        $goods_id = get_table_date('wholesale', 'act_id=\'' . $act_id . '\'', array('goods_id'), 2);
        if (!$goods_id) {
            $this->ajaxReturn(['msg' => L('no_wholesale_goods')]);
        }

        //by zxk 获取商品规格
        $properties = get_wholesale_goods_properties($goods_id);
        $specscount = count($properties['spe']);

        //获取商品总数
        if (0 < $specscount)
        {
            $attr_array = (empty($_REQUEST['attr_array']) ? array() : $_REQUEST['attr_array']);
            $total_number = array_sum($num_array);
        }
        else
        {
            $goods_number = (empty($_REQUEST['goods_number']) ? 0 : intval($_REQUEST['goods_number']));
            $total_number = $goods_number;
        }

        $price_info = wholesale_calculate_goods_price($goods_id, $total_number);
        $goods_info = get_table_date('goods', 'goods_id=\'' . $goods_id . '\'', array('goods_name, goods_sn, user_id'));

        if (!empty($_SESSION['user_id'])) {
            $sess = "";
        } else {
            $sess = real_cart_mac_ip();
        }

        $common_data = array();
        $common_data['user_id'] = $_SESSION['user_id'];
        $common_data['session_id'] = $sess;
        $common_data['goods_id'] = $goods_id;
        $common_data['goods_sn'] = $goods_info['goods_sn'];
        $common_data['goods_name'] = $goods_info['goods_name'];
        $common_data['market_price'] = $price_info['market_price'];
        $common_data['goods_price'] = $price_info['unit_price'];
        $common_data['goods_number'] = 0;
        $common_data['extension_code'] = 'wholesale';
        $common_data['extension_id'] = $act_id;
        $common_data['goods_attr_id'] = '';
        $common_data['ru_id'] = $goods_info['user_id'];
        $common_data['add_time'] = gmtime();
        $common_data['rec_type']     = 0;
        $common_data['is_real']      = 1;
        $common_data['freight']      =  $goods_info['freight'];
        $common_data['tid']      =  $goods_info['tid'];


//        clear_cart(CART_WHOLESALE_GOODS);
        $sess_id = ' user_id = \'' . $_SESSION['user_id'] . '\' ';
        if (0 < $specscount)
        {
            foreach ($attr_array as $key => $val )
            {
                $attr = explode(',', $key);

                $data = $common_data;

                $gooda_attr = wholesale_get_goods_attr_array($key);
                foreach ($gooda_attr as $v )
                {
                    $data['goods_attr'] .= $v['attr_name'] . ':' . $v['attr_value'] . "\n";
                }

                $data['goods_attr_id'] = $key;
                $data['goods_number'] = $val;

                $set = get_find_in_set($attr, 'goods_attr_id', ',');
                $sql = ' SELECT rec_id FROM ' . $GLOBALS['ecs']->table('cart') . ' WHERE ' . $sess_id . ' AND goods_id = \'' . $goods_id . '\' ' . $set . ' ';
                $rec_id = $GLOBALS['db']->getOne($sql);

                if (!(empty($rec_id)))
                {
                    $this->db->autoExecute($this->ecs->table('cart'), $data, 'UPDATE', 'rec_id=\'' . $rec_id . '\'');
                }
                else
                {
                    $this->db->autoExecute($this->ecs->table('cart'), $data, 'INSERT');
                }
            }
        } else {
            $cart = $common_data;
            $cart['goods_number'] = $goods_number;

            $sql = ' SELECT rec_id FROM ' . $GLOBALS['ecs']->table('cart') . ' WHERE ' . $sess_id . ' AND goods_id = \'' . $goods_id . '\' ';
            $rec_id = $GLOBALS['db']->getOne($sql);
            if (!(empty($rec_id)))
            {
                $this->db->autoExecute($this->ecs->table('cart'), $cart, 'UPDATE', 'rec_id=\'' . $rec_id . '\'');
            }
            else {
                $this->db->autoExecute($this->ecs->table('cart'), $cart, 'INSERT');
            }
        }

        /* 更新：记录购物流程类型：批发 */
        calculate_cart_goods_price($goods_id, '', 'wholesale', $wholesale['act_id']);
        show_message('已添加成功');
    }

    /**
     * 批发进货单页面
     */
    public function actionCart()
    {
        $goods_list = isset($_SESSION['wholesale_goods']) ? $_SESSION['wholesale_goods'] : [];
        $total = 0;
        foreach ($goods_list as $key => $val) {
            $total += $val['subtotal'];

            $sql = "SELECT shop_price FROM " . $this->ecs->table('goods') . ' WHERE goods_id = ' . $val['goods_id'];
            $res = $this->db->getRow($sql);
            $goods_list[$key]['shop_price'] = $res['shop_price'];
            $goods_list[$key]['format_shop_price'] = price_format($res['shop_price']);
        }

        $this->assign('cart_goods', $goods_list);
        $this->assign('total', price_format($total));
        $this->assign('page_title', L('wholesaled_goods'));    // 页面标题

        $this->display();
    }

    /**
     * 从进货单删除
     */
    public function actionDropGoods()
    {
        $id = I('id');
        if (isset($_SESSION['wholesale_goods'][$id])) {
            unset($_SESSION['wholesale_goods'][$id]);
        }
        $this->ajaxReturn(['error' => 0, 'msg' => '删除成功']);
    }

    /**
     * 提交订单
     */
    public function actionSubmitOrder()
    {
        include_once(ROOT_PATH . 'includes/lib_order.php');
        $files = [
            'order'
        ];
        $this->load_helper($files);
        /* 检查进货单中是否有商品 */
        if (count($_SESSION['wholesale_goods']) == 0) {
            $this->ajaxReturn(['error' => 1, 'msg' => L('no_wholesale_goods_in_cart')]);
        }

        /* 检查备注信息 */
        if (empty($_POST['remark'])) {
            $this->ajaxReturn(['error' => 1, 'msg' => L('please_mark_wholesale_info')]);
        }

        /* 计算商品总额 */
        $goods_amount = 0;
        foreach ($_SESSION['wholesale_goods'] as $goods) {
            $goods_amount += $goods['subtotal'];
        }

        $order = [
            'postscript' => htmlspecialchars($_POST['remark']),
            'user_id' => $_SESSION['user_id'],
            'add_time' => gmtime(),
            'order_status' => OS_UNCONFIRMED,
            'shipping_status' => SS_UNSHIPPED,
            'pay_status' => PS_UNPAYED,
            'goods_amount' => $goods_amount,
            'order_amount' => $goods_amount,
            'extension_code' => 'wholesale',
            'referer' => 'touch',
        ];

        /* 插入订单表 */
        $error_no = 0;
        do {
            $order['order_sn'] = get_order_sn(); //获取新订单号
            $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('order_info'), $order, 'INSERT');

            $error_no = $GLOBALS['db']->errno();

            if ($error_no > 0 && $error_no != 1062) {
                die($GLOBALS['db']->errorMsg());
            }
        } while ($error_no == 1062); //如果是订单号重复则重新提交数据

        $new_order_id = $this->db->getLastInsID();
        $order['order_id'] = $new_order_id;

        /* 插入订单商品 */
        foreach ($_SESSION['wholesale_goods'] as $goods) {
            //如果存在货品
            $product_id = 0;
            if (!empty($goods['goods_attr_id'])) {
                $goods_attr_id = [];
                foreach ($goods['goods_attr_id'] as $value) {
                    $goods_attr_id[$value['attr_id']] = $value['attr_val_id'];
                }

                ksort($goods_attr_id);
                $goods_attr = implode('|', $goods_attr_id);

                $sql = "SELECT product_id FROM " . $this->ecs->table('products') . " WHERE goods_attr = '$goods_attr' AND goods_id = '" . $goods['goods_id'] . "'";
                $product_id = $this->db->getOne($sql);
            }

            $sql = "INSERT INTO " . $this->ecs->table('order_goods') . "( " .
                "order_id, goods_id, goods_name, goods_sn, product_id, goods_number, market_price, " .
                "goods_price, goods_attr, is_real, extension_code, parent_id, is_gift, ru_id) " .
                " SELECT '$new_order_id', goods_id, goods_name, goods_sn, '$product_id','$goods[goods_number]', market_price, " .
                "'$goods[goods_price]', '$goods[goods_attr]', is_real, extension_code, 0, 0 , user_id " .
                " FROM " . $this->ecs->table('goods') .
                " WHERE goods_id = '$goods[goods_id]'";
            $this->db->query($sql);
        }

        /* 清空进货单 */
        unset($_SESSION['wholesale_goods']);

        /* 提示 */
        $this->ajaxReturn(['error' => 0, 'msg' => sprintf(L('ws_order_submitted'), $order['order_sn'])]);
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

        $goods_id = get_table_date('wholesale', 'act_id=\'' . $this->act_id . '\'', array('goods_id'), 2);
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

        $goods_id = get_table_date('wholesale', 'act_id=\'' . $this->act_id . '\'', array('goods_id'), 2);
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
