<?php

/**
 * ECSHOP 控制台首页
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
require_once(ROOT_PATH . '/includes/lib_order.php');

include_once(ROOT_PATH . '/includes/cls_image.php');
$image = new cls_image($_CFG['bgcolor']);

$adminru = get_admin_ru_id();
$ru_id = $adminru['ru_id'];

surplus_time($ru_id);//判断商家年审剩余时间

$smarty->assign('ru_id', $ru_id);

$smarty->assign('menus',$_SESSION['menus']);

if($_REQUEST['act'] == 'merchants_first' || $_REQUEST['act'] == 'shop_top'|| $_REQUEST['act'] == 'merchants_second'){
    $smarty->assign('action_type',"index");
}else{
    $smarty->assign('action_type',"");
}
if($ru_id == 0){
        $smarty->assign('priv_ru',   1);
}else{
        $smarty->assign('priv_ru',   0);
}

$data = read_static_cache('main_user_str');
if ($data === false) {
    $smarty->assign('is_false',    '1');
}else{
    $smarty->assign('is_false',    '0');
}

$data = read_static_cache('seller_goods_str');
if ($data === false) {
    $smarty->assign('goods_false',    '1');
}else{
    $smarty->assign('goods_false',    '0');
}
/* ------------------------------------------------------ */
//-- 框架
/* ------------------------------------------------------ */
if ($_REQUEST['act'] == '') {

    $user_id = intval($_SESSION['seller_id']); //admin_user表中的user_id;
    $ru_id = $db->getOne("SELECT ru_id FROM " . $ecs->table('admin_user') . " WHERE user_id='$user_id'");

    /* 商家信息 */
    $sql = "SELECT u.*,s.* FROM " . $ecs->table('admin_user') . " AS u LEFT JOIN " . $ecs->table('seller_shopinfo') . " AS s ON u.ru_id = s.ru_id WHERE u.user_id = '$user_id'";
    $seller_info = $db->getRow($sql);
    //转换时间;
    $seller_info['last_login'] = local_date('Y-m-d H:i:s', $seller_info['last_login']);
    $seller_info['shopName'] = get_shop_name($seller_info['ru_id'], 1);
    /* 商家商品 */

    //上架、删除、下架、 库存预警 的商品;
    $seller_goods_info['is_sell'] = $db->getOne("SELECT COUNT(*) FROM " . $ecs->table('goods') . " WHERE user_id ='$ru_id' AND is_on_sale = 1");
    $seller_goods_info['is_delete'] = $db->getOne("SELECT COUNT(*) FROM " . $ecs->table('goods') . " WHERE user_id ='$ru_id' AND is_delete = 1");
    $seller_goods_info['is_on_sale'] = $db->getOne("SELECT COUNT(*) FROM " . $ecs->table('goods') . " WHERE user_id ='$ru_id' AND is_on_sale = 0");
    $seller_goods_info['is_warn'] = $db->getOne("SELECT COUNT(*) FROM " . $ecs->table('goods') . " WHERE user_id ='$ru_id' AND goods_number <= warn_number");

    //总发布商品数;
    $sql = "SELECT COUNT(*) FROM " . $ecs->table('goods') . " WHERE user_id ='$ru_id'";
    $seller_goods_info['total'] = $db->getOne($sql);

    /* 取得支持货到付款和不支持货到付款的支付方式 */
    $ids = get_pay_ids();
    /* ecmoban start zhou */
    $today_start=local_mktime(0,0,0,date('m'),date('d'),date('Y'));
    $today_end=local_mktime(0,0,0,date('m'),date('d')+1,date('Y'))-1;
    $month_start=local_mktime(0,0,0,date('m'),1,date('Y'));
    $month_end=local_mktime(23,59,59,date('m'),date('t'),date('Y'));
    $today = array();

    $where_date = '';
    $where_og = '';
    $where_og .= " AND (SELECT count(*) FROM " .$GLOBALS['ecs']->table('order_info'). " AS oi2 WHERE oi2.main_order_id = oi.order_id) = 0 ";  //主订单下有子订单时，则主订单不显示

    //ecmoban模板堂 --zhuo start
    if ($ru_id > 0) {
        $where_date .= " AND (SELECT ru_id FROM " .$GLOBALS['ecs']->table('order_goods'). " AS og WHERE oi.order_id = og.order_id LIMIT 1) = '" .$ru_id. "'";  //主订单下有子订单时，则主订单不显示
    }
    //ecmoban模板堂 --zhuo end



    //ecmoban模板堂 --zhuo start
    $where_goods = '';
    $where_cmt = '';

    if ($ru_id > 0) {
        $where_og .= " AND (SELECT og.ru_id FROM " .$GLOBALS['ecs']->table('order_goods') ." AS og WHERE oi.order_id = og.order_id LIMIT 1". ") = " . $ru_id;

        $where_goods = " and user_id = " . $ru_id;
        $where_cmt = " and ru_id = " . $ru_id;
    }
    //ecmoban模板堂 --zhuo end

    /* 已完成的订单 */
    $order['finished'] = $db->getOne('SELECT count(*) FROM ' . $ecs->table('order_info') . " as oi " .
        " WHERE 1 " . order_query_sql('finished') . $where_og);
    $status['finished'] = CS_FINISHED;

    /* 待发货的订单： */
    $order['await_ship'] = $db->getOne('SELECT count(*) FROM ' . $ecs->table('order_info') . " as oi " .
        " WHERE 1 " . order_query_sql('await_ship') . $where_og);
    $status['await_ship'] = CS_AWAIT_SHIP;

    /* 待付款的订单： */
    $order['await_pay'] = $db->getOne('SELECT count(*) FROM ' . $ecs->table('order_info') . " as oi " .
        " WHERE 1 " . order_query_sql('await_pay') . $where_og);
    $status['await_pay'] = CS_AWAIT_PAY;

    /* “未确认”的订单 */
    $order['unconfirmed'] = $db->getOne('SELECT count(*) FROM ' . $ecs->table('order_info') . " as oi " .
        " WHERE 1 " . order_query_sql('unconfirmed') . $where_og);
    $status['unconfirmed'] = OS_UNCONFIRMED;

    /* “交易中的”的订单(配送方式非"已收货"的所有订单) */
    $order['shipped_deal'] = $db->getOne('SELECT count(*) FROM ' . $ecs->table('order_info') . " as oi " .
        " WHERE  shipping_status<>" . SS_RECEIVED . $where_og);
    $status['shipped_deal'] = SS_RECEIVED;

    /* “部分发货”的订单 */
    $order['shipped_part'] = $db->getOne('SELECT count(*) FROM ' . $ecs->table('order_info') . " as oi " .
        " WHERE  shipping_status=" . SS_SHIPPED_PART . $where_og);
    $status['shipped_part'] = OS_SHIPPED_PART;

    $order['stats'] = $db->getRow('SELECT COUNT(*) AS oCount, IFNULL(SUM(oi.order_amount), 0) AS oAmount' .
            ' FROM ' . $ecs->table('order_info') . " as oi" . " where 1 " . $where_og);

    //待评价订单
    $signNum0 = get_order_no_comment($ru_id, 0);
    $smarty->assign('no_comment', $signNum0);
    //订单纠纷
    $sql = "SELECT COUNT(*) FROM".$ecs->table('complaint')."WHERE complaint_state > 0 AND ru_id = '$ru_id'";
    $complaint_count = $db->getOne($sql);
    $smarty->assign("complaint_count",$complaint_count);
    //退换货

    $where_return = '';
    if ($ru_id > 0) {
        $where_return = " and og.ru_id = '" . $ru_id . "'";
    }

    $sql = "SELECT o.order_id, o.order_sn FROM " .$ecs->table('order_info'). " AS o LEFT JOIN " .$ecs->table('order_goods'). " AS og ON og.order_id=o.order_id LEFT JOIN " .$ecs->table('users'). " AS u ON u.user_id=o.user_id RIGHT JOIN " .$ecs->table('order_return'). " AS r ON r.order_id = o.order_id WHERE 1" . $where_return;
    $order['return_number'] = count($db->getAll($sql));

    $smarty->assign('order', $order);
    $smarty->assign('status', $status);

    /* 缺货登记 */

    //ecmoban模板堂 --zhuo start
    $leftJoin_bg = '';
    $where_bg = '';
    if ($ru_id > 0) {
        $leftJoin_bg = " left join " . $ecs->table('goods') . " as g on bg.goods_id = g.goods_id ";
        $where_bg = " and g.user_id = " . $ru_id;
    }
    //ecmoban模板堂 --zhuo end

    $sql = 'SELECT COUNT(*) FROM ' . $ecs->table('booking_goods') . "as bg " .
        $leftJoin_bg .
        ' WHERE is_dispose = 0' . $where_bg;
    $booking_goods = $db->getOne($sql);

    $smarty->assign('booking_goods', $booking_goods);
    /* 退款申请 */
    $smarty->assign('new_repay', $db->getOne('SELECT COUNT(*) FROM ' . $ecs->table('user_account') . ' WHERE process_type = ' . SURPLUS_RETURN . ' AND is_paid = 0 '));


    /* 销售情况统计(已付款的才算数) */
    //1.总销量;
    $sql = query_sales($ru_id);
    $total_shipping_info = $db->getRow($sql);

    //2.昨天销量;
    $beginYesterday = local_mktime(0, 0, 0, date('m'), date('d') - 1, date('Y'));
    $endYesterday = local_mktime(0, 0, 0, date('m'), date('d'), date('Y')) - 1;

    $where = " AND oi.pay_time BETWEEN $beginYesterday AND $endYesterday ";
    $sql = query_sales($ru_id, $where);
    $yseterday_shipping_info = $db->getRow($sql);


    //3.月销量;
    $beginThismonth = local_mktime(0, 0, 0, date('m'), 1, date('Y'));
    $endThismonth = local_mktime(23, 59, 59, date('m'), date('t'), date('Y'));
    $where = " AND oi.pay_time BETWEEN $beginThismonth AND $endThismonth ";
    $sql = query_sales($ru_id, $where);
    $month_shipping_info = $db->getRow($sql);

    //当前优惠活动
    $favourable_count = get_favourable_count($ru_id);
    $smarty->assign('favourable_count',$favourable_count);
    $smarty->assign('file_list',get_dir_file_list());

    //即将到期优惠活动
    $favourable_dateout_count = get_favourable_dateout_count($ru_id);
    $smarty->assign('favourable_dateout_count',$favourable_dateout_count);

    //待商品回复咨询
    $reply_count = get_comment_reply_count($ru_id);
    $smarty->assign('reply_count',$reply_count);

    $hot_count = get_goods_special_count($ru_id, 'is_hot');
    $new_count = get_goods_special_count($ru_id, 'is_new');
    $best_count = get_goods_special_count($ru_id, 'is_best');
    $promotion_count = get_goods_special_count($ru_id, 'promotion');

    $smarty->assign('hot_count',$hot_count);
    $smarty->assign('new_count',$new_count);
    $smarty->assign('best_count',$best_count);
    $smarty->assign('promotion_count',$promotion_count);

    /* 商家帮助 */
    $sql="SELECT * FROM ".$ecs->table('article')."WHERE cat_id = '".$_CFG['seller_index_article']."' ";
    $articles = $db->getAll($sql);

    /* 单品销售数量排名(已付款的才算数) */
    $sql = "SELECT goods_id ,goods_name,sales_volume AS goods_shipping_total FROM" . $ecs->table('goods') .
            " WHERE user_id='$ru_id' AND is_delete = 0 AND is_on_sale = 1 ORDER BY goods_shipping_total DESC LIMIT 10";
    $goods_info = $db->getAll($sql);


    $smarty->assign('total_shipping_info',$total_shipping_info);
    $smarty->assign('month_shipping_info',$month_shipping_info);
    $smarty->assign('yseterday_shipping_info',$yseterday_shipping_info);
    $smarty->assign('goods_info',$goods_info);
    $smarty->assign('articles',$articles);
    $smarty->assign('seller_goods_info',$seller_goods_info);

    if($seller_info['logo_thumb']) {
        $seller_info['logo_thumb'] = str_replace('../', '', $seller_info['logo_thumb']);
        $seller_info['logo_thumb'] = get_image_path(0, $seller_info['logo_thumb']);
    }

    $smarty->assign('seller_info',$seller_info);
    $smarty->assign('shop_url', urlencode($ecs->seller_url()));

    $merchants_goods_comment = get_merchants_goods_comment($seller_info['ru_id']); //商家所有商品评分类型汇总
    $smarty->assign('merch_cmt',  $merchants_goods_comment);

    //今日PC客单价
    $today_sales = get_sales(1);
    $smarty->assign('today_sales', $today_sales);

    //昨日PC客单价
    $yes_sales = get_sales(2);
    $smarty->assign('yes_sales', $yes_sales);

    //今日移动客单价
    $today_move_sales = get_move_sales(1);
    $smarty->assign('today_move_sales', $today_move_sales);

    //昨日移动客单价
    $yes_move_sales = get_move_sales(2);
    $smarty->assign('yes_move_sales', $yes_move_sales);

    //今日PC子订单数
    $today_sub_order = get_sub_order(1);
    $smarty->assign('today_sub_order', $today_sub_order);

    //昨日PC子订单数
    $yes_sub_order = get_sub_order(2);
    $smarty->assign('yes_sub_order', $yes_sub_order);

    //今日移动子订单数
    $today_move_sub_order = get_move_sub_order(1);
    $smarty->assign('today_move_sub_order', $today_nove_sub_order);

    //昨日移动子订单数
    $yes_move_sub_order = get_move_sub_order(2);
    $smarty->assign('yes_move_sub_order', $yes_move_sub_order);

    //今日总成交额
    $t_count = str_replace('<em>¥</em>', '', $today_sales['count']);
    $y_count = str_replace('<em>¥</em>', '', $today_move_sales['count']);
    $all_count = price_format($t_count + $y_count);
    $smarty->assign('all_count', $all_count);

    //今日全店成交转化率
    $t_view = viewip($ru_id);
    $all_order = $today_sales['order'] + $today_move_sales['order'];
    if ($t_view['todaycount']) {
        $cj = $all_order / $t_view['todaycount'];
    } else {
        $cj = 0;
    }
    $smarty->assign('cj', number_format($cj, 3, '.', ''));



    $smarty->display('index.dwt');

}
/*------------------------------------------------------ */
//-- 商家开店向导第一步
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'merchants_first')
{
    $smarty->assign('primary_cat',     $_LANG['19_merchants_store']);

    admin_priv('seller_store_informa');//by kong

    $seller_shop_info = array(
        'shop_logo' => '',
        'logo_thumb' => '',
        'street_thumb' => '',
        'brand_thumb' => ''
    );

    $smarty->assign('countries',    get_regions());
    $smarty->assign('provinces',    get_regions(1, 1));

    $sql="select notice from ".$ecs->table('seller_shopinfo')." where ru_id = 0 LIMIT 1";
    $seller_notice=$db->getOne($sql);
    $smarty->assign('seller_notice',  $seller_notice);

    //获取入驻商家店铺信息 wang 商家入驻
    $sql="select ss.*,sq.* from ".$ecs->table('seller_shopinfo')." as ss ".
	" left join ".$ecs->table('seller_qrcode')." as sq on sq.ru_id = ss.ru_id ".
	" where ss.ru_id='".$adminru['ru_id']."' LIMIT 1"; //by wu
    $seller_shop_info=$db->getRow($sql);
    $action='add';
    if($seller_shop_info)
    {
        $action='update';
    }

    $shipping_list = warehouse_shipping_list();
    $smarty->assign('shipping_list',  $shipping_list);
    //获取店铺二级域名 by kong
    $domain_name=$db->getOne(" SELECT domain_name FROM".$ecs->table("seller_domain")." WHERE ru_id='".$adminru['ru_id']."'");
    $seller_shop_info['domain_name']=$domain_name;//by kong

    if(!isset($seller_shop_info['templates_mode'])){
        $seller_shop_info['templates_mode'] = 1;
    }

    //处理修改数据 by wu start
    $diff_data = get_seller_shopinfo_changelog($adminru['ru_id']);
    $seller_shop_info = array_replace($seller_shop_info, $diff_data);
    //处理修改数据 by wu end
    if ($seller_shop_info['shop_logo']) {
        $seller_shop_info['shop_logo'] = str_replace('../', '', $seller_shop_info['shop_logo']);
        $seller_shop_info['shop_logo'] = get_image_path(0, $seller_shop_info['shop_logo']);
    }
    if ($seller_shop_info['logo_thumb']) {
        $seller_shop_info['logo_thumb'] = str_replace('../', '', $seller_shop_info['logo_thumb']);
        $seller_shop_info['logo_thumb'] = get_image_path(0, $seller_shop_info['logo_thumb']);
    }
    if ($seller_shop_info['street_thumb']) {
        $seller_shop_info['street_thumb'] = str_replace('../', '', $seller_shop_info['street_thumb']);
        $seller_shop_info['street_thumb'] = get_image_path(0, $seller_shop_info['street_thumb']);
    }
    if ($seller_shop_info['brand_thumb']) {
        $seller_shop_info['brand_thumb'] = str_replace('../', '', $seller_shop_info['brand_thumb']);
        $seller_shop_info['brand_thumb'] = get_image_path(0, $seller_shop_info['brand_thumb']);
    }

    $smarty->assign('shop_info',$seller_shop_info);

    /*  @author-bylu  start  */
    $shop_information = get_shop_name($adminru['ru_id']);
    $adminru['ru_id'] == 0 ? $shop_information['is_dsc'] = true : $shop_information['is_dsc'] = false;//判断当前商家是平台,还是入驻商家 bylu
    $smarty->assign('shop_information',$shop_information);
    /*  @author-bylu  end  */

    $shop_information = get_shop_name($adminru['ru_id']);
    $smarty->assign('shop_information',$shop_information);

    $smarty->assign('cities',    get_regions(2, $seller_shop_info['province']));
    $smarty->assign('districts',    get_regions(3, $seller_shop_info['city']));

    $smarty->assign('http', $ecs->http());
    $smarty->assign('data_op',$action);
    assign_query_info();
    $smarty->assign('current', 'index_first');
    $smarty->assign('ur_here', $_LANG['04_merchants_basic_info']);
    $smarty->display('store_setting.dwt');
}

/*------------------------------------------------------ */
//-- 商家开店向导第二步
/*------------------------------------------------------ */

 elseif ($_REQUEST['act'] == 'merchants_second') {
    $shop_name = empty($_POST['shop_name']) ? '' : addslashes(trim($_POST['shop_name']));
    $shop_title = empty($_POST['shop_title']) ? '' : addslashes(trim($_POST['shop_title']));
    $shop_keyword = empty($_POST['shop_keyword']) ? '' : addslashes(trim($_POST['shop_keyword']));
    $shop_country = empty($_POST['shop_country']) ? 0 : intval($_POST['shop_country']);
    $shop_province = empty($_POST['shop_province']) ? 0 : intval($_POST['shop_province']);
    $shop_city = empty($_POST['shop_city']) ? 0 : intval($_POST['shop_city']);
    $shop_district = empty($_POST['shop_district']) ? 0 : intval($_POST['shop_district']);
    $shipping_id = empty($_POST['shipping_id']) ? 0 : intval($_POST['shipping_id']);
    $shop_address = empty($_POST['shop_address']) ? '' : addslashes(trim($_POST['shop_address']));
    $mobile = empty($_POST['mobile']) ? '' : trim($_POST['mobile']); //by wu
    $seller_email = empty($_POST['seller_email']) ? '' : addslashes(trim($_POST['seller_email']));
    $street_desc = empty($_POST['street_desc']) ? '' : addslashes(trim($_POST['street_desc']));
    $kf_qq = empty($_POST['kf_qq']) ? '' : $_POST['kf_qq'];
    $kf_ww = empty($_POST['kf_ww']) ? '' : $_POST['kf_ww'];
    $kf_touid = empty($_POST['kf_touid']) ? '' : addslashes(trim($_POST['kf_touid'])); //客服账号 bylu
    $kf_appkey = empty($_POST['kf_appkey']) ? 0 : addslashes(trim($_POST['kf_appkey'])); //appkey bylu
    $kf_secretkey = empty($_POST['kf_secretkey']) ? 0 : addslashes(trim($_POST['kf_secretkey'])); //secretkey bylu
    $kf_logo = empty($_POST['kf_logo']) ? 'http://' : addslashes(trim($_POST['kf_logo'])); //头像 bylu
    $kf_welcomeMsg = empty($_POST['kf_welcomeMsg']) ? '' : addslashes(trim($_POST['kf_welcomeMsg'])); //欢迎语 bylu
    $meiqia = empty($_POST['meiqia']) ? '' : addslashes(trim($_POST['meiqia'])); //美洽客服
    $kf_type = empty($_POST['kf_type']) ? 0 : intval($_POST['kf_type']);
    $kf_tel = empty($_POST['kf_tel']) ? '' : addslashes(trim($_POST['kf_tel']));
    $notice = empty($_POST['notice']) ? '' : addslashes(trim($_POST['notice']));
    $data_op = empty($_POST['data_op']) ? '' : $_POST['data_op'];
    $check_sellername = empty($_POST['check_sellername']) ? 0 : intval($_POST['check_sellername']);
    $shop_style = intval($_POST['shop_style']);
    $domain_name = empty($_POST['domain_name']) ? '' : trim($_POST['domain_name']);
    $templates_mode = empty($_REQUEST['templates_mode'])  ? 0 : intval($_REQUEST['templates_mode']);

    $tengxun_key = empty($_POST['tengxun_key']) ? '' : addslashes(trim($_POST['tengxun_key']));
    $longitude = empty($_POST['longitude']) ? '' : addslashes(trim($_POST['longitude']));
    $latitude = empty($_POST['latitude']) ? '' : addslashes(trim($_POST['latitude']));

    $js_appkey = empty($_POST['js_appkey']) ? '' : $_POST['js_appkey']; //扫码appkey
    $js_appsecret = empty($_POST['js_appsecret']) ? '' : $_POST['js_appsecret']; //扫码appsecret

    $print_type = empty($_POST['print_type']) ? 0 : intval($_POST['print_type']); //打印方式
	$kdniao_printer = empty($_POST['kdniao_printer']) ? '' : $_POST['kdniao_printer']; //打印机

    //判断域名是否存在  by kong
    if (!empty($domain_name)) {
        $sql = " SELECT count(id) FROM " . $ecs->table("seller_domain") . " WHERE domain_name = '" . $domain_name . "' AND ru_id !='" . $adminru['ru_id'] . "'";
        if ($db->getOne($sql) > 0) {
            $lnk[] = array('text' => '返回首页', 'href' => 'index.php?act=main');
            sys_msg('域名已存在', 0, $lnk);
        }
    }
    $seller_domain = array(
        'ru_id' => $adminru['ru_id'],
        'domain_name' => $domain_name,
    );


    $shop_info = array(
        'ru_id' => $adminru['ru_id'],
        'shop_name' => $shop_name,
        'shop_title' => $shop_title,
        'shop_keyword' => $shop_keyword,
        'country' => $shop_country,
        'province' => $shop_province,
        'city' => $shop_city,
        'district' => $shop_district,
        'shipping_id' => $shipping_id,
        'shop_address' => $shop_address,
        'mobile' => $mobile,
        'seller_email' => $seller_email,
        'kf_qq' => $kf_qq,
        'kf_ww' => $kf_ww,
        'kf_appkey' => $kf_appkey, // bylu
        'kf_secretkey' => $kf_secretkey, // bylu
        'kf_touid' => $kf_touid, // bylu
        'kf_logo' => $kf_logo, // bylu
        'kf_welcomeMsg' => $kf_welcomeMsg, // bylu
        'meiqia' => $meiqia,
        'kf_type' => $kf_type,
        'kf_tel' => $kf_tel,
        'notice' => $notice,
        'street_desc' => $street_desc,
        'shop_style' => $shop_style,
        'check_sellername' => $check_sellername,
        'templates_mode' => $templates_mode,
        'tengxun_key' => $tengxun_key,
        'longitude' => $longitude,
        'latitude' => $latitude,
		'js_appkey' => $js_appkey, //扫码appkey
		'js_appsecret' => $js_appsecret, //扫码appsecret
        'print_type' => $print_type,
        'kdniao_printer' => $kdniao_printer
    );

    $sql = "SELECT ss.shop_logo, ss.logo_thumb, ss.street_thumb, ss.brand_thumb, sq.qrcode_thumb FROM " . $ecs->table('seller_shopinfo') . " as ss " .
            " left join " . $ecs->table('seller_qrcode') . " as sq on sq.ru_id=ss.ru_id " .
            " WHERE ss.ru_id='" . $adminru['ru_id'] . "'"; //by wu
    $store = $db->getRow($sql);

    /* 允许上传的文件类型 */
    $allow_file_types = '|GIF|JPG|PNG|BMP|';

    if ($_FILES['shop_logo']) {
        $file = $_FILES['shop_logo'];
        /* 判断用户是否选择了文件 */
        if ((isset($file['error']) && $file['error'] == 0) || (!isset($file['error']) && $file['tmp_name'] != 'none')) {
            /* 检查上传的文件类型是否合法 */
            if (!check_file_type($file['tmp_name'], $file['name'], $allow_file_types)) {
                sys_msg(sprintf($_LANG['msg_invalid_file'], $file['name']));
            } else {

                if ($file['name']) {
                    $ext = explode('.', $file['name']);
                    $ext = array_pop($ext);
                } else {
                    $ext = "";
                }

                $file_name = '../seller_imgs/seller_logo/seller_logo' . $adminru['ru_id'] . '.' . $ext;
                /* 判断是否上传成功 */
                if (move_upload_file($file['tmp_name'], $file_name)) {
                    $shop_info['shop_logo'] = $file_name;
                } else {
                    sys_msg(sprintf($_LANG['msg_upload_failed'], $file['name'], '../seller_imgs/seller_' . $adminru['ru_id']));
                }
            }
        }
    }

    $del_logo_thumb = '';
    if ($_FILES['logo_thumb']) {
        $file = $_FILES['logo_thumb'];
        /* 判断用户是否选择了文件 */
        if ((isset($file['error']) && $file['error'] == 0) || (!isset($file['error']) && $file['tmp_name'] != 'none')) {
            /* 检查上传的文件类型是否合法 */
            if (!check_file_type($file['tmp_name'], $file['name'], $allow_file_types)) {
                sys_msg(sprintf($_LANG['msg_invalid_file'], $file['name']));
            } else {

                if ($file['name']) {
                    $ext = explode('.', $file['name']);
                    $ext = array_pop($ext);
                } else {
                    $ext = "";
                }

                $file_name = '../seller_imgs/seller_logo/logo_thumb/logo_thumb' . $adminru['ru_id'] . '.' . $ext;
                /* 判断是否上传成功 */
                if (move_upload_file($file['tmp_name'], $file_name)) {
                    include_once(ROOT_PATH . '/includes/cls_image.php');
                    $image = new cls_image($_CFG['bgcolor']);

                    $goods_thumb = $image->make_thumb($file_name, 120, 120, "../seller_imgs/seller_logo/logo_thumb/");
                    $shop_info['logo_thumb'] = $goods_thumb;

                    if (!empty($goods_thumb)) {
                        if ($store['logo_thumb']) {
                            $store['logo_thumb'] = str_replace('../', '', $store['logo_thumb']);
                            $del_logo_thumb = $store['logo_thumb'];
                        }
                        @unlink(ROOT_PATH . $del_logo_thumb);
                    }
                } else {
                    sys_msg(sprintf($_LANG['msg_upload_failed'], $file['name'], 'seller_imgs/logo_thumb_' . $adminru['ru_id']));
                }
            }
        }
    }

    $street_thumb = $image->upload_image($_FILES['street_thumb'], 'store_street/street_thumb');  //图片存放地址 -- data/septs_Image
    $brand_thumb = $image->upload_image($_FILES['brand_thumb'], 'store_street/brand_thumb');  //图片存放地址 -- data/septs_Image

    $domain_id = $db->getOne("SELECT id FROM " . $ecs->table('seller_domain') . " WHERE ru_id ='" . $adminru['ru_id'] . "'"); //by kong
    /* 二级域名绑定  by kong  satrt */
    if ($domain_id > 0) {
        $db->autoExecute($ecs->table('seller_domain'), $seller_domain, 'UPDATE', "ru_id='" . $adminru['ru_id'] . "'");
    } else {
        $db->autoExecute($ecs->table('seller_domain'), $seller_domain, 'INSERT');
    }
    /* 二级域名绑定  by kong  end */
    //二维码中间logo by wu start
    if ($_FILES['qrcode_thumb']) {
        $file = $_FILES['qrcode_thumb'];
        /* 判断用户是否选择了文件 */
        if ((isset($file['error']) && $file['error'] == 0) || (!isset($file['error']) && $file['tmp_name'] != 'none')) {
            /* 检查上传的文件类型是否合法 */
            if (!check_file_type($file['tmp_name'], $file['name'], $allow_file_types)) {
                sys_msg(sprintf($_LANG['msg_invalid_file'], $file['name']));
            } else {
				$name = explode('.', $file['name']);
                $ext = array_pop($name);
                $file_name = '../seller_imgs/seller_qrcode/qrcode_thumb/qrcode_thumb' . $adminru['ru_id'] . '.' . $ext;
                /* 判断是否上传成功 */
                if (move_upload_file($file['tmp_name'], $file_name)) {
                    include_once(ROOT_PATH . '/includes/cls_image.php');
                    $image = new cls_image($_CFG['bgcolor']);

                    $qrcode_thumb = $image->make_thumb($file_name, 120, 120, "../seller_imgs/seller_qrcode/qrcode_thumb/");
                    //$shop_info['qrcode_thumb']=$qrcode_thumb;

                    if (!empty($qrcode_thumb)) {
                        if ($store['qrcode_thumb']) {
                            $store['qrcode_thumb'] = str_replace('../', '', $store['qrcode_thumb']);
                            $del_logo_thumb = $store['qrcode_thumb'];
                        }
                        @unlink(ROOT_PATH . $del_logo_thumb);
                    }
                    /* 保存 */
                    $sql = " select * from " . $GLOBALS['ecs']->table('seller_qrcode') . " where ru_id='" . $adminru['ru_id'] . "' limit 1";
                    $qrinfo = $GLOBALS['db']->getRow($sql);
                    if (empty($qrinfo)) {
                        $sql = " insert into " . $GLOBALS['ecs']->table('seller_qrcode') . " (ru_id,qrcode_thumb) " .
                                " values " .
                                "('" . $adminru['ru_id'] . "','" . $qrcode_thumb . "')";
                        $GLOBALS['db']->query($sql);
                    } else {
                        $sql = " update " . $GLOBALS['ecs']->table('seller_qrcode') . " set ru_id='" . $adminru['ru_id'] . "', " .
                                " qrcode_thumb='" . $qrcode_thumb . "' ";
                        $GLOBALS['db']->query($sql);
                    }
                } else {
                    sys_msg(sprintf($_LANG['msg_upload_failed'], $file['name'], 'seller_imgs/qrcode_thumb_' . $adminru['ru_id']));
                }
            }
        }
    }
    //二维码中间logo by wu end

    $shop_logo = '';
    if ($shop_info['shop_logo']) {
        $shop_logo = str_replace('../', '', $shop_info['shop_logo']);
    }

    $add_logo_thumb = '';
    if ($shop_info['logo_thumb']) {
        $add_logo_thumb = str_replace('../', '', $shop_info['logo_thumb']);
    }

    get_oss_add_file(array($street_thumb, $brand_thumb, $shop_logo, $add_logo_thumb));

    $admin_user = array(
        'email' => $seller_email
    );

    $db->autoExecute($ecs->table('admin_user'), $admin_user, 'UPDATE', "user_id = '" . $_SESSION['seller_id'] . "'");

    if ($data_op == 'add') {
        $shop_info['street_thumb'] = $street_thumb;
        $shop_info['brand_thumb'] = $brand_thumb;

        if (!$store) {
			//处理修改数据 by wu start
			$data_keys = array_keys($shop_info); //更新数据字段
			$db_data = array(); //数据库中数据
			$diff_data = array_diff_assoc($shop_info, $db_data); //数据库中数据与提交数据差集
			if(!empty($diff_data)){ //有数据变化
				//清空旧数据
				$sql = " DELETE FROM ".$GLOBALS['ecs']->table('seller_shopinfo_changelog')." WHERE ru_id = '{$adminru['ru_id']}' ";
				$GLOBALS['db']->query($sql);
				//将修改数据插入日志
				foreach($diff_data as $key=>$val){
					$changelog = array('data_key'=>$key, 'data_value'=>$val, 'ru_id'=>$adminru['ru_id']);
					$db->autoExecute($ecs->table('seller_shopinfo_changelog'), $changelog, 'INSERT');
				}
			}
			//处理修改数据 by wu end

			//$db->autoExecute($ecs->table('seller_shopinfo'), $shop_info, 'INSERT');
        }

        $lnk[] = array('text' => '返回上一步', 'href' => 'index.php?act=merchants_first');
        sys_msg('添加店铺信息成功', 0, $lnk);
    } else {
        $sql = "select check_sellername from " . $ecs->table('seller_shopinfo') . " where ru_id='" . $adminru['ru_id'] . "'";
        $seller_shop_info = $db->getRow($sql);

        if ($seller_shop_info['check_sellername'] != $check_sellername) {
            $shop_info['shopname_audit'] = 0;
        }

        $oss_street_thumb = '';
        if (!empty($street_thumb)) {
            $oss_street_thumb = $store['street_thumb'];
            $shop_info['street_thumb'] = $street_thumb;
            @unlink(ROOT_PATH . $oss_street_thumb);
        }

        $oss_brand_thumb = '';
        if (!empty($brand_thumb)) {
            $oss_brand_thumb = $store['brand_thumb'];
            $shop_info['brand_thumb'] = $brand_thumb;
            @unlink(ROOT_PATH . $oss_brand_thumb);
        }

        //OSS文件存储ecmoban模板堂 --zhuo start
        if ($GLOBALS['_CFG']['open_oss'] == 1) {
            $bucket_info = get_bucket_info();
            $url = $GLOBALS['ecs']->seller_url();

            $self = explode("/", substr(PHP_SELF, 1));
            $count = count($self);
            if ($count > 1) {
                $real_path = $self[$count - 2];
                if ($real_path == SELLER_PATH) {
                    $str_len = -(str_len(SELLER_PATH) + 1);
                    $url = substr($GLOBALS['ecs']->seller_url(), 0, $str_len);
                }
            }

            $urlip = get_ip_url($url);
            $url = $urlip . "oss.php?act=del_file";
            $Http = new Http();
            $post_data = array(
                'bucket' => $bucket_info['bucket'],
                'keyid' => $bucket_info['keyid'],
                'keysecret' => $bucket_info['keysecret'],
                'is_cname' => $bucket_info['is_cname'],
                'endpoint' => $bucket_info['outside_site'],
                'object' => array(
                    $oss_street_thumb,
                    $oss_brand_thumb,
                    $del_logo_thumb
                )
            );

            $Http->doPost($url, $post_data);
        }
        //OSS文件存储ecmoban模板堂 --zhuo end

		//处理修改数据 by wu start
		$data_keys = array_keys($shop_info); //更新数据字段
		$db_data = get_table_date('seller_shopinfo', "ru_id='{$adminru['ru_id']}'", $data_keys); //数据库中数据
                //
                //获取零食表数据 有  已零时表数据为准
                $diff_data_old = get_seller_shopinfo_changelog($adminru['ru_id']);
                $db_data = array_replace($db_data, $diff_data_old);

		$diff_data = array_diff_assoc($shop_info, $db_data); //数据库中数据与提交数据差集
		if(!empty($diff_data)){ //有数据变化
			$review_status = array('review_status'=>1);
			$db->autoExecute($ecs->table('seller_shopinfo'), $review_status, 'UPDATE', "ru_id='" . $adminru['ru_id'] . "'");

			//将修改数据插入日志
			foreach($diff_data as $key=>$val){
                //清空旧数据
                $sql = " DELETE FROM ".$GLOBALS['ecs']->table('seller_shopinfo_changelog')." WHERE ru_id = '{$adminru['ru_id']}' AND data_key = '{$key}'";
			    $GLOBALS['db']->query($sql);
//                $db->autoExecute($ecs->table('seller_domain'), $seller_domain, 'UPDATE', "ru_id='" . $adminru['ru_id'] . "'");
				$changelog = array('data_key'=>$key, 'data_value'=>$val, 'ru_id'=>$adminru['ru_id']);
				$db->autoExecute($ecs->table('seller_shopinfo_changelog'), $changelog, 'INSERT');
			}
		}
		//处理修改数据 by wu end

        //$db->autoExecute($ecs->table('seller_shopinfo'), $shop_info, 'UPDATE', "ru_id='" . $adminru['ru_id'] . "'");
        $lnk[] = array('text' => '返回上一步', 'href' => 'index.php?act=merchants_first');
        sys_msg('更新店铺信息成功', 0, $lnk);
    }
}

//wang 商家入驻 店铺头部装修
elseif ($_REQUEST['act'] == 'shop_top') {

    admin_priv('seller_store_other'); //by kong
    $smarty->assign('primary_cat',     $_LANG['19_merchants_store']);
    $smarty->assign('ur_here', '店铺头部装修');
    //获取入驻商家店铺信息 wang 商家入驻

    $seller_shop_info = get_seller_info($adminru['ru_id'], array('id', 'seller_theme', 'shop_color'));

    if ($seller_shop_info['id'] > 0) {
        //店铺头部
        $header_sql = "select content, headtype, headbg_img, shop_color from " . $GLOBALS['ecs']->table('seller_shopheader') . " where seller_theme='" . $seller_shop_info['seller_theme'] . "' and ru_id = '" . $adminru['ru_id'] . "'";
        $shopheader_info = $GLOBALS['db']->getRow($header_sql);

        $header_content = $shopheader_info['content'];

        /* 创建 百度编辑器 wang 商家入驻 */
        create_ueditor_editor('shop_header', $header_content,586);

        $smarty->assign('form_action', 'shop_top_edit');
        $smarty->assign('shop_info', $seller_shop_info);
        $smarty->assign('shopheader_info', $shopheader_info);
    } else {
        $lnk[] = array('text' => '设置店铺信息', 'href' => 'index.php?act=merchants_first');
        sys_msg('请先设置店铺基本信息', 0, $lnk);
    }
    $smarty->assign('current', 'index_top');
    $smarty->display('seller_shop_header.dwt');
}

elseif($_REQUEST['act'] == 'shop_top_edit')
{
    //正则去掉js代码
    $preg = "/<script[\s\S]*?<\/script>/i";

    $shop_header = !empty($_REQUEST['shop_header']) ? preg_replace($preg, "", stripslashes($_REQUEST['shop_header'])) : '';
    $seller_theme = !empty($_REQUEST['seller_theme']) ? preg_replace($preg, "", stripslashes($_REQUEST['seller_theme'])) : '';
    $shop_color = !empty($_REQUEST['shop_color']) ? $_REQUEST['shop_color'] : '';
    $headtype = isset($_REQUEST['headtype']) ? intval($_REQUEST['headtype']) : 0;

    $img_url = '';
    if ($headtype == 0) {
        /* 处理图片 */
        /* 允许上传的文件类型 */
        $allow_file_types = '|GIF|JPG|PNG|BMP|';

        if ($_FILES['img_url']) {
            $file = $_FILES['img_url'];
            /* 判断用户是否选择了文件 */
            if ((isset($file['error']) && $file['error'] == 0) || (!isset($file['error']) && $file['tmp_name'] != 'none')) {
                /* 检查上传的文件类型是否合法 */
                if (!check_file_type($file['tmp_name'], $file['name'], $allow_file_types)) {
                    sys_msg(sprintf($_LANG['msg_invalid_file'], $file['name']));
                } else {

                    $ext = array_pop(explode('.', $file['name']));
                    $file_dir = '../seller_imgs/seller_header_img/seller_' . $adminru['ru_id'];
                    if (!is_dir($file_dir)) {
                        mkdir($file_dir);
                    }
                    $file_name = $file_dir . "/slide_" . gmtime() . '.' . $ext;
                    /* 判断是否上传成功 */
                    if (move_upload_file($file['tmp_name'], $file_name)) {
                        $img_url = $file_name;

                        $oss_img_url = str_replace("../", "", $img_url);
                        get_oss_add_file(array($oss_img_url));
                    } else {
                        sys_msg('图片上传失败');
                    }
                }
            }
        } else {
            sys_msg('必须上传图片');
        }
    }

    $sql = "SELECT headbg_img FROM " . $ecs->table('seller_shopheader') . " WHERE ru_id='" . $adminru['ru_id'] . "' and seller_theme='" . $seller_theme . "'";
    $shopheader_info = $db->getRow($sql);

    if (empty($img_url)) {
        $img_url = $shopheader_info['headbg_img'];
    }

    //跟新店铺头部
    $sql = "update " . $ecs->table('seller_shopheader') . " set content='$shop_header', shop_color='$shop_color', headbg_img='$img_url', headtype='$headtype' where ru_id='" . $adminru['ru_id'] . "' and seller_theme='" . $seller_theme . "'";
    $db->query($sql);

    $lnk[] = array('text' => '返回上一步', 'href' => 'index.php?act=shop_top');

    sys_msg('店铺头部装修成功', 0, $lnk);
}

/* ------------------------------------------------------ */
//-- license操作
/* ------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'license') {
    $is_ajax = $_GET['is_ajax'];

    if (isset($is_ajax) && $is_ajax) {
        // license 检查
        include_once(ROOT_PATH . 'includes/cls_transport.php');
        include_once(ROOT_PATH . 'includes/cls_json.php');
        include_once(ROOT_PATH . 'includes/lib_main.php');
        include_once(ROOT_PATH . 'includes/lib_license.php');

        $license = license_check();
        switch ($license['flag']) {
            case 'login_succ':
                if (isset($license['request']['info']['service']['ecshop_b2c']['cert_auth']['auth_str'])) {
                    make_json_result(process_login_license($license['request']['info']['service']['ecshop_b2c']['cert_auth']));
                } else {
                    make_json_error(0);
                }
                break;

            case 'login_fail':
            case 'login_ping_fail':
                make_json_error(0);
                break;

            case 'reg_succ':
                $_license = license_check();
                switch ($_license['flag']) {
                    case 'login_succ':
                        if (isset($_license['request']['info']['service']['ecshop_b2c']['cert_auth']['auth_str']) && $_license['request']['info']['service']['ecshop_b2c']['cert_auth']['auth_str'] != '') {
                            make_json_result(process_login_license($license['request']['info']['service']['ecshop_b2c']['cert_auth']));
                        } else {
                            make_json_error(0);
                        }
                        break;

                    case 'login_fail':
                    case 'login_ping_fail':
                        make_json_error(0);
                        break;
                }
                break;

            case 'reg_fail':
            case 'reg_ping_fail':
                make_json_error(0);
                break;
        }
    } else {
        make_json_error(0);
    }
}
/* ------------------------------------------------------ */
//-- 检查订单
/* ------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'check_order') {

    $firstSecToday = local_mktime(0, 0, 0, date("m"), date("d"), date("Y"));
    $lastSecToday = local_mktime(0, 0, 0, date("m"), date("d")+1, date("Y"))-1;

    if (empty($_SESSION['last_check'])) {
        $_SESSION['last_check'] = gmtime();
        make_json_result('', '', array('new_orders' => 0, 'new_paid' => 0));
    }

    //ecmoban模板堂 --zhuo
    $where = "";
    $where = " AND (SELECT og.ru_id FROM " . $GLOBALS['ecs']->table('order_goods') .' as og' . " WHERE og.order_id = o.order_id limit 0, 1) = '" .$adminru['ru_id']. "' ";
    $where .= " AND (select count(*) from " .$GLOBALS['ecs']->table('order_info'). " as oi2 WHERE oi2.main_order_id = o.order_id) = 0 ";  //主订单下有子订单时，则主订单不显示
    $where .= " AND o.shipping_status = " . SS_UNSHIPPED;

    /* 新订单 */
    $sql = 'SELECT COUNT(*) FROM ' . $ecs->table('order_info') . " as o" .
            " WHERE o.add_time >= " . $firstSecToday . " AND o.add_time <= " .$lastSecToday. $where;
    $arr['new_orders'] = $db->getOne($sql);

    /* 新付款的订单 */
    $sql = 'SELECT COUNT(*) FROM ' . $ecs->table('order_info') . " as o" .
            ' WHERE o.pay_time >= ' . $firstSecToday . " AND o.pay_time <= " .$lastSecToday. $where;
    $arr['new_paid'] = $db->getOne($sql);

    $_SESSION['last_check'] = gmtime();

    $_SESSION['firstSecToday'] = $firstSecToday;
    $_SESSION['lastSecToday'] = $lastSecToday;

    if (!(is_numeric($arr['new_orders']) && is_numeric($arr['new_paid']))) {
        make_json_error($db->error());
    } else {
        make_json_result('', '', $arr);
    }
}

/* ------------------------------------------------------ */
//-- 检查商家账单是否生成
/* ------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'check_bill') {
    /* by zxk jisuan */
    //取消商家账单生成
    $result = array();
    die(json_encode($result));
    /* by zxk jisuan */
    $checkbill_number = isset($GLOBALS['_CFG']['checkbill_number']) && !empty($GLOBALS['_CFG']['checkbill_number']) ? $GLOBALS['_CFG']['checkbill_number'] : 10;

    $day_time = local_date("Y-m-d", gmtime());
    $checkbil_array = array(
        $day_time => array(
            $adminru['ru_id'] => array(
                'checkbill_number' => 1
            )
        )
    );

    $cfg_checkbill = read_static_cache('checkbill_number_' . $adminru['ru_id'], '/data/sc_file/seller_bill/');
    if ($cfg_checkbill === false) {
        write_static_cache('checkbill_number_' . $adminru['ru_id'], $checkbil_array, '/data/sc_file/seller_bill/');
    } else {

        if(count($cfg_checkbill) >= 7){
            dsc_unlink(ROOT_PATH . DATA_DIR . "/sc_file/seller_bill/checkbill_number_" .$adminru['ru_id']. ".php");

            $cfg_checkbill = array(
                $day_time => array(
                    'checkbill_number' => $cfg_checkbill[$day_time][$adminru['ru_id']]['checkbill_number']
                )
            );
        }

        if($cfg_checkbill[$day_time][$adminru['ru_id']]['checkbill_number'] < $checkbill_number){
            $cfg_checkbill[$day_time][$adminru['ru_id']]['checkbill_number'] += 1;

            write_static_cache('checkbill_number_' . $adminru['ru_id'], $cfg_checkbill, '/data/sc_file/seller_bill/');
        }
    }

    /**
     * 判断是否异步加载过规定次数
     * 默认是10次
     */
    if($cfg_checkbill !== false && $cfg_checkbill[$day_time][$adminru['ru_id']]['checkbill_number'] >= $checkbill_number){
        $is_check_bill = 0; //停止加载
    }else{
        $is_check_bill = 1; //启动加载
    }

    if ($is_check_bill) {
        $result = array();

        $sql = "SELECT u.user_id AS seller_id, IFNULL(s.cycle, 0) AS cycle, p.percent_value FROM " . $GLOBALS['ecs']->table('merchants_shop_information') . " AS u " .
                " LEFT JOIN " . $GLOBALS['ecs']->table('merchants_server') . " AS s ON u.user_id = s.user_id" .
                " LEFT JOIN " . $GLOBALS['ecs']->table('merchants_percent') . " AS p ON s.suppliers_percent = p.percent_id" .
                " WHERE u.user_id = '" . $adminru['ru_id'] . "'";
        $seller_list = $GLOBALS['db']->getAll($sql);

        $last_year_start = 0;
        $last_year_end = 0;

        $notime = gmtime();
        $year = local_date("Y", $notime); //当前年份

        $year_exp = local_date("Y-m-d", $notime); //当前年月份
        $year_exp = explode("-", $year_exp);
        $nowYear = intval($year_exp[0]); //当前年份
        $nowMonth = intval($year_exp[1]); //当前月份
        $nowDay = intval($year_exp[2]); //当前日期

        foreach ($seller_list as $key => $row) {

            $day_array = array();
            $is_charge = 1;

            /* 按天数 */
            if ($row['cycle'] == 7) {

                $sql = "SELECT MAX(end_time) FROM " . $GLOBALS['ecs']->table('seller_commission_bill') . " WHERE seller_id = '" . $row['seller_id'] . "' AND bill_cycle = '" . $row['cycle'] . "' LIMIT 1";
                $end_time = $GLOBALS['db']->getOne($sql);

                if ($end_time) {
                    $row['bill_time'] = $end_time;
                }

                $last_year_start = local_date("Y-m-d 00:00:00", $row['bill_time']);
                $bill_time = $row['bill_time'] + ($row['day_number'] - 1) * 24 * 60 * 60;
                $last_year_end = local_date("Y-m-d 23:59:59", $bill_time);

                $thistime = gmtime();
                $bill_end_time = local_strtotime($last_year_end);

                if ($thistime <= $bill_end_time) {
                    $is_charge = 0;
                }
            }

            /* 按年 */ elseif ($row['cycle'] == 6) {

                $day_array = get_bill_one_year($row['seller_id'], $row['cycle']);

                if (empty($day_array)) {
                    $last_year_start = ($year - 1) . "-01-01 00:00:00"; //去年开始的第一天
                    $last_year_end = ($year - 1) . "-12-31 23:59:59";   //去年结束的最后一天

                    $day_array[0]['last_year_start'] = $last_year_start;
                    $day_array[0]['last_year_end'] = $last_year_end;
                }
            }

            /* 6个月 */ elseif ($row['cycle'] == 5) {

                $day_array = get_bill_half_year($row['seller_id'], $row['cycle']);

                if (empty($day_array)) {
                    /* 判断当前月份是否大于6月份，是否已是七月份 */
                    if ($nowMonth > 6) {

                        $last_year_start = $year . "-01-01 00:00:00"; //当前年份开始的第一天
                        $last_year_end = $year . "-06-30 23:59:59";   //当前年份结束的最后一天
                    } else {

                        /* 获取去年下半年的时间段 */
                        $lastYear = $nowYear - 1;

                        $last_year_start = $lastYear . "-07-01 00:00:00"; //去年后半年开始的第一天
                        $last_year_end = $lastYear . "-12-31 23:59:59";   //后半年结束的最后一天
                    }

                    $day_array[0]['last_year_start'] = $last_year_start;
                    $day_array[0]['last_year_end'] = $last_year_end;
                }
            }

            /* 1个季度 */ elseif ($row['cycle'] == 4) {
                $day_array = get_bill_quarter($row['seller_id'], $row['cycle']);

                if (empty($day_array)) {
                    if ($nowMonth > 3 && $nowMonth <= 6) {
                        /* 当前第一季度时间段 */
                        $last_year_start = $nowYear . "-01-01 00:00:00"; //当前第一季度开始的第一天
                        $last_year_end = $nowYear . "-03-31 23:59:59";   //当前第一季度结束的最后一天
                    } elseif ($nowMonth > 6 && $nowMonth <= 9) {
                        /* 当前第二季度时间段 */
                        $last_year_start = $nowYear . "-04-01 00:00:00"; //当前第二季度开始的第一天
                        $last_year_end = $nowYear . "-06-30 23:59:59";   //当前第二季度结束的最后一天
                    } elseif ($nowMonth > 9 && $nowMonth <= 12) {
                        /* 当前第三季度时间段 */
                        $last_year_start = $nowYear . "-07-01 00:00:00"; //当前第三季度开始的第一天
                        $last_year_end = $nowYear . "-09-30 23:59:59";   //当前第三季度结束的最后一天
                    } elseif ($nowMonth <= 3) {
                        /* 当前第四季度时间段 */
                        $last_year_start = $nowYear - 1 . "-10-01 00:00:00"; //当前第四季度开始的第一天
                        $last_year_end = $nowYear - 1 . "-12-31 23:59:59";   //当前第四季度结束的最后一天
                    }

                    $day_array[0]['last_year_start'] = $last_year_start;
                    $day_array[0]['last_year_end'] = $last_year_end;
                }
            }

            /* 1个月 */ elseif ($row['cycle'] == 3) {
                $day_array = get_bill_one_month($row['seller_id'], $row['cycle']);

                if (empty($day_array)) {

                    $nowMonth = $nowMonth - 1;

                    /* 获取当月天数 */
                    $days = cal_days_in_month(CAL_GREGORIAN, $nowMonth, $nowYear);

                    if ($nowMonth <= 9) {
                        $nowMonth = "0" . $nowMonth;
                    }

                    $last_year_start = $nowYear . "-" . $nowMonth . "-01 00:00:00"; //上一个月的第一天
                    $last_year_end = $nowYear . "-" . $nowMonth . "-" . $days . " 23:59:59"; //上一个月的最后一天

                    $day_array[0]['last_year_start'] = $last_year_start;
                    $day_array[0]['last_year_end'] = $last_year_end;
                }
            }

            /* 15天（半个月） */ elseif ($row['cycle'] == 2) {
                $day_array = get_bill_half_month($row['seller_id'], $row['cycle']);

                if (empty($day_array)) {
                    $lastDay = local_date('Y-m-t');
                    $lastDay = explode("-", $lastDay);
                    $halfDay = intval($lastDay[2] / 2);

                    if ($nowDay > $halfDay) {
                        $last_year_start = $lastDay[0] . "-" . $lastDay[1] . "-01 00:00:00"; //当前月开始的第一天
                        $last_year_end = $lastDay[0] . "-" . $lastDay[1] . "-" . $halfDay . " 23:59:59"; //当前月开始的第一天
                    } else {
                        $lastMonth_firstDay = $nowYear . "-" . $nowMonth . "-01 00:00:00";
                        $lastMonth_lastDay = local_date('Y-m-d', local_strtotime("$lastMonth_firstDay +1 month -1 day")) . " 23:59:59";

                        $lastMonth = local_date('Y-m-d', local_strtotime("$lastMonth_firstDay +1 month -1 day"));
                        $lastMonth = explode("-", $lastMonth);
                        $halfMonth = intval($lastMonth[2] / 2);
                        $middleMonth = $lastMonth[0] . "-" . $lastMonth[1] . "-" . ($halfMonth + 1);

                        $middleMonth_lastDay = $middleMonth . " 23:59:59";
                        $middleMonth_firstDay = $middleMonth . " 00:00:00";
                        $last_year_start = $middleMonth_firstDay;   //当前月月中的天数日期
                        $last_year_end = $lastMonth_lastDay;    //上一个月的最后一天（以当前是5月15号之前运算）
                    }
                    $day_array[0]['last_year_start'] = $last_year_start;
                    $day_array[0]['last_year_end'] = $last_year_end;
                }
            }

            /* 七天(按一个礼拜) */ elseif ($row['cycle'] == 1) {
                $day_array = get_bill_seven_day($row['seller_id'], $row['cycle']);

                if (empty($day_array)) {
                    $week = local_date('w'); //当前月的日期本周
                    $thisWeekMon = local_strtotime('+' . 1 - $week . ' days'); //本周礼拜一
                    $lastWeekMon = 7 * 24 * 60 * 60; //上个礼拜一的时间
                    $lastWeeksun = 1 * 24 * 60 * 60; //上个礼拜日的时间

                    $lastWeekMon = $thisWeekMon - $lastWeekMon;
                    $lastWeeksun = $thisWeekMon - $lastWeeksun;

                    $last_year_start = local_date('Y-m-d 00:00:00', $lastWeekMon);
                    $last_year_end = local_date('Y-m-d 23:59:59', $lastWeeksun);
                    $day_array[0]['last_year_start'] = $last_year_start;
                    $day_array[0]['last_year_end'] = $last_year_end;
                }
            }

            /* 每天 */ else {
                $day_array = get_bill_per_day($row['seller_id'], $row['cycle']);

                if (empty($day_array)) {
                    $last_year_start = local_date("Y-m-d 00:00:00", local_strtotime("-1 day"));
                    $last_year_end = local_date("Y-m-d 23:59:59", local_strtotime("-1 day"));
                    $day_array[0]['last_year_start'] = $last_year_start;
                    $day_array[0]['last_year_end'] = $last_year_end;
                }
            }

            if ($day_array) {
                foreach ($day_array as $keys => $rows) {
                    $last_year_start = local_strtotime($rows['last_year_start']); //时间戳
                    $last_year_end = local_strtotime($rows['last_year_end']); //时间戳

                    $sql = "SELECT id FROM " . $GLOBALS['ecs']->table('seller_commission_bill') . " WHERE seller_id = '" . $row['seller_id'] . "' AND bill_cycle = '" . $row['cycle'] . "'" .
                            " AND start_time >= '$last_year_start' AND end_time <= '$last_year_end'";
                    $bill_id = $GLOBALS['db']->getOne($sql, true);

                    if (!$bill_id && $is_charge == 1 && ($last_year_start > 0 && $last_year_end > 0 && $last_year_start < $last_year_end)) {

                        $bill_sn = get_order_sn();

                        $other = array(
                            'seller_id' => $row['seller_id'],
                            'bill_sn' => $bill_sn,
                            'proportion' => $row['percent_value'],
                            'start_time' => $last_year_start,
                            'end_time' => $last_year_end,
                            'bill_cycle' => $row['cycle'],
                            'operator' => $_SESSION['admin_name']
                        );

                        $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('seller_commission_bill'), $other, 'INSERT');
                    }
                }
            }
        }
    }

    make_json_result('', '', $result);
}

/* ------------------------------------------------------ */
//-- 检查出账单
/* ------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'out_check_bill') {
    $result = array();

    $bill_list = commission_bill_list(1);

    make_json_result('', '', $result);
}

/* ------------------------------------------------------ */
//-- 修改快捷菜单 by wu
/* ------------------------------------------------------ */
 elseif ($_REQUEST['act'] == 'change_user_menu') {
    $adminru = get_admin_ru_id();
    $result = array('error' => 0, 'message' => '', 'content' => '');
    $action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : '';
    $status = isset($_REQUEST['status']) ? intval($_REQUEST['status']) : 0;
    //已存在的快捷菜单
    $user_menu = get_user_menu_list();
    //检查是否已存在
    $change = get_user_menu_status($action);
    //
    if (!$change) {
        $user_menu[] = $action;
        $sql = " UPDATE " . $GLOBALS['ecs']->table('seller_shopinfo') . " set user_menu = '" . implode(',', $user_menu) . "' WHERE ru_id = '" . $adminru['ru_id'] . "' ";
        if ($GLOBALS['db']->query($sql)) {
            $result['error'] = 1;
        }
    }
    if ($change) {
        $user_menu = array_diff($user_menu, array($action));
        $sql = " UPDATE " . $GLOBALS['ecs']->table('seller_shopinfo') . " set user_menu = '" . implode(',', $user_menu) . "' WHERE ru_id = '" . $adminru['ru_id'] . "' ";
        if ($GLOBALS['db']->query($sql)) {
            $result['error'] = 2;
        }
    }

    //var_dump($user_menu);

    die(json_encode($result));
}

/* ------------------------------------------------------ */
//-- 清除缓存
/* ------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'clear_cache') {
    if (file_exists(ROOT_PATH . 'mobile/api/script/clear_cache.php')) {
        require_once(ROOT_PATH . 'mobile/api/script/clear_cache.php');
    }

    $sql = "UPDATE " . $GLOBALS['ecs']->table('shop_config') . " SET value = 0 WHERE code = 'is_downconfig'";
    $GLOBALS['db']->query($sql);

    clear_all_files('', SELLER_PATH);
    sys_msg($_LANG['caches_cleared']);
}

/* ------------------------------------------------------ */
//-- 获取店铺坐标
/* ------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'tengxun_coordinate') {
    $result = array('error' => 0, 'message' => '', 'content' => '');

    $province = !empty($_REQUEST['province']) ? intval($_REQUEST['province']) : 0;
    $city = !empty($_REQUEST['city']) ? intval($_REQUEST['city']) : 0;
    $district = !empty($_REQUEST['district']) ? intval($_REQUEST['district']) : 0;
    $address = !empty($_REQUEST['address']) ? trim($_REQUEST['address']) : 0;

    //$seller_info = get_seller_info($adminru['ru_id'], array('tengxun_key'));

    $region = get_seller_region(array('province' => $province, 'city' => $city, 'district' => $district));
    $key = $GLOBALS["_CFG"]['tengxun_key']; //密钥
    $region .= $address; //地址
    $url = "http://apis.map.qq.com/ws/geocoder/v1/?address=" . $region . "&key=" . $key;
    $http = new Http();
    $data = $http->doGet($url);
    $data = json_decode($data, true);

    if($data['status'] == 0){
        $result['lng'] = $data['result']['location']['lng'];
        $result['lat'] = $data['result']['location']['lat'];
    }else{
        $result['error'] = 1;
        $result['message'] = $data['message'];
    }

    die(json_encode($result));
}

/* ------------------------------------------------------ */
//-- 管理员头像上传
/* ------------------------------------------------------ */
elseif($_REQUEST['act'] == 'upload_store_img')
{
    $result = array("error"=>0, "message"=>"", "content"=>"");
    include_once(ROOT_PATH . '/includes/cls_image.php');
    $image = new cls_image($_CFG['bgcolor']);
	$admin_id = get_admin_id();

    if($_FILES['img']['name'])
    {
        //$name_arr = explode(".", $_FILES['img']['name']);
        //$file_type = end($name_arr);
        //$img_name = $store_id . "." . $file_type;
        $dir         = 'store_user';

        $img_name = $image->upload_image($_FILES['img'],$dir);

        if($img_name)
        {
            $result['error'] = 1;
            $result['content'] = "../" . $img_name;
            //删除原图片

            $store_user_img = $GLOBALS['db']->getOne(" SELECT admin_user_img FROM ".$GLOBALS['ecs']->table('admin_user')." WHERE user_id = '".$admin_id."' ");
            @unlink("../" . $store_user_img);
            //插入新图片
            $sql = " UPDATE ".$GLOBALS['ecs']->table('admin_user')." SET admin_user_img = '$img_name' WHERE user_id = '".$admin_id."' ";
            $GLOBALS['db']->query($sql);
        }
    }
    die(json_encode($result));
}

//PC端客单价
function get_sales($day_num){

    $adminru = get_admin_ru_id();
    $where = " AND o.pay_status = 2";
    $where .= " AND (SELECT count(*) FROM " .$GLOBALS['ecs']->table('order_info'). " AS oi2 WHERE oi2.main_order_id = o.order_id) = 0 ";  //主订单下有子订单时，则主订单不显示

    //计算24小内的时间戳
    if ($day_num == 1) {
        $date_start = local_mktime(0, 0, 0, date('m', time()), date('d', time()), date('Y', time()));
        $date_end = local_mktime(23, 59, 59, date('m', time()), date('d', time()), date('Y', time()));
    } elseif ($day_num == 2) {
        $date_end = local_mktime(0, 0, 0, date('m'), date('d'), date('Y')) - 1;
        $date_start = $date_end - 3600 * 24 + 1;
    }

    /* 查询订单 */
    $sql = "SELECT IFNULL(SUM(" . order_commission_field('o.') . "),0) AS 'ga', COUNT(o.order_id) AS 'oi' " .
            " FROM " . $GLOBALS['ecs']->table('order_info') . " AS o " .
            " LEFT JOIN " . $GLOBALS['ecs']->table('baitiao_log') . " AS bai ON o.order_id=bai.order_id WHERE o.add_time BETWEEN " . $date_start . ' AND ' . $date_end . " AND o.referer NOT IN('touch', 'mobile')" .
            " AND (SELECT og.ru_id FROM " . $GLOBALS['ecs']->table('order_goods') . " AS og WHERE og.order_id = o.order_id LIMIT 1) = '" . $adminru['ru_id'] . "'" . $where;
    $row = $GLOBALS['db']->getRow($sql);

    //计算客单价，客单价 = 订单总额/订单数
    if ($row && $row['oi']) {
        $sales = price_format(($row['ga']) / $row['oi']);  //客单价计算  + $row['sf'] 不计算运费
        $count = price_format($row['ga']);  //PC端成交计算  + $row['sf'] 不计算运费
        $arr = array('sales' => $sales, 'count' => $count, 'order' => $row['oi']);
        return $arr;
    } else {
        return 0;
    }
}

//移动端客单价
function get_move_sales($day_num){

    //计算24小内的时间戳
    if ($day_num == 1) {
        $date_start = local_mktime(0, 0, 0, date('m', time()), date('d', time()), date('Y', time()));
        $date_end = local_mktime(23, 59, 59, date('m', time()), date('d', time()), date('Y', time()));
    } elseif ($day_num == 2) {
        $date_end = local_mktime(0, 0, 0, date('m'), date('d'), date('Y')) - 1;
        $date_start = $date_end - 3600 * 24 + 1;
    }

    $adminru = get_admin_ru_id();
    $where = " AND o.pay_status = 2";
    $where .= " AND (SELECT count(*) FROM " .$GLOBALS['ecs']->table('order_info'). " AS oi2 WHERE oi2.main_order_id = o.order_id) = 0 ";  //主订单下有子订单时，则主订单不显示

    /* 查询订单 */
    $sql = " SELECT IFNULL(SUM(" . order_commission_field('o.') . "),0) AS 'ga', COUNT(o.order_id) AS 'oi'" .
            " FROM " . $GLOBALS['ecs']->table('order_info') . " AS o " .
            "LEFT JOIN " . $GLOBALS['ecs']->table('baitiao_log') . " AS bai ON o.order_id=bai.order_id WHERE o.add_time BETWEEN " . $date_start . ' AND ' . $date_end . " AND o.referer IN('touch', 'mobile')" .
            " AND (SELECT og.ru_id FROM " . $GLOBALS['ecs']->table('order_goods') . " AS og WHERE og.order_id = o.order_id LIMIT 1) = '" . $adminru['ru_id'] . "'" . $where;
    $row = $GLOBALS['db']->getRow($sql);
    //计算客单价，客单价 = 订单总额/订单数
    if ($row && $row['oi']) {
        $sales = price_format(($row['ga']) / $row['oi']);  //客单价计算  + $row['sf']
        $count = price_format($row['ga']);  //PC端成交计算   + $row['sf']
        $arr = array('sales' => $sales, 'count' => $count, 'order' => $row['oi']);
        return $arr;
    } else {
        return 0;
    }
}

//获取PC子订单数
function get_sub_order($day_num){

    $adminru = get_admin_ru_id();
    $where = " AND o.pay_status = 2";
    $where .= " AND (SELECT count(*) FROM " .$GLOBALS['ecs']->table('order_info'). " AS oi2 WHERE oi2.main_order_id = o.order_id) = 0 ";  //主订单下有子订单时，则主订单不显示

    //计算24小内的时间戳
    if ($day_num == 1) {
        $date_start = local_mktime(0, 0, 0, date('m', time()), date('d', time()), date('Y', time()));
        $date_end = local_mktime(23, 59, 59, date('m', time()), date('d', time()), date('Y', time()));
    } elseif ($day_num == 2) {
        $date_end = local_mktime(0, 0, 0, date('m'), date('d'), date('Y')) - 1;
        $date_start = $date_end - 3600 * 24 + 1;
    }
    //查询子订单数
    $sql = "SELECT COUNT(o.order_id) AS 'oi' " .
            " FROM " . $GLOBALS['ecs']->table('order_info') . " AS o " .
            " WHERE o.add_time BETWEEN " . $date_start . ' AND ' . $date_end . " AND o.referer NOT IN('touch', 'mobile')" .
            " AND (SELECT og.ru_id FROM " . $GLOBALS['ecs']->table('order_goods') . " AS og WHERE og.order_id = o.order_id LIMIT 1) = '" . $adminru['ru_id'] . "'" . $where;
    $row = $GLOBALS['db']->getRow($sql);
    if ($row && $row['oi']) {
        $sub_order = $row['oi'];
        $arr = array('sub_order' => $sub_order);
        return $arr;
    } else {
        return 0;
    }
}

//获取移动子订单数
function get_move_sub_order($day_num){

    //计算24小内的时间戳
    if ($day_num == 1) {
        $date_start = local_mktime(0, 0, 0, date('m', time()), date('d', time()), date('Y', time()));
        $date_end = local_mktime(23, 59, 59, date('m', time()), date('d', time()), date('Y', time()));
    } elseif ($day_num == 2) {
        $date_end = local_mktime(0, 0, 0, date('m'), date('d'), date('Y')) - 1;
        $date_start = $date_end - 3600 * 24 + 1;
    }

    $adminru = get_admin_ru_id();
    $where = " AND o.pay_status = 2";
    $where .= " AND (SELECT count(*) FROM " .$GLOBALS['ecs']->table('order_info'). " AS oi2 WHERE oi2.main_order_id = o.order_id) = 0 ";  //主订单下有子订单时，则主订单不显示

    //查询子订单数
    $sql = "SELECT COUNT(*) AS 'oi' " .
            " FROM " . $GLOBALS['ecs']->table('order_info') . " AS o " .
            " WHERE o.add_time BETWEEN " . $date_start . ' AND ' . $date_end . " AND o.referer IN('touch', 'mobile')" .
            " AND (SELECT og.ru_id FROM " . $GLOBALS['ecs']->table('order_goods') . " AS og WHERE og.order_id = o.order_id LIMIT 1) = '" . $adminru['ru_id'] . "'" . $where;
    $row = $GLOBALS['db']->getRow($sql);
    if ($row && $row['oi']) {
        $sub_order = $row['oi'];
        $arr = array('sub_order' => $sub_order);
        return $arr;
    } else {
        return 0;
    }
}

//输出访问者统计
function viewip($ru_id){

    $date_start = local_mktime(0, 0, 0, date('m', time()), date('d', time()), date('Y', time()));
    $date_end = local_mktime(23, 59, 59, date('m', time()), date('d', time()), date('Y', time()));

    $sql = "SELECT COUNT(i.ipid) AS ip " . " FROM " . $GLOBALS['ecs']->table('source_ip') . " AS i " .
            "LEFT JOIN " . $GLOBALS['ecs']->table('seller_shopinfo') . "AS s ON i.storeid = s.ru_id " .
            " WHERE i.iptime BETWEEN " . $date_start . ' AND ' . $date_end . " AND i.storeid = '" . $ru_id . "'";
    $row = $GLOBALS['db']->getRow($sql);
    if ($row && $row['ip']) {
        $todaycount = $row['ip'];
        $arr = array('todaycount' => $todaycount);
        return $arr;
    } else {
        return 0;
    }
}


/*
* 销量查询
* 订单状态未已确认、已付款、非未发货订单
* 计算总金额的条件同计算佣金的条件
* @param   string ru_id 商家ID
* @param   string where 时间条件
*/
function query_sales($ru_id = 0,$where = ''){
    $sql = " SELECT COUNT(oi.order_id) order_total,IFNULL(SUM(" . order_commission_field('oi.') . "),0) money_total FROM " .
            $GLOBALS['ecs']->table('order_info') . "oi " .
            " WHERE 1 AND oi.order_id IN (SELECT order_id FROM " . $GLOBALS['ecs']->table('order_goods') . " WHERE ru_id = '$ru_id' ) " . " AND oi.pay_status = 2" . $where .
            " AND (select count(*) from " . $GLOBALS['ecs']->table('order_info') . " as oi2 where oi2.main_order_id = oi.order_id) = 0 ";  //主订单下有子订单时，则主订单不显示
    return $sql;
}

/*
*待评价查询
*/
function get_order_no_comment($ru_id = 0, $sign = 0){
	$where = " AND oi.order_status " . db_create_in(array(OS_CONFIRMED, OS_SPLITED)) ."  AND oi.shipping_status = '" .SS_RECEIVED. "' AND oi.pay_status " . db_create_in(array(PS_PAYED, PS_PAYING));
	$where .= " AND (SELECT count(*) FROM " .$GLOBALS['ecs']->table('order_info'). " AS oi2 WHERE oi2.main_order_id = og.order_id) = 0 ";  //主订单下有子订单时，则主订单不显示
	if($sign == 0){
        $where .= " AND (SELECT count(*) FROM " .$GLOBALS['ecs']->table('comment'). " AS c WHERE c.comment_type = 0 AND c.id_value = g.goods_id AND c.rec_id = og.rec_id AND c.parent_id = 0 AND c.ru_id = '$ru_id') = 0 ";
    }
	$sql = "SELECT count(*) FROM " .$GLOBALS['ecs']->table('order_goods') ." AS og ".
                "LEFT JOIN ". $GLOBALS['ecs']->table('order_info') ." AS oi ON og.order_id = oi.order_id ".
                "LEFT JOIN  ". $GLOBALS['ecs']->table('goods') ." AS g ON og.goods_id = g.goods_id ".
                "WHERE og.ru_id = '$ru_id' $where ";
    $arr = $GLOBALS['db']->getOne($sql);
	return $arr;
}

/*
* 判断商家年审剩余时间
*/
function surplus_time($ru_id) {

    if ($_SESSION['verify_time']) {
        $sql = " SELECT ru_id, grade_id, add_time, year_num FROM " . $GLOBALS['ecs']->table('merchants_grade') . " WHERE ru_id = '$ru_id' " .
                " ORDER BY id DESC LIMIT 1 ";
        $row = $GLOBALS['db']->getRow($sql);

        $time = gmtime();
        $year = 1 * 60 * 60 * 24 * 365; //一年
        $month = 1 * 60 * 60 * 24 * 30; //一个月
        $enter_overtime = $row['add_time'] + $row['year_num'] * $year; //审核结束时间
        $two_month_later = local_strtotime('+2 months'); //2个月后
        $one_month_later = local_strtotime('+1 months'); //1个月后
        $minus = $enter_overtime - $time;
        $days = (local_date('d', $minus) > 0) ? intval(local_date('d', $minus)) : 0;
        unset($_SESSION['verify_time']);
        //var_dump($enter_overtime,$three_month_later);exit;
        if ($enter_overtime <= $time) {//审核过期
            $sql = " UPDATE " . $GLOBALS['ecs']->table('merchants_shop_information') . " SET merchants_audit = 0 WHERE user_id = '$ru_id' ";
            $GLOBALS['db']->query($sql);
            sys_msg('审核已过期，请联系平台续费后重试', 1);
            return false;
        } elseif ($enter_overtime < $one_month_later) {//审核过期前30天
            $link[] = array('text' => $_LANG['back_list'], 'href' => "index.php");
            $content = " 离审核过期剩余不足" . $days . "天，请尽快提交年审资料尽快续费 ";
            sys_msg($content, 0, $link);
        } elseif ($enter_overtime < $two_month_later) {//审核过期前60天
            $link[] = array('text' => $_LANG['back_list'], 'href' => "index.php");
            sys_msg('离审核过期不足2个月，请尽快提交年审资料尽快续费', 0, $link);
        } else {//未到提醒期
            return true;
        }
    } else {
        return true;
    }
}

?>
