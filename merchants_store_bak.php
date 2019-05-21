<?php

/**
 * DSC 购物流程
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

/* 过滤 XSS 攻击和SQL注入 */
get_request_filter();

// qq登录
if (isset($_GET['code']) && !empty($_GET['code'])) {

    $oath_where = '';
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        $oath_where .= "&user_id=" . $_SESSION['user_id'];
        $oath_where .= "&jump=account_bind";
    }

    $redirect_url = $ecs->url() . 'user.php?act=oath_login&type=qq&code=' . $_GET['code'] . $oath_where;
    header('location:' . $redirect_url);
    exit;
}

require(dirname(__FILE__) . '/includes/phpqrcode/phpqrcode.php'); //by wu
require(ROOT_PATH . '/includes/lib_visual.php');

if ((DEBUG_MODE & 2) != 2)
{
    $smarty->caching = true;
}

require(ROOT_PATH . 'includes/lib_area.php');  //ecmoban模板堂 --zhuo

define('IN_ECS', true);

$seller_domain = get_seller_domain();

if($seller_domain){
    $merchant_id = $seller_domain['ru_id'];
    $smarty->assign('is_jsonp',    1);
}else{
    $merchant_id = isset($_REQUEST['merchant_id']) ? intval($_REQUEST['merchant_id']) : 0;
    $smarty->assign('is_jsonp',    0);
}

$ua = strtolower($_SERVER['HTTP_USER_AGENT']);

$uachar = "/(nokia|sony|ericsson|mot|samsung|sgh|lg|philips|panasonic|alcatel|lenovo|cldc|midp|mobile)/i";

if(($ua == '' || preg_match($uachar, $ua))&& !strpos(strtolower($_SERVER['REQUEST_URI']),'wap'))
{
    $Loaction = 'mobile/index.php?m=store&a=shop_info&id='.$merchant_id;

    if (!empty($Loaction))
    {
        ecs_header("Location: $Loaction\n");

        exit;
    }
}

/* 初始化分页信息 */
$page = isset($_REQUEST['page'])   && intval($_REQUEST['page'])  > 0 ? intval($_REQUEST['page'])  : 1;
$size = isset($_CFG['page_size'])  && intval($_CFG['page_size']) > 0 ? intval($_CFG['page_size']) : 10;
$brand = isset($_REQUEST['brand']) && intval($_REQUEST['brand']) > 0 ? intval($_REQUEST['brand']) : 0;
$price_max = isset($_REQUEST['price_max']) && intval($_REQUEST['price_max']) > 0 ? intval($_REQUEST['price_max']) : 0;
$price_min = isset($_REQUEST['price_min']) && intval($_REQUEST['price_min']) > 0 ? intval($_REQUEST['price_min']) : 0;
$filter_attr_str = isset($_REQUEST['filter_attr']) ? htmlspecialchars(trim($_REQUEST['filter_attr'])) : '0';

$filter_attr_str = trim(urldecode($filter_attr_str));
$filter_attr_str = preg_match('/^[\d\.]+$/',$filter_attr_str) ? $filter_attr_str : '';
$filter_attr = empty($filter_attr_str) ? '' : explode('.', $filter_attr_str);

/*模板名称*/
$tem = isset($_REQUEST['tem']) ? addslashes($_REQUEST['tem']) : '';

//正则去掉js代码
$preg = "/<script[\s\S]*?<\/script>/i";

/* 排序、显示方式以及类型 */
$default_display_type = $_CFG['show_order_type'] == '0' ? 'list' : ($_CFG['show_order_type'] == '1' ? 'grid' : 'text');
$default_sort_order_method = $_CFG['sort_order_method'] == '0' ? 'DESC' : 'ASC';
$default_sort_order_type   = $_CFG['sort_order_type'] == '0' ? 'goods_id' : ($_CFG['sort_order_type'] == '1' ? 'shop_price' : 'last_update');

$sort  = (isset($_REQUEST['sort'])  && in_array(trim(strtolower($_REQUEST['sort'])), array('goods_id', 'shop_price', 'last_update','sales_volume','comments_number'))) ? trim($_REQUEST['sort'])  : $default_sort_order_type;
$order = (isset($_REQUEST['order']) && in_array(trim(strtoupper($_REQUEST['order'])), array('ASC', 'DESC')))                              ? trim($_REQUEST['order']) : $default_sort_order_method;

$display  = isset($_REQUEST['display']) ? strtolower($_REQUEST['display']) : '';
$display = !empty($display)?preg_replace($preg,"",stripslashes($display)):'';
$keyword = trim($_REQUEST['keyword']) ? trim($_REQUEST['keyword']) : '';
$keyword = htmlspecialchars($keyword);
$user_id = isset($_SESSION['user_id'])? $_SESSION['user_id'] : 0;
$temp_code = isset($_REQUEST['temp_code']) ? trim($_REQUEST['temp_code']) : '';
$cat_id = 0;
if(isset($_REQUEST['id']) && !empty($_REQUEST['id'])){
    $cat_id = intval($_REQUEST['id']);
}elseif(isset($_REQUEST['cat_id']) && !empty($_REQUEST['cat_id'])){
    $cat_id = intval($_REQUEST['cat_id']);
}

//商家不存则跳转回首页
$shop_date = array('shop_id');
$shop_where = "user_id = '$merchant_id'";
$shop_id = get_table_date('merchants_shop_information', $shop_where, $shop_date);

if(($merchant_id == 0 || $shop_id < 1) && $temp_code == ''){
    header("Location: index.php\n");
    exit;
}

//判断是否已经关注
$sql = "SELECT rec_id FROM " . $ecs->table('collect_store') . " WHERE user_id = '$user_id' AND ru_id = '$merchant_id' ";
$rec_id = $db->getOne($sql);

//商品模式
$mode     = (isset($_REQUEST['mode'])) ? trim($_REQUEST['mode']) : '';
if (!in_array($mode, ['group_buy', 'presale', 'wholesale', 'sample'])) {
    $mode = '';
}
$smarty->assign('mode', $mode);


//ecmoban模板堂 --zhuo start 仓库
if (!empty($_REQUEST['act']) && $_REQUEST['act'] == 'in_warehouse') {

    include('includes/cls_json.php');

    $json = new JSON;
    $res = array('err_msg' => '', 'result' => '', 'qty' => 1);

    clear_cache_files();

    setcookie('region_id', $pid, gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
    setcookie('regionId', $pid, gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);

    $area_region = 0;
    setcookie('area_region', $area_region, gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);

    $res['goods_id'] = $goods_id;

    $json = new JSON;
    die($json->encode($res));
} elseif (!empty($_REQUEST['act']) && $_REQUEST['act'] == 'in_stock') {

    include('includes/cls_json.php');

    $json = new JSON;
    $res = array('err_msg' => '', 'result' => '', 'qty' => 1);

    clear_cache_files();

    if (!isset($_COOKIE['province'])) {
        $area_array = get_ip_area_name();

        if ($area_array['county_level'] == 2) {
            $date = array('region_id', 'parent_id', 'region_name');
            $where = "region_name = '" . $area_array['area_name'] . "' AND region_type = 2";
            $city_info = get_table_date('region', $where, $date, 1);

            $date = array('region_id', 'region_name');
            $where = "region_id = '" . $city_info[0]['parent_id'] . "'";
            $province_info = get_table_date('region', $where, $date);

            $where = "parent_id = '" . $city_info[0]['region_id'] . "' order by region_id asc limit 0, 1";
            $district_info = get_table_date('region', $where, $date, 1);
        } elseif ($area_array['county_level'] == 1) {
            $area_name = $area_array['area_name'];

            $date = array('region_id', 'region_name');
            $where = "region_name = '$area_name'";
            $province_info = get_table_date('region', $where, $date);

            $where = "parent_id = '" . $province_info['region_id'] . "' order by region_id asc limit 0, 1";
            $city_info = get_table_date('region', $where, $date, 1);

            $where = "parent_id = '" . $city_info[0]['region_id'] . "' order by region_id asc limit 0, 1";
            $district_info = get_table_date('region', $where, $date, 1);
        }
    }

    $goods_id = empty($_GET['id']) ? 0 : $_GET['id'];
    $province = empty($_GET['province']) ? $province_info['region_id'] : $_GET['province'];
    $city = empty($_GET['city']) ? $city_info[0]['region_id'] : $_GET['city'];
    $district = empty($_GET['district']) ? $district_info[0]['region_id'] : $_GET['district'];
    $d_null = empty($_GET['d_null']) ? 0 : $_GET['d_null'];
    $user_id = empty($_GET['user_id']) ? 0 : $_GET['user_id'];

    $user_address = get_user_address_region($user_id);
    $user_address = explode(",", $user_address['region_address']);

    setcookie('province', $province, gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
    setcookie('city', $city, gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);

    setcookie('district', $district, gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);

    $regionId = 0;
    setcookie('regionId', $regionId, gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);

    $res['d_null'] = $d_null;

    if ($d_null == 0) {
        if (in_array($district, $user_address)) {
            $res['isRegion'] = 1;
        } else {
            $res['message'] = $_LANG['region_message'];
            $res['isRegion'] = 88; //原为0
        }
    } else {
        setcookie('district', '', gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
    }

    $res['goods_id'] = $goods_id;

    die($json->encode($res));
} elseif (!empty($_REQUEST['act']) && $_REQUEST['act'] == 'ajax_collect_store') { //Ajax取消/关注
    //修改 by tong
    include_once('includes/cls_json.php');

    $json = new JSON;
    $res = array('err_msg' => '', 'result' => '', 'error' => 0);

    $type = isset($_REQUEST['type']) ? intval($_REQUEST['type']) : 0;
    $merchant_id = isset($_REQUEST['merchant_id']) ? intval($_REQUEST['merchant_id']) : 0;
    $execute = isset($_REQUEST['execute']) ? intval($_REQUEST['execute']) : 0;

    if ((isset($_SESSION['user_id']) && $_SESSION['user_id'] < 1) || !isset($_SESSION['user_id'])) {
        $res['error'] = 2;
    } else {
        if ($execute == 1) {
            // 弹出提示
            if ($type == 0 || $type == 1) {
                $res['error'] = 3;
            } else if ($type == 2) {
                if ($rec_id < 1) {
                    $res['error'] = 3;
                } else {
                    $res['error'] = 1;
                }
            }
        } else {
            //取消关注
            if ($type == 0 || $type == 1) {
                if (!empty($merchant_id)) {
                    $sql = "DELETE FROM " . $ecs->table('collect_store') . " WHERE ru_id in($merchant_id)";
                    $db->query($sql);
                }
            }
            //添加关注
            if ($rec_id < 1) {
                $is_attention = 1;
                $sql = "INSERT INTO " . $ecs->table('collect_store') . "(`user_id`, `ru_id`, `add_time`, `is_attention`)VALUES('$user_id', '$merchant_id', '" . gmtime() . "', '$is_attention')";
                $db->query($sql);
            }
        }
    }

    $res['type'] = $type;
    $res['merchant_id'] = $merchant_id;

    die($json->encode($res));
}

$smarty->assign("rec_id",$rec_id);
$smarty->assign("collect_store",$rec_id);
//判断商家使用模板
if($temp_code){
    $templates_mode = 1;
    $tem = $temp_code;
}else{
    $sql = "SELECT templates_mode FROM".$ecs->table("seller_shopinfo")." WHERE ru_id = '$merchant_id'";
    $templates_mode = $db->getOne($sql);
}
//if($templates_mode == 1 && $cat_id == 0 && $display == ''&& $brand == 0 && $filter_attr=='' && $keyword == ''&& $price_max=='' && $price_min == ''){
//    require(ROOT_PATH . 'preview.php');
//    exit;
//}

#需要查询的IP start

if (!isset($_COOKIE['province'])) {
    $area_array = get_ip_area_name();

    if ($area_array['county_level'] == 2) {
        $date = array('region_id', 'parent_id', 'region_name');
        $where = "region_name = '" . $area_array['area_name'] . "' AND region_type = 2";
        $city_info = get_table_date('region', $where, $date, 1);

        $date = array('region_id', 'region_name');
        $where = "region_id = '" . $city_info[0]['parent_id'] . "'";
        $province_info = get_table_date('region', $where, $date);

        $where = "parent_id = '" . $city_info[0]['region_id'] . "' order by region_id asc limit 0, 1";
        $district_info = get_table_date('region', $where, $date, 1);
    } elseif ($area_array['county_level'] == 1) {
        $area_name = $area_array['area_name'];

        $date = array('region_id', 'region_name');
        $where = "region_name = '$area_name'";
        $province_info = get_table_date('region', $where, $date);

        $where = "parent_id = '" . $province_info['region_id'] . "' order by region_id asc limit 0, 1";
        $city_info = get_table_date('region', $where, $date, 1);

        $where = "parent_id = '" . $city_info[0]['region_id'] . "' order by region_id asc limit 0, 1";
        $district_info = get_table_date('region', $where, $date, 1);
    }
}
#需要查询的IP end

$order_area = get_user_order_area($user_id);
$user_area = get_user_area_reg($user_id); //2014-02-25

if ($order_area['province'] && $user_id > 0) {
    $province_id = $order_area['province'];
} else {
    if ($user_area['province'] > 0) {
        $province_id = $user_area['province'];
        setcookie('province', $user_area['province'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
        $region_id = get_province_id_warehouse($province_id);
    } else {

        $sql = "select region_name from " . $ecs->table('region_warehouse') . " where regionId = '" . $province_info['region_id'] . "'";
        $warehouse_name = $db->getOne($sql);

        $province_id = $province_info['region_id'];
        $cangku_name = $warehouse_name;
        $region_id = get_warehouse_name_id(0, $cangku_name);
    }
}

if ($order_area['province'] && $user_id > 0) {
    $city_id = $order_area['city'];
} else {
    if ($user_area['city'] > 0) {
        $city_id = $user_area['city'];
        setcookie('city', $user_area['city'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
    } else {
        $city_id = $city_info[0]['region_id'];
    }
}

if ($order_area['province'] && $user_id > 0) {
    $district_id = $order_area['district'];
} else {
    if ($user_area['district'] > 0) {
        $district_id = $user_area['district'];
        setcookie('district', $user_area['district'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
    } else {
        $district_id = $district_info[0]['region_id'];
    }
}

$province_id = isset($_COOKIE['province']) ? $_COOKIE['province'] : $province_id;

$child_num = get_region_child_num($province_id);
if ($child_num > 0) {
    $city_id = isset($_COOKIE['city']) ? $_COOKIE['city'] : $city_id;
} else {
    $city_id = '';
}

$child_num = get_region_child_num($city_id);
if ($child_num > 0) {
    $district_id = isset($_COOKIE['district']) ? $_COOKIE['district'] : $district_id;
} else {
    $district_id = '';
}

$region_id = !isset($_COOKIE['region_id']) ? $region_id : $_COOKIE['region_id'];

$goods_warehouse = get_warehouse_goods_region($province_id); //查询用户选择的配送地址所属仓库
if ($goods_warehouse) {
    $regionId = $goods_warehouse['region_id'];
    if ($_COOKIE['region_id'] && $_COOKIE['regionId']) {
        $gw = 0;
    } else {
        $gw = 1;
    }
}
if ($gw) {
    $region_id = $regionId;
    setcookie('area_region', $region_id, gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
}

setcookie('goodsId', $goods_id, gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);

//ecmoban模板堂 --zhuo end 仓库

for ($i = 1; $i <= $_CFG['auction_ad']; $i++) {
    $ad_arr .= "'users_a" . $i . ",";
}

$smarty->assign('adarr',       $ad_arr); // 分类广告位

for($i=1;$i<=$_CFG['auction_ad'];$i++){
        $adarr_bott .= "'users_b".$i.",";
}

$smarty->assign('adarr_bott',       $adarr_bott); // 分类广告位
 

$shop_name = get_shop_name($merchant_id, 1); //店铺名称	
$grade_info = get_seller_grade($merchant_id); //等级信息
$store_conut = get_merchants_store_info($merchant_id);
$store_info = get_merchants_store_info($merchant_id, 1);

$is_dwt = 0;
if ($cat_id > 0) {
    $is_dwt = 1;
} elseif ($display != '') {
    $is_dwt = 1;
} elseif ($brand > 0) {
    $is_dwt = 1;
} elseif ($filter_attr != '') {
    $is_dwt = 1;
} elseif ($keyword != '') {
    $is_dwt = 1;
} elseif ($price_max != '') {
    $is_dwt = 1;
} elseif ($price_min != '') {
    $is_dwt = 1;
} elseif ($cat_id > 0 && $sort != '') {
    $is_dwt = 1;
}

$is_cache = 1;
if($store_conut > 0 && !empty($store_info['seller_theme'])){
    if($is_dwt == 1){
        $dwt = 'merchants_store.dwt';
    }else{
        
        $id_name = '_'.$merchant_id."',";
	$str_ad = str_replace(',',$id_name,$ad_arr);
	$in_ad_arr = substr($str_ad,0,strlen($str_ad)-1);
        
        $ad_child = get_ad_posti_child($in_ad_arr);

	$smarty->assign('ad_child', $ad_child);     
        
	$shopheader = get_store_header($merchant_id, $store_info['seller_theme']);
        
	$header_content = $shopheader['content'];
	$smarty->assign('header_content',       $header_content); // 头部内容
	$smarty->assign('shopheader',       $shopheader); // 头部信息
	
	$store_bg = get_store_bg($merchant_id, $store_info['seller_theme']);
	$smarty->assign('store_bg',       $store_bg); // 店铺背景
	
	$smarty->assign('store',       $store_info); // 店铺背景
	
        $is_cache = 0;
	$dwt = $store_info['seller_theme'].'/seller_store.dwt';
    }
}else{
    if($is_dwt == 1){
        $dwt = 'merchants_store.dwt';
    }else{
        $dwt = 'merchants_store.dwt';
    }
}

if($is_cache){
    $cache_id = sprintf('%X', crc32($cat_id . '-' . $merchant_id . '-' . $display . '-' . $sort  .'-' . $order  .'-' . $page . '-' . $size . '-' . $_SESSION['user_rank'] . '-' .
                $_CFG['lang'] .'-'. $brand. '-' . $price_max . '-' .$price_min . '-' . $filter_attr_str.'-'.$keyword.'-'.$mode));
    $not = '';
}else{
    $cache_id = '';
    $not = 'not';
}

if (true || !$smarty->is_cached($dwt, $cache_id))
{
    assign_template('',array(),$merchant_id);

    $smarty->assign('merchant_id',       $merchant_id); // 商家ID
    $smarty->assign('cat_id',       $cat_id); // 分类ID

    $parent_id = category_store_parent($cat_id);
    $smarty->assign('parent_id',       $parent_id); // 分类父级ID

    //logo start
    $cat['name'] = $cat['cat_name'];
    $cat['id'] = $cat['cat_id'];
    $smarty->assign('cat',        $cat);
    //logo end

    //筛选 start
    $cat = get_store_cat_info($cat_id);   // 获得分类的相关信息
    
    //ecmoban模板堂 --zhuo start
    if($cat_id == 0){
        $cat_where = " g.user_id = '$merchant_id' AND ";
        $children = '';
    }else{
        $children = get_children($cat_id, 0, 0, 'merchants_category', "g.user_cat");
        $cat_where = "$children AND ";
    }
    //ecmoban模板堂 --zhuo end

    //地区ID
    $province_list = get_warehouse_province();

    $smarty->assign('province_list',                $province_list); //省、直辖市

    $city_list = get_region_city_county($province_id);
    $smarty->assign('city_list',                $city_list); //省下级市

    $district_list = get_region_city_county($city_id); 
    $smarty->assign('district_list',                $district_list);//市下级县

    $smarty->assign('province_row',  get_region_info($province_id));  
    $smarty->assign('city_row',  get_region_info($city_id));  
    $smarty->assign('district_row',  get_region_info($district_id));
    $smarty->assign('user_id',  $user_id);

    $smarty->assign('region_id',  $region_id); //仓库
    $area_info = get_area_info($province_id);
    $smarty->assign('area_id',  $area_info['region_id']); //仓库管理的地区ID
    //地区ID

    if ($cat_id > 0) {


        if (!empty($cat)) {
            $smarty->assign('keywords', htmlspecialchars($cat['keywords']));
            $smarty->assign('description', htmlspecialchars($cat['cat_desc']));
            $smarty->assign('cat_style', htmlspecialchars($cat['style']));
        } else {
            /* 如果分类不存在则返回首页 */
            ecs_header("Location: ./\n");

            exit;
        }

        /* 赋值固定内容 */
        $brand_info = get_brand_info($brand);
        $brand_name = !empty($brand_info) ? $brand_info['brand_name'] : '';

        /* 获取价格分级 */
        if ($cat['grade'] == 0 && $cat['parent_id'] != 0) {
            $cat['grade'] = get_store_parent_grade($cat_id); //如果当前分类级别为空，取最近的上级分类
        }

        if ($cat['grade'] > 1) {
            /* 需要价格分级 */

            /*
              算法思路：
              1、当分级大于1时，进行价格分级
              2、取出该类下商品价格的最大值、最小值
              3、根据商品价格的最大值来计算商品价格的分级数量级：
              价格范围(不含最大值)    分级数量级
              0-0.1                   0.001
              0.1-1                   0.01
              1-10                    0.1
              10-100                  1
              100-1000                10
              1000-10000              100
              4、计算价格跨度：
              取整((最大值-最小值) / (价格分级数) / 数量级) * 数量级
              5、根据价格跨度计算价格范围区间
              6、查询数据库

              可能存在问题：
              1、
              由于价格跨度是由最大值、最小值计算出来的
              然后再通过价格跨度来确定显示时的价格范围区间
              所以可能会存在价格分级数量不正确的问题
              该问题没有证明
              2、
              当价格=最大值时，分级会多出来，已被证明存在
             */

            //ecmoban模板堂 --zhuo start
            $leftJoin = '';

            $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_goods') . " as wg on g.goods_id = wg.goods_id and wg.region_id = '$region_id' ";
            $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_area_goods') . " as wag on g.goods_id = wag.goods_id and wag.region_id = '" . $area_info['region_id'] . "'";
            //ecmoban模板堂 --zhuo end	

            $sql = "SELECT min(IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price))) AS min, " .
                    " max(IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price))) as max " .
                    " FROM " . $ecs->table('goods') . " AS g " . $leftJoin .
                    " WHERE $cat_where g.is_delete = 0 AND g.is_on_sale = 1 AND g.is_alone_sale = 1  ";
            //获得当前分类下商品价格的最大值、最小值

            $row = $db->getRow($sql);

            // 取得价格分级最小单位级数，比如，千元商品最小以100为级数
            $price_grade = 0.0001;
            for ($i = -2; $i <= log10($row['max']); $i++) {
                $price_grade *= 10;
            }

            //跨度
            $dx = ceil(($row['max'] - $row['min']) / ($cat['grade']) / $price_grade) * $price_grade;
            if ($dx == 0) {
                $dx = $price_grade;
            }

            for ($i = 1; $row['min'] > $dx * $i; $i ++)
                ;

            for ($j = 1; $row['min'] > $dx * ($i - 1) + $price_grade * $j; $j++)
                ;
            $row['min'] = $dx * ($i - 1) + $price_grade * ($j - 1);

            for (; $row['max'] >= $dx * $i; $i ++)
                ;
            $row['max'] = $dx * ($i) + $price_grade * ($j - 1);

            $sql = "SELECT (FLOOR((IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) - $row[min]) / $dx)) AS sn, COUNT(*) AS goods_num  " .
                    " FROM " . $ecs->table('goods') . " AS g " . $leftJoin .
                    " WHERE $cat_where g.is_delete = 0 AND g.is_on_sale = 1 AND g.is_alone_sale = 1 " .
                    " GROUP BY sn ";

            $price_grade = $db->getAll($sql);

            foreach ($price_grade as $key => $val) {
                $temp_key = $key + 1;
                $price_grade[$temp_key]['goods_num'] = $val['goods_num'];
                $price_grade[$temp_key]['start'] = $row['min'] + round($dx * $val['sn']);
                $price_grade[$temp_key]['end'] = $row['min'] + round($dx * ($val['sn'] + 1));
                $price_grade[$temp_key]['price_range'] = $price_grade[$temp_key]['start'] . '&nbsp;-&nbsp;' . $price_grade[$temp_key]['end'];
                $price_grade[$temp_key]['formated_start'] = price_format($price_grade[$temp_key]['start']);
                $price_grade[$temp_key]['formated_end'] = price_format($price_grade[$temp_key]['end']);

                $build_uri = array(
                    'cid' => $cat_id,
                    'urid' => $merchant_id,
                    'append' => $cat['cat_name'],
                    'brand_id' => $brand,
                    'price_min' => $price_grade[$temp_key]['start'],
                    'price_max' => $price_grade[$temp_key]['end'],
                    'filter_attr' => $filter_attr_str
                );

                $domain_url = get_seller_domain_url($merchant_id, $build_uri);
                $price_grade[$temp_key]['url'] = $domain_url['domain_name'];
                /* 判断价格区间是否被选中 */

                if (isset($_REQUEST['price_min']) && $price_grade[$temp_key]['start'] == $price_min && $price_grade[$temp_key]['end'] == $price_max) {
                    $price_grade[$temp_key]['selected'] = 1;
                } else {
                    $price_grade[$temp_key]['selected'] = 0;
                }
            }

            $price_grade[0]['start'] = 0;
            $price_grade[0]['end'] = 0;
            $price_grade[0]['price_range'] = $_LANG['all_attribute'];

            $build_uri = array(
                'cid' => $cat_id,
                'urid' => $merchant_id,
                'append' => $cat['cat_name'],
                'brand_id' => $brand,
                'price_min' => 0,
                'price_max' => 0,
                'filter_attr' => $filter_attr_str
            );

            $domain_url = get_seller_domain_url($merchant_id, $build_uri);
            $price_grade[0]['url'] = $domain_url['domain_name'];
            $price_grade[0]['selected'] = empty($price_max) ? 1 : 0;

            $smarty->assign('price_grade', $price_grade);
        }

        /* 品牌筛选 */
        $sql = "SELECT b.* FROM " . $GLOBALS['ecs']->table('brand') . " AS b " .
                " LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON b.brand_id = g.brand_id " .
                " WHERE $cat_where b.audit_status = 1 GROUP BY b.brand_id";

        $brands = $GLOBALS['db']->getAll($sql);

        foreach ($brands AS $key => $val) {
            $temp_key = $key + 1;
            $brands[$temp_key]['brand_name'] = $val['brand_name'];
            $brands[$temp_key]['brand_id'] = $val['brand_id'];

            $build_uri = array(
                'cid' => $cat_id,
                'urid' => $merchant_id,
                'append' => $cat['cat_name'],
                'brand_id' => $val['brand_id'],
                'price_min' => $price_min,
                'price_max' => $price_max,
                'filter_attr' => $filter_attr_str
            );

            $domain_url = get_seller_domain_url($merchant_id, $build_uri);
            $brands[$temp_key]['url'] = $domain_url['domain_name'];
            
            /* 判断品牌是否被选中 */
            if ($brand == $brands[$temp_key]['brand_id']) {
                $brands[$temp_key]['selected'] = 1;
            } else {
                $brands[$temp_key]['selected'] = 0;
            }
        }

        $brands[0]['brand_name'] = $_LANG['all_attribute'];

        $build_uri = array(
            'cid' => $cat_id,
            'urid' => $merchant_id,
            'append' => $cat['cat_name'],
            'brand_id' => 0,
            'price_min' => $price_min,
            'price_max' => $price_max,
            'filter_attr' => $filter_attr_str
        );

        $domain_url = get_seller_domain_url($merchant_id, $build_uri);
        $brands[0]['url'] = $domain_url['domain_name'];
        $brands[0]['selected'] = empty($brand) ? 1 : 0;
        
        $smarty->assign('brands', $brands);

        /* 属性筛选 */
        $ext = ''; //商品查询条件扩展
        if ($cat['filter_attr'] > 0) {
            $cat_filter_attr = explode(',', $cat['filter_attr']);       //提取出此分类的筛选属性
            $all_attr_list = array();

            foreach ($cat_filter_attr AS $key => $value) {
                $sql = "SELECT a.attr_name FROM " . $ecs->table('attribute') . " AS a, " . $ecs->table('goods_attr') . " AS ga, " . $ecs->table('goods') . " AS g " .
                        "WHERE $cat_where a.attr_id = ga.attr_id AND g.goods_id = ga.goods_id AND g.is_delete = 0 AND g.is_on_sale = 1 AND g.is_alone_sale = 1 AND a.attr_id = '$value'";

                if ($temp_name = $db->getOne($sql)) {
                    $all_attr_list[$key]['filter_attr_name'] = $temp_name;

                    $sql = "SELECT a.attr_id, MIN(a.goods_attr_id ) AS goods_id, a.attr_value AS attr_value FROM " . $ecs->table('goods_attr') . " AS a, " . $ecs->table('goods') .
                            " AS g" .
                            " WHERE $cat_where g.goods_id = a.goods_id AND g.is_delete = 0 AND g.is_on_sale = 1 AND g.is_alone_sale = 1 " .
                            " AND a.attr_id = '$value' " .
                            " GROUP BY a.attr_value";

                    $attr_list = $db->getAll($sql);

                    $temp_arrt_url_arr = array();

                    for ($i = 0; $i < count($cat_filter_attr); $i++) {        //获取当前url中已选择属性的值，并保留在数组中
                        $temp_arrt_url_arr[$i] = !empty($filter_attr[$i]) ? $filter_attr[$i] : 0;
                    }

                    $temp_arrt_url_arr[$key] = 0;                           //“全部”的信息生成
                    $temp_arrt_url = implode('.', $temp_arrt_url_arr);
                    $all_attr_list[$key]['attr_list'][0]['attr_value'] = $_LANG['all_attribute'];

                    $build_uri = array(
                        'cid' => $cat_id,
                        'urid' => $merchant_id,
                        'append' => $cat['cat_name'],
                        'brand_id' => $brand,
                        'price_min' => $price_min,
                        'price_max' => $price_max,
                        'filter_attr' => $temp_arrt_url
                    );

                    $domain_url = get_seller_domain_url($merchant_id, $build_uri);
                    $all_attr_list[$key]['attr_list'][0]['url'] = $domain_url['domain_name'];
                    $all_attr_list[$key]['attr_list'][0]['selected'] = empty($filter_attr[$key]) ? 1 : 0;

                    foreach ($attr_list as $k => $v) {
                        $temp_key = $k + 1;
                        $temp_arrt_url_arr[$key] = $v['goods_id'];       //为url中代表当前筛选属性的位置变量赋值,并生成以‘.’分隔的筛选属性字符串
                        $temp_arrt_url = implode('.', $temp_arrt_url_arr);

                        $all_attr_list[$key]['attr_list'][$temp_key]['attr_value'] = $v['attr_value'];

                        $build_uri = array(
                            'cid' => $cat_id,
                            'urid' => $merchant_id,
                            'append' => $cat['cat_name'],
                            'brand_id' => $brand,
                            'price_min' => $price_min,
                            'price_max' => $price_max,
                            'filter_attr' => $temp_arrt_url
                        );

                        $domain_url = get_seller_domain_url($merchant_id, $build_uri);
                        $all_attr_list[$key]['attr_list'][$temp_key]['url'] = $domain_url['domain_name'];

                        if (!empty($filter_attr[$key]) AND $filter_attr[$key] == $v['goods_id']) {
                            $all_attr_list[$key]['attr_list'][$temp_key]['selected'] = 1;
                        } else {
                            $all_attr_list[$key]['attr_list'][$temp_key]['selected'] = 0;
                        }
                    }
                }
            }

            $smarty->assign('filter_attr_list', $all_attr_list);
            // 扩展商品查询条件
            if (!empty($filter_attr)) {
                $ext_sql = "SELECT DISTINCT(b.goods_id) FROM " . $ecs->table('goods_attr') . " AS a, " . $ecs->table('goods_attr') . " AS b " . "WHERE ";
                $ext_group_goods = array();

                foreach ($filter_attr AS $k => $v) {
                    if (is_numeric($v) && $v != 0 && isset($cat_filter_attr[$k])) {
                        $sql = $ext_sql . "b.attr_value = a.attr_value AND b.attr_id = " . $cat_filter_attr[$k] . " AND a.goods_attr_id = " . $v;
                        $ext_group_goods = $db->getColCached($sql);
                        $ext .= ' AND ' . db_create_in($ext_group_goods, 'g.goods_id');
                    }
                }
            }
        }
        //筛选 end
    }
    $cat_name = '';
    if($cat_id > 0){
        $sql = "SELECT cat_name FROM".$ecs->table('merchants_category')."WHERE cat_id = '$cat_id'";
        $cat_name = "-".$db->getOne($sql);
    }
    $position = assign_ur_here(0, $shop_name.$cat_name);
    
    $position = assign_ur_here(0, $shop_name);
    $smarty->assign('page_title',      $position['title']);    // 页面标题
    $smarty->assign('ur_here',         $position['ur_here']);  // 当前位置
    $smarty->assign('helps',           get_shop_help());       // 网店帮助
    
    if ($dwt == 'merchants_store.dwt') {
        if (defined('THEME_EXTENSION')) {
            $categories_pro = get_category_tree_leve_one();
            $smarty->assign('categories_pro', $categories_pro); // 分类树加强版
        }
    } else {
        $categories_pro = get_category_tree_leve_one();
        $smarty->assign('categories_pro', $categories_pro); // 分类树加强版
    }

    $smarty->assign('keyword',    htmlspecialchars($keyword));
    $smarty->assign('price_min',    htmlspecialchars($price_min));
    $smarty->assign('price_max',    htmlspecialchars($price_max));

    $count = get_store_goods_count($children, $brand, $price_min, $price_max, $ext, $merchant_id, $region_id, $area_info['region_id'], $keyword);
    $max_page = ($count> 0) ? ceil($count / $size) : 1; 
    if ($page > $max_page)
    {
        $page = $max_page;
    }

    //判断是否传参数
//    if ($mode) {
//        $list = store_goods_list($children, $brand, $price_min, $price_max, $ext, 1000, $page, $sort, $order, $merchant_id, $region_id, $area_info['region_id'], $keyword, $mode);
//        switch($mode) {
//            case 'group_buy':
//                $group_buy_list = $list;
//                break;
//            case 'presale':
//                $presale_list = $list;
//                break;
//            case 'sample':
//                $sample_list = $list;
//                break;
//            case 'wholesale':
//                $wholesale_list = $list;
//                break;
//        }
//    } else {
//        $group_buy_list = store_goods_list($children, $brand, $price_min, $price_max, $ext, 1000, $page, $sort, $order, $merchant_id, $region_id, $area_info['region_id'], $keyword, 'group_buy');
//        $sample_list = store_goods_list($children, $brand, $price_min, $price_max, $ext, 1000, $page, $sort, $order, $merchant_id, $region_id, $area_info['region_id'], $keyword, 'sample');
//        $presale_list = store_goods_list($children, $brand, $price_min, $price_max, $ext, 1000, $page, $sort, $order, $merchant_id, $region_id, $area_info['region_id'], $keyword, 'presale');
//        $wholesale_list = store_goods_list($children, $brand, $price_min, $price_max, $ext, 1000, $page, $sort, $order, $merchant_id, $region_id, $area_info['region_id'], $keyword, 'wholesale');
//
//    }

    $goods_list = store_goods_list($children, $brand, $price_min, $price_max, $ext, 1000, $page, $sort, $order, $merchant_id, $region_id, $area_info['region_id'], $keyword);

    $smarty->assign('group_buy_list',       $group_buy_list);
    $smarty->assign('sample_list',       $sample_list);
    $smarty->assign('presale_list',       $presale_list);
    $smarty->assign('wholesale_list',       $wholesale_list);

    $smarty->assign('goods_list',       $goods_list);
    $smarty->assign('script_name',       'merchants_store');
    $smarty->assign('category',         $cat_id);
    $smarty->assign('count',         $count);
    
    if($merchant_id > 0){
        $goods_hot = 'g.store_hot = 1';
        $goods_new = 'g.store_new = 1';       
    }
    if (defined('THEME_EXTENSION')){
        $collect_store = 0;
       if($_SESSION['user_id'] > 0){
           $sql = "SELECT rec_id FROM " . $GLOBALS['ecs']->table('collect_store') . " WHERE user_id = '" . $_SESSION['user_id'] . "' AND ru_id = '" .$merchant_id . "' ";
           $collect_store = $GLOBALS['db']->getOne($sql);
       }
       $smarty->assign('collect_store',  $collect_store);
   }
    $goods_hot = get_hot_new_best_goods($children, $goods_hot, $merchant_id, $region_id, $area_info['region_id']);
    $smarty->assign('goods_hot',         $goods_hot); //热销商品

    $goods_new = get_hot_new_best_goods($children, $goods_new, $merchant_id, $region_id, $area_info['region_id']);
    $smarty->assign('goods_new',         $goods_new); //新品推荐

    assign_pager('merchants_store',            $cat_id, $count, $size, $sort, $order, $page, '', $brand, $price_min, $price_max, $display, $filter_attr_str, '', '', $merchant_id, $keyword); // 分页
	
    /* 页面中的动态内容 */
    assign_dynamic('merchants_store');
}

if($merchant_id > 0){
	$merchants_goods_comment = get_merchants_goods_comment($merchant_id); //商家所有商品评分类型汇总
}

$smarty->assign('merch_cmt',  $merchants_goods_comment); 

$store_category = get_user_store_category($merchant_id); //店铺导航栏
$smarty->assign('store_category',         $store_category);

//商家二维码 by wu start
$sql="select ss.*,sq.*, msf.license_fileImg from ".$ecs->table('seller_shopinfo')." as ss ".
	" left join".$ecs->table('seller_qrcode')." as sq on sq.ru_id=ss.ru_id ".
	" left join".$ecs->table('merchants_steps_fields')." as msf on msf.user_id = ss.ru_id ".
	" where ss.ru_id='$merchant_id'";
$basic_info = $db->getRow($sql);

$logo = str_replace('../', '',$basic_info['qrcode_thumb']);
$size = '155x155';
$url = $ecs->url();
//$data = $url."qrurl.php?type=seller&id=".$merchant_id;
$data = $url."mobile/index.php?r=store/index/shop_info&id=".$merchant_id;
$errorCorrectionLevel = 'Q'; // 纠错级别：L、M、Q、H
$matrixPointSize = 4; // 点的大小：1到10
$filename = "seller_imgs/seller_qrcode/seller_qrcode_" . $merchant_id . ".png";
QRcode::png($data, $filename, $errorCorrectionLevel, $matrixPointSize);
$QR = imagecreatefrompng($filename);
if($logo !== FALSE)
{
        $logo = imagecreatefromstring(file_get_contents($logo));
        $QR_width = imagesx($QR);
        $QR_height = imagesy($QR);		
        $logo_width = imagesx($logo);
        $logo_height = imagesy($logo);			
        // Scale logo to fit in the QR Code
        $logo_qr_width = $QR_width/5;
        $scale = $logo_width/$logo_qr_width;
        $logo_qr_height = $logo_height/$scale;
        $from_width = ($QR_width-$logo_qr_width)/2;
        //echo $from_width;exit;
        imagecopyresampled($QR, $logo, $from_width, $from_width, 0, 0, $logo_qr_width, $logo_qr_height, $logo_width, $logo_height);			
}
imagepng($QR, $filename);
imagedestroy($QR);
$smarty->assign('seller_qrcode_img', $filename);
$smarty->assign('seller_qrcode_text', $basic_info['shop_name']);
//商家二维码 by wu end
$basic_info['shop_logo'] = str_replace('../', '', $basic_info['shop_logo']);

//二维码 by yan xin end
$basic_info['qrcode_thumb'] = str_replace('../', '', $basic_info['qrcode_thumb']);//二维码

//OSS文件存储ecmoban模板堂 --zhuo start
if($GLOBALS['_CFG']['open_oss'] == 1 && $basic_info['shop_logo']){
    $bucket_info = get_bucket_info();
    $basic_info['shop_logo'] = $bucket_info['endpoint'] . $basic_info['shop_logo'];
}else{
    $basic_info['shop_logo'] = $_CFG['site_domain'] . $basic_info['shop_logo'];
}
//OSS文件存储ecmoban模板堂 --zhuo end    

if ($GLOBALS['_CFG']['customer_service'] == 0) {
    $im_merchant_id = 0;
}else{
    $im_merchant_id = $merchant_id;
}

/*  @author-bylu 判断当前商家是否允许"在线客服" start  */
$shop_information = get_shop_name($im_merchant_id);
$shop_information['kf_tel'] =$db->getOne("SELECT kf_tel FROM ".$ecs->table('seller_shopinfo')."WHERE ru_id = '$im_merchant_id'");
//判断当前商家是平台,还是入驻商家 bylu
if($im_merchant_id == 0){
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

/*处理客服QQ数组 by kong*/
if($basic_info['kf_qq']){
    $kf_qq=array_filter(preg_split('/\s+/', $basic_info['kf_qq']));
    $kf_qq=explode("|",$kf_qq[0]);
    if(!empty($kf_qq[1])){
        $basic_info['kf_qq'] = $kf_qq[1];
    }else{
       $basic_info['kf_qq'] = ""; 
    }
    
}else{
    $basic_info['kf_qq'] = "";
}
/*处理客服旺旺数组 by kong*/
if($basic_info['kf_ww']){
    $kf_ww=array_filter(preg_split('/\s+/', $basic_info['kf_ww']));
    $kf_ww=explode("|",$kf_ww[0]);
    if(!empty($kf_ww[1])){
        $basic_info['kf_ww'] = $kf_ww[1];
    }else{
        $basic_info['kf_ww'] ="";
    }
    
}else{
    $basic_info['kf_ww'] ="";
}

$smarty->assign('basic_info',         $basic_info);  //店铺详细信息

$sql = "select parent_id from " . $ecs->table('merchants_category') . " where cat_id = '$cat_id'";
$parent_id = $db->getOne($sql);

if ($parent_id == 0) {
    $cat_id = 0;
}

$banner_list = get_store_banner_list($merchant_id, $store_info['seller_theme']); //店铺首页轮播图
$smarty->assign('banner_list',          $banner_list); 

$win_list = get_store_win_list($merchant_id, $region_id, $area_info['region_id'], $store_info['seller_theme']); //店铺橱窗
$smarty->assign('win_list',          $win_list);

if (!defined('THEME_EXTENSION')) {
    $suspension_two = get_template_js(array('suspension-two'));
    $smarty->assign('suspension_two', $suspension_two);
}

$smarty->assign('site_domain',          $_CFG['site_domain']);  //网站域名
$smarty->assign('shop_name',         $shop_name);  
$smarty->assign('grade_info',$grade_info);

$build_uri = array(
    'urid' => $merchant_id,
    'append' => $shop_name
);

$domain_url = get_seller_domain_url($merchant_id, $build_uri);
$merchants_url = $domain_url['domain_name'];
$smarty->assign('merchants_url',          $merchants_url);  //网站域名

$all_cat_list = cat_list(0, 1, 0, 'merchants_category', array(), 0, $merchant_id);
$smarty->assign('all_cat_list',  $all_cat_list);
$cat_list = cat_list($cat_id, 1, 0, 'merchants_category', array(), 0, $merchant_id);
$smarty->assign('cat_store_list',  $cat_list);

$smarty->assign('filename', 'merchants_store');

$smarty->display($dwt, '', $not);


function store_goods_list($children, $brand, $min, $max, $ext, $size, $page, $sort, $order, $merchant_id, $warehouse_id = 0, $area_id = 0, $keyword, $mode = '') {
    //ecmoban模板堂 --zhuo start
    $now = gmtime();
    $where = 1;

    if ($children == '') {
        $where .= " AND g.user_id = '$merchant_id' ";
    } else {
        $where .= " AND g.user_id = '$merchant_id' "." AND $children ";
    }

    $display = $GLOBALS['display'];
    $where .= " AND g.is_delete = 0 ";

    if ($brand > 0) {
        $where .= "AND g.brand_id = $brand ";
    }

    //ecmoban模板堂 --zhuo start
    if ($GLOBALS['_CFG']['review_goods'] == 1) {
        $where .= ' AND b.review_status > 2 ';
    }

    $fields = "";
    $join = '';
    switch($mode) {
        case 'group_buy':
            $table = $GLOBALS['ecs']->table('goods_activity');
            $where .= ' AND b.act_type = '.GAT_GROUP_BUY;
            $where .= ' AND b.start_time <= ' . $now;
            $where .= ' AND b.is_finished < 3';
            $fields .= ',b.start_time AS start_date, b.end_time AS end_date ';
            break;
        case 'presale':
            $table = $GLOBALS['ecs']->table('presale_activity');
            $where .= ' AND b.is_finished < 3';
            break;
        case 'sample':
            $table = $GLOBALS['ecs']->table('sample_activity');
            break;
        case 'wholesale':
            $table = $GLOBALS['ecs']->table('wholesale');
            $fields .= ',MIN(wvp.volume_price) AS volume_price ';
            $join .= ' LEFT JOIN ' . $GLOBALS['ecs']->table('wholesale_volume_price') . ' AS wvp ON wvp.goods_id = g.goods_id';
            break;
    }

    if (!$table) {
        return;
    }


    $sql = "select * from (SELECT b.*, IFNULL(g.goods_thumb, '') AS goods_thumb,g.shop_price,g.market_price".$fields.
        " FROM " . $table . " AS b " .
        " inner JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON b.goods_id = g.goods_id " .
        $join .
        " WHERE " . $where  . " ORDER BY b.act_id desc  limit 0,10000) as bb GROUP BY bb.goods_id ";

    $res = $GLOBALS['db']->selectLimit($sql, $size, ($page - 1) * $size);

    $list = [];
    while ($row = $GLOBALS['db']->fetchRow($res)) {
        if (!$row['act_id']) {
            continue;
        }
        if (in_array($mode, ['group_buy', 'presale'])) {
            $ext_info = unserialize($row['ext_info']);
            $row = array_merge($row, $ext_info);

            /* 处理价格阶梯 */
            $price_ladder = $row['price_ladder'];
            if (!is_array($price_ladder) || empty($price_ladder))
            {
                $price_ladder = array(array('amount' => 0, 'price' => 0));
            }
            else
            {
                foreach ($price_ladder as $key => $amount_price)
                {
                    $price_ladder[$key]['formated_price'] = price_format($amount_price['price']);
                }
            }

            $row['price_ladder'] = $price_ladder;

            $price    = $row['market_price']; //原价
            $nowprice = $row['price_ladder'][0]['price']; //现价

            $row['volume_price'] = $row['price_ladder'][0]['price'];

        }

        $stat = activity_stat($row['act_id'], $mode);
        $row['cur_amount'] = $stat['total_goods'];

        //商品图片
        $row['goods_thumb'] = get_image_path($row['goods_id'], $row['goods_thumb'], true);

        $mode = $mode == 'wholesale' ? 'wholesale_goods' : $mode;
        $row['url'] = build_uri($mode, ['acid'=>$row['act_id'], 'aid'=>$row['act_id'], 'gbid' => $row['act_id'],'id'=>$row['act_id'], 'presaleid'=>$row['act_id'], 'act'=>'view']);;

        $list[] = $row;
    }
    return $list;
}

/*------------------------------------------------------ */
//-- PRIVATE FUNCTION
/*------------------------------------------------------ */

/**
 * 获得分类下的商品
 *
 * @access  public
 * @param   string  $children
 * @return  array
 */
function store_get_goods($children, $brand, $min, $max, $ext, $size, $page, $sort, $order, $merchant_id, $warehouse_id = 0, $area_id = 0, $keyword, $mode = '') {
    //ecmoban模板堂 --zhuo start
    if ($children == '') {
        $cat_where = " AND g.user_id = '$merchant_id' ";
    } else {
        $cat_where = " AND $children ";
    }

    $display = $GLOBALS['display'];
    $where = "g.is_on_sale = 1 AND g.is_alone_sale = 1 AND " .
            "g.is_delete = 0 $cat_where";

    if ($brand > 0) {
        $where .= "AND g.brand_id = $brand ";
    }



    //ecmoban模板堂 --zhuo start
    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_goods') . " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_area_goods') . " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";
    //ecmoban模板堂 --zhuo end

    if ($min > 0) {
        $where .= " AND IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) >= $min ";
    }

    if ($max > 0) {
        $where .= " AND IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) <= $max ";
    }

    $where .= " AND g.user_id = '$merchant_id'";

    if (!empty($keyword)) {
        $where .= " AND g.goods_name LIKE '%" . mysql_like_quote($keyword) . "%'";
    }


    if ($sort == 'last_update') {
        $sort = 'g.last_update';
    }

    //ecmoban模板堂 --zhuo start
    if ($GLOBALS['_CFG']['review_goods'] == 1) {
        $where .= ' AND g.review_status > 2 ';
    }
    //ecmoban模板堂 --zhuo end	

    /* 获得商品列表 */
    $sql = 'SELECT g.goods_id, g.goods_name, g.model_price, g.model_attr, g.goods_name_style, g.comments_number,g.sales_volume,g.market_price, g.is_new, g.is_best, g.is_hot, ' .
            ' IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) AS org_price, ' .
            "IFNULL(IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]'), g.shop_price * '$_SESSION[discount]')  AS shop_price, g.is_promote, " .
            "IFNULL(IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)), g.promote_price) AS promote_price, g.goods_type, " .
            'g.promote_start_date, g.promote_end_date, g.goods_brief, g.goods_thumb , g.goods_img, g.model_attr, g.product_price, g.product_promote_price ' .
            'FROM ' . $GLOBALS['ecs']->table('goods') . ' AS g ' . $leftJoin .
            'LEFT JOIN ' . $GLOBALS['ecs']->table('member_price') . ' AS mp ' .
            "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
            "WHERE $where $ext  group by g.goods_id  ORDER BY $sort $order";
    $res = $GLOBALS['db']->selectLimit($sql, $size, ($page - 1) * $size);

    $arr = array();
    $idx = 0;
    while ($row = $GLOBALS['db']->fetchRow($res)) {
        if ($row['promote_price'] > 0) {
            $promote_price = bargain_price($row['promote_price'], $row['promote_start_date'], $row['promote_end_date']);
        } else {
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

        /* 处理商品水印图片 */
        $watermark_img = '';

        if ($promote_price != 0) {
            $watermark_img = "watermark_promote_small";
        } elseif ($row['is_new'] != 0) {
            $watermark_img = "watermark_new_small";
        } elseif ($row['is_best'] != 0) {
            $watermark_img = "watermark_best_small";
        } elseif ($row['is_hot'] != 0) {
            $watermark_img = 'watermark_hot_small';
        }

        if ($watermark_img != '') {
            $arr[$idx]['watermark_img'] = $watermark_img;
        }

        $arr[$idx]['goods_id'] = $row['goods_id'];
        if ($display == 'grid') {
            $arr[$idx]['goods_name'] = $GLOBALS['_CFG']['goods_name_length'] > 0 ? sub_str($row['goods_name'], $GLOBALS['_CFG']['goods_name_length']) : $row['goods_name'];
        } else {
            $arr[$idx]['goods_name'] = $row['goods_name'];
        }
        $arr[$idx]['name'] = $row['goods_name'];
        $arr[$idx]['goods_brief'] = $row['goods_brief'];
        $arr[$idx]['sales_volume'] = $row['sales_volume'];
        $arr[$idx]['comments_number'] = $row['comments_number'];
        /* 折扣节省计算 by ecmoban start */
        if ($row['market_price'] > 0) {
            $discount_arr = get_discount($row['goods_id']); //函数get_discount参数goods_id
        }
        $arr[$idx]['zhekou'] = $discount_arr['discount'];  //zhekou
        $arr[$idx]['jiesheng'] = $discount_arr['jiesheng']; //jiesheng
        /* 折扣节省计算 by ecmoban end */
        $arr[$idx]['goods_style_name'] = add_style($row['goods_name'], $row['goods_name_style']);
        $goods_id = $row['goods_id'];

        $count = $GLOBALS['db']->getOne("SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('comment') . " where comment_type=0 and id_value ='$goods_id'");

        $arr[$idx]['review_count'] = $count;

        $arr[$idx]['market_price'] = price_format($row['market_price']);
        $arr[$idx]['shop_price'] = price_format($row['shop_price']);
        $arr[$idx]['type'] = $row['goods_type'];
        $arr[$idx]['is_promote'] = $row['is_promote'];
        $arr[$idx]['promote_price'] = ($promote_price > 0) ? price_format($promote_price) : '';
        $arr[$idx]['goods_thumb'] = get_image_path($row['goods_id'], $row['goods_thumb'], true);
        $arr[$idx]['goods_img'] = get_image_path($row['goods_id'], $row['goods_img']);
        $arr[$idx]['url'] = build_uri('goods', array('gid' => $row['goods_id']), $row['goods_name']);
        
        $arr[$idx]['pictures'] = get_goods_gallery($row['goods_id']);// 商品相册
        
        $idx++;
    }

    return $arr;
}

/**
 * 获得分类下的商品总数
 *
 * @access  public
 * @param   string     $cat_id
 * @return  integer
 */
function get_store_goods_count($children, $brand = 0, $min = 0, $max = 0, $ext='', $merchant_id, $warehouse_id = 0, $area_id = 0, $keyword)
{
    //ecmoban模板堂 --zhuo start
    if($children == ''){
        $cat_where = " AND g.user_id = '$merchant_id' ";
    }else{ 
        $cat_where = " AND $children ";
    }
    
    $leftJoin = " left join " .$GLOBALS['ecs']->table('warehouse_goods'). " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
    $leftJoin .= " left join " .$GLOBALS['ecs']->table('warehouse_area_goods'). " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";
    //ecmoban模板堂 --zhuo end
	
    $where  = "g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 $cat_where";

    if ($brand > 0)
    {
        $where .=  " AND g.brand_id = $brand ";
    }

    if ($min > 0)
    {
        $where .= " AND IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) >= $min ";
    }

    if ($max > 0)
    {
        $where .= " AND IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) <= $max ";
    }
	
	$where .= " AND g.user_id = '$merchant_id'";
	
	if(!empty($keyword)){
		$where .= " AND g.goods_name LIKE '%" . mysql_like_quote($keyword) . "%'";
	}
	
	//ecmoban模板堂 --zhuo start
    if($GLOBALS['_CFG']['review_goods'] == 1){
    	$where .= ' AND g.review_status > 2 ';
    }
    //ecmoban模板堂 --zhuo end	

    /* 返回商品总数 */
    return $GLOBALS['db']->getOne('SELECT COUNT(*) FROM ' . $GLOBALS['ecs']->table('goods') . " AS g " .$leftJoin. " WHERE $where $ext");
}

//热门商品
function get_hot_new_best_goods($children = '', $type = '', $merchant_id, $warehouse_id = 0, $area_id = 0) {
    
    $arr = array();
    $where = '';
    if (!empty($type)) {

        //ecmoban模板堂 --zhuo start
        $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_goods') . " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
        $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_area_goods') . " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";

        if ($GLOBALS['_CFG']['review_goods'] == 1) {
            $where .= ' AND g.review_status > 2 ';
        }
        //ecmoban模板堂 --zhuo end	
        $where .= " AND g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0";
        
        if($children){
            $where .= " AND " . $children;
        }
        
        $sql = "SELECT g.*, " .
                "IFNULL(IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]'), g.shop_price * '$_SESSION[discount]')  AS shop_price, " .
                "IFNULL(IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)), g.promote_price) AS promote_price " .
                " FROM " . $GLOBALS['ecs']->table('goods') . " AS g" .
                $leftJoin .
                'LEFT JOIN ' . $GLOBALS['ecs']->table('member_price') . ' AS mp ' .
                "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
                "WHERE $type $where AND g.user_id = '$merchant_id' ORDER BY g.last_update DESC";

        $res = $GLOBALS['db']->getAll($sql);

        foreach ($res as $key => $row) {
            $arr[$key] = $row;

            if ($row['promote_price'] > 0) {
                $promote_price = bargain_price($row['promote_price'], $row['promote_start_date'], $row['promote_end_date']);
            } else {
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
            
            /* 处理商品水印图片 */
            $watermark_img = '';

            if ($promote_price != 0) {
                $watermark_img = "watermark_promote_small";
            } elseif ($row['is_new'] != 0) {
                $watermark_img = "watermark_new_small";
            } elseif ($row['is_best'] != 0) {
                $watermark_img = "watermark_best_small";
            } elseif ($row['is_hot'] != 0) {
                $watermark_img = 'watermark_hot_small';
            }

            if ($watermark_img != '') {
                $arr[$key]['watermark_img'] = $watermark_img;
            }

            $arr[$key]['goods_id'] = $row['goods_id'];
            if ($display == 'grid') {
                $arr[$key]['goods_name'] = $GLOBALS['_CFG']['goods_name_length'] > 0 ? sub_str($row['goods_name'], $GLOBALS['_CFG']['goods_name_length']) : $row['goods_name'];
            } else {
                $arr[$key]['goods_name'] = $row['goods_name'];
            }
            $arr[$key]['name'] = $row['goods_name'];
            $arr[$key]['goods_brief'] = $row['goods_brief'];
            $arr[$key]['sales_volume'] = $row['sales_volume'];
            $arr[$key]['comments_number'] = $row['comments_number'];
            /* 折扣节省计算 by ecmoban start */
            if ($row['market_price'] > 0) {
                $discount_arr = get_discount($row['goods_id']); //函数get_discount参数goods_id
            }
            $arr[$key]['zhekou'] = $discount_arr['discount'];  //zhekou
            $arr[$key]['jiesheng'] = $discount_arr['jiesheng']; //jiesheng
            /* 折扣节省计算 by ecmoban end */
            $arr[$key]['goods_style_name'] = add_style($row['goods_name'], $row['goods_name_style']);
            $goods_id = $row['goods_id'];

            $count = $GLOBALS['db']->getOne("SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('comment') . " where comment_type=0 and id_value ='$goods_id'");

            $arr[$key]['review_count'] = $count;

            $arr[$key]['market_price'] = price_format($row['market_price']);
            $arr[$key]['shop_price'] = price_format($row['shop_price']);
            $arr[$key]['type'] = $row['goods_type'];
            $arr[$key]['is_promote'] = $row['is_promote'];
            $arr[$key]['promote_price'] = ($promote_price > 0) ? price_format($promote_price) : '';
            $arr[$key]['goods_thumb'] = get_image_path($row['goods_id'], $row['goods_thumb'], true);
            $arr[$key]['goods_img'] = get_image_path($row['goods_id'], $row['goods_img']);
            $arr[$key]['url'] = build_uri('goods', array('gid' => $row['goods_id']), $row['goods_name']);

            $mc_one = ments_count_rank_num($goods_id, 1);  //一颗星
            $mc_two = ments_count_rank_num($goods_id, 2);     //两颗星	
            $mc_three = ments_count_rank_num($goods_id, 3);    //三颗星
            $mc_four = ments_count_rank_num($goods_id, 4);  //四颗星
            $mc_five = ments_count_rank_num($goods_id, 5);  //五颗星
            $arr[$key]['zconments'] = get_conments_stars($mc_all, $mc_one, $mc_two, $mc_three, $mc_four, $mc_five);
        }
    }

    return $arr;
}

//category父级分类ID  parent_id
function category_store_parent($cat_id){
	$sql = "select parent_id from " .$GLOBALS['ecs']->table('merchants_category'). " where cat_id = '$cat_id'";
	return $GLOBALS['db']->getOne($sql);
}

//查询店铺基本信息以及店铺信息是否存在
function get_merchants_store_info($merchant_id, $type = 0){
    
    if($type == 0){
       $select = "count(*)"; 
    }elseif($type == 1){
       $select  = "seller_theme, shop_keyword, notice"; 
    }
    
    $sql = "select " .$select. " from " .$GLOBALS['ecs']->table('seller_shopinfo'). " where ru_id = '$merchant_id'";
    
    if($type == 0){
       $res = $GLOBALS['db']->getOne($sql);
    }elseif($type == 1){
       $res = $GLOBALS['db']->getRow($sql);
    }
    
    return $res;
}

?>