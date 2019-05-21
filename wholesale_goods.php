<?php
 
define('IN_ECS', true);
require dirname(__FILE__) . '/includes/init.php';
require ROOT_PATH . '/includes/lib_area.php';
require ROOT_PATH . '/includes/lib_wholesale.php';
require ROOT_PATH . '/includes/lib_publicfunc.php';

// by zxk 跳转
$ua = strtolower($_SERVER['HTTP_USER_AGENT']);

$uachar = "/(nokia|sony|ericsson|mot|samsung|htc|sgh|lg|sharp|sie-|philips|panasonic|alcatel|lenovo|iphone|ipod|blackberry|meizu|android|netfront|symbian|ucweb|windowsce|palm|operamini|operamobi|opera mobi|openwave|nexusone|cldc|midp|wap|mobile)/i";

if(($ua == '' || preg_match($uachar, $ua))&& !strpos(strtolower($_SERVER['REQUEST_URI']),'wap'))
{
    $id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;

    $Loaction = 'mobile/index.php?m=wholesale&a=detail&id=' . $id;

    if (!empty($Loaction))
    {
        ecs_header("Location: $Loaction\n");

        exit;
    }
}

//分区类型
$smarty->assign('act_type', 'wholesale');

$area_info = get_area_info($province_id);
$area_id = $area_info['region_id'];
$smarty->assign('helps', get_shop_help());
$where = 'regionId = \'' . $province_id . '\'';
$date = array('parent_id');
$region_id = get_table_date('region_warehouse', $where, $date, 2);
if (isset($_COOKIE['region_id']) && !(empty($_COOKIE['region_id']))) 
{
	$region_id = $_COOKIE['region_id'];
}
$user_id = (!(empty($_SESSION['user_id'])) ? $_SESSION['user_id'] : 0);
get_request_filter();
if (!(empty($_REQUEST['act'])) && ($_REQUEST['act'] == 'get_select_record')) 
{
	include 'includes/cls_json.php';
	$json = new JSON();
	$result = array('error' => '', 'message' => 0, 'content' => '');
	$goods_id = (empty($_REQUEST['goods_id']) ? 0 : intval($_REQUEST['goods_id']));
	$goods_type = get_table_date('wholesale', 'goods_id=\'' . $goods_id . '\'', array('goods_type'), 2);

    //by zxk 获取商品规格
    $properties = get_wholesale_goods_properties($goods_id, $region_id, $area_id);
    $specscount = count($properties['spe']);
	if (0 < $specscount)
	{
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
        $result['record_data'] = $smarty->fetch('library/wholesale_select_record_data.lbi');
	}
	else 
	{
		$goods_number = (empty($_REQUEST['goods_number']) ? 0 : intval($_REQUEST['goods_number']));
		$result['total_number'] = $goods_number;
	}

    $data = lib_wholesale_calculate_goods_price($goods_id, $result['total_number']);
	$result['data'] = $data;
	exit($json->encode($result));
}
if (!(empty($_REQUEST['act'])) && ($_REQUEST['act'] == 'price')) 
{
	include 'includes/cls_json.php';
	$json = new JSON();
	$res = array('err_msg' => '', 'err_no' => 0, 'result' => '', 'qty' => 1);
	$attr_id = (isset($_REQUEST['attr']) && !(empty($_REQUEST['attr'])) ? explode(',', $_REQUEST['attr']) : array());
	$number = (isset($_REQUEST['number']) ? intval($_REQUEST['number']) : 1);
	$warehouse_id = (isset($_REQUEST['warehouse_id']) ? intval($_REQUEST['warehouse_id']) : 0);
	$area_id = (isset($_REQUEST['area_id']) ? intval($_REQUEST['area_id']) : 0);
	$goods_id = (isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0);
	$onload = (isset($_REQUEST['onload']) ? trim($_REQUEST['onload']) : '');
	$goods_attr = (isset($_REQUEST['goods_attr']) && !(empty($_REQUEST['goods_attr'])) ? explode(',', $_REQUEST['goods_attr']) : array());
	$attr_ajax = get_wholesale_goods_attr_ajax($goods_id, $goods_attr, $attr_id);
	$act_id = get_table_date('wholesale', 'goods_id=\'' . $goods_id . '\'', array('act_id'), 2);
	$smarty->assign('goods', get_wholesale_goods_info($act_id));
	$main_attr_list = get_wholesale_main_attr_list($goods_id, $attr_id);
	$smarty->assign('main_attr_list', $main_attr_list);
	$res['main_attr_list'] = $smarty->fetch('library/wholesale_main_attr_list.lbi');
	exit($json->encode($res));
}
$act_id = (isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0);
$goods = get_wholesale_goods_info($act_id);
$goods_id = $goods['goods_id'];

//检查采购商权限
$priv_user_id = get_table_date('goods', 'goods_id=\'' . $goods['goods_id'] . '\'', array('user_id'), 2);
if (!is_scscp_admin() && !is_scscp_seller($priv_user_id)) {
    priv();
}

assign_template();
$position = assign_ur_here($goods['cat_id'], $goods['goods_name'], array(), '', $goods['user_id']);
$properties = get_wholesale_goods_properties($goods['goods_id'], $region_id, $area_id);
$basic_info = get_shop_info_content($goods['user_id']);

$shop_information = get_shop_name($goods['user_id']);
$shop_information['kf_tel'] = $db->getOne('SELECT kf_tel FROM ' . $ecs->table('seller_shopinfo') . 'WHERE ru_id = \'' . $goods['user_id'] . '\'');
$business_practice = $db->getOne('SELECT business_practice FROM ' . $ecs->table('seller_shopinfo') . 'WHERE ru_id = \'' . $goods['user_id'] . '\'');
if ($business_practice == 1) 
{
	$shop_information['business_practice'] = '代购代销';
}
else 
{
	$shop_information['business_practice'] = '厂家直销';
}
if ($goods['user_id'] == 0) 
{
	if ($db->getOne('SELECT kf_im_switch FROM ' . $ecs->table('seller_shopinfo') . 'WHERE ru_id = 0')) 
	{
		$shop_information['is_dsc'] = true;
	}
	else 
	{
		$shop_information['is_dsc'] = false;
	}
}
else 
{
	$shop_information['is_dsc'] = false;
}
if (0 < $goods['brand_id']) 
{
	$brand_act = '';
	$brand = get_goods_brand($goods['brand_id'], $goods['user_id']);
	if ($brand) 
	{
		$sql = 'SELECT lb.brand_id, (SELECT brand_name FROM ' . $GLOBALS['ecs']->table('brand') . ' AS b WHERE b.brand_id = lb.brand_id LIMIT 1) AS brand_name FROM ' . $GLOBALS['ecs']->table('link_brand') . ' AS lb where lb.bid = \'' . $brand['brand_id'] . '\' LIMIT 1';
		$res = $GLOBALS['db']->getRow($sql);
		$goods['brand_id'] = $res['brand_id'];
		$goods['goods_brand'] = $res['brand_name'];
	}
	$brand_logo = $GLOBALS['db']->getOne(' SELECT brand_logo FROM ' . $GLOBALS['ecs']->table('brand') . ' WHERE brand_id = \'' . $goods['brand_id'] . '\' LIMIT 1 ');
	$goods['goods_brand_url'] = build_uri('brand', array('bid' => $goods['brand_id'], 'mbid' => $brand['brand_id']), $goods['goods_brand']);
	$goods['brand_thumb'] = $ecs->url() . 'data/brandlogo/' . $brand_logo;
}
$smarty->assign('shop_information', $shop_information);
$smarty->assign('kf_appkey', $basic_info['kf_appkey']);
$smarty->assign('im_user_id', 'dsc' . $_SESSION['user_id']);
$basic_date = array('region_name');
$basic_info['province'] = get_table_date('region', 'region_id = \'' . $basic_info['province'] . '\'', $basic_date, 2);
$basic_info['city'] = get_table_date('region', 'region_id= \'' . $basic_info['city'] . '\'', $basic_date, 2) . '市';
$smarty->assign('basic_info', $basic_info);
$shop_info = get_merchants_shop_info('merchants_steps_fields', $goods['user_id']);
$adress = get_license_comp_adress($shop_info['license_comp_adress']);
$smarty->assign('shop_info', $shop_info);
$smarty->assign('adress', $adress);
$goods_price = ' IF(w.price_model=0, w.goods_price, (SELECT MIN(vp.volume_price) FROM ' . $GLOBALS['ecs']->table('wholesale_volume_price') . ' AS vp WHERE vp.goods_id = \'' . $goods_id . '\')) AS price ';
$sql = ' SELECT g.goods_thumb, g.goods_name, w.act_id, ' . $goods_price . ' FROM ' . $ecs->table('wholesale') . ' AS w LEFT JOIN ' . $ecs->table('goods') . ' AS g ON g.goods_id = w.goods_id WHERE w.is_recommend = 1 AND g.user_id = \'' . $goods['user_id'] . '\' ';
$seller_recommend = $db->getRow($sql);
if ($seller_recommend) 
{
	$seller_recommend['goods_url'] = build_uri('wholesale_goods', array('aid' => $seller_recommend['act_id']), $seller_recommend['goods_name']);
	$smarty->assign('seller_recommend', $seller_recommend);
}
$see_more_goods = see_more_goods($goods['user_id'], $act_id);
$smarty->assign('see_more_goods', $see_more_goods);
$region = array(1, $province_id, $city_id, $district_id);
$shippingFee = goodsShippingFee($goods_id, $region_id, $region);
$smarty->assign('shippingFee', $shippingFee);
$area = array('region_id' => $region_id, 'province_id' => $province_id, 'city_id' => $city_id, 'district_id' => $district_id, 'goods_id' => $goods_id, 'user_id' => $user_id, 'area_id' => $area_id, 'merchant_id' => $goods['user_id']);
if (defined('THEME_EXTENSION')) 
{
	$business_cate = get_wholesale_cat();
	$smarty->assign('business_cate', $business_cate);
}
$smarty->assign('properties', $properties['pro']);
$smarty->assign('specification', $properties['spe']);
$smarty->assign('specscount',       count($properties['spe']));
$smarty->assign('page_title', $position['title']);
$smarty->assign('ur_here', $position['ur_here']);
$smarty->assign('now_time', gmtime());
$smarty->assign('goods', $goods);
$smarty->assign('pictures', get_goods_gallery($goods_id));
$smarty->assign('cfg', $_CFG);
$smarty->assign('goods_id', $goods['goods_id']);

//评分 start zxk
$comment_allCount = get_goods_comment_count($goods_id);
$smarty->assign('comment_allCount',        $comment_allCount);

$stat = wholesale_stat($act_id);
$smarty->assign('orderG_number', $stat['total_goods']); //购买的商品数量


//@author guan start
if ($_CFG['two_code']) {
    require(dirname(__FILE__) . '/includes/phpqrcode/phpqrcode.php'); //by zxk

    $group_buy_path = ROOT_PATH .IMAGE_DIR. "/wholesale_wenxin/";

    if (!file_exists($group_buy_path)) {
        make_dir($group_buy_path);
    }

    $logo = empty($_CFG['two_code_logo']) ? $goods['goods_img'] : str_replace('../', '', $_CFG['two_code_logo']);

    $size = '200x200';
    $url = $ecs->url();
    $two_code_links = trim($_CFG['two_code_links']);
    $two_code_links = empty($two_code_links) ? $url : $two_code_links;
    $data = $two_code_links . 'wholesale_goods.php?id=' . $act_id;
    $errorCorrectionLevel = 'H'; // 纠错级别：L、M、Q、H
    $matrixPointSize = 4; // 点的大小：1到10
    $filename = IMAGE_DIR . "/wholesale_wenxin/weixin_code_" . $goods['goods_id'] . ".png";

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

//商家服务
$merchants_goods_comment = get_merchants_goods_comment($goods['user_id']); //商家所有商品评分类型汇总
$smarty->assign('merch_cmt',  $merchants_goods_comment);

//评分 start
$comment_allCount = get_goods_comment_count($goods_id);
$comment_all = get_comments_percent($goods_id);
$smarty->assign('comment_allCount',        $comment_allCount);
$smarty->assign('comment_all',  $comment_all);

$smarty->assign('wholesale_param', $goods['price_ladder']);
$smarty->assign('area', $area);
$smarty->assign('act_id', $act_id);
$main_attr_list = get_wholesale_main_attr_list($goods['goods_id']);
$smarty->assign('main_attr_list', $main_attr_list);
$smarty->display('wholesale_goods.dwt', $cache_id);
?>