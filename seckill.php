<?php

/**
 * ECSHOP 秒杀商品前台文件
 * ============================================================================
 * 版权所有 2016-2018 产供销网络科技(广州)有限公司，并保留所有权利。
 * 网站地址: http://www.chandaoxiao.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: Hallett
 */

define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');

//ecmoban模板堂 --zhuo start
require(ROOT_PATH . 'includes/lib_area.php');  //ecmoban模板堂 --zhuo
$area_info = get_area_info($province_id);
$area_id = $area_info['region_id'];

$where = "regionId = '$province_id'";
$date = array('parent_id');
$region_id = get_table_date('region_warehouse', $where, $date, 2);
//ecmoban模板堂 --zhuo end

$keywords   = !empty($_REQUEST['keywords'])   ? htmlspecialchars(trim($_REQUEST['keywords'])):'';

if(isset($_REQUEST['keywords'])){
    clear_all_files();
}

if ((DEBUG_MODE & 2) != 2)
{
    $smarty->caching = true;
}

$user_id = isset($_SESSION['user_id'])? $_SESSION['user_id'] : 0;
$cat_id = isset($_REQUEST['cat_id']) && !empty($_REQUEST['cat_id']) ? intval($_REQUEST['cat_id']) : 0;

/*------------------------------------------------------ */
//-- act 操作项的初始化
/*------------------------------------------------------ */
$template = "seckill_list";
if (empty($_REQUEST['act']))
{
    if(defined('THEME_EXTENSION')){
        $template = "seckill";
    }
    $_REQUEST['act'] = 'list';
}

/*------------------------------------------------------ */
//-- 秒杀商品 --> 秒杀活动商品列表
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'list') {
    
    assign_template();
    $position = assign_ur_here('seckill');
    $smarty->assign('page_title', $position['title']);    // 页面标题
    $smarty->assign('ur_here', $position['ur_here']);  // 当前位置
    $smarty->assign('helps', get_shop_help());       // 网店帮助	
    $categories_pro = get_top_category_tree();
    $smarty->assign('categories_pro', $categories_pro);

    $seckill_list = seckill_goods_list();
    foreach ($seckill_list as $k => $v) {
        $seckill_list[$k]['begin_time_formated'] = local_date('Y-m-d H:i:s', $v['begin_time']);
        $seckill_list[$k]['end_time_formated'] = local_date('Y-m-d H:i:s', $v['end_time']);
        if ($v['status'] == true) {
            $guess_goods = $v['goods'];
        }
        if ($v['soon'] == true) {
            $will_begin = $v['goods'];
        }
    }

    if ($guess_goods) {//更多好货根据商品销量排序
        foreach ($guess_goods AS $uniqid => $row) {
            foreach ($row AS $key => $value) {
                $arrSort[$key][$uniqid] = $value;
            }
        }
        array_multisort($arrSort['percent'], SORT_DESC, $guess_goods);
    }

    $smarty->assign('seckill_list', $seckill_list);

    if ($cat_id) {
        $cat_info = get_cat_info($cat_id, array('cat_alias_name'));
        $smarty->assign('cat_alias_name', $cat_info['cat_alias_name']);
        $smarty->assign('will_begin', $will_begin);
        $smarty->display('seckill_cat_list.dwt');
    } else {
        //广告
        for ($i = 1; $i <= $_CFG['auction_ad']; $i++) {
            $seckill_top_ad .= "'seckill_top_ad" . $i . ","; //秒杀列表页面广告
        }

        $smarty->assign('seckill_top_ad', $seckill_top_ad); //liu
        $smarty->assign('guess_goods', $guess_goods);
        $smarty->display('seckill_list.dwt');
    }
}

/*------------------------------------------------------ */
//-- 秒杀商品 --> 商品详情
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'view')
{
    /* 取得参数：秒杀活动ID */
    $seckill_id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
    if ($seckill_id <= 0) {
        ecs_header("Location: ./\n");
        exit;
    }
    
    assign_template();

    /* 取得秒杀活动信息 */
    $seckill = seckill_info($seckill_id);
    $goods_id = $seckill['goods_id'];
    
    //秒杀结束直接跳到普通商品购买
    if($seckill['is_end'] && !$seckill['status']){
        $Location = build_uri('goods', array('gid'=>$seckill['goods_id']), $seckill['goods_name']);
        ecs_header("Location: $Location\n");
    }

    if (!$seckill) {
        show_message($_LANG['now_not_snatch']);
    }

    $first_month_day = local_mktime(0, 0, 0, date('m'), 1, date('Y')); //本月第一天
    $last_month_day = local_mktime(0, 0, 0, date('m'), date('t'), date('Y')) + 24 * 60 * 60 - 1; //本月最后一天

    $start_date = local_strtotime($seckill['begin_time']);
    $end_date = local_strtotime($seckill['end_time']);
    
    $position = assign_ur_here($seckill['cat_id'], $seckill['goods_name'], array(), '', $seckill['user_id']);
    $properties = get_goods_properties($goods_id, $region_id, $area_id);  // 获得商品的规格和属性
    $comment_all = get_comments_percent($goods_id);
    $order_goods = get_for_purchasing_goods($start_date, $end_date, $goods_id, $_SESSION['user_id'], 'seckill');

    $smarty->assign('look_top', get_top_seckill_goods('click_count'));

    if ($area_info['region_id'] == NULL) {
        $area_info['region_id'] = 0;
    }

    $sql = "select province, city, kf_type, kf_ww, kf_qq, shop_name from " . $ecs->table('seller_shopinfo') . " where ru_id='" . $goods['user_id'] . "'";
    $basic_info = $db->getRow($sql);

    $basic_date = array('region_name');
    $basic_info['province'] = get_table_date('region', "region_id = '" . $basic_info['province'] . "'", $basic_date, 2);
    $basic_info['city'] = get_table_date('region', "region_id= '" . $basic_info['city'] . "'", $basic_date, 2) . "市";

    /* 处理客服旺旺数组 by kong */
    if ($basic_info['kf_ww']) {
        $kf_ww = array_filter(preg_split('/\s+/', $basic_info['kf_ww']));
        $kf_ww = explode("|", $kf_ww[0]);
        if (!empty($kf_ww[1])) {
            $basic_info['kf_ww'] = $kf_ww[1];
        } else {
            $basic_info['kf_ww'] = "";
        }
    } else {
        $basic_info['kf_ww'] = "";
    }
    /* 处理客服QQ数组 by kong */
    if ($basic_info['kf_qq']) {
        $kf_qq = array_filter(preg_split('/\s+/', $basic_info['kf_qq']));
        $kf_qq = explode("|", $kf_qq[0]);
        if (!empty($kf_qq[1])) {
            $basic_info['kf_qq'] = $kf_qq[1];
        } else {
            $basic_info['kf_qq'] = "";
        }
    } else {
        $basic_info['kf_qq'] = "";
    }

    $merchant_seckill = get_merchant_seckill_goods($seckill['id'], $goods['user_id']);
    $smarty->assign('merchant_seckill_goods', $merchant_seckill);

    /* 判断当前商家是否允许"在线客服" begin  */
    $goods_info = goods_info($goods_id); //通过商品ID获取到ru_id;
    if ($GLOBALS['_CFG']['customer_service'] == 0) {
        $goods_info['user_id'] = 0;
    }
    $shop_information = get_shop_name($goods_info['user_id']); //通过ru_id获取到店铺信息;
    //判断当前商家是平台,还是入驻商家 
    if ($goods_info['user_id'] == 0) {
        //判断平台是否开启了IM在线客服
        if ($db->getOne("SELECT kf_im_switch FROM " . $ecs->table('seller_shopinfo') . "WHERE ru_id = 0")) {
            $shop_information['is_dsc'] = true;
        } else {
            $shop_information['is_dsc'] = false;
        }
    } else {
        $shop_information['is_dsc'] = false;
    }
    $shop_information['goods_id'] = $goods_id;
    $smarty->assign('shop_information', $shop_information);
    /* end */

    $area = array(
        'region_id' => $region_id, //仓库ID
        'province_id' => $province_id,
        'city_id' => $city_id,
        'district_id' => $district_id,
        'goods_id' => $goods_id,
        'user_id' => $user_id,
        'area_id' => $area_info['region_id'],
        'merchant_id' => $seckill['user_id'],
    );

    $properties = get_goods_properties($goods_id, $region_id, $area_info['region_id']);  // 获得商品的规格和属性  //ecmoban模板堂 --zhuo	
    $smarty->assign('cfg', $_CFG);                // 模板赋值
    $smarty->assign('properties', $properties['pro']);                              // 商品属性
    $smarty->assign('specification', $properties['spe']);                              // 商品规格

    //商品运费
    $region = array(1, $province_id, $city_id, $district_id);
    $shippingFee = goodsShippingFee($goods_id, $region_id, $region, $seckill['sec_price']);
    $smarty->assign('shippingFee', $shippingFee);

    $smarty->assign('ur_here', $position['ur_here']);  // 当前位置
    
    if (defined('THEME_EXTENSION')) {
        $categories_pro = get_category_tree_leve_one();
        $smarty->assign('categories_pro', $categories_pro); // 分类树加强版
    }
	
    $smarty->assign('id', $seckill['goods_id']);
    $smarty->assign('area', $area);
    $smarty->assign('orderG_number', $order_goods['goods_number']);
    $smarty->assign('comment_all', $comment_all);
    $smarty->assign('properties', $properties['pro']);                              // 商品属性	
    $smarty->assign('goods', $seckill);
    $smarty->assign('page_title', $position['title']);    // 页面标题
    $smarty->assign('pictures', get_goods_gallery($goods_id));  // 商品相册
    $smarty->assign('comment_percent', comment_percent($goods_id));
    $smarty->assign('basic_info', $basic_info);
    $smarty->assign('region_id', $region_id);
    $smarty->assign('area_id', $area_id);
    $smarty->assign('extend_info', get_goods_extend_info($goods_id)); //扩展信息 by wu
    $smarty->assign('helps', get_shop_help());       // 网店帮助
    $smarty->display('seckill_goods.dwt');
}

elseif ($_REQUEST['act'] == 'price')
{
    include('includes/cls_json.php');

    $json   = new JSON;
    $res    = array('err_msg' => '', 'err_no' => 0, 'result' => '', 'qty' => 1);

    $goods_id     = (isset($_REQUEST['id'])) ? intval($_REQUEST['id']) : 0; //仓库管理的地区ID
    $attr_id    = isset($_REQUEST['attr']) ? explode(',', $_REQUEST['attr']) : array();
    $number     = (isset($_REQUEST['number'])) ? intval($_REQUEST['number']) : 1;
    $warehouse_id     = (isset($_REQUEST['warehouse_id'])) ? intval($_REQUEST['warehouse_id']) : 0;
    $area_id     = (isset($_REQUEST['area_id'])) ? intval($_REQUEST['area_id']) : 0; //仓库管理的地区ID
	
    $onload     = (isset($_REQUEST['onload'])) ? trim($_REQUEST['onload']) : ''; //仓库管理的地区ID
    $goods = seckill_info($goods_id);

    if ($goods_id == 0)
    {
        $res['err_msg'] = $_LANG['err_change_attr'];
        $res['err_no']  = 1;
    }
    else
    {
        if ($number == 0)
        {
            $res['qty'] = $number = 1;
        }
        else
        {
            $res['qty'] = $number;
        }
	
        $res['attr_number'] = $goods['sec_num'];	
    }


    die($json->encode($res));
}

/*------------------------------------------------------ */
//-- 秒杀商品 --> 购买
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'buy')
{
    /* 查询：判断是否登录 */
    if ($_SESSION['user_id'] <= 0)
    {
        show_message($_LANG['gb_error_login'], '', '', 'error');
    }
	
    $warehouse_id     = (isset($_REQUEST['warehouse_id'])) ? intval($_REQUEST['warehouse_id']) : 0;
    $area_id     = (isset($_REQUEST['area_id'])) ? intval($_REQUEST['area_id']) : 0; //仓库管理的地区ID

    /* 查询：取得参数：秒杀活动id */
    $sec_goods_id = isset($_POST['sec_goods_id']) ? intval($_POST['sec_goods_id']) : 0;
    if ($sec_goods_id <= 0)
    {
        ecs_header("Location: ./\n");
        exit;
    }

    /* 查询：取得数量 */
    $number = isset($_POST['number']) ? intval($_POST['number']) : 1;
    $number = $number < 1 ? 1 : $number;

    /* 查询：取得秒杀活动信息 */
    $seckill = seckill_info($sec_goods_id, $number);
    if (empty($seckill))
    {
        ecs_header("Location: ./\n");
        exit;
    }

    /* 查询：检查秒杀活动是否是进行中 */
    if (!$seckill['status'])
    {
        show_message($_LANG['gb_error_status'], '', '', 'error');
    }
	
	//ecmoban模板堂 --zhuo start 

	if ($seckill['model_attr'] == 1) {
		$table_products = "products_warehouse";
		$type_files = " and warehouse_id = '$warehouse_id'";
	} elseif ($seckill['model_attr'] == 2) {
		$table_products = "products_area";
		$type_files = " and area_id = '$area_id'";
	} else {
		$table_products = "products";
		$type_files = "";
	}

    //ecmoban模板堂 --zhuo end
	
	$goods_id = !empty($seckill['goods_id']) ? $seckill['goods_id'] : 0;
	
	$sql = "SELECT * FROM " . $GLOBALS['ecs']->table($table_products) . " WHERE goods_id = '$goods_id'" . $type_files . " LIMIT 0, 1";
    $prod = $GLOBALS['db']->getRow($sql);

    

    /* 查询：取得规格 */
    $specs = isset($_POST['goods_spec']) ? htmlspecialchars(trim($_POST['goods_spec'])) : '';
    /* 查询：查询规格名称和值，不考虑价格 */
    $attr_list = array();
    $sql = "SELECT a.attr_name, g.attr_value " .
            "FROM " . $ecs->table('goods_attr') . " AS g, " .
                $ecs->table('attribute') . " AS a " .
            "WHERE g.attr_id = a.attr_id " .
            "AND g.goods_attr_id " . db_create_in($specs) . " ORDER BY a.sort_order, a.attr_id, g.goods_attr_id";
    $res = $db->query($sql);
    while ($row = $db->fetchRow($res))
    {
        $attr_list[] = $row['attr_name'] . ': ' . $row['attr_value'];
    }
    $goods_attr = join(chr(13) . chr(10), $attr_list);	
	
    $start_date = $seckill['begin_date'];
    $end_date = $seckill['end_date'];
    $order_goods = get_for_purchasing_goods($start_date, $end_date, $seckill['goods_id'], $_SESSION['user_id']);

    $restrict_amount = $number + $order_goods['goods_number'];

	
    /* 更新：清空订货单中所有团购商品 */
    include_once(ROOT_PATH . 'includes/lib_order.php');
	
    if(!empty($_SESSION['user_id'])){
            $sess = "";
    }else{
            $sess = real_cart_mac_ip();
    }
	
    /* 更新：清空订货单中所有秒杀商品 */
    include_once(ROOT_PATH . 'includes/lib_order.php');
    clear_cart(CART_SECKILL_GOODS);	
    /* 更新：加入订货单 */
    $goods_price = isset($seckill['sec_price']) > 0 ? $seckill['sec_price'] : $seckill['shop_price'];
    $cart = array(
        'user_id'        => $_SESSION['user_id'],
        'session_id'     => $sess,
        'goods_id'       => $seckill['goods_id'],
        'product_id'     => $prod['product_id'],
        'goods_sn'       => addslashes($seckill['goods_sn']),
        'goods_name'     => addslashes($seckill['goods_name']),
        'market_price'   => $seckill['market_price'],
        'goods_price'    => $goods_price,
        'goods_number'   => $number,
        'goods_attr'     => addslashes($goods_attr),
        'goods_attr_id'  => $specs,
		//ecmoban模板堂 --zhuo start
		'ru_id'			 => $seckill['user_id'],
		'warehouse_id'   => $region_id,
		'area_id'  		 => $area_id,
		//ecmoban模板堂 --zhuo end
        'is_real'        => $seckill['is_real'],
        'extension_code' => 'seckill'.$seckill['id'],
        'parent_id'      => 0,
        'rec_type'       => CART_SECKILL_GOODS,
        'is_gift'        => 0
    );

    $db->autoExecute($ecs->table('cart'), $cart, 'INSERT');

    /* 更新：记录购物流程类型：团购 */
    $_SESSION['flow_type'] = CART_SECKILL_GOODS;
    $_SESSION['extension_code'] = 'seckill';
    $_SESSION['extension_id'] = $sec_goods_id;

    /* 进入收货人页面 */
    $_SESSION['browse_trace'] = "seckill";
    ecs_header("Location: ./flow.php?step=checkout\n");
    exit;
}

function get_top_seckill_goods()
{
    $date_begin = local_strtotime(local_date('Ymd'));
    $sql = "SELECT sg.*,g.goods_name, g.shop_price, g.sales_volume, g.goods_thumb, g.goods_id FROM " . $GLOBALS['ecs']->table('seckill_goods') . " sg "
            . " LEFT JOIN " . $GLOBALS['ecs']->table('seckill') . " AS s ON s.sec_id = sg.sec_id "
            . " LEFT JOIN " . $GLOBALS['ecs']->table('seckill_time_bucket') . " AS stb ON sg.tb_id = stb.id "
            . " LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " g ON sg.goods_id = g.goods_id WHERE acti_time >= $date_begin AND g.goods_id <> '' LIMIT 5 ";
    $look_top_list = $GLOBALS['db']->getAll($sql);
    foreach ($look_top_list as $key => $look_top) {
        $look_top['goods_thumb'] = get_image_path($look_top['goods_id'], $look_top['goods_thumb'], true);
        $look_top['url'] = build_uri('seckill', array('act' => "view", 'secid' => $look_top['id']), $look_top['goods_name']);
        $look_top_list_1[] = $look_top;
    }

    return $look_top_list_1;
}

function get_merchant_seckill_goods($sec_goods_id, $ru_id){
    
    $date_begin = local_strtotime(local_date('Ymd'));
    $sql = "SELECT sg.id, sg.sec_price, g.goods_name, g.goods_thumb, g.sales_volume FROM " . $GLOBALS['ecs']->table('seckill_goods') . " sg "
            . " LEFT JOIN " . $GLOBALS['ecs']->table('seckill') . " AS s ON sg.sec_id = s.sec_id "
            . " LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " g ON sg.goods_id = g.goods_id "
            . " LEFT JOIN " . $GLOBALS['ecs']->table('seckill_time_bucket') . " AS stb ON sg.tb_id = stb.id "
            . " WHERE g.user_id = '$ru_id' AND acti_time >= $date_begin AND g.goods_id <> '' LIMIT 4 ";
    $merchant_seckill = $GLOBALS['db']->getAll($sql);

    foreach ($merchant_seckill as $key => $row) {
        $merchant_seckill[$key]['shop_price'] = price_format($row['sec_price'], false);
        $merchant_seckill[$key]['goods_thumb'] = get_image_path($row['goods_id'], $row['goods_thumb'], true);
        $merchant_seckill[$key]['url'] = build_uri('seckill', array('act' => "view", 'secid' => $row['id']), $row['goods_name']);
    }

    return $merchant_seckill;
}

?>