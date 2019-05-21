<?php

/**
 * DSC 预定商品
 * ============================================================================
 * 版权所有 2005-2016 上海商创网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.ecmoban.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: Zhuo $
 * $Id: common.php 2016-01-04 Zhuo $
 */

define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');

// by zxk 跳转
$ua = strtolower($_SERVER['HTTP_USER_AGENT']);

$uachar = "/(nokia|sony|ericsson|mot|samsung|htc|sgh|lg|sharp|sie-|philips|panasonic|alcatel|lenovo|iphone|ipod|blackberry|meizu|android|netfront|symbian|ucweb|windowsce|palm|operamini|operamobi|opera mobi|openwave|nexusone|cldc|midp|wap|mobile)/i";

if(($ua == '' || preg_match($uachar, $ua))&& !strpos(strtolower($_SERVER['REQUEST_URI']),'wap'))
{
    if(isset($_REQUEST['act']) && $_REQUEST['act'] == 'view'){
        $id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
    }

    $Loaction = 'mobile/index.php?m=presale&a=detail&id=' . $id;

    if (!empty($Loaction))
    {
        ecs_header("Location: $Loaction\n");

        exit;
    }
}

if ((DEBUG_MODE & 2) != 2)
{
    $smarty->caching = true;
}

require(ROOT_PATH . '/includes/lib_area.php');

//预约
$smarty->assign('act_type', 'presale');

//ecmoban模板堂 --zhuo start
$area_info = get_area_info($province_id);
$area_id = $area_info['region_id'];

$where = "regionId = '$province_id'";
$date = array('parent_id');
$region_id = get_table_date('region_warehouse', $where, $date, 2);

if(isset($_COOKIE['region_id']) && !empty($_COOKIE['region_id'])){
    $region_id = $_COOKIE['region_id'];
}
//ecmoban模板堂 --zhuo end

get_request_filter();
$_POST = get_request_filter($_POST, 1);

//ecmoban模板堂 --zhuo start 仓库
$pid = isset($_REQUEST['pid'])  ? intval($_REQUEST['pid']) : 0;
$user_id = isset($_SESSION['user_id'])? $_SESSION['user_id'] : 0;
//ecmoban模板堂 --zhuo end 仓库


//分类导航页
$smarty->assign('pre_nav_list', get_pre_nav());
/*------------------------------------------------------ */
//-- act 操作项的初始化
/*------------------------------------------------------ */
if (empty($_REQUEST['act']))
{
    $_REQUEST['act'] = 'index';
}
if (!empty($_REQUEST['act']) && $_REQUEST['act'] == 'price2') {
    //添加 $_REQUEST['act'] 为空bug
    include('includes/cls_json.php');
    header("Content-type: text/html; charset=utf-8");

    $json   = new JSON;
    $res    = array('err_msg' => '', 'err_no' => 0, 'result' => '', 'qty' => 1);

    $act_id = isset($_REQUEST['act_id']) ? intval($_REQUEST['act_id']) : 0;
    if ($act_id <= 0)
    {
        exit($json->encode($res));
    }

    //预约活动
    $presale = presale_info($act_id);

    if (empty($presale))
    {
        exit($json->encode($res));
    }

    //商品id
    $goods_id     = $presale['goods_id']; //仓库管理的地区ID

    $attr_id    = isset($_REQUEST['attr']) ? explode(',', $_REQUEST['attr']) : array();
    $warehouse_id     = (isset($_REQUEST['warehouse_id'])) ? intval($_REQUEST['warehouse_id']) : 0;
    $area_id     = (isset($_REQUEST['area_id'])) ? intval($_REQUEST['area_id']) : 0; //仓库管理的地区ID

    $onload     = (isset($_REQUEST['onload'])) ? trim($_REQUEST['onload']) : ''; //仓库管理的地区ID

    $goods_attr = (isset($_REQUEST['goods_attr']) && !(empty($_REQUEST['goods_attr'])) ? explode(',', $_REQUEST['goods_attr']) : array());
    $attr_ajax = get_goods_attr_ajax($goods_id, $goods_attr, $attr_id);

    //获取阶梯价格
    $price_ladder = $presale['price_ladder'];
    $prices = array_column($price_ladder, 'formated_price');

    $presale['goods_price_formatted'] = max($prices);

    $smarty->assign('goods', $presale);

    $main_attr_list = get_main_attr_list($goods_id, $attr_id);
    $smarty->assign('main_attr_list', $main_attr_list);

    $res['main_attr_list'] = $smarty->fetch('library/main_attr_list.lbi');
    exit($json->encode($res));

} elseif (!empty($_REQUEST['act']) && $_REQUEST['act'] == 'price') {
    exit;
    $goods_id = isset($_REQUEST['id'])  ? intval($_REQUEST['id']) : 0;
    include('includes/cls_json.php');

    $json   = new JSON;
    $res    = array('err_msg' => '', 'err_no' => 0, 'result' => '', 'qty' => 1);

    $attr_id    = isset($_REQUEST['attr']) && !empty($_REQUEST['attr']) ? explode(',', $_REQUEST['attr']) : array();
    $number     = (isset($_REQUEST['number'])) ? intval($_REQUEST['number']) : 1;
    $warehouse_id     = (isset($_REQUEST['warehouse_id'])) ? intval($_REQUEST['warehouse_id']) : 0;
    $area_id     = (isset($_REQUEST['area_id'])) ? intval($_REQUEST['area_id']) : 0; //仓库管理的地区ID

    $onload     = (isset($_REQUEST['onload'])) ? trim($_REQUEST['onload']) : ''; //仓库管理的地区ID

    $goods_attr    = isset($_REQUEST['goods_attr']) && !empty($_REQUEST['goods_attr']) ? explode(',', $_REQUEST['goods_attr']) : array();
    $attr_ajax = get_goods_attr_ajax($goods_id, $goods_attr, $attr_id);

    $goods = get_goods_info($goods_id, $warehouse_id, $area_id);
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

        //ecmoban模板堂 --zhuo start
        $products = get_warehouse_id_attr_number($goods_id, $_REQUEST['attr'], $goods['user_id'], $warehouse_id, $area_id);
        $attr_number = $products['product_number'];
        $product_promote_price = isset($products['product_promote_price']) ? $products['product_promote_price'] : 0;

        if($goods['model_attr'] == 1){
                $table_products = "products_warehouse";
                $type_files = " and warehouse_id = '$warehouse_id'";
        }elseif($goods['model_attr'] == 2){
                $table_products = "products_area";
                $type_files = " and area_id = '$area_id'";
        }else{
                $table_products = "products";
                $type_files = "";
        }

        $sql = "SELECT * FROM " .$GLOBALS['ecs']->table($table_products). " WHERE goods_id = '$goods_id'" .$type_files. " LIMIT 0, 1";
        $prod = $GLOBALS['db']->getRow($sql);

        if($goods['goods_type'] == 0){
            $attr_number = $goods['goods_number'];
        }else{
            if(empty($prod)){ //当商品没有属性库存时
                $attr_number = $goods['goods_number'];
            }
        }

        if(empty($prod)){ //当商品没有属性库存时
            $res['bar_code'] = $goods['bar_code'];
        }else{
            $res['bar_code'] = $products['bar_code'];
        }

        $attr_number = 999; //预售商品，不受库存限制

        $res['attr_number'] = $attr_number;
        //ecmoban模板堂 --zhuo end

        $res['show_goods'] = 0;
        if($goods_attr && $GLOBALS['_CFG']['add_shop_price'] == 0){
            if(count($goods_attr) == count($attr_ajax['attr_id'])){
                $res['show_goods'] = 1;
            }
        }

        $shop_price = get_final_price($goods_id, $number, true, $attr_id, $warehouse_id, $area_id);
        $res['shop_price'] = price_format($shop_price);

        //属性价格
        $spec_price = get_final_price($goods_id, $number, true, $attr_id, $warehouse_id, $area_id, 1, 0, 0, $res['show_goods'], $product_promote_price);
        if($GLOBALS['_CFG']['add_shop_price'] == 0){
            $res['result'] = price_format($spec_price);
        }else{
            $res['result'] = price_format($shop_price);
        }

        $res['spec_price'] = price_format($spec_price);
        $res['original_shop_price'] = $shop_price;
        $res['original_spec_price'] = $spec_price;
        $res['marketPrice_amount'] = price_format($goods['marketPrice'] + $spec_price);
        $res['result'] = price_format($shop_price);

        if($GLOBALS['_CFG']['add_shop_price'] == 0){
            $goods['marketPrice'] = isset($products['product_market_price']) && !empty($products['product_market_price']) ? $products['product_market_price'] : $goods['marketPrice'];
            $res['result_market'] = price_format($goods['marketPrice']); // * $number
        }else{
            $res['result_market'] = price_format($goods['marketPrice'] + $spec_price); // * $number
        }
    }

    if($GLOBALS['_CFG']['open_area_goods'] == 1){

        $area_list = get_goods_link_area_list($goods_id, $goods['user_id']);
        if($area_list['goods_area']){
            if(!in_array($area_id, $area_list['goods_area'])){
                $res['err_no']  = 2;
            }
        } else {
            $res['err_no']  = 2;
        }
    }

    $presale = get_presale_time($goods_id);
    $res['act_id'] = isset($presale['act_id']) ? $presale['act_id'] : 0;
    $res['onload'] = $onload;
    $res['presale'] = $presale;

    die($json->encode($res));
} elseif (!empty($_REQUEST['act']) && $_REQUEST['act'] == 'get_select_record')
{
    //添加查询价格act
    include 'includes/cls_json.php';
    include_once(ROOT_PATH . 'includes/lib_order.php');
    $json = new JSON();
    $result = array('error' => '', 'message' => 0, 'content' => '');

    $act_id = isset($_REQUEST['act_id']) ? intval($_REQUEST['act_id']) : 0;
    if ($act_id <= 0)
    {
        exit($json->encode($res));
    }

    $presale = presale_info($act_id);
    if (empty($presale))
    {
        exit($json->encode($res));
    }

    $goods_type = $presale['goods_type'];
    $goods_id     = $presale['goods_id']; //仓库管理的地区ID

    //by zxk 获取商品规格
    $properties = get_goods_properties($goods_id, $region_id, $area_id);
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

        $record_data = get_select_record_data($goods_id, $attr_num_array);
        $smarty->assign('record_data', $record_data);
        $result['record_data'] = $smarty->fetch('library/select_record_data.lbi');
    } else {
        $goods_number = (empty($_REQUEST['goods_number']) ? 0 : intval($_REQUEST['goods_number']));
        $result['total_number'] = $goods_number;
    }

    $data = calculate_goods_price($act_id, $result['total_number'], 'presale');
    $result['data'] = $data;
    exit($json->encode($result));


} elseif ($_REQUEST['act'] == 'in_stock'){

	include('includes/cls_json.php');

    $json   = new JSON;
    $res    = array('err_msg' => '', 'result' => '', 'qty' => 1);

	clear_cache_files();

    $act_id = empty($_REQUEST['act_id']) ? 0 : intval($_REQUEST['act_id']);
    $goods_id = empty($_REQUEST['id']) ? 0 : intval($_REQUEST['id']);
    $province = empty($_REQUEST['province']) ? 1 : intval($_REQUEST['province']);
    $city = empty($_REQUEST['city']) ? 52 : intval($_REQUEST['city']);
    $district = empty($_REQUEST['district']) ? 500 : intval($_REQUEST['district']);
	$d_null = empty($_REQUEST['d_null']) ? 0 : intval($_REQUEST['d_null']);
	$user_id = empty($_REQUEST['user_id']) ? 0 : ($_REQUEST['user_id']);

	$user_address = get_user_address_region($user_id);
	$user_address = explode(",",$user_address['region_address']);

	setcookie('province', $province, gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
	setcookie('city', $city, gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);

	setcookie('district', $district, gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);

	$regionId = 0;
	setcookie('regionId', $regionId, gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);

	//清空
	setcookie('type_province', 0, gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
	setcookie('type_city', 0, gmtime() + 3600 * 24 * 30);
	setcookie('type_district', 0, gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);

	$res['d_null'] = $d_null;

	if($d_null == 0){
		if(in_array($district,$user_address)){
			$res['isRegion'] = 1;
		}else{
			$res['message'] = $_LANG['region_message'];
			$res['isRegion'] = 88; //原为0
		}
	}else{
		setcookie('district', '', gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
	}

	$res['goods_id'] = $goods_id;
        $res['act_id'] = $act_id;

    die($json->encode($res));

}

/*------------------------------------------------------ */
//-- 预售 --> 首页
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'index')
{
    // 调用数据
    $pre_goods = get_pre_cat();
    $smarty->assign('pre_cat_goods', $pre_goods);

	$categories_pro = get_category_tree_leve_one();
    $smarty->assign('categories_pro',  $categories_pro); // 分类树加强版

    assign_template();
    $smarty->assign('helps',      get_shop_help());       // 网店帮助
    $position = assign_ur_here(0, '预订专区');
    $smarty->assign('page_title', $position['title']);    // 页面标题
    $smarty->assign('ur_here',    $position['ur_here']);  // 当前位置

    /**小图 start**/
    for($i=1;$i<=$_CFG['auction_ad'];$i++)
    {
        $presale_banner   .= "'presale_banner".$i.","; //预售轮播banner
        $presale_banner_small   .= "'presale_banner_small".$i.","; //预售小轮播
        $presale_banner_small_left   .= "'presale_banner_small_left".$i.","; //预售小轮播 左侧
        $presale_banner_small_right   .= "'presale_banner_small_right".$i.","; //预售小轮播 右侧

        //热门分类
        $top .= "'top_cat_presale".$i.",";
        //每日上新
         $new .=  "'new_cat_presale".$i.",";
    }

    $smarty->assign('top', $top);
    $smarty->assign('new', $new);

    $smarty->assign('pager', array('act'=>'index'));
    $smarty->assign('presale_banner',       $presale_banner);
    $smarty->assign('presale_banner_small',       $presale_banner_small);
    $smarty->assign('presale_banner_small_left',       $presale_banner_small_left);
    $smarty->assign('presale_banner_small_right',       $presale_banner_small_right);

    /**小图 end**/

    $smarty->assign('act_type', 'presale');

    /* 显示模板 */
    $smarty->display('presale_index.dwt');
}

/*------------------------------------------------------ */
//-- 预售 --> 特惠专区
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'area')
{
    // 调用数据

    /* 显示模板 */
    $smarty->display('presale_area.dwt', $cache_id);
}

/*------------------------------------------------------ */
//-- 预售 --> 新品发布
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'new')
{
    $where = '';
    // 筛选条件
    $cat_id = isset($_REQUEST['cat_id']) && intval($_REQUEST['cat_id']) > 0 ? intval($_REQUEST['cat_id']) : 0;
    $status = isset($_REQUEST['status']) && intval($_REQUEST['status']) > 0 ? intval($_REQUEST['status']) : 0;// 状态1即将开始，2预约中，3已结束

    $children = get_children($cat_id, $type, 0, 'presale_cat', "a.cat_id");

    //1未开始，2进行中，3结束
    $now = gmtime();
    if ($status == 1)
    {
        $where .= " AND a.start_time > $now ";
    }
    elseif ($status == 2)
    {
        $where .= " AND a.start_time < $now AND $now < a.end_time ";
    }
    elseif ($status == 3)
    {
        $where .= " AND $now > a.end_time ";
    }

    $pager = array('cat_id'=>$cat_id, 'act' => 'new', 'status' => $status);
    $smarty->assign('pager',$pager);

    $pre_status['status_cat'] = get_presale_url("new", 0, 0, "新品发布");
    $pre_status['status_all'] = get_presale_url("new", $cat_id, 0, "新品发布");
    $pre_status['status_one'] = get_presale_url("new", $cat_id, 1, "新品发布");
    $pre_status['status_two'] = get_presale_url("new", $cat_id, 2, "新品发布");
    $pre_status['status_three'] = get_presale_url("new", $cat_id, 3, "新品发布");
    $smarty->assign('pre_status', $pre_status);

    //所有分类
    $pre_category = get_pre_category('new', $status);
    $smarty->assign('pre_category', $pre_category);

    $sql = "SELECT a.*, g.goods_thumb, g.goods_img, g.goods_name, g.shop_price, g.market_price, g.sales_volume FROM ".$GLOBALS['ecs']->table('presale_activity')." AS a"
            . " LEFT JOIN ".$GLOBALS['ecs']->table('goods')." AS g ON a.goods_id = g.goods_id "
            . " WHERE $children AND g.goods_id > 0 $where AND a.review_status = 3 ORDER BY a.end_time DESC,a.start_time DESC ";
    $res = $GLOBALS['db']->getAll($sql);

    foreach ($res as $key => $row) {
        $res[$key]['thumb'] = get_image_path($row['goods_id'], $row['goods_thumb'], true);
        $res[$key]['goods_img'] = get_image_path($row['goods_id'], $row['goods_img']);
        $res[$key]['url'] = build_uri('presale', array('act' => 'view', 'presaleid' => $row['act_id']));

        $res[$key]['end_time_date'] = local_date("Y-m-d H:i:s", $row['end_time']);
        $res[$key]['end_time_day'] = local_date("Y-m-d", $row['end_time']);

        $res[$key]['start_time_date'] = local_date("Y-m-d H:i:s", $row['start_time']);
        $res[$key]['start_time_day'] = local_date("Y-m-d", $row['start_time']);

        if ($row['start_time'] >= $now) {
            $res[$key]['no_start'] = 1;
        }
        if ($row['end_time'] <= $now) {
            $res[$key]['already_over'] = 1;
        }
    }

    // 按日期重新排序数据分组
    $date_array = array();
    foreach ($res as $key => $row)
    {
        $date_array[$row['end_time_day']][] = $row;

    }

    // 把日期键值替换成数字0、1、2...,日期楼层下商品归类
    $date_result = array();
    foreach ($date_array as $key => $value)
    {
        $date_result[]['goods'] = $value;
    }

    foreach ($date_result as $key => $value)
    {
        $date_result[$key]['end_time_day'] = $value['goods'][0]['end_time_day'];
        $date_result[$key]['end_time_y'] = local_date('Y', gmstr2time($value['goods'][0]['end_time_day']));
        $date_result[$key]['end_time_m'] = local_date('m', gmstr2time($value['goods'][0]['end_time_day']));
        $date_result[$key]['end_time_d'] = local_date('d', gmstr2time($value['goods'][0]['end_time_day']));
        $date_result[$key]['count_goods'] = count($value['goods']);
    }

    $smarty->assign('date_result', $date_result);

    assign_template();
    $smarty->assign('helps',      get_shop_help());       // 网店帮助
    $position = assign_ur_here();
    $smarty->assign('page_title', $position['title']);    // 页面标题
    $smarty->assign('ur_here',    $position['ur_here']);  // 当前位置

    /**小图 start**/
    for($i=1;$i<=$_CFG['auction_ad'];$i++)
    {
        $presale_banner_new   .= "'presale_banner_new".$i.","; //预售轮播banner
    }

    $smarty->assign('presale_banner_new',       $presale_banner_new);

    /* 显示模板 */
    $smarty->display('presale_new.dwt');
}

/*------------------------------------------------------ */
//-- 预售 --> 抢先订
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'advance')
{
    //筛选条件
    $price_min = isset($_REQUEST['price_min']) && intval($_REQUEST['price_min']) > 0 ? intval($_REQUEST['price_min']) : 0;
    $price_max = isset($_REQUEST['price_max']) && intval($_REQUEST['price_max']) > 0 ? intval($_REQUEST['price_max']) : 0;

    $default_sort_order_method = $_CFG['sort_order_method'] == '0' ? 'DESC' : 'ASC';
    $default_sort_order_type   = $_CFG['sort_order_type'] == '0' ? 'act_id' : ($_CFG['sort_order_type'] == '1' ? 'shop_price' : 'start_time');

    $sort  = (isset($_REQUEST['sort'])  && in_array(trim(strtolower($_REQUEST['sort'])), array('shop_price', 'start_time', 'act_id'))) ? trim($_REQUEST['sort'])  : $default_sort_order_type;
    $order = (isset($_REQUEST['order']) && in_array(trim(strtoupper($_REQUEST['order'])), array('ASC', 'DESC'))) ? trim($_REQUEST['order']) : $default_sort_order_method;

    $cat_id = isset($_REQUEST['cat_id']) && intval($_REQUEST['cat_id']) > 0 ? intval($_REQUEST['cat_id']) : 0;
    $status = isset($_REQUEST['status']) && intval($_REQUEST['status']) > 0 ? intval($_REQUEST['status']) : 0;// 状态1即将开始，2预约中，3已结束
    // 调用数据
    $goods = get_pre_goods($cat_id, $min=0, $max=0, $start_time, $end_time, $sort, $status, $order);

    $pre_category = get_pre_category("advance", $status);
    $smarty->assign('pre_category', $pre_category);

    $pager = array('cat_id'=>$cat_id, 'brand' => $brand, 'act' => 'advance','price_min' => $price_min,'price_max' => $price_max,'sort' => $sort,'order' => $order,'status' => $status);
    $smarty->assign('pager',$pager);
    $smarty->assign("goods", $goods);

    assign_template();
    $smarty->assign('helps',      get_shop_help());       // 网店帮助
    $position = assign_ur_here();
    $smarty->assign('page_title', $position['title']);    // 页面标题
    $smarty->assign('ur_here',    $position['ur_here']);  // 当前位置

    /**小图 start**/
    for($i=1;$i<=$_CFG['auction_ad'];$i++)
    {
        $presale_banner_advance   .= "'presale_banner_advance".$i.","; //预售轮播banner
    }

    $smarty->assign('presale_banner_advance',       $presale_banner_advance);

    /* 显示模板 */
    $smarty->display('presale_advance.dwt', $cache_id);
}

/*------------------------------------------------------ */
//-- 预售 --> 抢先订
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'category')
{
    $brand = $ecs->get_explode_filter($_REQUEST['brand']); //过滤品牌参数

    //筛选条件
    $price_min = isset($_REQUEST['price_min']) && intval($_REQUEST['price_min']) > 0 ? intval($_REQUEST['price_min']) : 0;
    $price_max = isset($_REQUEST['price_max']) && intval($_REQUEST['price_max']) > 0 ? intval($_REQUEST['price_max']) : 0;

    $default_sort_order_method = $_CFG['sort_order_method'] == '0' ? 'DESC' : 'ASC';
    $default_sort_order_type   = $_CFG['sort_order_type'] == '0' ? 'act_id' : ($_CFG['sort_order_type'] == '1' ? 'shop_price' : 'start_time');

    $sort  = (isset($_REQUEST['sort'])  && in_array(trim(strtolower($_REQUEST['sort'])), array('shop_price', 'start_time', 'act_id'))) ? trim($_REQUEST['sort'])  : $default_sort_order_type;
    $order = (isset($_REQUEST['order']) && in_array(trim(strtoupper($_REQUEST['order'])), array('ASC', 'DESC'))) ? trim($_REQUEST['order']) : $default_sort_order_method;

    $cat_id = isset($_REQUEST['cat_id']) && intval($_REQUEST['cat_id']) > 0 ? intval($_REQUEST['cat_id']) : 0;
    if (!$cat_id) {
        header("Location: index.php\n");
        exit;
    }


    /* 平台品牌筛选 */
    if (true) {
        $children = get_children($cat_id);

        $cat_keys = get_array_keys_cat($cat_id);
        $brand_select = '';
        $brand_tag_where = '';


        //关联地区显示商品
        if ($GLOBALS['_CFG']['open_area_goods'] == 1) {
            $brand_select = " , ( SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('link_area_goods') . " as lag WHERE lag.goods_id = g.goods_id AND lag.region_id = '$area_id' LIMIT 1) AS area_goods_num ";
            $where_having = " AND area_goods_num > 0 ";
        }

        if ($GLOBALS['_CFG']['review_goods'] == 1) {
            $brand_tag_where .= ' AND g.review_status > 2 ';
        }

        $sql = "SELECT b.brand_id, b.brand_name, b.brand_logo, COUNT(*) AS goods_num " . $brand_select .
            "FROM " . $GLOBALS['ecs']->table('brand') . "AS b ".
            " LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON g.brand_id = b.brand_id AND g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 $brand_tag_where ".
            " LEFT JOIN " . $GLOBALS['ecs']->table('goods_cat') . " AS gc ON g.goods_id = gc.goods_id " .
            " WHERE $children OR " . 'gc.cat_id ' . db_create_in(array_unique(array_merge(array($cat_id), $cat_keys))) . " AND b.is_show = 1 " .
            "GROUP BY b.brand_id HAVING goods_num > 0 $where_having ORDER BY b.sort_order, b.brand_id ASC";


        $brands_list = $GLOBALS['db']->getAll($sql);


        //by zxk
        $pin = new pin();    /*  增加获取字母类 这里实例化对象 */

        $brands = array();
        foreach ($brands_list AS $key => $val)
        {
            $temp_key = $key; //by zhang

            $brands[$temp_key]['brand_id'] = $val['brand_id'];
            $brands[$temp_key]['brand_name'] = $val['brand_name'];

            //by zhang start
            $bdimg_path="data/brandlogo/";   				   // 图片路径
            $bd_logo=$val['brand_logo']?$val['brand_logo']:""; // 图片名称
            if(empty($bd_logo)){
                $brands[$temp_key]['brand_logo'] =""; 		   // 获取品牌图片
            }else{
                $brands[$temp_key]['brand_logo'] =$bdimg_path.$bd_logo;
            }

            $brands[$temp_key]['brand_letters'] = strtoupper(substr($pin->Pinyin($val['brand_name'],'UTF8'),0,1));  //获取品牌字母
            //by zhang end

            //OSS文件存储ecmoban模板堂 --zhuo start
            if($GLOBALS['_CFG']['open_oss'] == 1 && $brands[$temp_key]['brand_logo']){
                $bucket_info = get_bucket_info();
                $brands[$temp_key]['brand_logo'] = $bucket_info['endpoint'] . $brands[$temp_key]['brand_logo'];
            }
            //OSS文件存储ecmoban模板堂 --zhuo end


            $brands[$temp_key]['url'] = build_uri('presale_category', array('view'=>'list', 'cid' => $cat_id, 'bid' => $val['brand_id'], 'filter_attr'=>$filter_attr_str), $cat['cat_name']);

            /* 判断品牌是否被选中 */ // by zhang
            if (!strpos($brand,",") && $brand == $brands_list[$key]['brand_id'])
            {
                $brands[$temp_key]['selected'] = 1;
            }
            if (stripos($brand,","))
            {
                $brand2=explode(",",$brand);
                for ($i=0; $i <$brand2[$i] ; $i++) {
                    if($brand2[$i]==$brands_list[$key]['brand_id']){
                        $brands[$temp_key]['selected'] = 1;
                    }
                }
            }
        }

        $letter=range('A','Z');
        $smarty->assign('letter', $letter);

        // 为0或没设置的时候 加载模板
        if($brands){
            $smarty->assign('brands', $brands);
        }


        foreach ($brands as $key => $value) {
            if ($value['selected'] == 1) {
                $bd.=$value['brand_name'] . ",";
                $get_bd[$key]['brand_id'] = $value['brand_id'];

                if ($_CFG['rewrite']) {
                    $brand_id = "b" . $get_bd[$key]['brand_id'];
                    if (stripos($value['url'], $brand_id)) {
                        $get_bd[$key]['url'] = str_replace($brand_id, "b0", $value['url']);
                    }
                } else {
                    $brand_id = "brand=" . $get_bd[$key]['brand_id'];
                    if (stripos($value['url'], $brand_id)) {
                        $get_bd[$key]['url'] = str_replace($brand_id, "brand=0", $value['url']);
                    }
                }
                $br_url = $get_bd[$key]['url'];
            }
        }

        $get_brand['br_url'] = $br_url;
        $get_brand['bd']=substr($bd,0,-1);

        $smarty->assign('get_bd',            $get_brand);               // 品牌已选模块
        //by zhang end
    }

    $status = isset($_REQUEST['status']) && intval($_REQUEST['status']) > 0 ? intval($_REQUEST['status']) : 0;// 状态1即将开始，2预约中，3已结束

    // 调用数据
    $goods = get_pre_goods($cat_id, $min=0, $max=0, $start_time, $end_time, $sort, $status, $order, $brand);

    //所有分类
    $pre_category = get_pre_category('category', $status);
    $smarty->assign('pre_category', $pre_category);

    $pager = array('cat_id'=>$cat_id, 'act' => 'category','brand' => $brand, 'price_min' => $price_min,'price_max' => $price_max,'sort' => $sort,'order' => $order,'status' => $status);
    $smarty->assign('pager',$pager);
    $smarty->assign("goods", $goods);

    assign_template();
    $smarty->assign('helps',      get_shop_help());       // 网店帮助
    $position = assign_ur_here();
    $smarty->assign('page_title', $position['title']);    // 页面标题
    $smarty->assign('ur_here',    $position['ur_here']);  // 当前位置

    /**小图 start**/
    for($i=1;$i<=$_CFG['auction_ad'];$i++)
    {
        $presale_banner_category   .= "'presale_banner_category".$i.","; //预售轮播banner
    }

    $smarty->assign('presale_banner_category',       $presale_banner_category);

    /* 显示模板 */
    $smarty->display('presale_category.dwt', $cache_id);
}


/*------------------------------------------------------ */
//-- 猜你喜欢--换一组ajax处理
/*------------------------------------------------------ */
elseif (!empty($_REQUEST['act']) && $_REQUEST['act'] == 'guess_goods')
{
    include('includes/cls_json.php');

    $json   = new JSON;
    $res    = array('err_msg' => '', 'result' => '');

    $page    = (isset($_REQUEST['page'])) ? intval($_REQUEST['page']) : 1;
    if($page > 3){
        $page = 1;
    }
    $need_cache = $GLOBALS['smarty']->caching;
    $need_compile = $GLOBALS['smarty']->force_compile;
    $GLOBALS['smarty']->caching = false;
    $GLOBALS['smarty']->force_compile = true;

    $guess_goods = get_guess_goods($user_id, 1, $page, 7);

    $smarty->assign('guess_goods', $guess_goods);
    $smarty->assign('pager', $pager);

    $res['page'] = $page;
    $res['result'] = $GLOBALS['smarty']->fetch('library/guess_goods_love.lbi');

    $GLOBALS['smarty']->caching = $need_cache;
    $GLOBALS['smarty']->force_compile = $need_compile;

    die($json->encode($res));
}
/*------------------------------------------------------ */
//-- 预售 --> 商品详情
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'view')
{
    require(dirname(__FILE__) . '/includes/phpqrcode/phpqrcode.php'); //by wu

    /* 取得参数：预售活动id */
    $presale_id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
    if ($presale_id <= 0)
    {
        ecs_header("Location: ./\n");
        exit;
    }


    /* 取得预售活动信息 */
    $presale = presale_info($presale_id, 0, $user_id);

    if (empty($presale))
    {
        show_message($_LANG['now_not_snatch']);
    }

    //检查权限
    $priv_user_id = get_table_date('goods', 'goods_id=\'' . $presale['goods_id'] . '\'', array('user_id'), 2);
    if (!is_scscp_admin() && !is_scscp_seller($priv_user_id)) {
        priv();
    }

    assign_template();

    $categories_pro = get_category_tree_leve_one();
    $smarty->assign('categories_pro',  $categories_pro); // 分类树加强版

    /* 缓存id：语言，预售活动id，状态，（如果是进行中）当前数量和是否登录 */
    $cache_id = $_CFG['lang'] . '-presale-' . $presale_id . '-' . $presale['status'].  time();
    if ($presale['status'] == GBS_UNDER_WAY)
    {
        $cache_id = $cache_id . '-' . $presale['valid_goods'] . '-' . intval($_SESSION['user_id'] > 0);
    }
    $cache_id = sprintf('%X', crc32($cache_id));


    /* 如果没有缓存，生成缓存 */
    if (!$smarty->is_cached('presale_goods.dwt', $cache_id))
    {

        //ecmoban模板堂 --zhuo start 限购
        $start_date = $presale['xiangou_start_date'];
        $end_date = $presale['xiangou_end_date'];

        $nowTime = gmtime();
        if ($nowTime > $start_date && $nowTime < $end_date) {
            $xiangou = 1;
        } else {
            $xiangou = 0;
        }

        $smarty->assign('xiangou', $xiangou);
        $smarty->assign('orderG_number', $presale['valid_goods']); //购买的商品数量
        //ecmoban模板堂 --zhuo end 限购

        //距离目标数量
        if ($presale['restrict_amount']) {
            $juli = $presale['restrict_amount'] - $presale['valid_goods'];
            $smarty->assign('juli', $juli);
        }

        $now = gmtime();
        $presale['gmt_end_date'] = local_strtotime($presale['end_time']);
        $presale['gmt_start_date'] = local_strtotime($presale['start_time']);
        if ($presale['gmt_start_date'] >= $now )
        {
            $presale['no_start'] = 1;
        }
        if( $presale['gmt_end_date'] <= $now )
        {
                $presale['already_over'] = 1;
        }

        $smarty->assign('presale', $presale);

        /* 取得预售商品信息 */
        $goods_id = $presale['goods_id'];
        $goods = get_goods_info($goods_id, $region_id, $area_id);
        if (empty($goods))
        {
            ecs_header("Location: ./\n");
            exit;
        }

        $smarty->assign('goods', $goods);

        $smarty->assign('id',           $goods_id);
        $smarty->assign('type',         0);

        //评分 start
        $comment_allCount = get_goods_comment_count($goods_id);
        $comment_all = get_comments_percent($goods_id);
        $smarty->assign('comment_allCount',        $comment_allCount);
        $smarty->assign('comment_all',  $comment_all);
        if($goods['user_id'] > 0){
                $merchants_goods_comment = get_merchants_goods_comment($goods['user_id']); //商家所有商品评分类型汇总
                $smarty->assign('merch_cmt',  $merchants_goods_comment);
        }
        //评分 end

        //ecmoban模板堂 --zhuo start
        $shop_info = get_merchants_shop_info('merchants_steps_fields', $goods['user_id']);
        $adress = get_license_comp_adress($shop_info['license_comp_adress']);

        $smarty->assign('shop_info',       $shop_info);
        $smarty->assign('adress',       $adress);

        $province_list = get_warehouse_province();

        $smarty->assign('province_list',                $province_list); //省、直辖市

        $city_list = get_region_city_county($province_id);
        $smarty->assign('city_list',                $city_list); //省下级市

        $district_list = get_region_city_county($city_id);
        $smarty->assign('district_list',                $district_list);//市下级县

        $smarty->assign('goods_id',			$goods_id); //商品ID

        $warehouse_list = get_warehouse_list_goods();
        $smarty->assign('warehouse_list',			$warehouse_list); //仓库列

        $warehouse_name = get_warehouse_name_id($region_id);

        $smarty->assign('warehouse_name',			$warehouse_name); //仓库名称
        $smarty->assign('region_id',			$region_id); //商品仓库region_id

        $smarty->assign('user_id',			$_SESSION['user_id']);

        $smarty->assign('shop_price_type', $goods['model_price']); //商品价格运营模式 0代表统一价格（默认） 1、代表仓库价格 2、代表地区价格
        $smarty->assign('area_id', $area_id); //地区ID
        //ecmoban模板堂 --zhuo start 仓库

        //预约人数
        $pre_num = get_pre_num($goods_id);
        $smarty->assign('pre_num', $pre_num);

        /* 取得商品的规格 */
        $properties = get_goods_properties($goods_id);
        $smarty->assign('properties', $properties['pro']);    //商品属性
        $smarty->assign('specification', $properties['spe']); // 商品规格
        $smarty->assign('specscount',       count($properties['spe']));

        $smarty->assign('area_htmlType',  'presale');

        $smarty->assign('province_row',  get_region_info($province_id));
        $smarty->assign('city_row',  get_region_info($city_id));
        $smarty->assign('district_row',  get_region_info($district_id));

        //模板赋值
        $smarty->assign('cfg', $_CFG);
        $position = assign_ur_here($presale['cat_id'], $presale['goods_name'], array(), '', $presale['user_id']);

        $smarty->assign('page_title', $position['title']);    // 页面标题
        $smarty->assign('ur_here',    $position['ur_here']);  // 当前位置

        $smarty->assign('categories', get_categories_tree()); // 分类树
        $smarty->assign('helps',      get_shop_help());       // 网店帮助

		$smarty->assign('look_top', get_top_presale_goods($goods_id, $presale['pa_catid'])); // 看了又看
        //$smarty->assign('top_goods',  get_top10('', 'presale', 0, $region_id, $area_info['region_id']));           // 销售排行
        $smarty->assign('guess_goods',     get_guess_goods($user_id, 1, $page=1, 7,$region_id, $area_info['region_id']));         //猜你喜欢
        $smarty->assign('best_goods',      get_recommend_goods('best', '', $region_id, $area_info['region_id'], $goods['user_id'], 1, 'presale'));    // 推荐商品
        $smarty->assign('new_goods',       get_recommend_goods('new', '', $region_id, $area_info['region_id'], $goods['user_id'], 1, 'presale'));     // 最新商品
        $smarty->assign('hot_goods',       get_recommend_goods('hot', '', $region_id, $area_info['region_id'], $goods['user_id'], 1, 'presale'));     // 最新商品
        $smarty->assign('pictures',   get_goods_gallery($goods_id)); // 商品相册
        $smarty->assign('promotion_info', get_promotion_info());

        $all_count = get_discuss_type_count($goods_id); //帖子总数
        $GLOBALS['smarty']->assign('all_count', $all_count);

        //相关分类
        $goods_related_cat = get_goods_related_cat($presale['pa_catid']);
        $smarty->assign('goods_related_cat',       $goods_related_cat);
    }

	//关联商品
    $linked_goods = get_linked_goods($goods_id, $region_id, $area_info['region_id']);
	$smarty->assign('related_goods',       $linked_goods);

    //　详情部分 评分 start
    $comment_all = get_comments_percent($goods_id);

    if($goods['user_id'] > 0){
            $merchants_goods_comment = get_merchants_goods_comment($goods['user_id']); //商家所有商品评分类型汇总
    }
    $smarty->assign('comment_all',  $comment_all);

    /**
     * 店铺分类
     */
    if ($goods['user_id']) {
        $goods_store_cat = get_child_tree_pro(0, 0, 'merchants_category', 0, $goods['user_id']);

        if ($goods_store_cat) {
            $goods_store_cat = array_values($goods_store_cat);
        }

        $smarty->assign('goods_store_cat', $goods_store_cat);
    }

    $discuss_list = get_discuss_all_list($goods_id, 0, 1, 10);
    $smarty->assign('discuss_list',       $discuss_list);


    //更新商品点击次数
    $sql = 'UPDATE ' . $ecs->table('goods') . ' SET click_count = click_count + 1 '.
           "WHERE goods_id = '" . $group_buy['goods_id'] . "'";
    $db->query($sql);

    //@author guan start
    if ($_CFG['two_code']) {
        $group_buy_path = ROOT_PATH .IMAGE_DIR. "/presale_wenxin/";

        if (!file_exists($group_buy_path)) {
            make_dir($group_buy_path);
        }

        $logo = empty($_CFG['two_code_logo']) ? $goods['goods_img'] : str_replace('../', '', $_CFG['two_code_logo']);

        $size = '200x200';
        $url = $ecs->url();
        $two_code_links = trim($_CFG['two_code_links']);
        $two_code_links = empty($two_code_links) ? $url : $two_code_links;
        $data = $two_code_links . 'presale.php?act=view&id=' . $presale_id;
        $errorCorrectionLevel = 'H'; // 纠错级别：L、M、Q、H
        $matrixPointSize = 4; // 点的大小：1到10
        $filename = IMAGE_DIR . "/presale_wenxin/weixin_code_" . $goods['goods_id'] . ".png";

        QRcode::png($data, $filename, $errorCorrectionLevel, $matrixPointSize);

        $QR = imagecreatefrompng($filename);
        //$QR = imagecreatefrompng('./chart.png');//外面那QR图
        if ($logo !== FALSE) {
            $logo = imagecreatefromstring(file_get_contents($logo));

            $QR_width = imagesx($QR);
            $QR_height = imagesy($QR);

            $logo_width = imagesx($logo);
            $logo_height = imagesy($logo);

            // Scale logo to fit in the QR Code
            $logo_qr_width = $QR_width / 5;
            $scale = $logo_width / $logo_qr_width;
            $logo_qr_height = $logo_height / $scale;
            $from_width = ($QR_width - $logo_qr_width) / 2;
            //echo $from_width;exit;
            imagecopyresampled($QR, $logo, $from_width, $from_width, 0, 0, $logo_qr_width, $logo_qr_height, $logo_width, $logo_height);
        }

        imagepng($QR, $filename);
        imagedestroy($QR);
        $smarty->assign('weixin_img_url', $filename);
        $smarty->assign('weixin_img_text', trim($_CFG['two_code_mouse']));
        $smarty->assign('two_code', trim($_CFG['two_code']));
    }

    $smarty->assign('act_id',  $presale_id);
    $smarty->assign('now_time',  gmtime());           // 当前系统时间

    $smarty->assign('area_htmlType',       'presale');

    $basic_info = get_shop_info_content($goods['user_id']);

    $basic_date = array('region_name');
    $basic_info['province'] = get_table_date('region', "region_id = '" . $basic_info['province'] . "'", $basic_date, 2);
    $basic_info['city'] = get_table_date('region', "region_id= '" . $basic_info['city'] . "'", $basic_date, 2) . "市";

    /*  @author-bylu 判断当前商家是否允许"在线客服" start  */
    $shop_information = get_shop_name($goods['user_id']);//通过ru_id获取到店铺信息;
	$shop_information['kf_tel'] =$db->getOne("SELECT kf_tel FROM ".$ecs->table('seller_shopinfo')."WHERE ru_id = '".$goods['user_id']."'");

    //判断当前商家是平台,还是入驻商家 bylu
    if($goods_info['user_id'] == 0){
        //判断平台是否开启了IM在线客服
        if($db->getOne("SELECT kf_im_switch FROM ".$ecs->table('seller_shopinfo')."WHERE ru_id = 0")){
            $shop_information['is_dsc'] = true;
        }else{
            $shop_information['is_dsc'] = false;
        }
    }else{
        $shop_information['is_dsc'] = false;
    }
    $smarty->assign('shop_information',$shop_information);
    /*  @author-bylu  end  */

    $smarty->assign('basic_info',  $basic_info);

    $area = array(
        'region_id' => $region_id,  //仓库ID
        'province_id' => $province_id,
        'city_id' => $city_id,
        'district_id' => $district_id,
        'street_id' => $street_id,
        'street_list' => $street_list,
        'goods_id' => $goods_id,
        'user_id' => $user_id,
        'area_id' => $area_info['region_id'],
        'merchant_id' => $goods['user_id'],
    );

    $smarty->assign('area',  $area);

    if (!defined('THEME_EXTENSION')) {
        //商品运费
        $region = array(1, $province_id, $city_id, $district_id, $street_id, $street_list);
        $shippingFee = goodsShippingFee($goods_id, $region_id, $region);
        $smarty->assign('shippingFee', $shippingFee);
    }

    $smarty->display('presale_goods.dwt', $cache_id);
}
/*------------------------------------------------------ */
//-- 预售商品 --> 购买
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'buy2') {
    priv();

    /* 查询：判断是否登录 */
    if ($_SESSION['user_id'] <= 0)
    {
        show_message($_LANG['gb_error_login'], '', '', 'error');
    }

    include_once 'includes/cls_json.php';
    $json = new JSON();
    $result = array('error' => 0, 'message' => '', 'content' => '');

    $warehouse_id     = (isset($_REQUEST['warehouse_id'])) ? intval($_REQUEST['warehouse_id']) : 0;
    $area_id     = (isset($_REQUEST['area_id'])) ? intval($_REQUEST['area_id']) : 0; //仓库管理的地区ID

    /* 查询：取得参数：预约活动id */
    $presale_id = isset($_POST['act_id']) ? intval($_POST['act_id']) : 0;
    if ($presale_id <= 0)
    {
        exit($json->encode($result));
    }

    //获取团购信息
    $goods_id = get_table_date('presale_activity', 'act_id=\'' . $presale_id . '\'', array('goods_id'), 2);

    $properties = get_goods_properties($goods_id, $region_id, $area_id);  // 获得商品的规格和属性
    $specscount = count($properties['spe']);

    //获取商品总数
    if (0 < $specscount)
    {
        $attr_array = (empty($_REQUEST['attr_array']) ? array() : $_REQUEST['attr_array']);
        $num_array = (empty($_REQUEST['num_array']) ? array() : $_REQUEST['num_array']);
        $total_number = array_sum($num_array);
    }
    else
    {
        $goods_number = (empty($_REQUEST['goods_number']) ? 0 : intval($_REQUEST['goods_number']));
        $total_number = $goods_number;
    }

    /* 查询：取得预售活动信息 */
    $presale = presale_info($presale_id, $total_number, $user_id);
    if (empty($presale))
    {
        exit($json->encode($result));
    }

    /* 查询：检查预售活动是否是进行中 */
    if ($presale['status'] != GBS_UNDER_WAY)
    {
        show_message($_LANG['presale_error_status'], '', '', 'error');
    }

    /* 查询：取得预约商品信息 */
    $goods = goods_info($presale['goods_id'], $warehouse_id, $area_id);
    if (empty($goods))
    {
        ecs_header("Location: ./\n");
        exit;
    }

    //最小起批量
    if ($presale['moq'] && $total_number < $presale['moq']) {
//        show_message('您订购的商品少于最小起订量', '', '', 'error');
    }

    $start_date = 0;
    $end_date = $nowTime;
//    $order_goods = get_for_purchasing_goods($start_date, $end_date, $presale['goods_id'], $_SESSION['user_id'], 'presale');

    //by zxk
    $order_goods['goods_number'] = $presale['valid_goods'];
    $restrict_amount = $total_number + $order_goods['goods_number'];

    /* 查询：判断数量是否足够 */
    if($presale['restrict_amount'] > 0 && $restrict_amount > $presale['restrict_amount'])
    {
        show_message($_LANG['gb_error_restrict_amount'], '', '', 'error');
    }
    elseif ($presale['restrict_amount'] > 0 && ($total_number > ($presale['restrict_amount'] - $presale['valid_goods'])))
    {
        show_message($_LANG['gb_error_goods_lacking'], '', '', 'error');
    }

    //ecmoban模板堂 --zhuo start 限购
    $nowTime = gmtime();
    $start_date = $goods['xiangou_start_date'];
    $end_date = $goods['xiangou_end_date'];

    if ($goods['is_xiangou'] == 1 && $nowTime > $start_date && $nowTime < $end_date) {

        if ($presale['total_goods'] >= $goods['xiangou_num']) {
            $message = $presale['goods_name'] . " 商品您已购买达到上限";
            show_message($message, $_LANG['back_to_presale'], 'presale.php?id=' . $presale['act_id'] . '&act=view');
        } else {
            if ($goods['xiangou_num'] > 0) {
                if ($goods['is_xiangou'] == 1 && $presale['total_goods'] + $number > $goods['xiangou_num']) {
                    //可购买数量
                    $number = $goods['xiangou_num'] - $presale['total_goods'];
                }
            }
        }
    }
    //ecmoban模板堂 --zhuo end 限购
    $goods_price = $presale['cur_price'];

    /* 更新：清空进货单中所有团购商品 */
    include_once(ROOT_PATH . 'includes/lib_order.php');
    clear_cart(CART_PRESALE_GOODS);

    //ecmoban模板堂 --zhuo start
    $area_info = get_area_info($province_id);
    $area_id = $area_info['region_id'];

    $where = "regionId = '$province_id'";
    $date = array('parent_id');
    $region_id = get_table_date('region_warehouse', $where, $date, 2);

    if(!empty($_SESSION['user_id'])){
        $sess = "";
    }else{
        $sess = real_cart_mac_ip();
    }
    //ecmoban模板堂 --zhuo end

    $common_cart = array(
        'user_id'        => $_SESSION['user_id'],
        'session_id'     => $sess,
        'goods_id'       => $presale['goods_id'],
        'product_id'     => $product_info['product_id'],
        'goods_sn'       => addslashes($goods['goods_sn']),
        'goods_name'     => addslashes($goods['goods_name']),
        'market_price'   => $goods['market_price'],
        //ecmoban模板堂 --zhuo start
        'ru_id' => $goods['user_id'],
        'warehouse_id' => $region_id,
        'area_id' => $area_id,
        //ecmoban模板堂 --zhuo end
        'is_real'        => $goods['is_real'],
        'extension_code' => 'presale',
        'extension_id' => $presale['act_id'],
        'parent_id'      => 0,
        'rec_type'       => 0,
        'is_gift'        => 0,
        'freight' => $goods['freight'],
        'tid' => $goods['tid'],
    );

    $sess_id = ' user_id = \'' . $_SESSION['user_id'] . '\' ';

    $goods_price = $presale['cur_price'];

    if (0 < $specscount) {
        //商品规格存在
        foreach ($attr_array as $key => $val ) {
            $val = trim($val, ',');

            $specs = $val;
            $_specs = explode(',', $val);
            $product_info = get_products_info($goods['goods_id'], $_specs, $warehouse_id, $area_id);

            empty($product_info) ? $product_info = array('product_number' => 0, 'product_id' => 0) : '';

            if($goods['model_attr'] == 1){
                $table_products = "products_warehouse";
                $type_files = " and warehouse_id = '$warehouse_id'";
            }elseif($goods['model_attr'] == 2){
                $table_products = "products_area";
                $type_files = " and area_id = '$area_id'";
            }else{
                $table_products = "products";
                $type_files = "";
            }


            $sql = "SELECT * FROM " .$GLOBALS['ecs']->table($table_products). " WHERE goods_id = '" .$goods['goods_id']. "'" .$type_files. " LIMIT 0, 1";
            $prod = $GLOBALS['db']->getRow($sql);

            $number = $num_array[$key];

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
                $db->autoExecute($ecs->table('cart'), $cart, 'UPDATE', 'rec_id=\'' . $rec_id . '\'');
            }
            else
            {
                $db->autoExecute($ecs->table('cart'), $cart, 'INSERT');
            }
        }
    } else {
        $product_info = array('product_number' => 0, 'product_id' => 0);

        if($goods['model_attr'] == 1){
            $table_products = "products_warehouse";
            $type_files = " and warehouse_id = '$warehouse_id'";
        }elseif($goods['model_attr'] == 2){
            $table_products = "products_area";
            $type_files = " and area_id = '$area_id'";
        }else{
            $table_products = "products";
            $type_files = "";
        }

        $sql = "SELECT * FROM " .$GLOBALS['ecs']->table($table_products). " WHERE goods_id = '" .$goods['goods_id']. "'" .$type_files. " LIMIT 0, 1";
        $prod = $GLOBALS['db']->getRow($sql);

        /* 更新：加入进货单 */
        $cart = $common_cart;
        $cart['product_id'] = $product_info['product_id'];
        $cart['goods_price'] = $goods_price;
        $cart['goods_number'] = $goods_number;
        $cart['goods_attr'] = addslashes($goods_attr);
        $cart['goods_attr_id'] = $specs;

        $sql = ' SELECT rec_id FROM ' . $GLOBALS['ecs']->table('cart') . ' WHERE ' . $sess_id . ' AND goods_id = \'' . $goods_id . '\' ';
        $rec_id = $GLOBALS['db']->getOne($sql);
        if (!(empty($rec_id)))
        {
            $db->autoExecute($ecs->table('cart'), $cart, 'UPDATE', 'rec_id=\'' . $rec_id . '\'');
        }
        else
        {
            $db->autoExecute($ecs->table('cart'), $cart, 'INSERT');
        }
    }


    calculate_cart_goods_price($goods_id, '', 'presale', $presale['act_id']);
    $cart_info = insert_cart_info(1);
    $result['cart_num'] = $cart_info['number'];
    exit($json->encode($result));
}

/*------------------------------------------------------ */
//-- 预售商品 --> 购买
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'buy')
{
    priv();
    /* 查询：判断是否登录 */
    if ($_SESSION['user_id'] <= 0)
    {
        show_message($_LANG['gb_error_login'], '', '', 'error');
    }

    $warehouse_id     = (isset($_REQUEST['warehouse_id'])) ? intval($_REQUEST['warehouse_id']) : 0;
    $area_id     = (isset($_REQUEST['area_id'])) ? intval($_REQUEST['area_id']) : 0; //仓库管理的地区ID

    /* 查询：取得参数：预售活动id */
    $presale_id = isset($_POST['presale_id']) ? intval($_POST['presale_id']) : 0;
    if ($presale_id <= 0)
    {
        ecs_header("Location: ./\n");
        exit;
    }

    /* 查询：取得数量 */
    $number = isset($_POST['number']) ? intval($_POST['number']) : 1;
    $number = $number < 1 ? 1 : $number;

    /* 查询：取得预售活动信息 */
    $presale = presale_info($presale_id, $number, $user_id);
    if (empty($presale))
    {
        ecs_header("Location: ./\n");
        exit;
    }

    /* 查询：检查预售活动是否是进行中 */
    if ($presale['status'] != GBS_UNDER_WAY)
    {
        show_message($_LANG['presale_error_status'], '', '', 'error');
    }

    /* 查询：取得规格 */
    $specs = isset($_POST['goods_spec']) ? htmlspecialchars(trim($_POST['goods_spec'])) : '';

    $attr_id = !empty($specs) ? explode(',', $specs) : '';
    /* 查询：取得预售商品信息 */
    $goods = goods_info($presale['goods_id'], $warehouse_id, $area_id, array(), $attr_id);

    if (empty($goods))
    {
        ecs_header("Location: ./\n");
        exit;
    }

    /* 查询：如果商品有规格则取规格商品信息 配件除外 */
    if ($specs)
    {
        $_specs = explode(',', $specs);
        $product_info = get_products_info($goods['goods_id'], $_specs, $warehouse_id, $area_id);
    }

    empty($product_info) ? $product_info = array('product_number' => 0, 'product_id' => 0) : '';

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

    /* 更新：清空进货单中所有预售商品 */
    include_once(ROOT_PATH . 'includes/lib_order.php');
    clear_cart(CART_PRESALE_GOODS);

    //ecmoban模板堂 --zhuo start
    $area_id = $area_info['region_id'];

    $where = "regionId = '$province_id'";
    $date = array('parent_id');
    $region_id = get_table_date('region_warehouse', $where, $date, 2);

    if(!empty($_SESSION['user_id'])){
            $sess = "";
    }else{
            $sess = real_cart_mac_ip();
    }
    //ecmoban模板堂 --zhuo end

    //ecmoban模板堂 --zhuo start 限购
    $nowTime = gmtime();
    $start_date = $goods['xiangou_start_date'];
    $end_date = $goods['xiangou_end_date'];

    if ($goods['is_xiangou'] == 1 && $nowTime > $start_date && $nowTime < $end_date) {

        if ($presale['total_goods'] >= $goods['xiangou_num']) {
            $message = $presale['goods_name'] . " 商品您已购买达到上限";
            show_message($message, $_LANG['back_to_presale'], 'presale.php?id=' . $presale['act_id'] . '&act=view');
        } else {
            if ($goods['xiangou_num'] > 0) {
                if ($goods['is_xiangou'] == 1 && $presale['total_goods'] + $number > $goods['xiangou_num']) {
                    //可购买数量
                    $number = $goods['xiangou_num'] - $presale['total_goods'];
                }
            }
        }
    }
    //ecmoban模板堂 --zhuo end 限购

    /* 更新：加入进货单 */
    $cart = array(
        'user_id'        => $_SESSION['user_id'],
        'session_id'     => $sess,
        'goods_id'       => $presale['goods_id'],
        'product_id'     => $product_info['product_id'],
        'goods_sn'       => addslashes($goods['goods_sn']),
        'goods_name'     => addslashes($goods['goods_name']),
        'market_price'   => $goods['market_price'],
        'goods_price'    => get_final_price($presale['goods_id']),
        'goods_number'   => $number,
        'goods_attr'     => addslashes($goods_attr),
        'goods_attr_id'  => $specs,
        //ecmoban模板堂 --zhuo start
        'ru_id'          => $goods['user_id'],
        'warehouse_id'   => $region_id,
        'area_id'        => $area_id,
        //ecmoban模板堂 --zhuo end
        'is_real'        => $goods['is_real'],
        'extension_code' => 'presale',
        'parent_id'      => 0,
        'rec_type'       => 0,
        'is_gift'        => 0
    );

    $db->autoExecute($ecs->table('cart'), $cart, 'INSERT');

    /* 更新：记录购物流程类型：预售 */
    $_SESSION['flow_type'] = CART_PRESALE_GOODS;
    $_SESSION['extension_code'] = 'presale';
    $_SESSION['extension_id'] = $presale['act_id'];

    /* 进入收货人页面 */
    $_SESSION['browse_trace'] = "presale";
    ecs_header("Location: ./flow.php?step=checkout\n");
    exit;
}

/**
 * 取得某页的所有预售商品
 *
 */
function get_pre_goods($cat_id, $min=0, $max=0, $start_time=0, $end_time=0, $sort, $status=0, $order='desc', $brand=0)
{
    //$children = get_children($cat_id);

    $now = gmtime();
    $where = '';
    if ($cat_id > 0)
    {
        $children = get_children($cat_id);

        $where = " AND ".$children;
    }

    if ($brand)
    {
        if (stripos($brand,",")) {
            $where .= " AND g.brand_id in (".$brand.")";
        } else {
            $where .= " AND g.brand_id = '$brand'";
        }
    }

    //1未开始，2进行中，3结束
    if ($status == 1)
    {
        $where .= " AND a.start_time > $now ";
    }
    elseif ($status == 2)
    {
        $where .= " AND a.start_time < $now AND $now < a.end_time ";
    }
    elseif ($status == 3)
    {
        $where .= " AND $now > a.end_time ";
    }

    if ($sort == 'shop_price')
    {
        $sort = "g.$sort";
    }  else
    {
        $sort = "a.$sort";
    }

    $sql = "SELECT a.*, g.goods_thumb, g.goods_img, g.goods_name, g.shop_price, g.market_price, g.sales_volume FROM ".$GLOBALS['ecs']->table('presale_activity')." AS a "
                . " LEFT JOIN ".$GLOBALS['ecs']->table('goods')." AS g ON a.goods_id = g.goods_id "
                . " WHERE g.goods_id > 0 $where AND a.review_status = 3 ORDER BY $sort $order";

     $res =  $GLOBALS['db']->getAll($sql);
     foreach ($res as $key => $row)
    {
        $res[$key]['goods_name'] = $row['goods_name'];	// !empty($row['act_name']) ? $row['act_name'] : $row['goods_name']
        $res[$key]['thumb'] = get_image_path($row['goods_id'], $row['goods_thumb'], true);
        $res[$key]['goods_img'] = get_image_path($row['goods_id'], $row['goods_img']);
        $res[$key]['url'] = build_uri('presale', array('act' => 'view', 'presaleid' => $row['act_id']));

        $res[$key]['end_time_date'] = local_date('Y-m-d H:i:s', $row['end_time']);
        $res[$key]['start_time_date'] = local_date('Y-m-d H:i:s', $row['start_time']);

        if ($row['start_time'] >= $now )
        {
            $res[$key]['no_start'] = 1;
        }
        if ($row['end_time'] <= $now )
        {
            $res[$key]['already_over'] = 1;
        }

        $stat = presale_stat($row['act_id'], $row['deposit']);
        $res[$key]['cur_amount'] = $stat['valid_goods'];         // 当前数量

        $res[$key]['is_end']     = $now > $row['end_time'] ? 1 : 0 ;
        $ext_info = unserialize($row['ext_info']);
        $res[$key] = array_merge($res[$key], $ext_info);
    }

    return $res;
}

/*预售商品详情页预约人数*/
function get_pre_num($goods_id)
{
	$sql = "SELECT pre_num FROM " . $GLOBALS['ecs']->table('presale_activity') . " WHERE goods_id='$goods_id'";
	$res = $GLOBALS['db']->getOne($sql);
	return $res;
}

/**
 * 获得预售分类商品
 *
 */
function get_pre_cat()
{
    $sql = "SELECT * FROM ".$GLOBALS['ecs']->table('presale_cat')." ORDER BY sort_order ASC ";
    $cat_res = $GLOBALS['db']->getAll($sql);

    foreach ($cat_res as $key => $row)
    {
        $cat_res[$key]['goods'] = get_cat_goods($row['cat_id'], $row['act_id']);
        $cat_res[$key]['count_goods'] = count(get_cat_goods($row['cat_id']));
        $cat_res[$key]['cat_url'] = build_uri('presale', array('act' => 'category', 'cid' => $row['cat_id']), $row['cat_name']);
    }
    return $cat_res;
}


// 获取分类下商品并进行分组
function get_cat_goods($cat_id)
{
    $now = gmtime();
    $sql = "SELECT a.*, g.goods_thumb, g.goods_img, g.goods_name, g.shop_price, g.market_price, g.sales_volume, s.* FROM ".$GLOBALS['ecs']->table('presale_activity')." AS a "
                . " LEFT JOIN ".$GLOBALS['ecs']->table('goods')." AS g ON a.goods_id = g.goods_id "
				. " LEFT JOIN ".$GLOBALS['ecs']->table('seller_shopinfo')." AS s ON a.user_id = s.ru_id "
                . "WHERE a.cat_id = '$cat_id' AND a.review_status = 3 ";

    $res = $GLOBALS['db']->getAll($sql);
    foreach ($res as $key => $row)
    {
        $res[$key]['goods_name'] = $row['goods_name'];	//!empty($row['act_name']) ? $row['act_name'] : $row['goods_name']
        $res[$key]['thumb'] = get_image_path($row['goods_id'], $row['goods_thumb'], true);
        $res[$key]['goods_img'] = get_image_path($row['goods_id'], $row['goods_img']);
        $res[$key]['url'] = build_uri('presale', array('act' => 'view', 'presaleid' => $row['act_id']), $row['goods_name']);

        $res[$key]['shop_url'] = build_uri('merchants_index', array('merchant_id' => $row['ru_id']), $row['shop_name']); //by yx

        $res[$key]['end_time_date'] = local_date('Y-m-d H:i:s', $row['end_time']);
        $res[$key]['start_time_date'] = local_date('Y-m-d H:i:s', $row['start_time']);

        if ($row['start_time'] >= $now) {
            $res[$key]['no_start'] = 1;
        }
        if ($row['end_time'] <= $now) {
            $res[$key]['already_over'] = 1;
        }
    }

    return $res;
}

// 获取预售导航信息
function get_pre_nav()
{
    $sql = "SELECT * FROM ".$GLOBALS['ecs']->table('presale_cat')." WHERE parent_id = 0 ORDER BY sort_order ASC LIMIT 7 ";
    $res = $GLOBALS['db']->getAll($sql);

    foreach($res as $key=>$row){
        $res[$key]['cat_id'] = $row['cat_id'];
        $res[$key]['cat_name'] = $row['cat_name'];
        $res[$key]['url'] = build_uri('presale', array('act' => 'category', 'cid' => $row['cat_id']), $row['cat_name']);
    }

    return $res;
}

/*
 * 查询商品是否预售
 * 是，则返回预售结束时间
 */
function get_presale_time($goods_id){
    $sql = "SELECT act_id, pay_end_time FROM " .$GLOBALS['ecs']->table('presale_activity'). " WHERE goods_id = '$goods_id' AND review_status = 3 LIMIT 1";
    $res = $GLOBALS['db']->getRow($sql);

    if($res['pay_end_time']){
        $res['pay_end_time'] = local_date($GLOBALS['_CFG']['time_format'], $res['pay_end_time']);

        if($res['pay_end_time']){
            $pay_end_time = explode(" ", $res['pay_end_time']);
            $atthe = explode(":", $pay_end_time[1]);
            $res['str_time'] = $pay_end_time[0] ." ". $atthe[0] . ":" . $atthe[1];
        }else{
            $res['str_time'] = $res['pay_end_time'];
        }
    }

    return $res;
}

/**
 * 获得指定商品的关联商品
 *
 * @access  public
 * @param   integer     $goods_id
 * @return  array
 */
function get_linked_goods($goods_id, $warehouse_id = 0, $area_id = 0)
{
	//ecmoban模板堂 --zhuo start
        $where = '';
	$leftJoin = '';

	$shop_price = "wg.warehouse_price, wg.warehouse_promote_price, wag.region_price, wag.region_promote_price, g.model_price, g.model_attr, ";
	$leftJoin .= " left join " .$GLOBALS['ecs']->table('warehouse_goods'). " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
	$leftJoin .= " left join " .$GLOBALS['ecs']->table('warehouse_area_goods'). " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";

        if($GLOBALS['_CFG']['open_area_goods'] == 1){
            $leftJoin .= " left join " .$GLOBALS['ecs']->table('link_area_goods'). " as lag on g.goods_id = lag.goods_id ";
            $where .= " and lag.region_id = '$area_id' ";
        }
	//ecmoban模板堂 --zhuo end

        $sql = 'SELECT g.goods_id, g.goods_name, g.goods_thumb, g.goods_img, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) AS org_price, ' .
            "IFNULL(IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]'), g.shop_price * '$_SESSION[discount]')  AS shop_price, " .
            'IFNULL(IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)), g.promote_price) AS promote_price, ' .
            ' g.promote_start_date, g.promote_end_date, g.market_price, g.sales_volume, g.model_attr, g.product_price, g.product_promote_price ' .
            'FROM ' . $GLOBALS['ecs']->table('link_goods') . ' lg ' .
            'LEFT JOIN ' . $GLOBALS['ecs']->table('goods') . ' AS g ON g.goods_id = lg.link_goods_id ' .
            "LEFT JOIN " . $GLOBALS['ecs']->table('member_price') . " AS mp " .
            "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
            $leftJoin .
            "WHERE lg.goods_id = '$goods_id' AND g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 " .
            $where .
            "LIMIT " . $GLOBALS['_CFG']['related_goods_number'];
    $res = $GLOBALS['db']->query($sql);
    $arr = array();
    while ($row = $GLOBALS['db']->fetchRow($res))
    {
        if ($row['promote_price'] > 0)
        {
            $promote_price = bargain_price($row['promote_price'], $row['promote_start_date'], $row['promote_end_date']);
        }
        else
        {
            $promote_price = 0;
        }

        /**
         * 重定义商品价格
         * 商品价格 + 属性价格
         * start
         */
        $price_info = get_goods_one_attr_price($row, $warehouse_id, $area_id, $promote_price);
        $row = !empty($row) ? array_merge($row, $price_info) : $row;
        $promote_price = $row['promote_price'];
        /**
         * 重定义商品价格
         * end
         */

        $arr[$row['goods_id']]['goods_id']     = $row['goods_id'];
        $arr[$row['goods_id']]['goods_name']   = $row['goods_name'];
        $arr[$row['goods_id']]['short_name']   = $GLOBALS['_CFG']['goods_name_length'] > 0 ?
            sub_str($row['goods_name'], $GLOBALS['_CFG']['goods_name_length']) : $row['goods_name'];
        $arr[$row['goods_id']]['goods_thumb']  = get_image_path($row['goods_id'], $row['goods_thumb'], true);
        $arr[$row['goods_id']]['goods_img']    = get_image_path($row['goods_id'], $row['goods_img']);
        $arr[$row['goods_id']]['market_price'] = price_format($row['market_price']);
        $arr[$row['goods_id']]['shop_price']   = price_format($row['shop_price']);
        $arr[$row['goods_id']]['promote_price']    = ($promote_price > 0) ? price_format($promote_price) : '';
        $arr[$row['goods_id']]['url']          = build_uri('goods', array('gid'=>$row['goods_id']), $row['goods_name']);
        $arr[$row['goods_id']]['sales_volume'] = $row['sales_volume'];
    }
    return $arr;
}

/**
 * 获取商品ajax属性是否都选中
 */
function get_goods_attr_ajax($goods_id, $goods_attr, $goods_attr_id){

    $arr = array();
    $arr['attr_id'] = '';
    $where = "";
    if($goods_attr){

        $goods_attr = implode(",", $goods_attr);
        $where .= " AND ga.attr_id IN($goods_attr)";

        if($goods_attr_id){
            $goods_attr_id = implode(",", $goods_attr_id);
            $where .= " AND ga.goods_attr_id IN($goods_attr_id)";
        }

        $sql = "SELECT ga.goods_attr_id, ga.attr_id, ga.attr_value  FROM " .$GLOBALS['ecs']->table('goods_attr') ." AS ga".
                " LEFT JOIN " . $GLOBALS['ecs']->table('attribute') ." AS a ON ga.attr_id = a.attr_id ".
                " WHERE  ga.goods_id = '$goods_id' $where AND a.attr_type > 0 ORDER BY a.sort_order, a.attr_id, ga.goods_attr_id";
        $res = $GLOBALS['db']->getAll($sql);

        foreach($res as $key=>$row){
            $arr[$row['attr_id']][$row['goods_attr_id']] = $row;

            $arr['attr_id'] .= $row['attr_id'] . ",";
        }

        if($arr['attr_id']){
            $arr['attr_id'] = substr($arr['attr_id'], 0, -1);
            $arr['attr_id'] = explode(",", $arr['attr_id']);
        }else{
            $arr['attr_id'] = array();
        }
    }

    return $arr;
}

/*
 * 相关分类
 */
function get_goods_related_cat($cat_id){
    $sql = "SELECT parent_id FROM " .$GLOBALS['ecs']->table('presale_cat'). " WHERE cat_id = '$cat_id'";
    $res = $GLOBALS['db']->getOne($sql, true);

    $sql = "SELECT cat_id, cat_name FROM " .$GLOBALS['ecs']->table('presale_cat'). " WHERE parent_id = '" .$res. "'";
    $res = $GLOBALS['db']->getAll($sql);

    foreach($res as $key=>$row){
        $res[$key]['cat_id'] = $row['cat_id'];
        $res[$key]['cat_name'] = $row['cat_name'];
        $res[$key]['url'] = build_uri('presale', array('act' => 'category', 'cid' => $row['cat_id']), $row['cat_name']);
    }

    return $res;
}

//分类（新品、抢先订）
function get_pre_category($act = 'new', $status = 0){

    $sql = "SELECT cat_id, cat_name FROM ".$GLOBALS['ecs']->table('presale_cat')." WHERE parent_id = 0 ORDER BY sort_order ASC ";
    $res = $GLOBALS['db']->getAll($sql);

    foreach($res as $key=>$row){
        $res[$key]['cat_id'] = $row['cat_id'];
        $res[$key]['cat_name'] = $row['cat_name'];
        $res[$key]['url'] = build_uri('presale', array('act' => $act, 'cid' => $row['cat_id'], 'status' => $status), $row['cat_name']);
    }

    return $res;
}

//预售链接
function get_presale_url($act, $cat_id, $status, $cat_name){
    return build_uri('presale', array('act' => $act, 'cid' => $cat_id, 'status' => $status), $cat_name);
}

// 预售看了又看
function get_top_presale_goods($goods_id, $cat_id)
{
    $now = gmtime();
    $sql = "SELECT a.*, g.goods_thumb, g.goods_img, g.goods_name, g.shop_price, g.market_price, g.sales_volume, s.* FROM ".$GLOBALS['ecs']->table('presale_activity')." AS a "
                . " LEFT JOIN ".$GLOBALS['ecs']->table('goods')." AS g ON a.goods_id = g.goods_id "
				. " LEFT JOIN ".$GLOBALS['ecs']->table('seller_shopinfo')." AS s ON a.user_id = s.ru_id "
                . "WHERE a.cat_id = '$cat_id' AND a.review_status = 3 AND a.start_time <= '$now' AND a.end_time >= '$now' AND g.goods_id <> '$goods_id' ORDER BY g.click_count DESC LIMIT 5 ";

    $res = $GLOBALS['db']->getAll($sql);
	if($res){
		foreach ($res as $key => $row)
		{
			$res[$key]['goods_name'] = $row['goods_name'];	//!empty($row['act_name']) ? $row['act_name'] : $row['goods_name']
			$res[$key]['shop_price'] = price_format($res[$key]['shop_price']);
			$res[$key]['thumb'] = get_image_path($row['goods_id'], $row['goods_thumb'], true);
			$res[$key]['goods_img'] = get_image_path($row['goods_id'], $row['goods_img']);
			$res[$key]['url'] = build_uri('presale', array('act' => 'view', 'presaleid' => $row['act_id']), $row['goods_name']);
		}
		return $res;
	}

}

function get_main_attr_list($goods_id = 0, $attr = array())
{
    $sql = ' SELECT DISTINCT attr_id FROM ' . $GLOBALS['ecs']->table('goods_attr') . ' WHERE goods_id = \'' . $goods_id . '\'';
    $attr_ids = $GLOBALS['db']->getCol($sql);
    if (!(empty($attr_ids)))
    {
        $attr_ids = implode(',', $attr_ids);
        $sort_order = ' ORDER BY sort_order DESC, attr_id DESC ';

        //单一属性bug
        $sql = ' SELECT attr_id FROM ' . $GLOBALS['ecs']->table('attribute') . ' WHERE  attr_type > 0 AND attr_id IN (' . $attr_ids . ') ' . $sort_order . ' LIMIT 1 ';
        $attr_id = $GLOBALS['db']->getOne($sql);
        $sql = ' SELECT goods_attr_id, attr_value FROM ' . $GLOBALS['ecs']->table('goods_attr') . ' WHERE goods_id = \'' . $goods_id . '\' AND attr_id = \'' . $attr_id . '\' ORDER BY goods_attr_id ';
        $data = $GLOBALS['db']->getAll($sql);

        if ($data) {
            foreach ($data as $key => $val) {
                $new_arr = array_merge($attr, array($val['goods_attr_id']));
                $data[$key]['attr_group'] = implode(',', $new_arr);
            }

            return $data;
        }

    }
    return false;
}

function get_select_record_data($goods_id = 0, $attr_num_array = array())
{
    $new_array = array();
    foreach ($attr_num_array as $key => $val )
    {
        $arr = explode(',', $val['attr']);
        $end_attr = end($arr);
        array_pop($arr);
        $attr_key = implode(',', $arr);
        $new_array[$attr_key][$end_attr] = $val['num'];
    }
    $record_data = array();
    foreach ($new_array as $key => $val )
    {
        $data = array();
        $data['main_attr'] = get_goods_attr_array($key);
        foreach ($val as $k => $v )
        {
            $a = array();
            $a['attr_num'] = $v;
            $b = get_goods_attr_array($k);
            $c = $b[0];
            $a = array_merge($a, $c);
            $data['end_attr'][] = $a;
        }
        $record_data[$key] = $data;
    }
    return $record_data;
}
?>
