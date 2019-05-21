<?php

define('IN_ECS', true);
require dirname(__FILE__) . '/includes/init.php';
require ROOT_PATH . 'includes/lib_area.php';
require ROOT_PATH . 'includes/lib_order.php';
require ROOT_PATH . 'includes/lib_wholesale.php';
require_once ROOT_PATH . 'languages/' . $_CFG['lang'] . '/user.php';
require_once ROOT_PATH . 'languages/' . $_CFG['lang'] . '/shopping_flow.php';
$area_info = get_area_info($province_id);
$area_id = $area_info['region_id'];
$where = 'regionId = \'' . $province_id . '\'';
$date = array('parent_id');
$region_id = get_table_date('region_warehouse', $where, $date, 2);
if (isset($_COOKIE['region_id']) && !(empty($_COOKIE['region_id']))) 
{
	$region_id = $_COOKIE['region_id'];
}
$smarty->assign('keywords', htmlspecialchars($_CFG['shop_keywords']));
$smarty->assign('description', htmlspecialchars($_CFG['shop_desc']));
if (!(isset($_REQUEST['step']))) 
{
	$_REQUEST['step'] = 'cart';
}
if (!(empty($_SESSION['user_id']))) 
{
	$sess_id = ' user_id = \'' . $_SESSION['user_id'] . '\' ';
	$a_sess = ' a.user_id = \'' . $_SESSION['user_id'] . '\' ';
	$b_sess = ' b.user_id = \'' . $_SESSION['user_id'] . '\' ';
	$c_sess = ' c.user_id = \'' . $_SESSION['user_id'] . '\' ';
	$sess = '';
}
else 
{
	$sess_id = ' session_id = \'' . real_cart_mac_ip() . '\' ';
	$a_sess = ' a.session_id = \'' . real_cart_mac_ip() . '\' ';
	$b_sess = ' b.session_id = \'' . real_cart_mac_ip() . '\' ';
	$c_sess = ' c.session_id = \'' . real_cart_mac_ip() . '\' ';
	$sess = real_cart_mac_ip();
}
assign_template();
$position = assign_ur_here(0, $_LANG['shopping_flow']);
$smarty->assign('page_title', $position['title']);
$smarty->assign('ur_here', $position['ur_here']);
$smarty->assign('helps', get_shop_help());
$smarty->assign('lang', $_LANG);
$smarty->assign('show_marketprice', $_CFG['show_marketprice']);
if (defined('THEME_EXTENSION')) 
{
	$business_cate = get_wholesale_cat();
	$smarty->assign('business_cate', $business_cate);
}
$smarty->assign('data_dir', DATA_DIR);
$smarty->assign('user_id', $_SESSION['user_id']);
if ($_REQUEST['step'] == 'add_to_cart') 
{
    priv();
	include_once 'includes/cls_json.php';
	$json = new JSON();
	$result = array('error' => 0, 'message' => '', 'content' => '');
    $act_id = (empty($_REQUEST['act_id']) ? 0 : intval($_REQUEST['act_id']));

    //* 查询：取得参数：批发活动id */
    if (!$act_id) {
        exit($json->encode($result));
    }

    //获取批发活动产品信息
    $goods_id = get_table_date('wholesale', 'act_id=\'' . $act_id . '\'', array('goods_id'), 2);
    if (!$goods_id) {
        exit($json->encode($result));
    }

	$goods_type = get_table_date('wholesale', 'goods_id=\'' . $goods_id . '\'', array('goods_type'), 2);

    //by zxk 获取商品规格
    $properties = get_wholesale_goods_properties($goods_id, $region_id, $area_id);
    $specscount = count($properties['spe']);

    /* 更新：清空进货单中所有团购商品 */
    include_once(ROOT_PATH . 'includes/lib_order.php');
//    clear_cart(CART_WHOLESALE_GOODS);
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
	$price_info = calculate_goods_price($act_id, $total_number, 'wholesale');
	$goods_info = get_table_date('goods', 'goods_id=\'' . $goods_id . '\'', array('goods_name, goods_sn, user_id, freight, tid'));
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
    $common_data['freight']  = $goods_info['freight'];
    $common_data['tid']  = $goods_info['tid'];
	if (0 < $specscount)
	{

		foreach ($attr_array as $key => $val )
		{
			$attr = explode(',', $val);
			$data = $common_data;
			$gooda_attr = lib_wholesale_get_goods_attr_array($val);

			foreach ($gooda_attr as $v )
			{
				$data['goods_attr'] .= $v['attr_name'] . ':' . $v['attr_value'] . "\n";
			}
			$data['goods_attr_id'] = $val;
			$data['goods_number'] = $num_array[$key];
			$set = get_find_in_set($attr, 'goods_attr_id', ',');
			$sql = ' SELECT rec_id FROM ' . $GLOBALS['ecs']->table('cart') . ' WHERE ' . $sess_id . ' AND goods_id = \'' . $goods_id . '\' ' . $set . ' ';
			$rec_id = $GLOBALS['db']->getOne($sql);
			if (!(empty($rec_id)))
			{
				$db->autoExecute($ecs->table('cart'), $data, 'UPDATE', 'rec_id=\'' . $rec_id . '\'');
			}
			else
			{
				$db->autoExecute($ecs->table('cart'), $data, 'INSERT');
			}
		}
	}
	else
	{
		$data = $common_data;
		$data['goods_number'] = $goods_number;
		$sql = ' SELECT rec_id FROM ' . $GLOBALS['ecs']->table('cart') . ' WHERE ' . $sess_id . ' AND goods_id = \'' . $goods_id . '\' ';
		$rec_id = $GLOBALS['db']->getOne($sql);
		if (!(empty($rec_id)))
		{
			$db->autoExecute($ecs->table('cart'), $data, 'UPDATE', 'rec_id=\'' . $rec_id . '\'');
		}
		else
		{
			$db->autoExecute($ecs->table('cart'), $data, 'INSERT');
		}
	}

    calculate_cart_goods_price($goods_id, '', 'wholesale', $act_id);
    $cart_info = insert_cart_info(1);
    $result['cart_num'] = $cart_info['number'];
	exit($json->encode($result));
}
elseif ($_REQUEST['step'] == 'checkout')
{
    //添加批发检查菜单
    /**
     * 初始化红包、优惠券、储值卡
     */
    unset($_SESSION['flow_order']['bonus_id']);
    unset($_SESSION['flow_order']['uc_id']);
    unset($_SESSION['flow_order']['vc_id']);

    $sc_rand = rand(1000, 9999);
    $sc_guid = sc_guid();

    $account_cookie = MD5($sc_guid . "-" . $sc_rand);
    setcookie('done_cookie', $account_cookie, gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);

    $smarty->assign('sc_guid', $sc_guid);
    $smarty->assign('sc_rand', $sc_rand);

    //@author-bylu 检测当前用户白条相关权限(是否逾期,逾期不能下单);
    //这里主要是为了防止用户在逾期前进货单中已存在商品,之后逾期通过进货单"结算"入口下单;
    bt_auth_check($stges_qishu=null,$is_jiesuan=true);

    //检测用户是否是采购商(不是不能下单)
    /* 取得购物类型 */
    $flow_type = 7;

    //配送方式--自提点标识
    $_SESSION['merchants_shipping'] = array();

    //ecmoban模板堂 --zhuo
    $store_id = isset($_REQUEST['store_id'])  ? intval($_REQUEST['store_id']) : 0;  // by kong 20160721 门店id

    $cart_value = get_cart_value($flow_type);

    //缓存进货单信息
    $_SESSION['cart_value'] = $cart_value;
    $smarty->assign('cart_value', $cart_value);

    /* 检查进货单中是否有商品 */
    $sql = "SELECT COUNT(*) FROM " . $ecs->table('cart') .
        " WHERE " .$sess_id;

    if ($db->getOne($sql) == 0)
    {
        show_message($_LANG['no_goods_in_cart'], '', '', 'warning');
    }

    /*
   * 检查用户是否已经登录
   * 如果用户已经登录了则检查是否有默认的收货地址
   * 如果没有登录则跳转到登录和注册页面
   */
    if ($_SESSION['user_id'] == 0)
    {
        /* 用户没有登录且没有选定匿名购物，转向到登录页面 */
        ecs_header("Location: user.php\n");
        exit;
    }

    //取得收货人默认地址
    $consignee = get_consignee($_SESSION['user_id']);
    if($consignee){
        setcookie('province', $consignee['province'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
        setcookie('city', $consignee['city'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
        setcookie('district', $consignee['district'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
        setcookie('street', $consignee['street'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
        setcookie('street_area', '', gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);

        $flow_warehouse = get_warehouse_goods_region($consignee['province']);
        setcookie('area_region', $flow_warehouse['region_id'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
        setcookie('flow_region', $flow_warehouse['region_id'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
    }

    $region_id = get_province_id_warehouse($consignee['province']);
    $area_info = get_area_info($consignee['province']);

    $smarty->assign('warehouse_id', $region_id);
    $smarty->assign('area_id', $area_info['region_id']);

    //ecmoban模板堂 --zhuo start 审核收货人地址
    $user_address = get_order_user_address_list($_SESSION['user_id']);

//    if($direct_shopping != 1 && !empty($_SESSION['user_id'])){
//        $_SESSION['browse_trace'] = "flow.php";
//    }else{
//        $_SESSION['browse_trace'] = "flow.php?step=checkout";
//    }

    //默认地址不存在
    if(!$user_address && $consignee){
        $consignee['province_name'] = get_goods_region_name($consignee['province']);
        $consignee['city_name'] = get_goods_region_name($consignee['city']);
        $consignee['district_name'] = get_goods_region_name($consignee['district']);
        $consignee['street_name'] = get_goods_region_name($consignee['street']);
        $consignee['region'] = $consignee['province_name'] ."&nbsp;". $consignee['city_name'] ."&nbsp;". $consignee['district_name'] ."&nbsp;". $consignee['street_name'];

        $user_address = array($consignee);
    }

    $smarty->assign('user_address', $user_address);
    $smarty->assign('auditStatus', $_CFG['auditStatus']);

    //有存在虚拟和实体商品 start
    //有存在虚拟和实体商品 end

    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    $smarty->assign('user_id', $user_id);

    //ecmoban模板堂 --zhuo end 审核收货人地址

    /* 初始化地区ID */
    $consignee['country']       = !isset($consignee['country']) && empty($consignee['country'])                ?    0 :   intval($consignee['country']);
    $consignee['province']      = !isset($consignee['province']) && empty($consignee['province'])              ?    0 :   intval($consignee['province']);
    $consignee['city']          = !isset($consignee['city']) && empty($consignee['city'])                      ?    0 :   intval($consignee['city']);
    $consignee['district']      = !isset($consignee['district']) && empty($consignee['district'])              ?    0 :   intval($consignee['district']);
    $consignee['street']        = !isset($consignee['street']) && empty($consignee['street'])                  ?    0 :   intval($consignee['street']);

    $_SESSION['flow_consignee'] = $consignee;


    $consignee['province_name'] = get_goods_region_name($consignee['province']);
    $consignee['city_name'] = get_goods_region_name($consignee['city']);
    $consignee['district_name'] = get_goods_region_name($consignee['district']);
    $consignee['street_name'] = get_goods_region_name($consignee['street']);
    $consignee['consignee_address'] = $consignee['province_name'] . $consignee['city_name'] . $consignee['district_name'] . $consignee['street_name'] . $consignee['address'];
    $smarty->assign('consignee', $consignee);

    /* 对商品信息赋值 */
    $cart_goods_list = wholesale_cart_goods(0, $cart_value);

    $smarty->assign('provinces', get_regions(1, 1));
    if($store_id > 0){

    }


    /*获取门店信息  by kong 20160721 start*/
    /*获取门店信息  by kong 20160721 end*/

    $smarty->assign('store_id',$store_id);
    $smarty->assign('cart_value',$cart_value);
    $smarty->assign('is_address',$is_address);


    $cart_goods_number = wholesale_get_buy_cart_goods_number($cart_value);
    $smarty->assign('cart_goods_number', $cart_goods_number);


    /* 对是否允许修改进货单赋值 */
    if ($flow_type != CART_GENERAL_GOODS || $_CFG['one_step_buy'] == '1')
    {
        $smarty->assign('allow_edit_cart', 0);
    }
    else
    {
        $smarty->assign('allow_edit_cart', 1);
    }

    /*
    * 取得购物流程设置
    */
    $smarty->assign('config', $_CFG);


    /* 如果能开发票，取得发票内容列表 */
    if ((!isset($_CFG['can_invoice']) || $_CFG['can_invoice'] == '1')
        && isset($_CFG['invoice_content'])
        && trim($_CFG['invoice_content']) != '' && $flow_type != CART_EXCHANGE_GOODS)
    {
        $inv_content_list = explode("\n", str_replace("\r", '', $_CFG['invoice_content']));
        $smarty->assign('inv_content', $inv_content_list[0]);
        //默认发票计算
        $order['need_inv']    = 1;
        $order['inv_type']    = $_CFG['invoice_type']['type'][0];
        $order['inv_payee']   = '个人';
        $order['inv_content'] = $inv_content_list[0];
    }


    //用户地址
    if(!$user_address){
        $consignee = array(
            'province' => 0,
            'city' => 0
        );
        // 取得国家列表、商店所在国家、商店所在国家的省列表
        $smarty->assign('country_list',       get_regions());
        $smarty->assign('please_select',       $_LANG['please_select']);

        $province_list = get_regions_log(1,1);
        $city_list     = get_regions_log(2, $consignee['province']);
        $district_list = get_regions_log(3, $consignee['city']);

        $smarty->assign('province_list', $province_list);
        $smarty->assign('city_list',     $city_list);
        $smarty->assign('district_list', $district_list);
        $smarty->assign('consignee', $consignee);
    }

    /*
     * 计算订单的费用
     */
    $cart_goods = get_new_group_cart_goods($cart_goods_list);

    $total = wholesale_order_fee($cart_goods, $consignee);
    $smarty->assign('total', $total);

    /* 取得支付列表 */
    if ($order['shipping_id'] == 0)
    {
        $cod        = true;
        $cod_fee    = 0;
    }
    else
    {
        $shipping = shipping_info($order['shipping_id']);
        $cod = $shipping['support_cod'];

        if ($cod)
        {
            /* 如果是团购，且保证金大于0，不能使用货到付款 */
            if ($flow_type == CART_GROUP_BUY_GOODS)
            {
                $group_buy_id = $_SESSION['extension_id'];
                if ($group_buy_id <= 0)
                {
                    show_message('error group_buy_id');
                }
                $group_buy = group_buy_info($group_buy_id);
                if (empty($group_buy))
                {
                    show_message('group buy not exists: ' . $group_buy_id);
                }

                if ($group_buy['deposit'] > 0)
                {
                    $cod = false;
                    $cod_fee = 0;

                    /* 赋值保证金 */
                    $smarty->assign('gb_deposit', $group_buy['deposit']);
                }
            }

            if ($cod)
            {
                $shipping_area_info = shipping_info($order['shipping_id']);
                $cod_fee            = isset($shipping_area_info['pay_fee']) ? $shipping_area_info['pay_fee'] : 0;
            }
        }
        else
        {
            $cod_fee = 0;
        }
    }

    $order = flow_order_info();
    /* 订单信息 */
    $smarty->assign('order', $order);

    // 给货到付款的手续费加<span id>，以便改变配送的时候动态显示
    $payment_list = available_payment_list(1, $cod_fee);

    if(isset($payment_list))
    {
        foreach ($payment_list as $key => $payment)
        {
            //ecmoban模板堂 --will start
            //pc端去除ecjia的支付方式
            if (substr($payment['pay_code'], 0 , 4) == 'pay_') {
                unset($payment_list[$key]);
                continue;
            }
            //ecmoban模板堂 --will end

            if ($payment['is_cod'] == '1')
            {
                $payment_list[$key]['format_pay_fee'] = '<span id="ECS_CODFEE">' . $payment['format_pay_fee'] . '</span>';
            }
            /* 如果有易宝神州行支付 如果订单金额大于300 则不显示 */
            if ($payment['pay_code'] == 'yeepayszx' && $total['amount'] > 300)
            {
                unset($payment_list[$key]);
            }

            if ($payment['pay_code'] == 'alipay_wap') {
                unset($payment_list[$key]);
            }

            /* 如果有余额支付 */
            if ($payment['pay_code'] == 'balance')
            {
                /* 如果未登录，不显示 */
                if ($_SESSION['user_id'] == 0)
                {
                    unset($payment_list[$key]);
                }
                else
                {
                    if ($_SESSION['flow_order']['pay_id'] == $payment['pay_id'])
                    {
                        $smarty->assign('disable_surplus', 1);
                    }
                }
            }
        }
    }

    //@模板堂-bylu 过滤掉在线支付的方法(余额支付,支付宝等等),因为订单结算页只允许显示一个在线支付按钮 start
    foreach ($payment_list as $k => $v) {
        if ($v['is_online'] == 1) {
            unset($payment_list[$k]);
        }
    }

    //@模板堂-bylu  end
    $smarty->assign('payment_list', $payment_list);

}
else if ($_REQUEST['step'] == 'done') 
{
	$common_data['consignee'] = (empty($_REQUEST['consignee']) ? '' : trim($_REQUEST['consignee']));
	$common_data['mobile'] = (empty($_REQUEST['mobile']) ? '' : trim($_REQUEST['mobile']));
	$common_data['address'] = (empty($_REQUEST['address']) ? '' : trim($_REQUEST['address']));
	$common_data['inv_type'] = (empty($_REQUEST['inv_type']) ? 0 : intval($_REQUEST['inv_type']));
	$common_data['pay_id'] = (empty($_REQUEST['pay_id']) ? 0 : intval($_REQUEST['pay_id']));
	$common_data['postscript'] = (empty($_REQUEST['postscript']) ? '' : trim($_REQUEST['postscript']));
	$common_data['inv_payee'] = (empty($_REQUEST['inv_payee']) ? '' : trim($_REQUEST['inv_payee']));
	$common_data['tax_id'] = (empty($_REQUEST['tax_id']) ? '' : trim($_REQUEST['tax_id']));
	$main_order = $common_data;
	$main_order['order_sn'] = get_order_sn();
	$main_order['main_order_id'] = 0;
	$main_order['user_id'] = $_SESSION['user_id'];
	$main_order['add_time'] = gmtime();
	$main_order['order_amount'] = 0;
	$GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('wholesale_order_info'), $main_order, 'INSERT');
	$main_order_id = $GLOBALS['db']->insert_id();
	$rec_ids = (empty($_REQUEST['rec_ids']) ? '' : implode(',', $_REQUEST['rec_ids']));
	$where = ' WHERE user_id = \'' . $_SESSION['user_id'] . '\' AND rec_id IN (' . $rec_ids . ') ';
	if (empty($rec_ids)) 
	{
	}
	$sql = ' SELECT DISTINCT ru_id FROM ' . $GLOBALS['ecs']->table('wholesale_cart') . $where;
	$ru_ids = $GLOBALS['db']->getCol($sql);
	foreach ($ru_ids as $key => $val ) 
	{
		$child_order = $common_data;
		$child_order['order_sn'] = get_order_sn();
		$child_order['main_order_id'] = $main_order_id;
		$child_order['user_id'] = $_SESSION['user_id'];
		$child_order['add_time'] = gmtime();
		$child_order['order_amount'] = 0;
		$GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('wholesale_order_info'), $child_order, 'INSERT');
		$child_order_id = $GLOBALS['db']->insert_id();
		$sql = ' SELECT goods_id, goods_name, goods_sn, goods_number, goods_price, goods_attr, goods_attr_id, ru_id FROM ' . $GLOBALS['ecs']->table('wholesale_cart') . $where . ' AND ru_id = \'' . $val . '\' ';
		$cart_goods = $GLOBALS['db']->getAll($sql);
		foreach ($cart_goods as $k => $v ) 
		{
			$v['order_id'] = $child_order_id;
			$GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('wholesale_order_goods'), $v, 'INSERT');
			$child_order['order_amount'] += $v['goods_price'] * $v['goods_number'];
		}
		$GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('wholesale_order_info'), $child_order, 'update', 'order_id =\'' . $child_order_id . '\'');
		$main_order['order_amount'] += $child_order['order_amount'];
	}
	$GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('wholesale_order_info'), $main_order, 'update', 'order_id =\'' . $main_order_id . '\'');
	$sql = ' DELETE FROM ' . $GLOBALS['ecs']->table('wholesale_cart') . $where;
	$GLOBALS['db']->query($sql);
	show_message($_LANG['wholesale_flow_prompt'], $_LANG['wholesale_back_home'], 'wholesale.php', 'info');
}
else if ($_REQUEST['step'] == 'remove') 
{
	require_once ROOT_PATH . 'includes/cls_json.php';
	$json = new JSON();
	$result = array('error' => 0, 'message' => '', 'content' => '');
	$goods_id = (empty($_REQUEST['goods_id']) ? 0 : intval($_REQUEST['goods_id']));
	if (!(empty($goods_id))) 
	{
		$sess_id .= ' AND goods_id = \'' . $goods_id . '\' ';
		$sql = ' DELETE FROM ' . $GLOBALS['ecs']->table('wholesale_cart') . ' WHERE ' . $sess_id . ' ';
		$GLOBALS['db']->query($sql);
	}
	exit($json->encode($result));
}
else if ($_REQUEST['step'] == 'batch_remove') 
{
	require_once ROOT_PATH . 'includes/cls_json.php';
	$json = new JSON();
	$result = array('error' => 0, 'message' => '', 'content' => '');
	$goods_id = (empty($_REQUEST['goods_id']) ? '' : trim($_REQUEST['goods_id']));
	if (!(empty($goods_id))) 
	{
		$sess_id .= ' AND goods_id IN (' . $goods_id . ') ';
		$sql = ' DELETE FROM ' . $GLOBALS['ecs']->table('wholesale_cart') . ' WHERE ' . $sess_id . ' ';
		$GLOBALS['db']->query($sql);
	}
	exit($json->encode($result));
}
else if ($_REQUEST['step'] == 'ajax_update_cart') 
{
	require_once ROOT_PATH . 'includes/cls_json.php';
	$json = new JSON();
	$result = array('error' => 0, 'message' => '', 'content' => '');
	$rec_ids = (empty($_REQUEST['rec_ids']) ? array() : $_REQUEST['rec_ids']);
	$rec_ids = implode(',', $rec_ids);
	$cart_goods = wholesale_cart_goods(0, $rec_ids);
	$goods_list = array();
	foreach ($cart_goods as $key => $val ) 
	{
		foreach ($val['goods_list'] as $k => $g ) 
		{
			$smarty->assign('goods', $g);
			$g['volume_price_lbi'] = $smarty->fetch('library/wholesale_cart_volume_price.lbi');
			$goods_list[$g['goods_id']] = $g;
		}
	}
	$result['goods_list'] = $goods_list;
	$cart_info = wholesale_cart_info(0, $rec_ids);
	$result['cart_info'] = $cart_info;
	exit($json->encode($result));
}
else if ($_REQUEST['step'] == 'update_rec_num') 
{
	require_once ROOT_PATH . 'includes/cls_json.php';
	$json = new JSON();
	$result = array('error' => 0, 'message' => '', 'content' => '');
	$rec_id = (empty($_REQUEST['rec_id']) ? 0 : intval($_REQUEST['rec_id']));
	$rec_num = (empty($_REQUEST['rec_num']) ? 0 : intval($_REQUEST['rec_num']));
	$cart_info = get_table_date('wholesale_cart', 'rec_id=\'' . $rec_id . '\'', array('goods_id', 'goods_attr_id'));
	if (empty($cart_info['goods_attr_id'])) 
	{
		$goods_number = get_table_date('wholesale', 'goods_id=\'' . $cart_info['goods_id'] . '\'', array('goods_number'), 2);
	}
	else 
	{
		$set = get_find_in_set(explode(',', $cart_info['goods_attr_id']));
		$goods_number = get_table_date('wholesale_products', 'goods_id=\'' . $cart_info['goods_id'] . '\' ' . $set, array('product_number'), 2);
	}
	$result['goods_number'] = $goods_number;
	if ($goods_number < $rec_num) 
	{
		$result['error'] = 1;
		$result['message'] = '该商品库存只有' . $goods_number . '个';
		$rec_num = $goods_number;
	}
	$sql = ' UPDATE ' . $GLOBALS['ecs']->table('wholesale_cart') . ' SET goods_number = \'' . $rec_num . '\' WHERE rec_id = \'' . $rec_id . '\' ';
	$GLOBALS['db']->query($sql);
	exit($json->encode($result));
}
else if ($_REQUEST['step'] == 'update_cart') 
{
}
else if ($_REQUEST['step'] == 'clear') 
{
	$sql = 'DELETE FROM ' . $ecs->table('wholesale_cart') . ' WHERE ' . $sess_id;
	$db->query($sql);
	ecs_header('Location:./' . "\n");
}
else 
{
	$goods_id = (empty($_REQUEST['goods_id']) ? 0 : trim($_REQUEST['goods_id']));
	$rec_ids = (empty($_REQUEST['rec_ids']) ? '' : trim($_REQUEST['rec_ids']));
	$goods_data = wholesale_cart_goods($goods_id, $rec_ids);
	$smarty->assign('goods_data', $goods_data);
	$cart_info = wholesale_cart_info($goods_id, $rec_ids);
	$smarty->assign('cart_info', $cart_info);
}
$history_goods = get_history_goods(0, $region_id, $area_id);
$smarty->assign('history_goods', $history_goods);
$smarty->assign('historyGoods_count', count($history_goods));
$smarty->assign('currency_format', $_CFG['currency_format']);
$smarty->assign('integral_scale', price_format($_CFG['integral_scale']));
$smarty->assign('step', $_REQUEST['step']);
assign_dynamic('shopping_flow');
$smarty->display('wholesale_flow.dwt');

//获取进货单信息
function get_cart_value() {
    if (!empty($_SESSION['user_id'])) {
        $c_sess = " c.user_id = '" . $_SESSION['user_id'] . "' ";
    } else {
        show_message('请登录', '', '', 'warning');
    }

    $sql = "SELECT c.rec_id FROM " . $GLOBALS['ecs']->table('wholesale_cart') .
        " AS c LEFT JOIN " . $GLOBALS['ecs']->table('goods') .
        " AS g ON c.goods_id = g.goods_id WHERE " . $c_sess . "order by c.rec_id asc";


    $goods_list = $GLOBALS['db']->getAll($sql);

    $rec_id = '';
    if ($goods_list) {
        foreach ($goods_list as $key => $row) {
            $rec_id .= $row['rec_id'] . ',';
        }

        $rec_id = substr($rec_id, 0, -1);
    }

    return $rec_id;

}

/**
 * 重新组合购物流程商品数组
 */
function get_new_group_cart_goods($cart_goods_list_new){
    $car_goods = array();
    foreach($cart_goods_list_new as $key=>$goods){
        foreach($goods['goods_list'] as $k => $list){
            $car_goods[] = $list;
        }
    }

    return $car_goods;
}

?>