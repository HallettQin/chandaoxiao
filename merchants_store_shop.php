<?php

/**
 * ECSHOP 首页文件
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

if ((DEBUG_MODE & 2) != 2)
{
    $smarty->caching = true;
}

//ecmoban模板堂 --zhuo start
require(ROOT_PATH . '/includes/lib_area.php');  //ecmoban模板堂 --zhuo
$area_info = get_area_info($province_id);
$area_id = $area_info['region_id'];

$where = "regionId = '$province_id'";
$date = array('parent_id');
$region_id = get_table_date('region_warehouse', $where, $date, 2);
//ecmoban模板堂 --zhuo end

$brand = isset($_REQUEST['brand']) && intval($_REQUEST['brand']) > 0 ? intval($_REQUEST['brand']) : 0;

$price_max = isset($_REQUEST['price_max']) && intval($_REQUEST['price_max']) > 0 ? intval($_REQUEST['price_max']) : 0;
$price_min = isset($_REQUEST['price_min']) && intval($_REQUEST['price_min']) > 0 ? intval($_REQUEST['price_min']) : 0;
$filter_attr_str = isset($_REQUEST['filter_attr']) ? htmlspecialchars(trim($_REQUEST['filter_attr'])) : '0';

$filter_attr_str = trim(urldecode($filter_attr_str));
$filter_attr_str = preg_match('/^[\d\.]+$/',$filter_attr_str) ? $filter_attr_str : '';

$filter_attr = empty($filter_attr_str) ? '' : explode('.', $filter_attr_str);

$default_sort_order_method = $_CFG['sort_order_method'] == '0' ? 'DESC' : 'ASC';
$default_sort_order_type   = $_CFG['sort_order_type'] == '0' ? 'goods_id' : ($_CFG['sort_order_type'] == '1' ? 'shop_price' : 'last_update');

$sort  = (isset($_REQUEST['sort'])  && in_array(trim(strtolower($_REQUEST['sort'])), array('goods_id', 'shop_price', 'last_update'))) ? trim($_REQUEST['sort'])  : $default_sort_order_type;
$order = (isset($_REQUEST['order']) && in_array(trim(strtoupper($_REQUEST['order'])), array('ASC', 'DESC')))                              ? trim($_REQUEST['order']) : $default_sort_order_method;

$page       = !empty($_REQUEST['page'])  && intval($_REQUEST['page'])  > 0 ? intval($_REQUEST['page'])  : 1;

if (defined('THEME_EXTENSION')) {
    $size = 24;
} else {
    $size = !empty($_CFG['page_size']) && intval($_CFG['page_size']) > 0 ? intval($_CFG['page_size']) : 10;
}


$merchant_id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;

$user_id = isset($_SESSION['user_id'])? $_SESSION['user_id'] : 0;

//商家不存则跳转回首页
$shop_date = array('shop_id');
$shop_where = "user_id = '$merchant_id'";
$shop_id = get_table_date('merchants_shop_information', $shop_where, $shop_date);
if($merchant_id == 0 || $shop_id < 1){
	header("Location: index.php\n");
    exit;
}

/*------------------------------------------------------ */
//-- 判断是否存在缓存，如果存在则调用缓存，反之读取相应内容
/*------------------------------------------------------ */
/* 缓存编号 */
$cache_id = sprintf('%X', crc32($_SESSION['user_rank'] . '-' . $_CFG['lang']));
$shop_info = get_shop_name($merchant_id, 3); //店铺名称
if (!$smarty->is_cached('merchants_shop.dwt'))
{
    assign_template('',array(),$merchant_id);
    	
    $position = assign_ur_here(0,$shop_info['shop_name']);
    $smarty->assign('page_title',      $position['title']);    // 页面标题
    $smarty->assign('ur_here',         $position['ur_here']);  // 当前位置
    
    $categories_pro = get_category_tree_leve_one();
    $smarty->assign('categories_pro',  $categories_pro); // 分类树加强版
    $goods_list = get_shop_goods_cmt_list($merchant_id, $region_id, $area_id, $price_min, $price_max, $page, $size, $sort, $order);

    $smarty->assign('goods_list',         $goods_list);

    $count = get_shop_goods_cmt_count($merchant_id, $price_min, $price_max, $sort);
    $smarty->assign('count',         $count);  

    assign_pager('merchants_store_shop',           0, $count, $size, $sort, $order, $page, '', $brand, $price_min, $price_max, 'list', $filter_attr_str, '', '', $merchant_id); // 分页
   
    /* 页面中的动态内容 */
    assign_dynamic('merchants_store_shop');
}

if($merchant_id > 0){
	$merchants_goods_comment = get_merchants_goods_comment($merchant_id); //商家所有商品评分类型汇总
}

$smarty->assign('merch_cmt',  $merchants_goods_comment); 

$build_uri = array(
    'urid' => $merchant_id,
    'append' => $shop_info['shop_name']
);

$domain_url = get_seller_domain_url($merchant_id, $build_uri);
$shop_info['store_url'] = $domain_url['domain_name'];
if (defined('THEME_EXTENSION')){
    $collect_store = 0;
    if($_SESSION['user_id'] > 0){
        $sql = "SELECT rec_id FROM " . $GLOBALS['ecs']->table('collect_store') . " WHERE user_id = '" . $_SESSION['user_id'] . "' AND ru_id = '" .$merchant_id . "' ";
        $collect_store = $GLOBALS['db']->getOne($sql);
    }
    $smarty->assign('collect_store',  $collect_store);
}
$smarty->assign('shop_info',         $shop_info); 

$brand_list = get_shop_brand_list($merchant_id); //商家品牌
$smarty->assign('helps',      get_shop_help());       // 网店帮助
$address = get_shop_address_info($merchant_id); //商家所在位置

$smarty->assign('brand_list',         $brand_list);  // 店铺信息
$smarty->assign('address',         $address);  // 店铺信息

$sql="select * from ".$ecs->table('seller_shopinfo')." where ru_id='$merchant_id'";
$basic_info = $db->getRow($sql);

$basic_info['logo_thumb'] = str_replace('../', '', $basic_info['logo_thumb']);

//OSS文件存储ecmoban模板堂 --zhuo start
if($GLOBALS['_CFG']['open_oss'] == 1){
    $bucket_info = get_bucket_info();
    $basic_info['logo_thumb']    = $bucket_info['endpoint'] . $basic_info['logo_thumb'];
}
//OSS文件存储ecmoban模板堂 --zhuo end
            
$smarty->assign('basic_info',         $basic_info);  //店铺详细信息

$smarty->assign('merchant_id',         $merchant_id);
$smarty->assign('script_name',         'merchants_store_shop');

$store_best_list = get_shop_goods_count_list(0, $region_id, $area_id, 1, 'store_best');
$smarty->assign('store_best_list', $store_best_list);

$smarty->display('merchants_shop.dwt');

?>