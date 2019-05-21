<?php

/**
 * ECSHOP 订单管理
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
require_once(ROOT_PATH . 'includes/lib_order.php');
require_once(ROOT_PATH . 'includes/lib_goods.php');

//商家单个权限 ecmoban模板堂 start
$user_action_list = get_user_action_list($_SESSION['admin_id']);

$order_back_apply = get_merchants_permissions($user_action_list, 'order_back_apply');
$smarty->assign('order_back_apply', $order_back_apply); //退换货权限

$order_os_remove = get_merchants_permissions($user_action_list, 'order_os_remove');
$smarty->assign('order_os_remove', $order_os_remove); //订单删除
//商家单个权限 ecmoban模板堂 end

//ecmoban模板堂 --zhuo start
$adminru = get_admin_ru_id();

if($adminru['ru_id'] == 0){
    $smarty->assign('priv_ru',   1);
}else{
    $smarty->assign('priv_ru',   0);
}

timing_cancel_order();

//ecmoban模板堂 --zhuo end
/*------------------------------------------------------ */
//-- 订单查询
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'order_query')
{
    /* 检查权限 */
    admin_priv('order_view');

    /* 载入配送方式 */
    $smarty->assign('shipping_list', shipping_list());

    /* 载入支付方式 */
    $smarty->assign('pay_list', payment_list());

    /* 载入国家 */
    $smarty->assign('country_list', get_regions());

    /* 载入订单状态、付款状态、发货状态 */
    $smarty->assign('os_list', get_status_list('order'));
    $smarty->assign('ps_list', get_status_list('payment'));
    $smarty->assign('ss_list', get_status_list('shipping'));

    //ecmoban模板堂 --zhuo start
    /* 获得该管理员的权限 */
    $priv_str = $db->getOne("SELECT action_list FROM " .$ecs->table('admin_user'). " WHERE user_id = '" .$_SESSION['admin_id']. "'");

    /* 如果被编辑的管理员拥有了all这个权限，将不能编辑 */
    if ($priv_str == 'all')
    {
            $smarty->assign('priv_str',       $priv_str);
    }else{
            $smarty->assign('priv_str',       $priv_str);
    }
    //ecmoban模板堂 --zhuo end

	if(isset($_REQUEST['seller_order_list'])){
        $seller_order_list = "&seller_order_list=1";
    }else{
        $seller_order_list = '';
    }

    $smarty->assign('seller_order_list',   $seller_order_list);

    $store_list = get_common_store_list();
    $smarty->assign('store_list',        $store_list);

    /* 模板赋值 */
    $smarty->assign('ur_here', $_LANG['03_order_query']);
    $smarty->assign('action_link', array('href' => 'order.php?act=list' . $seller_order_list, 'text' => $_LANG['02_order_list']));

    /* 显示模板 */
    assign_query_info();
    $smarty->display('order_query.dwt');
}

/*------------------------------------------------------ */
//-- 修改设置自动确认收货的时间（天为单位） ecmoban模板堂 --zhuo
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'edit_auto_delivery_time')
{
    check_authz_json('order_edit');

    $order_id = intval($_POST['id']);
	$delivery_time = json_str_iconv(trim($_POST['val']));

    /* 删除数据 */
    $sql = "UPDATE " . $GLOBALS['ecs']->table('order_info') ." SET auto_delivery_time = '$delivery_time'". " WHERE order_id = '$order_id'";
    $GLOBALS['db']->query($sql);

    clear_cache_files();
    make_json_result($delivery_time);
}

/*------------------------------------------------------ */
//-- 订单列表
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'list')
{
    /* 检查权限 */
    admin_priv('order_view');

    $user_id = !empty($_REQUEST['user_id']) ? intval($_REQUEST['user_id']) : 0;
    $smarty->assign('user_id', $user_id);

	if(isset($_REQUEST['seller_order_list'])){
        $seller_order = 1;
        $seller_order_list = "&seller_order_list=1";
    }else{
        $seller_order = 0;
        $seller_order_list = '';
    }

    $smarty->assign('seller_order',   $seller_order);

    /* 模板赋值 */
    $smarty->assign('ur_here', $_LANG['02_order_list']);
    $smarty->assign('action_link', array('href' => 'order.php?act=order_query' . $seller_order_list, 'text' => $_LANG['03_order_query']));

    //ecmoban模板堂 --zhuo start 订单导出
    $smarty->assign('action_link3', array('href' => 'javascript:download_orderlist();', 'text' => $_LANG['11_order_export']));
    //ecmoban模板堂 --zhuo end 订单导出
    if($user_id > 0){
        $smarty->assign('action_link2', array('href' => 'users.php?act=list', 'text' => $_LANG['02_order_list']));
    }

    $smarty->assign('status_list', $_LANG['cs']);   // 订单状态

    $smarty->assign('os_unconfirmed',   OS_UNCONFIRMED);
    $smarty->assign('cs_await_pay',     CS_AWAIT_PAY);
    $smarty->assign('cs_await_ship',    CS_AWAIT_SHIP);
    $smarty->assign('full_page',        1);

    $store_list = get_common_store_list();
    $smarty->assign('store_list',        $store_list);

    $order_list = order_list();

    /* 订单状态传值 */
    $composite_status = isset($_REQUEST['composite_status']) ? trim($_REQUEST['composite_status']) : -1;
    $smarty->assign('status', $composite_status);

    /* 订单内平台、店铺区分 */
    $smarty->assign('common_tabs', array('info' => isset($_REQUEST['seller_order_list']) ? 1 : 0 , 'url' => 'order.php?act=list'));

    $smarty->assign('order_list',   $order_list['orders']);
    $smarty->assign('filter',       $order_list['filter']);
    $smarty->assign('record_count', $order_list['record_count']);
    $smarty->assign('page_count',   $order_list['page_count']);
    $smarty->assign('sort_order_time', '<img src="images/sort_desc.gif">');

    //ecmoban模板堂 --zhuo start
	/* 获得该管理员的权限 */
    $priv_str = $db->getOne("SELECT action_list FROM " .$ecs->table('admin_user'). " WHERE user_id = '" .$_SESSION['admin_id']. "'");

    /* 如果被编辑的管理员拥有了all这个权限，将不能编辑 */
    if ($priv_str == 'all')
    {
        $smarty->assign('priv_str',       $priv_str);
    }else{
        $smarty->assign('priv_str',       $priv_str);
    }
    //ecmoban模板堂 --zhuo end

    /* 显示模板 */
    assign_query_info();
    $smarty->display('order_list.dwt');
}

/**
 * 退换货订单
 * by Leah
 */
elseif ($_REQUEST['act'] == 'return_list') {

    /* 检查权限 */
    admin_priv('order_back_apply');
    /* 模板赋值 */
    $smarty->assign('ur_here', $_LANG['12_back_apply']);
    $smarty->assign('action_link', array('href' => 'order.php?act=order_query', 'text' => $_LANG['03_order_query']));

    $smarty->assign('full_page', 1);
    $order_list = return_order_list();
    $smarty->assign('order_list', $order_list['orders']);
    $smarty->assign('filter', $order_list['filter']);
    $smarty->assign('record_count', $order_list['record_count']);
    $smarty->assign('page_count', $order_list['page_count']);
    /* 订单内平台、店铺区分 */
    $smarty->assign('common_tabs', array('info' => isset($_REQUEST['seller_order_list']) ? 1 : 0 , 'url' => 'order.php?act=return_list'));

    $store_list = get_common_store_list();
    $smarty->assign('store_list',        $store_list);

    assign_query_info();
    $smarty->display('return_list.dwt');
}

/**
 * 退换货分页 by Leah
 */
elseif ($_REQUEST['act'] == 'return_list_query') {
    /* 检查权限 */
    admin_priv('order_view');
    /* 模板赋值 */
    $order_list = return_order_list();
    $smarty->assign('order_list', $order_list['orders']);
    $smarty->assign('filter', $order_list['filter']);
    $smarty->assign('record_count', $order_list['record_count']);
    $smarty->assign('page_count', $order_list['page_count']);

    $store_list = get_common_store_list();
    $smarty->assign('store_list',        $store_list);

    make_json_result($smarty->fetch('return_list.dwt'), '', array('filter' => $order_list['filter'], 'page_count' => $order_list['page_count']));
}

/* ------------------------------------------------------ */
//--Excel文件下载数组处理
/* ------------------------------------------------------ */
elseif($_REQUEST['act'] == 'ajax_download'){
    //by zxk 添加500条记录
    $_REQUEST['page_size'] = 500;

    require(ROOT_PATH . '/includes/cls_json.php');
    $json = new JSON;
    $result = array('is_stop' => 0);

    $page = !empty($_REQUEST['page_down'])  ?  intval($_REQUEST['page_down']) : 0;//处理的页数
    $page_count = !empty($_REQUEST['page_count'])  ?  intval($_REQUEST['page_count']) : 0;//总页数
    $order_list = order_list($page);//获取订单数组

    $_SESSION['merchants_download_content'][] = $order_list;
    $result['page'] = $page;
    if($page < $page_count){
        $result['is_stop'] = 1;//未结算标识
        $result['next_page'] = $page+1;
    }
   die($json->encode($result));
}
/* ------------------------------------------------------ */
//--Excel文件下载 订单下载
/* ------------------------------------------------------ */
if ($_REQUEST['act'] == 'order_download') {
    header("Content-Disposition: attachment; filename=订单导出-".date('YmdHis').".zip");
    header("Content-Type: application/unknown");
    // 获取所有商家的下载数据 按照商家分组
    include_once('includes/cls_phpzip.php');
    $order_list = $_SESSION['merchants_download_content'];
    $zip = new PHPZip;
    if(!empty($order_list)){
        foreach($order_list as $k=>$order){
            //by zxk 不存在 继续循环
            if (!$order['orders']) {
                continue;
            }
            $k++;
            $content =  download_orderlist($order['orders']);
            $zip->add_file($content,  date('YmdHis').'-'.$k.'.csv');
        }
    }
    unset($_SESSION['merchants_download_content']);//清空导出对象
    die($zip->file());
}

/*------------------------------------------------------ */
//-- 排序、分页、查询
/*------------------------------------------------------ */
 elseif ($_REQUEST['act'] == 'query') {
    /* 检查权限 */
    admin_priv('order_view');
    $order_list = order_list();

    //ecmoban模板堂 --zhuo start
    /* 获得该管理员的权限 */
    $priv_str = $db->getOne("SELECT action_list FROM " . $ecs->table('admin_user') . " WHERE user_id = '" . $_SESSION['admin_id'] . "'");

    /* 如果被编辑的管理员拥有了all这个权限，将不能编辑 */
    if ($priv_str == 'all') {
        $smarty->assign('priv_str', $priv_str);
    } else {
        $smarty->assign('priv_str', $priv_str);
    }

	if(isset($_REQUEST['seller_order_list'])){
        $seller_order = 1;
    }else{
        $seller_order = 0;
    }

    $smarty->assign('seller_order',   $seller_order);

    /* 订单状态传值 */
    $composite_status = isset($_REQUEST['composite_status']) ? trim($_REQUEST['composite_status']) : -1;
    $smarty->assign('status', $composite_status);
    $smarty->assign('action_link', array('href' => 'order.php?act=order_query', 'text' => $_LANG['03_order_query']));
    //ecmoban模板堂 --zhuo end
    $smarty->assign('order_list', $order_list['orders']);
    $smarty->assign('filter', $order_list['filter']);
    $smarty->assign('record_count', $order_list['record_count']);
    $smarty->assign('page_count', $order_list['page_count']);
    $sort_flag = sort_flag($order_list['filter']);
    $smarty->assign($sort_flag['tag'], $sort_flag['img']);

    make_json_result($smarty->fetch('order_list.dwt'), '', array('filter' => $order_list['filter'], 'page_count' => $order_list['page_count']));
}

/*------------------------------------------------------ */
//-- 订单详情页面
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'info')
{

    /* 根据订单id或订单号查询订单信息 */
    if (isset($_REQUEST['order_id']))
    {
        $order_id = intval($_REQUEST['order_id']);
        $order = order_info($order_id);
    }
    elseif (isset($_REQUEST['order_sn']))
    {
        $order_sn = trim($_REQUEST['order_sn']);
        $order = order_info(0, $order_sn);
    }
    else
    {
        /* 如果参数不存在，退出 */
        die('invalid parameter');
    }

    /* 处理确认收货时间 start */
    if($order['shipping_status'] == 2 && empty($order['confirm_take_time'])){
        $sql = "SELECT MAX(log_time) AS log_time FROM " .$GLOBALS['ecs']->table('order_action'). " WHERE order_id = '" .$order['order_id']. "' AND shipping_status = '" .SS_RECEIVED. "'";
        $log_time = $GLOBALS['db']->getOne($sql, true);

        $log_other = array(
            'confirm_take_time' => $log_time
        );

        $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('order_info'), $log_other, 'UPDATE', "order_id = '" .$order['order_id']. "'");

        $order['confirm_take_time'] = $log_time;
    }
    /* 处理确认收货时间 end */

    /* 查询更新支付状态 start */
    if (($order['order_status'] == OS_UNCONFIRMED || $order['order_status'] == OS_CONFIRMED || $order['order_status'] == OS_SPLITED) && $order['pay_status'] == PS_UNPAYED) {

        $pay_log = get_pay_log($order['order_id'], 1);
        if ($pay_log && $pay_log['is_paid'] == 0) {
            $payment = payment_info($order['pay_id']);

            $file_pay = ROOT_PATH . 'includes/modules/payment/' . $payment['pay_code'] . '.php';
            if ($payment && file_exists($file_pay)) {
                /* 调用相应的支付方式文件 */
                include_once($file_pay);

                /* 取得在线支付方式的支付按钮 */
                if (class_exists($payment['pay_code'])) {

                    $pay_obj = new $payment['pay_code'];
                    $is_callable = array($pay_obj, 'query');

                    /* 判断类对象方法是否存在 */
                    if (is_callable($is_callable)) {

                        $order_other = array(
                            'order_sn' => $order['order_sn'],
                            'log_id' => $pay_log['log_id']
                        );

                        $pay_obj->query($order_other);

                        $sql = "SELECT order_status, shipping_status, pay_status, pay_time FROM " . $GLOBALS['ecs']->table('order_info') . " WHERE order_id = '" . $order['order_id'] . "' LIMIT 1";
                        $order_info = $GLOBALS['db']->getRow($sql);
                        if ($order_info) {
                            $order['order_status'] = $order_info['order_status'];
                            $order['shipping_status'] = $order_info['shipping_status'];
                            $order['pay_status'] = $order_info['pay_status'];
                            $order['pay_time'] = $order_info['pay_time'];
                        }
                    }
                }
            }
        }
    }
    /* 查询更新支付状态 end */

    $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table("comment") . " WHERE order_id = '" . $order['order_id'] . "'";
    $commented = $GLOBALS['db']->getOne($sql);
    $order['is_commented'] = !empty($commented) ? 1 : 0;

    //获取支付方式code
    $pay_code = get_order_payment($order['pay_id']);

    if($pay_code == "cod" || $pay_code == "bank" || $pay_code == 'balance'){
        $smarty->assign('pay_code', 1);
    }else{
        $smarty->assign('pay_code', 0);
    }

    $order['delivery_id'] = $GLOBALS['db']->getOne("SELECT delivery_id FROM " . $ecs->table('delivery_order') . " WHERE order_sn = '" .$order['order_sn']. "'", true);

    //众筹信息 by wu start
    $zc_goods_info = get_zc_goods_info($order_id);
    $smarty->assign('zc_goods_info', $zc_goods_info);

    //ecmoban模板堂 --zhuo start
    if ($_CFG['open_delivery_time'] == 1) {

        /* 查询订单信息，检查状态 */
        $sql = "SELECT order_id, user_id, order_sn , order_status, shipping_status, pay_status, auto_delivery_time, add_time, pay_time, " .
                "order_amount, goods_amount, tax, invoice_type, shipping_fee, insure_fee, pay_fee, pack_fee, card_fee, shipping_time, " .
                "bonus, integral_money, coupons, discount, money_paid, surplus, confirm_take_time, tax_id, extension_code " .
                "FROM " . $GLOBALS['ecs']->table('order_info') . " WHERE order_id = '" .$order['order_id']. "' LIMIT 1";

        $orderInfo = $GLOBALS['db']->getRow($sql);

        $confirm_take_time = gmtime();
        if (($orderInfo['order_status'] == OS_CONFIRMED || $orderInfo['order_status'] == OS_SPLITED) && $orderInfo['shipping_status'] == SS_SHIPPED && $orderInfo['pay_status'] == PS_PAYED) { //发货状态
            $delivery_time = $orderInfo['shipping_time'] + 24 * 3600 * $orderInfo['auto_delivery_time'];

            if ($confirm_take_time > $delivery_time) { //自动确认发货操作

                $sql = "UPDATE " . $GLOBALS['ecs']->table('order_info') . " SET order_status = '" . OS_SPLITED . "', ".
                        "shipping_status = '" . SS_RECEIVED . "', pay_status = '" . PS_PAYED . "', confirm_take_time = '$confirm_take_time' WHERE order_id = '" .$order['order_id']. "'";
                if ($GLOBALS['db']->query($sql))
                {
                    /* 记录日志 */
                    order_action($orderInfo['order_sn'], $orderInfo['order_status'], SS_RECEIVED, $orderInfo['pay_status'], '', $GLOBALS['_LANG']['buyer'], 0, $confirm_take_time);

                    $seller_id = $GLOBALS['db']->getOne("SELECT ru_id FROM " .$GLOBALS['ecs']->table('order_goods'). " WHERE order_id = '" .$order['order_id']. "'", true);
                    $value_card = $GLOBALS['db']->getOne("SELECT use_val FROM " .$GLOBALS['ecs']->table('value_card_record'). " WHERE order_id = '" .$order['order_id']. "'", true);

                    $return_amount = get_order_return_amount($order['order_id']);

                    $other = array(
                        'user_id'               => $orderInfo['user_id'],
                        'seller_id'             => $seller_id,
                        'order_id'              => $orderInfo['order_id'],
                        'order_sn'              => $orderInfo['order_sn'],
                        'order_status'          => $orderInfo['order_status'],
                        'shipping_status'       => SS_RECEIVED,
                        'pay_status'            => $orderInfo['pay_status'],
                        'order_amount'          => $orderInfo['order_amount'],
                        'return_amount'         => $return_amount,
                        'goods_amount'          => $orderInfo['goods_amount'],
                        'tax'                   => $orderInfo['tax'],
                        'tax_id'                => $orderInfo['tax_id'],
                        'invoice_type'          => $orderInfo['invoice_type'],
                        'shipping_fee'          => $orderInfo['shipping_fee'],
                        'insure_fee'            => $orderInfo['insure_fee'],
                        'pay_fee'               => $orderInfo['pay_fee'],
                        'pack_fee'              => $orderInfo['pack_fee'],
                        'card_fee'              => $orderInfo['card_fee'],
                        'bonus'                 => $orderInfo['bonus'],
                        'integral_money'        => $orderInfo['integral_money'],
                        'coupons'               => $orderInfo['coupons'],
                        'discount'              => $orderInfo['discount'],
                        'value_card'            => $value_card,
                        'money_paid'            => $orderInfo['money_paid'],
                        'surplus'               => $orderInfo['surplus'],
                        'confirm_take_time'     => $confirm_take_time
                    );

                    if($seller_id){
                        $insert_id = get_order_bill_log($other);

                        /* by zxk jisuan*/
                        //确认收货 商家结算到可用金额
                        order_confirm_change($orderInfo['order_id'], $insert_id);
                        /* by zxk jisuan*/
                    }
                }
            }
        }
    }
    //ecmoban模板堂 --zhuo end

    /* 如果订单不存在，退出 */
    if (empty($order))
    {
        die('order does not exist');
    }

    /* 根据订单是否完成检查权限 */
    if (order_finished($order))
    {
        admin_priv('order_view_finished');
    }
    else
    {
        admin_priv('order_view');
    }

    /* 如果管理员属于某个办事处，检查该订单是否也属于这个办事处 */
    $sql = "SELECT agency_id FROM " . $ecs->table('admin_user') . " WHERE user_id = '$_SESSION[admin_id]'";
    $agency_id = $db->getOne($sql);
    if ($agency_id > 0)
    {
        if ($order['agency_id'] != $agency_id)
        {
            sys_msg($_LANG['priv_error']);
        }
    }

    /* 取得上一个、下一个订单号 */
    if (!empty($_COOKIE['ECSCP']['lastfilter']))
    {
        $filter = unserialize(urldecode($_COOKIE['ECSCP']['lastfilter']));
        if (!empty($filter['composite_status']))
        {
            $where = '';
            //综合状态
            switch($filter['composite_status'])
            {
                case CS_AWAIT_PAY :
                    $where .= order_query_sql('await_pay');
                    break;

                case CS_AWAIT_SHIP :
                    $where .= order_query_sql('await_ship');
                    break;

                case CS_FINISHED :
                    $where .= order_query_sql('finished');
                    break;
                case 201 :
                    $where .= order_query_sql('presale_two_yes');
                    break;
                case 202 :
                    $where .= order_query_sql('presale_two_no');
                    break;

                default:
                    if ($filter['composite_status'] != -1)
                    {
                        $where .= " AND o.order_status = '$filter[composite_status]' ";
                    }
            }
        }
    }

    $sql = "SELECT MAX(order_id) FROM " . $ecs->table('order_info') . " as o WHERE order_id < '$order[order_id]'";
    if ($agency_id > 0)
    {
        $sql .= " AND agency_id = '$agency_id'";
    }
    if (!empty($where))
    {
        $sql .= $where;
    }
    $smarty->assign('prev_id', $db->getOne($sql));
    $sql = "SELECT MIN(order_id) FROM " . $ecs->table('order_info') . " as o WHERE order_id > '$order[order_id]'";
    if ($agency_id > 0)
    {
        $sql .= " AND agency_id = '$agency_id'";
    }
    if (!empty($where))
    {
        $sql .= $where;
    }
    $smarty->assign('next_id', $db->getOne($sql));

    /* 取得用户名 */
    if ($order['user_id'] > 0)
    {
        $user = user_info($order['user_id']);
        if (!empty($user))
        {
            $order['user_name'] = $user['user_name'];
        }
    }

    /* 取得所有办事处 */
    $sql = "SELECT agency_id, agency_name FROM " . $ecs->table('agency');
    $smarty->assign('agency_list', $db->getAll($sql));

    /* 取得区域名 */
    $order['region'] = get_user_region_address($order['order_id']);

    /* 格式化金额 */
    if ($order['order_amount'] < 0)
    {
        $order['money_refund']          = abs($order['order_amount']);
        $order['formated_money_refund'] = price_format(abs($order['order_amount']));
    }

    /* 其他处理 */
    $order['order_time']    = local_date($_CFG['time_format'], $order['add_time']);
    $order['pay_time']      = $order['pay_time'] > 0 ?
        local_date($_CFG['time_format'], $order['pay_time']) : $_LANG['ps'][PS_UNPAYED];
    $order['shipping_time'] = $order['shipping_time'] > 0 ?
        local_date($_CFG['time_format'], $order['shipping_time']) : $_LANG['ss'][SS_UNSHIPPED];
    $order['confirm_take_time'] = $order['confirm_take_time'] > 0 ?
        local_date($_CFG['time_format'], $order['confirm_take_time']) : $_LANG['ss'][SS_UNSHIPPED];
    $order['status']        = $_LANG['os'][$order['order_status']] . ',' . $_LANG['ps'][$order['pay_status']] . ',' . $_LANG['ss'][$order['shipping_status']];

    /* 取得订单的来源 */
    if ($order['from_ad'] == 0)
    {
        $order['referer'] = empty($order['referer']) ? $_LANG['from_self_site'] : $order['referer'];
    }
    elseif ($order['from_ad'] == -1)
    {
        $order['referer'] = $_LANG['from_goods_js'] . ' ('.$_LANG['from'] . $order['referer'].')';
    }
    else
    {
        /* 查询广告的名称 */
         $ad_name = $db->getOne("SELECT ad_name FROM " .$ecs->table('ad'). " WHERE ad_id='$order[from_ad]'");
         $order['referer'] = $_LANG['from_ad_js'] . $ad_name . ' ('.$_LANG['from'] . $order['referer'].')';
    }

    /* 此订单的发货备注(此订单的最后一条操作记录) */
    $sql = "SELECT action_note FROM " . $ecs->table('order_action').
           " WHERE order_id = '$order[order_id]' AND shipping_status = 1 ORDER BY log_time DESC";
    $order['invoice_note'] = $db->getOne($sql);

    /* 自提点信息 */
    $sql = "SELECT shipping_code FROM ". $ecs->table('shipping') ." WHERE shipping_id = '$order[shipping_id]'";
    if($db->getOne($sql) == 'cac'){
        $sql = "SELECT * FROM ".$ecs->table('shipping_point')." WHERE id IN (SELECT point_id FROM ".$ecs->table('order_info')." WHERE order_id='" .$order['order_id']. "')";
        $order['point']= $db->getRow($sql);
    }

    /* 判断当前订单是否是白条分期付订单 bylu */
    $sql="SELECT stages_total,stages_one_price,is_stages FROM " .$ecs->table('baitiao_log'). " WHERE order_id = '$order_id'";
    $baitiao_info=$db->getRow($sql);
    if($baitiao_info['is_stages']==1){
        $order['is_stages']=1;
        $order['stages_total']=$baitiao_info['stages_total'];
        $order['stages_one_price']=$baitiao_info['stages_one_price'];
    }

	/*增值发票 start*/
	if($order['invoice_type'] == 1){
		$user_id = $order['user_id'];
		$sql = " SELECT * FROM " . $ecs->table('users_vat_invoices_info') . " WHERE user_id = '$user_id' LIMIT 1";
		$res = $db->getRow($sql);
		$smarty->assign('vat_info',$res);
	}
	/*增值发票 end*/

    /* 取得订单商品总重量 */
    $weight_price = order_weight_price($order['order_id']);
    $order['total_weight'] = $weight_price['formated_weight'];

    $date = array('order_id');

        $order_child = count(get_table_date('order_info', "main_order_id='".$order['order_id']."'", $date, 1));
        $order['order_child'] = $order_child;
    /* 参数赋值：订单 */
    $smarty->assign('order', $order);

    /* 取得用户信息 */
    if ($order['user_id'] > 0)
    {
        /* 用户等级 */
        if ($user['user_rank'] > 0)
        {
            $where = " WHERE rank_id = '$user[user_rank]' ";
        }
        else
        {
            $where = " WHERE min_points <= " . intval($user['rank_points']) . " ORDER BY min_points DESC ";
        }
        $sql = "SELECT rank_name FROM " . $ecs->table('user_rank') . $where;
        $user['rank_name'] = $db->getOne($sql);

        // 用户红包数量
        $day    = getdate();
        $today  = local_mktime(23, 59, 59, $day['mon'], $day['mday'], $day['year']);
        $sql = "SELECT COUNT(*) " .
                "FROM " . $ecs->table('bonus_type') . " AS bt, " . $ecs->table('user_bonus') . " AS ub " .
                "WHERE bt.type_id = ub.bonus_type_id " .
                "AND ub.user_id = '$order[user_id]' " .
                "AND ub.order_id = 0 " .
                "AND bt.use_start_date <= '$today' " .
                "AND bt.use_end_date >= '$today'";
        $user['bonus_count'] = $db->getOne($sql);
        $smarty->assign('user', $user);

        // 地址信息
        $sql = "SELECT * FROM " . $ecs->table('user_address') . " WHERE user_id = '$order[user_id]'";
        $smarty->assign('address_list', $db->getAll($sql));
    }

    /* 取得订单商品及货品 */
    $goods_list = array();
    $goods_attr = array();
    $sql = " SELECT o.*, c.measure_unit, g.goods_number AS storage, g.model_inventory, g.model_attr as model_attr, o.goods_attr, g.suppliers_id, p.product_sn,
            g.user_id AS ru_id, g.brand_id, g.goods_thumb , g.bar_code, IF(oi.extension_code != '', oi.extension_code, o.extension_code), oi.extension_id  
            FROM " . $ecs->table('order_goods') . " AS o
                LEFT JOIN " . $ecs->table('products') . " AS p
                    ON p.product_id = o.product_id
                LEFT JOIN " . $ecs->table('goods') . " AS g
                    ON o.goods_id = g.goods_id
                LEFT JOIN " . $ecs->table('category') . " AS c
                    ON g.cat_id = c.cat_id
				LEFT JOIN " . $ecs->table('order_info') . " AS oi 
					ON o.order_id = oi.order_id
            WHERE o.order_id = '$order[order_id]' ";
    $res = $db->query($sql);
    while ($row = $db->fetchRow($res))
    {
        /* 虚拟商品支持 */
        if ($row['is_real'] == 0)
        {
            /* 取得语言项 */
            $filename = ROOT_PATH . 'plugins/' . $order['extension_code'] . '/languages/common_' . $_CFG['lang'] . '.php';
            if (file_exists($filename))
            {
                include_once($filename);
                if (!empty($_LANG[$order['extension_code'].'_link']))
                {
                    $row['goods_name'] = $row['goods_name'] . sprintf($_LANG[$order['extension_code'].'_link'], $row['goods_id'], $order['order_sn']);
                }
            }
        }

        if($row['model_inventory'] == 1){
            $row['storage'] = get_warehouse_area_goods($row['warehouse_id'], $row['goods_id'], 'warehouse_goods');
        }elseif($row['model_inventory'] == 2){
            $row['storage'] = get_warehouse_area_goods($row['area_id'], $row['goods_id'], 'warehouse_area_goods');
        }
        //ecmoban模板堂 --zhuo start 商品金额促销
        $row['goods_amount'] = $row['goods_price'] * $row['goods_number'];
        $goods_con = get_con_goods_amount($row['goods_amount'], $row['goods_id'], 0, 0, $row['parent_id']);

        $goods_con['amount'] = explode(',', $goods_con['amount']);
        $row['amount'] = min($goods_con['amount']);

        $row['dis_amount'] = $row['goods_amount'] - $row['amount'];
        $row['discount_amount'] = price_format($row['dis_amount'], false);
        //ecmoban模板堂 --zhuo end 商品金额促销

        //ecmoban模板堂 --zhuo start //库存查询
        $products = get_warehouse_id_attr_number($row['goods_id'], $row['goods_attr_id'], $row['ru_id'], $row['warehouse_id'], $row['area_id'], $row['model_attr']);
        $row['goods_storage'] = $products['product_number'];

        if($row['product_id']){
            $row['bar_code'] = $products['bar_code'];
        }

        if($row['model_attr'] == 1){
                $table_products = "products_warehouse";
                $type_files = " and warehouse_id = '" .$row['warehouse_id']. "'";
        }elseif($row['model_attr'] == 2){
                $table_products = "products_area";
                $type_files = " and area_id = '" .$row['area_id']. "'";
        }else{
                $table_products = "products";
                $type_files = "";
        }

        $sql = "SELECT * FROM " .$GLOBALS['ecs']->table($table_products). " WHERE goods_id = '" .$row['goods_id']. "'" .$type_files. " LIMIT 0, 1";
        $prod = $GLOBALS['db']->getRow($sql);
        if(empty($prod)){ //当商品没有属性库存时
                $row['goods_storage'] = $row['storage'];
        }
        $row['goods_storage'] = !empty($row['goods_storage']) ? $row['goods_storage'] : 0;
        $row['storage'] = $row['goods_storage'];
        $row['product_sn'] = $products['product_sn'];
        //ecmoban模板堂 --zhuo end //库存查询

        $brand = get_goods_brand_info($row['brand_id']);
        $row['brand_name'] = $brand['brand_name'];

        $_goods_thumb = get_image_path($row['goods_id'], $row['goods_thumb'], true);
        $row['goods_thumb'] = $_goods_thumb;

        $row['formated_subtotal']       = price_format($row['amount']);
        $row['formated_goods_price']    = price_format($row['goods_price']);
        //获取预售ID
        $sql = "SELECT act_id FROM " . $GLOBALS['ecs']->table('presale_activity') . " WHERE goods_id=" . $row['goods_id'];
        $row['act_id'] = $GLOBALS['db']->getOne($sql);

        $row['warehouse_name'] = $db->getOne("select region_name from " . $ecs->table('region_warehouse') . " where region_id = '" . $row['warehouse_id'] . "'");

        $goods_attr[] = explode(' ', trim($row['goods_attr'])); //将商品属性拆分为一个数组

        if ($row['extension_code'] == 'package_buy') {
            $row['goods_thumb'] = "../data/gallery_album/package_goods_default.jpg";
        }

        $goods_list[] = $row;
    }
    $attr = array();
    $arr  = array();
    foreach ($goods_attr AS $index => $array_val)
    {
        foreach ($array_val AS $value)
        {
            $arr = explode(':', $value);//以 : 号将属性拆开
            $attr[$index][] =  @array('name' => $arr[0], 'value' => $arr[1]);
        }
    }
    $smarty->assign('goods_attr', $attr);
    $smarty->assign('goods_list', $goods_list);

    /* 取得能执行的操作列表 */
    $operable_list = operable_list($order);
    $smarty->assign('operable_list', $operable_list);

    /* 判断退换货订单申请是否通过 strat */
    $sql = "SELECT count(ret_id) FROM " . $ecs->table('order_return') . " WHERE order_id = '$order[order_id]' AND return_status IN (-1, 0) AND refound_status = 0";
    //判断是否存在未处理的退换货申请
    $order_return_exist = $db->getOne($sql);

    $smarty->assign('order_return_exist', $order_return_exist);
    /* 判断退换货订单申请是否通过 end */

    /**
     * 取得用户收货时间 以快物流信息显示为准，目前先用用户收货时间为准，后期修改TODO by Leah S
     */
    $sql = "SELECT log_time  FROM " . $ecs->table('order_action') . " WHERE order_id = '$order[order_id]' ";
    $res_time = local_date($_CFG['time_format'], $db->getOne($sql));
    $smarty->assign('res_time', $res_time);
    /**
     * by Leah E
     */

    /* 取得订单操作记录 */
    $act_list = array();
    $sql = "SELECT * FROM " . $ecs->table('order_action') . " WHERE order_id = '$order[order_id]' ORDER BY log_time DESC,action_id DESC";
    $res = $db->query($sql);
    while ($row = $db->fetchRow($res))
    {
        $row['order_status']    = $_LANG['os'][$row['order_status']];
        $row['pay_status']      = $_LANG['ps'][$row['pay_status']];
        $row['shipping_status'] = $_LANG['ss'][$row['shipping_status']];
        $row['action_time']     = local_date($_CFG['time_format'], $row['log_time']);
        $act_list[] = $row;
    }
    $smarty->assign('action_list', $act_list);

    /* 取得是否存在实体商品 */
    if ($order['is_zc_order']) {
        $smarty->assign('exist_real_goods', true);
    } else {
        $smarty->assign('exist_real_goods', exist_real_goods($order['order_id']));
    }

	/* 返回门店列表 */
	if($order['pay_status'] == 2 && $order['shipping_status'] == 0)
	{
		$sql = " SELECT COUNT(*) FROM ".$GLOBALS['ecs']->table('store_order')." WHERE order_id = '$order[order_id]' AND store_id > 0 ";
		$have_store_order = $GLOBALS['db']->getOne($sql);
		if($have_store_order == 0)
		{
			$smarty->assign('can_set_grab_order', 1);
		}
	}

    //商家店铺信息打印到订单和快递单上
    $sql = "select shop_name,country,province,city,district,shop_address,kf_tel from " . $ecs->table('seller_shopinfo') . " where ru_id='" . $order['ru_id'] . "'";
    $store = $db->getRow($sql);

    $store['shop_name'] = get_shop_name($order['ru_id'], 1);

    /* 是否打印订单，分别赋值 */
    if (isset($_GET['print']))
    {
        $smarty->assign('shop_name',    $store['shop_name']);
        $smarty->assign('shop_url',     $ecs->url());
        $smarty->assign('shop_address', $store['shop_address']);
        $smarty->assign('service_phone',$store['kf_tel']);
        $smarty->assign('print_time',   local_date($_CFG['time_format']));
        $smarty->assign('action_user',  $_SESSION['admin_name']);

        $smarty->template_dir = '../' . DATA_DIR;
        $smarty->display('order_print.html');
    }
    /* 打印快递单 */
    elseif (isset($_GET['shipping_print']))
    {
        //快递鸟、电子面单 start
        if (get_print_type($adminru['ru_id'])) {
            $url = 'tp_api.php?act=kdniao_print&order_id=' . $order_id;
            ecs_header("Location: $url\n");
            exit;
        }
        //快递鸟、电子面单 end

        //发货地址所在地
        $region_array = array();
		$region = $db->getAll("SELECT region_id, region_name FROM " . $ecs->table("region")); //打印快递单地区 by wu
        if (!empty($region))
        {
            foreach($region as $region_data)
            {
                $region_array[$region_data['region_id']] = $region_data['region_name'];
            }
        }
        $smarty->assign('shop_name',    $store['shop_name']);
        $smarty->assign('order_id',    $order_id);
        $smarty->assign('province', $region_array[$store['province']]);
        $smarty->assign('city', $region_array[$store['city']]);
        $smarty->assign('district', $region_array[$store['district']]);
        $smarty->assign('shop_address', $store['shop_address']);
        $smarty->assign('service_phone',$store['kf_tel']);
        $shipping = $db->getRow("SELECT * FROM " . $ecs->table("shipping_tpl") . " WHERE shipping_id = '" . $order['shipping_id']."' and ru_id='".$order['ru_id']."'");
        //打印单模式
        if ($shipping['print_model'] == 2)
        {
            /* 可视化 */
            /* 快递单 */
            $shipping['print_bg'] = empty($shipping['print_bg']) ? '' : get_site_root_url() . $shipping['print_bg'];

            /* 取快递单背景宽高 */
            if (!empty($shipping['print_bg']))
            {
                $_size = @getimagesize($shipping['print_bg']);

                if ($_size != false)
                {
                    $shipping['print_bg_size'] = array('width' => $_size[0], 'height' => $_size[1]);
                }
            }

            if (empty($shipping['print_bg_size']))
            {
                $shipping['print_bg_size'] = array('width' => '1024', 'height' => '600');
            }

            /* 标签信息 */
            $lable_box = array();
            $lable_box['t_shop_country'] = $region_array[$store['country']]; //网店-国家
            $lable_box['t_shop_city'] = $region_array[$store['city']]; //网店-城市
            $lable_box['t_shop_province'] = $region_array[$store['province']]; //网店-省份
			$sql = "select og.ru_id from " .$GLOBALS['ecs']->table('order_info') ." as oi ". "," .$GLOBALS['ecs']->table('order_goods'). " as og " .
					" where oi.order_id = og.order_id and oi.order_id = '" .$order['order_id']. "' group by oi.order_id";
			$ru_id = $GLOBALS['db']->getOne($sql);

			if($ru_id > 0){

				$sql = "select shoprz_brandName, shopNameSuffix from " .$GLOBALS['ecs']->table('merchants_shop_information') . " where user_id = '$ru_id'";
				$shop_info = $GLOBALS['db']->getRow($sql);

				$lable_box['t_shop_name'] = $shop_info['shoprz_brandName'] . $shop_info['shopNameSuffix']; //店铺-名称
			}else{
				$lable_box['t_shop_name'] = $_CFG['shop_name']; //网店-名称
			}

            $lable_box['t_shop_district'] = $region_array[$store['district']]; //网店-区/县
            $lable_box['t_shop_tel'] = $store['kf_tel']; //网店-联系电话
            $lable_box['t_shop_address'] = $store['shop_address']; //网店-地址
            $lable_box['t_customer_country'] = $region_array[$order['country']]; //收件人-国家
            $lable_box['t_customer_province'] = $region_array[$order['province']]; //收件人-省份
            $lable_box['t_customer_city'] = $region_array[$order['city']]; //收件人-城市
            $lable_box['t_customer_district'] = $region_array[$order['district']]; //收件人-区/县
            $lable_box['t_customer_tel'] = $order['tel']; //收件人-电话
            $lable_box['t_customer_mobel'] = $order['mobile']; //收件人-手机
            $lable_box['t_customer_post'] = $order['zipcode']; //收件人-邮编
            $lable_box['t_customer_address'] = $order['address']; //收件人-详细地址
            $lable_box['t_customer_name'] = $order['consignee']; //收件人-姓名

            $gmtime_utc_temp = gmtime(); //获取 UTC 时间戳
            $lable_box['t_year'] = date('Y', $gmtime_utc_temp); //年-当日日期
            $lable_box['t_months'] = date('m', $gmtime_utc_temp); //月-当日日期
            $lable_box['t_day'] = date('d', $gmtime_utc_temp); //日-当日日期

            $lable_box['t_order_no'] = $order['order_sn']; //订单号-订单
            $lable_box['t_order_postscript'] = $order['postscript']; //备注-订单
            $lable_box['t_order_best_time'] = $order['best_time']; //送货时间-订单
            $lable_box['t_pigeon'] = '√'; //√-对号
            $lable_box['t_custom_content'] = ''; //自定义内容

            //标签替换
            $temp_config_lable = explode('||,||', $shipping['config_lable']);
            if (!is_array($temp_config_lable))
            {
                $temp_config_lable[] = $shipping['config_lable'];
            }
            foreach ($temp_config_lable as $temp_key => $temp_lable)
            {
                $temp_info = explode(',', $temp_lable);
                if (is_array($temp_info))
                {
                    $temp_info[1] = $lable_box[$temp_info[0]];
                }
                $temp_config_lable[$temp_key] = implode(',', $temp_info);
            }
            $shipping['config_lable'] = implode('||,||',  $temp_config_lable);

            $smarty->assign('shipping', $shipping);

            $smarty->display('print.dwt');
        }
        elseif (!empty($shipping['shipping_print']))
        {
            /* 代码 */
            echo $smarty->fetch("str:" . $shipping['shipping_print']);
        }
        else
        {
            $shipping_code = $db->getOne("SELECT shipping_code FROM " . $ecs->table('shipping') . " WHERE shipping_id='" . $order['shipping_id']."'");

			/* 处理app */
			if($order['referer'] == 'mobile')
			{
				$shipping_code = str_replace('ship_', '', $shipping_code);
			}

            if ($shipping_code)
            {
                include_once(ROOT_PATH . 'includes/modules/shipping/' . $shipping_code . '.php');
            }

            if (!empty($_LANG['shipping_print']))
            {
                echo $smarty->fetch("str:$_LANG[shipping_print]");
            }
            else
            {
                echo $_LANG['no_print_shipping'];
            }
        }
    }
    else
    {
        /* 模板赋值 */
        $smarty->assign('ur_here', $_LANG['order_info']);
        $smarty->assign('action_link', array('href' => 'order.php?act=list&' . list_link_postfix(), 'text' => $_LANG['02_order_list']));

        //查询订单是否有商品退款
        $sql = "SELECT count(*) FROM " .$GLOBALS['ecs']->table('order_return'). " WHERE order_id = '$order_id'";
        $GLOBALS['db']->getOne($sql);

        /* 显示模板 */
        assign_query_info();
        $smarty->display('order_info.dwt');
    }
}

/* ------------------------------------------------------ */
//-- 退货单详情
/* ------------------------------------------------------ */
/**
 * by Leah
 */
elseif ($_REQUEST['act'] == 'return_info') {
    /* 检查权限 */
    admin_priv('order_back_apply');

    $ret_id = intval(trim($_REQUEST['ret_id']));
    $rec_id = intval(trim($_REQUEST['rec_id']));

    /* 根据发货单id查询发货单信息 */
    if (!empty($ret_id) || !empty($rec_id)) {
        $back_order = return_order_info($ret_id);
    } else {
        die('order does not exist');
    }

    /* 如果管理员属于某个办事处，检查该订单是否也属于这个办事处 */
    $sql = "SELECT agency_id FROM " . $ecs->table('admin_user') . " WHERE user_id = '$_SESSION[admin_id]'";
    $agency_id = $db->getOne($sql);
    if ($agency_id > 0) {
        if ($back_order['agency_id'] != $agency_id) {
            sys_msg($_LANG['priv_error']);
        }

        /* 取当前办事处信息 */
        $sql = "SELECT agency_name FROM " . $ecs->table('agency') . " WHERE agency_id = '$agency_id' LIMIT 0, 1";
        $agency_name = $db->getOne($sql);
        $back_order['agency_name'] = $agency_name;
    }

    /* 取得用户名 */
    if ($back_order['user_id'] > 0) {
        $user = user_info($back_order['user_id']);
        if (!empty($user)) {
            $back_order['user_name'] = $user['user_name'];
        }
    }

    /* 取得区域名 */
    $back_order['region'] = $back_order['address_detail'];

    /* 是否保价 */
    $back_order['insure_yn'] = empty($order['insure_fee']) ? 0 : 1;

    /* 取得发货单商品 */;
    $goods_list = get_return_order_goods($rec_id);

    /**
     * 取的退换货订单商品
     */
    $return_list = get_return_goods($ret_id);


    //快递公司
    /* 取得可用的配送方式列表 */
    $region_id_list = array(
        $back_order['country'], $back_order['province'], $back_order['city'], $back_order['district']
    );

    $shipping_list = available_shipping_list($region_id_list, $back_order['ru_id']);

    /* 取得配送费用 */
    $total = order_weight_price($order_id);
    foreach ($shipping_list AS $key => $shipping) {

        $shipping_fee = shipping_fee($shipping['shipping_code'], unserialize($shipping['configure']), $total['weight'], $total['amount'], $total['number']); //计算运费
        $free_price = free_price($shipping['configure']);   //免费额度
        $shipping_list[$key]['shipping_fee'] = $shipping_fee;
        $shipping_list[$key]['format_shipping_fee'] = price_format($shipping_fee);
        $shipping_list[$key]['free_money'] = price_format($free_price['configure']['free_money']);
    }
    $smarty->assign('shipping_list', $shipping_list);

    /* 取得退货订单操作记录 */
    $action_list = get_return_action($ret_id);
    $smarty->assign('action_list', $action_list);

    /* 模板赋值 */
    $smarty->assign('back_order', $back_order);

    if ($back_order['is_zc_order']) {
        $smarty->assign('exist_real_goods', true);
    } else {
        $smarty->assign('exist_real_goods', $exist_real_goods);
    }

    $smarty->assign('goods_list', $goods_list);
    $smarty->assign('return_list', $return_list);
    $smarty->assign('back_id', $back_id); // 发货单id

    /* 显示模板 */
    $smarty->assign('ur_here', $_LANG['back_operate'] . $_LANG['detail']);
    $smarty->assign('action_link', array('href' => 'order.php?act=back_list&' . list_link_postfix(), 'text' => $_LANG['10_back_order']));
    assign_query_info();
    $smarty->display('return_order_info.dwt');
    exit; //
}

/*------------------------------------------------------ */
//-- 发货单列表
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'delivery_list')
{
    /* 检查权限 */
    admin_priv('delivery_view');

    /* 查询 */
    $result = delivery_list();

    /* 模板赋值 */
    $smarty->assign('ur_here', $_LANG['09_delivery_order']);

    /* 订单内平台、店铺区分 */
    $smarty->assign('common_tabs', array('info' => isset($_REQUEST['seller_order_list']) && $_REQUEST['seller_order_list']? 1 : 0 , 'url' => 'order.php?act=delivery_list'));

    $smarty->assign('os_unconfirmed',   OS_UNCONFIRMED);
    $smarty->assign('cs_await_pay',     CS_AWAIT_PAY);
    $smarty->assign('cs_await_ship',    CS_AWAIT_SHIP);
    $smarty->assign('full_page',        1);
    $smarty->assign('delivery_list',   $result['delivery']);
    $smarty->assign('filter',       $result['filter']);
    $smarty->assign('record_count', $result['record_count']);
    $smarty->assign('page_count',   $result['page_count']);
    $smarty->assign('sort_update_time', '<img src="images/sort_desc.gif">');

    /* 显示模板 */
    assign_query_info();
    $smarty->display('delivery_list.dwt');
}

/*------------------------------------------------------ */
//-- 搜索、排序、分页
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'delivery_query')
{
    /* 检查权限 */
    admin_priv('delivery_view');

    $result = delivery_list();

    $smarty->assign('delivery_list',   $result['delivery']);
    $smarty->assign('filter',       $result['filter']);
    $smarty->assign('record_count', $result['record_count']);
    $smarty->assign('page_count',   $result['page_count']);

    $sort_flag = sort_flag($result['filter']);
    $smarty->assign($sort_flag['tag'], $sort_flag['img']);
    make_json_result($smarty->fetch('delivery_list.dwt'), '', array('filter' => $result['filter'], 'page_count' => $result['page_count']));
}

/*------------------------------------------------------ */
//-- 发货单详细
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'delivery_info')
{
    /* 检查权限 */
    admin_priv('delivery_view');

    $delivery_id = intval(trim($_REQUEST['delivery_id']));

    /* 根据发货单id查询发货单信息 */
    if (!empty($delivery_id))
    {
        $delivery_order = delivery_order_info($delivery_id);
    }
    else
    {
        die('order does not exist');
    }

    /* 如果管理员属于某个办事处，检查该订单是否也属于这个办事处 */
    $sql = "SELECT agency_id FROM " . $ecs->table('admin_user') . " WHERE user_id = '" . $_SESSION['admin_id'] . "'";
    $agency_id = $db->getOne($sql);
    if ($agency_id > 0)
    {
        if ($delivery_order['agency_id'] != $agency_id)
        {
            sys_msg($_LANG['priv_error']);
        }

        /* 取当前办事处信息 */
        $sql = "SELECT agency_name FROM " . $ecs->table('agency') . " WHERE agency_id = '$agency_id' LIMIT 0, 1";
        $agency_name = $db->getOne($sql);
        $delivery_order['agency_name'] = $agency_name;
    }

    /* 取得用户名 */
    if ($delivery_order['user_id'] > 0)
    {
        $user = user_info($delivery_order['user_id']);
        if (!empty($user))
        {
            $delivery_order['user_name'] = $user['user_name'];
        }
    }

    /* 取得区域名 */
    $delivery_order['region'] = get_user_region_address($delivery_order['order_id']);

    /* 是否保价 */
    $order['insure_yn'] = empty($order['insure_fee']) ? 0 : 1;

    /* 取得发货单商品 */
    $goods_sql = "SELECT dg.*, g.goods_thumb, g.brand_id  FROM " . $ecs->table('delivery_goods') .
				  " AS dg LEFT JOIN ".$ecs->table('goods')." as g ON dg.goods_id = g.goods_id ".
                  " WHERE delivery_id = " . $delivery_order['delivery_id'];
    $goods_list = $GLOBALS['db']->getAll($goods_sql);
    foreach($goods_list AS $key=>$row)
    {
        $brand = get_goods_brand_info($row['brand_id']);
        $goods_list[$key]['brand_name'] = $brand['brand_name'];

        //图片显示
        $row['goods_thumb'] = get_image_path($row['goods_id'], $row['goods_thumb'], true);
        $goods_list[$key]['goods_thumb'] = $row['goods_thumb'];
    }
    /* 是否存在实体商品 */
    $exist_real_goods = 0;
    if ($goods_list)
    {
        foreach ($goods_list as $value)
        {
            if ($value['is_real'])
            {
                $exist_real_goods++;
            }
        }
    }

    /* 取得订单操作记录 */
    $act_list = array();
    $sql = "SELECT * FROM " . $ecs->table('order_action') . " WHERE order_id = '" . $delivery_order['order_id'] . "' AND action_place = 1 ORDER BY log_time DESC,action_id DESC";
    $res = $db->query($sql);
    while ($row = $db->fetchRow($res))
    {
        $row['order_status']    = $_LANG['os'][$row['order_status']];
        $row['pay_status']      = $_LANG['ps'][$row['pay_status']];
        $row['shipping_status'] = ($row['shipping_status'] == SS_SHIPPED_ING) ? $_LANG['ss_admin'][SS_SHIPPED_ING] : $_LANG['ss'][$row['shipping_status']];
        $row['action_time']     = local_date($_CFG['time_format'], $row['log_time']);
        $act_list[] = $row;
    }
    $smarty->assign('action_list', $act_list);

    /* 模板赋值 */
    $smarty->assign('delivery_order', $delivery_order);
	if($delivery_order['is_zc_order']){
		$smarty->assign('exist_real_goods', true);
	}else{
		$smarty->assign('exist_real_goods', $exist_real_goods);
	}
    $smarty->assign('goods_list', $goods_list);
    $smarty->assign('delivery_id', $delivery_id); // 发货单id

    /* 显示模板 */
    $smarty->assign('ur_here', $_LANG['delivery_operate'] . $_LANG['detail']);
    $smarty->assign('action_link', array('href' => 'order.php?act=delivery_list&' . list_link_postfix(), 'text' => $_LANG['09_delivery_order']));
    $smarty->assign('action_act', ($delivery_order['status'] == 2) ? 'delivery_ship' : 'delivery_cancel_ship');
    assign_query_info();
    $smarty->display('delivery_info.dwt');
    exit; //
}

/*------------------------------------------------------ */
//-- 发货单发货确认
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'delivery_ship')
{
    /* 检查权限 */
    admin_priv('delivery_view');


    /* 定义当前时间 */
    define('GMTIME_UTC', gmtime()); // 获取 UTC 时间戳

    /* 取得参数 */
    $delivery   = array();
    $order_id   = intval(trim($_REQUEST['order_id']));        // 订单id
    $delivery_id   = intval(trim($_REQUEST['delivery_id']));        // 发货单id
    $delivery['invoice_no'] = isset($_REQUEST['invoice_no']) ? trim($_REQUEST['invoice_no']) : '';
    $action_note    = isset($_REQUEST['action_note']) ? trim($_REQUEST['action_note']) : '';

    /* 根据发货单id查询发货单信息 */
    if (!empty($delivery_id))
    {
        $delivery_order = delivery_order_info($delivery_id);
    }
    else
    {
        die('order does not exist');
    }

    /* 查询订单信息 */
    $order = order_info($order_id);
    /* 检查此单发货商品库存缺货情况  ecmoban模板堂 --zhuo start 下单减库存*/
    $delivery_stock_sql = "SELECT DG.rec_id AS dg_rec_id, OG.rec_id AS og_rec_id, G.model_attr, G.model_inventory, DG.goods_id, DG.delivery_id, DG.is_real, DG.send_number AS sums, G.goods_number AS storage, G.goods_name, DG.send_number," .
                            " OG.goods_attr_id, OG.warehouse_id, OG.area_id, OG.ru_id, OG.order_id, OG.product_id FROM " . $GLOBALS['ecs']->table('delivery_goods') . " AS DG, " .
                            $GLOBALS['ecs']->table('goods') . " AS G, " .
                            $GLOBALS['ecs']->table('delivery_order') . " AS D, " .
                            $GLOBALS['ecs']->table('order_goods') . " AS OG " .
                            " WHERE DG.goods_id = G.goods_id AND DG.delivery_id = D.delivery_id AND D.order_id = OG.order_id AND DG.goods_sn = OG.goods_sn AND DG.product_id = OG.product_id AND DG.delivery_id = '$delivery_id' GROUP BY OG.rec_id ";

    $delivery_stock_result = $GLOBALS['db']->getAll($delivery_stock_sql);

    $virtual_goods = array();
    for($i=0; $i<count($delivery_stock_result); $i++){
        if($delivery_stock_result[$i]['model_attr'] == 1){
                $table_products = "products_warehouse";
                $type_files = " and warehouse_id = '" .$delivery_stock_result[$i]['warehouse_id']. "'";
        }elseif($delivery_stock_result[$i]['model_attr'] == 2){
                $table_products = "products_area";
                $type_files = " and area_id = '" .$delivery_stock_result[$i]['area_id']. "'";
        }else{
                $table_products = "products";
                $type_files = "";
        }

        $sql = "SELECT * FROM " .$GLOBALS['ecs']->table($table_products). " WHERE goods_id = '" .$delivery_stock_result[$i]['goods_id']. "'" .$type_files. " LIMIT 0, 1";
        $prod = $GLOBALS['db']->getRow($sql);

        /* 如果商品存在规格就查询规格，如果不存在规格按商品库存查询 */
        if(empty($prod)){
            if($delivery_stock_result[$i]['model_inventory'] == 1){
                $delivery_stock_result[$i]['storage'] = get_warehouse_area_goods($delivery_stock_result[$i]['warehouse_id'], $delivery_stock_result[$i]['goods_id'], 'warehouse_goods');
            }elseif($delivery_stock_result[$i]['model_inventory'] == 2){
                $delivery_stock_result[$i]['storage'] = get_warehouse_area_goods($delivery_stock_result[$i]['area_id'], $delivery_stock_result[$i]['goods_id'], 'warehouse_area_goods');
            }
        }else{
            $products = get_warehouse_id_attr_number($delivery_stock_result[$i]['goods_id'], $delivery_stock_result[$i]['goods_attr_id'], $delivery_stock_result[$i]['ru_id'], $delivery_stock_result[$i]['warehouse_id'], $delivery_stock_result[$i]['area_id'], $delivery_stock_result[$i]['model_attr']);
            $delivery_stock_result[$i]['storage'] = $products['product_number'];
        }

        if (($delivery_stock_result[$i]['sums'] > $delivery_stock_result[$i]['storage'] || $delivery_stock_result[$i]['storage'] <= 0) && (($_CFG['use_storage'] == '1'  && $_CFG['stock_dec_time'] == SDT_SHIP) || ($_CFG['use_storage'] == '0' && $delivery_stock_result[$i]['is_real'] == 0)))
        {
            /* 操作失败 */
            $links[] = array('text' => $_LANG['order_info'], 'href' => 'order.php?act=delivery_info&delivery_id=' . $delivery_id);
            sys_msg(sprintf($_LANG['act_good_vacancy'], $value['goods_name']), 1, $links);
            break;
        }

        /* 虚拟商品列表 virtual_card*/
        if ($delivery_stock_result[$i]['is_real'] == 0)
        {
            $virtual_goods[] = array(
                           'goods_id' => $delivery_stock_result[$i]['goods_id'],
                           'goods_name' => $delivery_stock_result[$i]['goods_name'],
                           'num' => $delivery_stock_result[$i]['send_number']
                           );
        }
    }
    //ecmoban模板堂 --zhuo end 下单减库存

    /* 发货 */
    /* 处理虚拟卡 商品（虚货） */
    if ($virtual_goods && is_array($virtual_goods) && count($virtual_goods) > 0)
    {
        foreach ($virtual_goods as $virtual_value)
        {
            virtual_card_shipping($virtual_value,$order['order_sn'], $msg, 'split');
        }

        //虚拟卡缺货
        if(!empty($msg)){
            $links[] = array('text' => $_LANG['delivery_sn'] . $_LANG['detail'], 'href' => 'order.php?act=delivery_info&delivery_id=' . $delivery_id);
            sys_msg($msg, 1, $links);
        }
    }

    /* 如果使用库存，且发货时减库存，则修改库存 */
    if ($_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_SHIP)
    {

        foreach ($delivery_stock_result as $value)
        {

            /* 商品（实货）、超级礼包（实货） ecmoban模板堂 --zhuo */
            if ($value['is_real'] != 0)
            {
                //（货品）
                if (!empty($value['product_id']))
                {
                    if($value['model_attr'] == 1){
                        $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('products_warehouse') . "
                                            SET product_number = product_number - " . $value['sums'] . "
                                            WHERE product_id = " . $value['product_id'];
                    }elseif($value['model_attr'] == 2){
                        $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('products_area') . "
                                            SET product_number = product_number - " . $value['sums'] . "
                                            WHERE product_id = " . $value['product_id'];
                    }else{
                        $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('products') . "
                                            SET product_number = product_number - " . $value['sums'] . "
                                            WHERE product_id = " . $value['product_id'];
                    }

                }else{
                    if($value['model_inventory'] == 1){
                        $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('warehouse_goods') . "
                                            SET region_number = region_number - " . $value['sums'] . "
                                            WHERE goods_id = " . $value['goods_id'] . " AND region_id = " . $value['warehouse_id'];
                    }elseif($value['model_inventory'] == 2){
                        $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('warehouse_area_goods') . "
                                            SET region_number = region_number - " . $value['sums'] . "
                                            WHERE goods_id = " . $value['goods_id'] . " AND region_id = " . $value['area_id'];
                    }else{
                        $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('goods') . "
                                            SET goods_number = goods_number - " . $value['sums'] . "
                                            WHERE goods_id = " . $value['goods_id'];
                    }
                }

                $GLOBALS['db']->query($minus_stock_sql, 'SILENT');

                //库存日志
                $logs_other = array(
                    'goods_id' =>$value['goods_id'],
                    'order_id' => $value['order_id'],
                    'use_storage' =>$_CFG['stock_dec_time'],
                    'admin_id' =>$_SESSION['admin_id'],
                    'number' => "- " . $value['sums'],
                    'model_inventory' =>$value['model_inventory'],
                    'model_attr' =>$value['model_attr'],
                    'product_id' =>$value['product_id'],
                    'warehouse_id' =>$value['warehouse_id'],
                    'area_id' =>$value['area_id'],
                    'add_time' => gmtime()
                );

                $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('goods_inventory_logs'), $logs_other, 'INSERT');
            }
        }
    }

    /* 修改发货单信息 */
    $invoice_no = str_replace(',', '<br>', $delivery['invoice_no']);
    $invoice_no = trim($invoice_no, '<br>');
    $_delivery['invoice_no'] = $invoice_no;
    $_delivery['status'] = 0; // 0，为已发货
    $query = $db->autoExecute($ecs->table('delivery_order'), $_delivery, 'UPDATE', "delivery_id = $delivery_id", 'SILENT');
    if (!$query)
    {
        /* 操作失败 */
        $links[] = array('text' => $_LANG['delivery_sn'] . $_LANG['detail'], 'href' => 'order.php?act=delivery_info&delivery_id=' . $delivery_id);
        sys_msg($_LANG['act_false'], 1, $links);
    }

    /* 标记订单为已确认 “已发货” */
    /* 更新发货时间 */
    $order_finish = get_all_delivery_finish($order_id);
    $shipping_status = ($order_finish == 1) ? SS_SHIPPED : SS_SHIPPED_PART;
    $arr['shipping_status']     = $shipping_status;
    $arr['shipping_time']       = GMTIME_UTC; // 发货时间
    $arr['invoice_no']          = trim($order['invoice_no'] . '<br>' . $invoice_no, '<br>');
    update_order($order_id, $arr);

    /* by zxk jiesuan */
    //发货单记录
    deliver_order($order_id, $invoice_no, $order['shipping_id'], $order['shipping_name'], $order['shipping_fee'], GMTIME_UTC, $shipping_status);
    /* by zxk jiesuan */

    /* 发货单发货记录log */
    order_action($order['order_sn'], OS_CONFIRMED, $shipping_status, $order['pay_status'], $action_note, $_SESSION['admin_name'], 1);

    /* 如果当前订单已经全部发货 */
    if ($order_finish)
    {
        /* 如果订单用户不为空，计算积分，并发给用户；发红包 */
        if ($order['user_id'] > 0)
        {
            /* 取得用户信息 */
            $user = user_info($order['user_id']);

            /* 计算并发放积分 */
            $integral = integral_to_give($order);
            /*如果已配送子订单的赠送积分大于0   减去已配送子订单积分*/
            if(!empty($child_order)){
                $integral['custom_points']=$integral['custom_points']-$child_order['custom_points'];
                 $integral['rank_points']=$integral['rank_points']-$child_order['rank_points'];
            }
            log_account_change($order['user_id'], 0, 0, intval($integral['rank_points']), intval($integral['custom_points']), sprintf($_LANG['order_gift_integral'], $order['order_sn']));

            /* 发放红包 */
            send_order_bonus($order_id);

            /* 发放优惠券 bylu */
            send_order_coupons($order_id);

        }

        /* 发送邮件 */
        $cfg = $_CFG['send_ship_email'];
        if ($cfg == '1')
        {
            $order['invoice_no'] = $invoice_no;
            $tpl = get_mail_template('deliver_notice');
            $smarty->assign('order', $order);
            $smarty->assign('send_time', local_date($_CFG['time_format']));
            $smarty->assign('shop_name', $_CFG['shop_name']);
            $smarty->assign('send_date', local_date($GLOBALS['_CFG']['time_format'], gmtime()));
            $smarty->assign('sent_date', local_date($GLOBALS['_CFG']['time_format'], gmtime()));
            //$smarty->assign('confirm_url', $ecs->url() . 'receive.php?id=' . $order['order_id'] . '&con=' . rawurlencode($order['consignee']));
			$smarty->assign('confirm_url', $ecs->url() . 'user.php?act=order_detail&order_id=' . $order['order_id']); //by wu
            $smarty->assign('send_msg_url',$ecs->url() . 'user.php?act=message_list&order_id=' . $order['order_id']);
            $content = $smarty->fetch('str:' . $tpl['template_content']);
            if (!send_mail($order['consignee'], $order['email'], $tpl['template_subject'], $content, $tpl['is_html']))
            {
                $msg = $_LANG['send_mail_fail'];
            }
        }

        /* 如果需要，发短信 */
        if ($GLOBALS['_CFG']['sms_order_shipped'] == '1' && $order['mobile'] != '') {

            //短信接口参数
            if (is_numeric($order['ru_id'])) {
                $shop_name = get_shop_name($order['ru_id'], 1);
            } else {
                $shop_name = "";
            }

            $user_info = get_admin_user_info($order['user_id']);

            $smsParams = array(
                'shop_name' => $shop_name,
                'shopname' => $shop_name,
                'user_name' => $user_info['user_name'],
                'username' => $user_info['user_name'],
                'consignee' => $order['consignee'],
                'order_sn' => $order['order_sn'],
                'ordersn' => $order['order_sn'],
                'invoice_no' => $order['invoice_no'],
                'invoiceno' => $order['invoice_no'],
                'mobile_phone' => $order['mobile'],
                'mobilephone' => $order['mobile']
            );

            if ($GLOBALS['_CFG']['sms_type'] == 0) {

                huyi_sms($smsParams, 'sms_order_shipped');

            } elseif ($GLOBALS['_CFG']['sms_type'] >=1) {

                $result = sms_ali($smsParams, 'sms_order_shipped'); //阿里大鱼短信变量传值，发送时机传值

                if ($result) {
                    $resp = $GLOBALS['ecs']->ali_yu($result);
                } else {
                    sys_msg('阿里大鱼短信配置异常', 1);
                }
            }
        }

        /* 更新商品销量 按照步骤发货 */
        get_goods_sale($order_id);
    }

    // 微信通模板消息 发货通知
    $file = ROOT_PATH .'mobile/app/Http/Wechat/Controllers/Index.php';
    if(file_exists($file) && $order['user_id'] > 0){
        $pushData = array(
            'first' => array('value' => '您的订单已发货'),
            'keyword1' => array('value' => $order['order_sn']), //订单
            'keyword2' => array('value' => $order['shipping_name']), //物流服务
            'keyword3' => array('value' => $order['invoice_no']),  //快递单号
            'keyword4' => array('value' => $order['consignee']),  // 收货信息
            'remark' => array('value' => '订单正在配送中，请您耐心等待')
        );
        $code = 'OPENTM202243318';
        $order_url = $GLOBALS['ecs']->url() . 'mobile/index.php?r=user/order/detail&order_id='.$order_id;
        $order_url = urlencode(base64_encode($order_url));
        //以json格式传输
        $data = urlencode(serialize($pushData));
        $url = $GLOBALS['ecs']->url() . 'mobile/?r=wechat/api&user_id='.$order['user_id'].'&code='.urlencode($code).'&pushData='.$data.'&url='.$order_url;
        curlGet($url);
    }

    /* 清除缓存 */
    clear_cache_files();

    /* 操作成功 */
    $links[] = array('text' => $_LANG['09_delivery_order'], 'href' => 'order.php?act=delivery_list');
    $links[] = array('text' => $_LANG['delivery_sn'] . $_LANG['detail'], 'href' => 'order.php?act=delivery_info&delivery_id=' . $delivery_id);
    sys_msg($_LANG['act_ok'], 0, $links);
}

/*------------------------------------------------------ */
//-- 检测确认收货订单 ecmoban模板堂 --zhuo
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'order_detection')
{
    $smarty->assign('ur_here', $_LANG['11_order_detection']);
    $store_list = get_common_store_list();
    $smarty->assign('store_list',        $store_list);

    $order_list = get_order_detection_list();
    $smarty->assign('order_list',   $order_list['orders']);
    $smarty->assign('filter',       $order_list['filter']);
    $smarty->assign('record_count', $order_list['record_count']);
    $smarty->assign('page_count',   $order_list['page_count']);
    $smarty->assign('sort_order_time', '<img src="images/sort_desc.gif">');
    /* 订单内平台、店铺区分 */
    $smarty->assign('common_tabs', array('info' => isset($_REQUEST['seller_order_list']) ? 1 : 0 , 'url' => 'order.php?act=order_detection'));

    $smarty->assign('full_page',    1);
    $smarty->assign('is_detection',    1);

    $smarty->display('order_detection_list.dwt');
}

/*------------------------------------------------------ */
//-- 发货中订单列表排序、分页、查询
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'detection_query')
{
    /* 检查权限 */
    admin_priv('order_detection');
    $order_list = get_order_detection_list();

    $smarty->assign('order_list',   $order_list['orders']);
    $smarty->assign('filter',       $order_list['filter']);
    $smarty->assign('record_count', $order_list['record_count']);
    $smarty->assign('page_count',   $order_list['page_count']);
    $sort_flag  = sort_flag($order_list['filter']);
    $smarty->assign($sort_flag['tag'], $sort_flag['img']);

    $smarty->assign('is_detection',    1);

    make_json_result($smarty->fetch('order_detection_list.dwt'), '', array('filter' => $order_list['filter'], 'page_count' => $order_list['page_count']));
}

/*------------------------------------------------------ */
//-- 处理系统设置订单自动确认收货订单
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'auto_order_detection')
{
    /* 检查权限 */
    admin_priv('order_detection');

    $smarty->assign('ur_here', $_LANG['11_order_detection']);

    $order_list = get_order_detection_list(3);

    if($order_list['orders'])
    {
        $_SESSION['is_ajax_detection'] = 1;
    }
    else
    {
        $_SESSION['is_ajax_detection'] = 0;
    }

    $order = get_order_detection_list(1);
    $smarty->assign('filter',   $order['filter']);
    $smarty->assign('is_detection',   2);
    $smarty->assign('full_page',    1);
    $smarty->display('order_detection_list.dwt');
}

/*------------------------------------------------------ */
//-- 处理系统设置订单自动确认收货订单
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'ajax_order_detection')
{
    /* 检查权限 */
    admin_priv('order_detection');
    include_once(ROOT_PATH . 'includes/cls_json.php');
    $json = new JSON();

   /* 设置最长执行时间为5分钟 */
    @set_time_limit(300);
    if($_SESSION['is_ajax_detection'] == 1){
        $order_list = get_order_detection_list(2);

        $result['page'] = $order_list['filter']['page'] + 1;
        $result['page_size'] = $order_list['filter']['page_size'];
        $result['record_count'] = $order_list['filter']['record_count'];
        $result['page_count'] = $order_list['filter']['page_count'];

        $result['order'] = $order_list['orders'][0];

        if($result['order']){
            $confirm_take_time = gmtime();
            $operator = $_SESSION['admin_name'];
            // 记录日志
            order_action($result['order']['order_sn'], $result['order']['order_status'], SS_RECEIVED, $result['order']['pay_status'], '', $operator, 0, $confirm_take_time);

            $GLOBALS['db']->query("UPDATE " .$GLOBALS['ecs']->table('order_info'). " SET confirm_take_time = '$confirm_take_time' WHERE order_id = '" .$result['order']['order_id']. "'");

            /* 生成账单订单记录 start */
            $sql = "SELECT order_id, user_id, order_sn , order_status, shipping_status, pay_status, " .
                    "order_amount, goods_amount, tax, shipping_fee, insure_fee, pay_fee, pack_fee, card_fee, " .
                    "bonus, integral_money, coupons, discount, money_paid, surplus, confirm_take_time " .
                    "FROM " . $GLOBALS['ecs']->table('order_info') . " WHERE order_id = '" .$result['order']['order_id']. "'";

            $order = $GLOBALS['db']->GetRow($sql);

            $seller_id = $GLOBALS['db']->getOne("SELECT ru_id FROM " .$GLOBALS['ecs']->table('order_goods'). " WHERE order_id = '" .$order['order_id']. "'", true);
            $value_card = $GLOBALS['db']->getOne("SELECT use_val FROM " .$GLOBALS['ecs']->table('value_card_record'). " WHERE order_id = '" .$order['order_id']. "'", true);
            $return_amount = get_order_return_amount($order['order_id']);

            $other = array(
                'user_id'               => $order['user_id'],
                'seller_id'             => $seller_id,
                'order_id'              => $order['order_id'],
                'order_sn'              => $order['order_sn'],
                'order_status'          => $order['order_status'],
                'shipping_status'       => $order['shipping_status'],
                'pay_status'            => $order['pay_status'],
                'order_amount'          => $order['total_fee'],
                'return_amount'         => $return_amount,
                'goods_amount'          => $order['goods_amount'],
                'tax'                   => $order['tax'],
                'shipping_fee'          => $order['shipping_fee'],
                'insure_fee'            => $order['insure_fee'],
                'pay_fee'               => $order['pay_fee'],
                'pack_fee'              => $order['pack_fee'],
                'card_fee'              => $order['card_fee'],
                'bonus'                 => $order['bonus'],
                'integral_money'        => $order['integral_money'],
                'coupons'               => $order['coupons'],
                'discount'              => $order['discount'],
                'value_card'            => $value_card,
                'money_paid'            => $order['money_paid'],
                'surplus'               => $order['surplus'],
                'confirm_take_time'     => $confirm_take_time
            );

            if($seller_id){
                $insert_id = get_order_bill_log($other);

                /* by zxk jisuan*/
                //确认收货 商家结算到可用金额
                order_confirm_change($order['order_id'], $insert_id);
                /* by zxk jisuan*/
            }
            /* 生成账单订单记录 end */
        }

        if($order_list['filter']['page'] > $order_list['filter']['page_count'])
        {
            $result['stop_ajax'] = 0;
            $_SESSION['is_ajax_detection'] = 0;
        }
        else
        {
            $result['stop_ajax'] = 1;
        }
    }else
    {
        $result['order'] = '';
        $result['stop_ajax'] = 0;
    }

    die($json->encode($result));
}

/*------------------------------------------------------ */
//-- 发货单取消发货
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'delivery_cancel_ship')
{
    /* 检查权限 */
    admin_priv('delivery_view');

    /* 取得参数 */
    $delivery = '';
    $order_id   = intval(trim($_REQUEST['order_id']));        // 订单id
    $delivery_id   = intval(trim($_REQUEST['delivery_id']));        // 发货单id
    $delivery['invoice_no'] = isset($_REQUEST['invoice_no']) ? trim($_REQUEST['invoice_no']) : '';
    $action_note = isset($_REQUEST['action_note']) ? trim($_REQUEST['action_note']) : '';

    /* 根据发货单id查询发货单信息 */
    if (!empty($delivery_id))
    {
        $delivery_order = delivery_order_info($delivery_id);
    }
    else
    {
        die('order does not exist');
    }

    /* 查询订单信息 */
    $order = order_info($order_id);

    /* 取消当前发货单物流单号 */
    $_delivery['invoice_no'] = '';
    $_delivery['status'] = 2;
    $query = $db->autoExecute($ecs->table('delivery_order'), $_delivery, 'UPDATE', "delivery_id = $delivery_id", 'SILENT');
    if (!$query)
    {
        /* 操作失败 */
        $links[] = array('text' => $_LANG['delivery_sn'] . $_LANG['detail'], 'href' => 'order.php?act=delivery_info&delivery_id=' . $delivery_id);
        sys_msg($_LANG['act_false'], 1, $links);
        exit;
    }

    /* 修改定单发货单号 */
    $invoice_no_order = explode('<br>', $order['invoice_no']);
    $invoice_no_delivery = explode('<br>', $delivery_order['invoice_no']);
    foreach ($invoice_no_order as $key => $value)
    {
        $delivery_key = array_search($value, $invoice_no_delivery);
        if ($delivery_key !== false)
        {
            unset($invoice_no_order[$key], $invoice_no_delivery[$delivery_key]);
            if (count($invoice_no_delivery) == 0)
            {
                break;
            }
        }
    }
    $_order['invoice_no'] = implode('<br>', $invoice_no_order);

    /* 更新配送状态 */
    $order_finish = get_all_delivery_finish($order_id);
    $shipping_status = ($order_finish == -1) ? SS_SHIPPED_PART : SS_SHIPPED_ING;
    $arr['shipping_status']     = $shipping_status;
    if ($shipping_status == SS_SHIPPED_ING)
    {
        $arr['shipping_time']   = ''; // 发货时间
    }
    $arr['invoice_no']          = $_order['invoice_no'];
    update_order($order_id, $arr);

    /* 发货单取消发货记录log */
    order_action($order['order_sn'], $order['order_status'], $shipping_status, $order['pay_status'], $action_note, $_SESSION['admin_name'], 1);

    /* 如果使用库存，则增加库存 */
    if ($_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_SHIP)
    {
        // 检查此单发货商品数量
        $virtual_goods = array();
        $delivery_stock_sql = "SELECT DG.goods_id, DG.product_id, DG.is_real, SUM(DG.send_number) AS sums
            FROM " . $GLOBALS['ecs']->table('delivery_goods') . " AS DG
            WHERE DG.delivery_id = '$delivery_id'
            GROUP BY DG.goods_id ";
        $delivery_stock_result = $GLOBALS['db']->getAll($delivery_stock_sql);
        foreach ($delivery_stock_result as $key => $value)
        {
            /* 虚拟商品 */
            if ($value['is_real'] == 0)
            {
                continue;
            }

            //（货品）
            if (!empty($value['product_id']))
            {
                $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('products') . "
                                    SET product_number = product_number + " . $value['sums'] . "
                                    WHERE product_id = " . $value['product_id'];
                $GLOBALS['db']->query($minus_stock_sql, 'SILENT');
            }

            $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('goods') . "
                                SET goods_number = goods_number + " . $value['sums'] . "
                                WHERE goods_id = " . $value['goods_id'];
            $GLOBALS['db']->query($minus_stock_sql, 'SILENT');
        }
    }

    /* 发货单全退回时，退回其它 */
    if ($order['order_status'] == SS_SHIPPED_ING)
    {
        /* 如果订单用户不为空，计算积分，并退回 */
        if ($order['user_id'] > 0)
        {
            /* 取得用户信息 */
            $user = user_info($order['user_id']);

            /* 计算并退回积分 */
            $integral = integral_to_give($order);
            log_account_change($order['user_id'], 0, 0, (-1) * intval($integral['rank_points']), (-1) * intval($integral['custom_points']), sprintf($_LANG['return_order_gift_integral'], $order['order_sn']));

            /* todo 计算并退回红包 */
            return_order_bonus($order_id);
        }
    }

    /* 清除缓存 */
    clear_cache_files();

    /* 操作成功 */
    $links[] = array('text' => $_LANG['delivery_sn'] . $_LANG['detail'], 'href' => 'order.php?act=delivery_info&delivery_id=' . $delivery_id);
    sys_msg($_LANG['act_ok'], 0, $links);
}

/*------------------------------------------------------ */
//-- 退货单列表
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'back_list')
{
    /* 检查权限 */
    admin_priv('back_view');

    /* 查询 */
    $result = back_list();

    /* 模板赋值 */
    $smarty->assign('ur_here', $_LANG['10_back_order']);

    /* 订单内平台、店铺区分 */
    $smarty->assign('common_tabs', array('info' => isset($_REQUEST['seller_order_list']) ? 1 : 0 , 'url' => 'order.php?act=back_list'));
    $smarty->assign('os_unconfirmed',   OS_UNCONFIRMED);
    $smarty->assign('cs_await_pay',     CS_AWAIT_PAY);
    $smarty->assign('cs_await_ship',    CS_AWAIT_SHIP);
    $smarty->assign('full_page',        1);

    $smarty->assign('back_list',   $result['back']);
    $smarty->assign('filter',       $result['filter']);
    $smarty->assign('record_count', $result['record_count']);
    $smarty->assign('page_count',   $result['page_count']);
    $smarty->assign('sort_update_time', '<img src="images/sort_desc.gif">');

    /* 显示模板 */
    assign_query_info();
    $smarty->display('back_list.dwt');
}

/**
 * 退换货原因列表  by Leah
 */ elseif ($_REQUEST['act'] == 'back_cause_list') {

    /* 检查权限 */
    admin_priv('order_back_cause');

    /* 查询 */
	$parent_id = isset($_GET['parent_id']) ? intval($_GET['parent_id']) : 0;
	if($parent_id > 0){
		$result = get_cause_cat_level($parent_id);
	}else{
		$result = cause_list(0, 0, FALSE);
	}

    /* 模板赋值 */
    $smarty->assign('ur_here', $_LANG['10_back_order']);
    $smarty->assign('action_link', array('href' => 'order.php?act=add_return_cause', 'text' => '添加退换货原因'));

    $smarty->assign('os_unconfirmed', OS_UNCONFIRMED);
    $smarty->assign('cs_await_pay', CS_AWAIT_PAY);
    $smarty->assign('cs_await_ship', CS_AWAIT_SHIP);
    $smarty->assign('full_page', 1);

    $smarty->assign('cause_list', $result);
    $smarty->assign('sort_update_time', '<img src="images/sort_desc.gif">');

    /* 显示模板 */
    assign_query_info();
    $smarty->display('back_cause_list.dwt');
}
elseif($_REQUEST['act'] == 'edit_bank_sort_order'){
    check_authz_json('order_back_cause');

    $id     = intval($_POST['id']);
    $order  = intval($_POST['val']);
    $sql = "UPDATE".$ecs->table('return_cause')." SET sort_order = '$order' WHERE cause_id = '$id'";
    if ($db->query($sql))
    {
        make_json_result($order);
    }
    else
    {
        make_json_error("系统出错，请重试");
    }
}

/**
 * 添加退货原因
 * by Leah
 */ elseif ($_REQUEST['act'] == 'add_return_cause') {

    /* 检查权限 */
    admin_priv('order_back_cause');
	$smarty->assign('ur_here','添加退换货原因');
    $smarty->assign('action_link', array('href' => 'order.php?act=back_cause_list', 'text' => '退换货原因列表'));

	$parent_id = isset($_GET['parent_id']) ? intval($_GET['parent_id']) : 0;
	if($parent_id){
		$cause_list = cause_list($parent_id, 0, false, 1);
		foreach($cause_list as $v){
			$cause_info['cause_name'] = $v['cause_name'];
			$cause_info['cause_id'] = $v['cause_id'];
		}
		$smarty->assign('parent_cat',$cause_info);
	}

    $cause_select = cause_list(0, 0, false);
	/* 简单处理缩进 */
	foreach($cause_select as $k=>$v){
		if($v['level']){
			$level = str_repeat('&nbsp;', $v['level'] * 4);
			$cause_select[$k]['name'] = $level.$v['name'];
		}
	}
    $smarty->assign('cause_list', $cause_select);
    $smarty->assign('form_act', 'inser_cause');
    $smarty->display('back_cause_info.dwt');
}
/**
 * 添加退换货原因  by Leah
 *
 */ elseif ($_REQUEST['act'] == 'inser_cause') {

    $cause['cause_name'] = !empty($_REQUEST['cause_name']) ? $_REQUEST['cause_name'] : '';
    $cause['parent_id'] = !empty($_REQUEST['parent_id']) ? intval($_REQUEST['parent_id']) : 0;
    $cause['sort_order'] = !empty($_REQUEST['sort_order']) ? intval($_REQUEST['sort_order']) : 50;
    $cause['is_show'] = !empty($_REQUEST['is_show']) ? intval($_REQUEST['is_show']) : 0;

    if (cause_exists($cause['cause_name'], $cause['parent_id'])) {
        /* 同级别下不能有重复的分类名称 */
        $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
        sys_msg($_LANG['cause_repeat'], 0, $link);
    }
    if ($db->autoExecute($ecs->table('return_cause'), $cause) !== false) {
        /* 添加链接 */
        $link[0]['text'] = '继续添加';
        $link[0]['href'] = 'order.php?act=add_return_cause';

        $link[1]['text'] = '返回列表页';
        $link[1]['href'] = 'order.php?act=back_cause_list';

        sys_msg($_LANG['add_success'], 0, $link);
    }
}
/**
 * 编辑退换货原因
 * by  Leah
 */ elseif ($_REQUEST['act'] == 'edit_cause') {


    /* 检查权限 */
    admin_priv('order_back_cause');
	$smarty->assign('ur_here','编辑退换货原因');
    $smarty->assign('action_link', array('href' => 'order.php?act=back_cause_list', 'text' => '退换货原因列表'));
    $c_id = !empty($_REQUEST['c_id']) ? intval($_REQUEST['c_id']) : 0;
    $cause_info = cause_info($c_id);

    $cause_list = cause_list(0, $cause_info['parent_id'], false);
	/* 简单处理缩进 */
	foreach($cause_list as $k=>$v){
		if($v['level']){
			$level = '';
			for($i=0;$i<$v['level'];$i++){
				$level .= "&nbsp;&nbsp;&nbsp;&nbsp;";
			}
			$cause_list[$k]['name'] = $level.$v['name'];
		}
	}
    $smarty->assign('c_id', $c_id);
    $smarty->assign('cause_info', $cause_info);
    $smarty->assign('cause_list', $cause_list);
    $smarty->assign('form_act', 'edit_cause_info');
    $smarty->display('back_cause_info.dwt');
}

/**
 * 修改
 * by leah
 */ elseif ($_REQUEST['act'] == 'edit_cause_info') {

    $c_id = !empty($_REQUEST['c_id']) ? $_REQUEST['c_id'] : 0;

    $cause['cause_name'] = !empty($_REQUEST['cause_name']) ? $_REQUEST['cause_name'] : '';
    $cause['parent_id'] = !empty($_REQUEST['parent_id']) ? intval($_REQUEST['parent_id']) : 0;
    $cause['sort_order'] = !empty($_REQUEST['sort_order']) ? intval($_REQUEST['sort_order']) : 50;
    $cause['is_show'] = !empty($_REQUEST['is_show']) ? intval($_REQUEST['is_show']) : 0;

    if (cause_exists($cause['cause_name'], $c_id)) {
        /* 同级别下不能有重复的分类名称 */
        $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
        sys_msg($_LANG['cause_repeat'], 0, $link);
    }
    if ($cause['parent_id']==$c_id) {
        /* 不能将原因分类设置为自己 by wu */
        $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
        sys_msg($_LANG['cause_set_self'], 0, $link);
    }
    if ($db->autoExecute($ecs->table('return_cause'), $cause, 'UPDATE', "cause_id='$c_id'") !== false) {
        /* 添加链接 */

        $link[0]['text'] = '返回退换货原因列表';
        $link[0]['href'] = 'order.php?act=back_cause_list';

        sys_msg($_LANG['edit_success'], 0, $link);
    }
}
/**
 * 删除退换货原因
 * by Leah
 */ elseif ($_REQUEST['act'] == 'remove_cause') {
    $c_id = $_REQUEST['id'];
    /* 删除退货单 */

    /* 查询是否存在子原因 by wu */
    $sql = "SELECT COUNT(*) FROM " .$GLOBALS['ecs']->table('return_cause'). " WHERE parent_id = '$c_id'";
    if ($db->getOne($sql) > 0)
    {
        make_json_error($_LANG['cannot_delete']);
    }

    $sql = "DELETE FROM " . $ecs->table('return_cause') . " WHERE cause_id = '$c_id'";
    $db->query($sql);

    $url = 'order.php?act=cause_query&' . str_replace('act=remove_cause', '', $_SERVER['QUERY_STRING']);

    ecs_header("Location: $url\n");
    exit;
}
/**
 * 退换货 搜索、排序、分页  by Leah
 */ elseif ($_REQUEST['act'] == 'cause_query') {

    $result = cause_list(0, 0, false);
    $smarty->assign('cause_list', $result);

    make_json_result($smarty->fetch('back_cause_list.dwt'));
}

/*------------------------------------------------------ */
//-- 搜索、排序、分页
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'back_query')
{
    /* 检查权限 */
    admin_priv('back_view');

    $result = back_list();

    $smarty->assign('back_list',   $result['back']);
    $smarty->assign('filter',       $result['filter']);
    $smarty->assign('record_count', $result['record_count']);
    $smarty->assign('page_count',   $result['page_count']);

    $sort_flag = sort_flag($result['filter']);
    $smarty->assign($sort_flag['tag'], $sort_flag['img']);
    make_json_result($smarty->fetch('back_list.dwt'), '', array('filter' => $result['filter'], 'page_count' => $result['page_count']));
}

/*------------------------------------------------------ */
//-- 退货单详细
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'back_info')
{
    /* 检查权限 */
    admin_priv('back_view');

    $back_id = intval(trim($_REQUEST['back_id']));

    /* 根据发货单id查询发货单信息 */
    if (!empty($back_id))
    {
        $back_order = back_order_info($back_id);
    }
    else
    {
        die('order does not exist');
    }

    /* 如果管理员属于某个办事处，检查该订单是否也属于这个办事处 */
    $sql = "SELECT agency_id FROM " . $ecs->table('admin_user') . " WHERE user_id = '$_SESSION[admin_id]'";
    $agency_id = $db->getOne($sql);
    if ($agency_id > 0)
    {
        if ($back_order['agency_id'] != $agency_id)
        {
            sys_msg($_LANG['priv_error']);
        }

        /* 取当前办事处信息*/
        $sql = "SELECT agency_name FROM " . $ecs->table('agency') . " WHERE agency_id = '$agency_id' LIMIT 0, 1";
        $agency_name = $db->getOne($sql);
        $back_order['agency_name'] = $agency_name;
    }

    /* 取得用户名 */
    if ($back_order['user_id'] > 0)
    {
        $user = user_info($back_order['user_id']);
        if (!empty($user))
        {
            $back_order['user_name'] = $user['user_name'];
        }
    }

    /* 取得区域名 */
    $back_order['region'] = get_user_region_address($back_order['order_id']);

    /* 是否保价 */
    $order['insure_yn'] = empty($order['insure_fee']) ? 0 : 1;

    /* 取得发货单商品 */
    $goods_sql = "SELECT b.*, g.goods_thumb, g.brand_id FROM " . $ecs->table('back_goods') ." AS b LEFT JOIN ".$ecs->table('goods')." AS g ON g.goods_id = b.goods_id ".
				  " WHERE back_id = " . $back_order['back_id'];
    $goods_list = $GLOBALS['db']->getAll($goods_sql);

    foreach($goods_list AS $key=>$row)
    {
        $brand = get_goods_brand_info($row['brand_id']);
        $goods_list[$key]['brand_name'] = $brand['brand_name'];

        //图片显示
        $row['goods_thumb'] = get_image_path($row['goods_id'], $row['goods_thumb'], true);

        $goods_list[$key]['goods_thumb'] = $row['goods_thumb'];
    }

    /* 是否存在实体商品 */
    $exist_real_goods = 0;
    if ($goods_list)
    {
        foreach ($goods_list as $value)
        {
            if ($value['is_real'])
            {
                $exist_real_goods++;
            }
        }
    }

    /* 模板赋值 */
    $smarty->assign('back_order', $back_order);
	if($back_order['is_zc_order']){
		$smarty->assign('exist_real_goods', true);
	}else{
		$smarty->assign('exist_real_goods', $exist_real_goods);
	}
    $smarty->assign('goods_list', $goods_list);
    $smarty->assign('back_id', $back_id); // 发货单id

    /* 显示模板 */
    $smarty->assign('ur_here', $_LANG['back_operate'] . $_LANG['detail']);
    $smarty->assign('action_link', array('href' => 'order.php?act=back_list&' . list_link_postfix(), 'text' => $_LANG['10_back_order']));
    assign_query_info();
    $smarty->display('back_info.dwt');
    exit; //
}

/*------------------------------------------------------ */
//-- 修改退换金额 --zhuo
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'edit_refound_amount')
{
    /* 检查权限 */
    admin_priv('order_edit');
    check_authz_json('order_edit');
    $type = empty($_REQUEST['type']) ? 0 : intval($_REQUEST['type']);
    $refound_amount = empty($_REQUEST['refound_amount']) ? 0 : floatval($_REQUEST['refound_amount']);
    $order_id = empty($_REQUEST['order_id']) ? 0 : intval($_REQUEST['order_id']);
    $rec_id = empty($_REQUEST['rec_id']) ? 0 : intval($_REQUEST['rec_id']);
    $ret_d = empty($_REQUEST['ret_d']) ? 0 : intval($_REQUEST['ret_d']);

    if($type == 1){

        $order = order_info($order_id);

        $paid_amount = $order['money_paid'] + $order['surplus'];
        if($paid_amount > 0 && $paid_amount >= $order['shipping_fee']){
            $paid_amount = $paid_amount - $order['shipping_fee'];
        }

        /* 退款 */
        if ($ret_d > 0) {
            $refound_fee = order_refound_fee($order_id, $ret_d); //已退金额
            $paid_amount = $paid_amount - $refound_fee;
            if ($refound_amount > $paid_amount) {
                $should_return = $paid_amount;
            }
        } else {
            $should_return = $refound_amount;
        }

        if($should_return > $paid_amount){
            $should_return = $paid_amount;
        }
    }else if($type == 2){

        /* 退运费 */
        $sql = "SELECT shipping_fee FROM " .$GLOBALS['ecs']->table('order_info'). " WHERE order_id = '$order_id'";
        $order_shipping_fee = $GLOBALS['db']->getOne($sql);

        //判断运费退款是否大于实际运费退款金额
        $is_refound_shippfee = order_refound_shipping_fee($order_id, $ret_d);
        $is_refound_shippfee_amount = $is_refound_shippfee + $refound_amount;

        if ($is_refound_shippfee_amount > $order_shipping_fee) {
            $shipping_fee = $order_shipping_fee - $is_refound_shippfee;
        } else {
            $shipping_fee = $refound_amount;
        }

        $refound_amount = $shipping_fee;
    }

    $data = array(
        'should_return' => !empty($should_return) ? $should_return : 0, //订单金额
        'refound_amount' => $refound_amount, //退款订单金额
        'order_shipping_fee' => $order_shipping_fee, //订单运费
        'return_shipping_fee' => $return_shipping_fee, //已退运费
        'surplus_fee' => $surplus_fee, //剩余可退运费
        'shipping_fee' => $shipping_fee, //可退运费
        'type' => $type
    );

    clear_cache_files();
    make_json_result($data);
}

/*------------------------------------------------------ */
//-- 修改订单退货退储值卡金额
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'edit_refound_value_card')
{
    /* 检查权限 */
    admin_priv('order_edit');
    check_authz_json('order_edit');
    $vc_id = empty($_REQUEST['vc_id']) ? 0 : intval($_REQUEST['vc_id']);
    $order_id = empty($_REQUEST['order_id']) ? 0 : intval($_REQUEST['order_id']);
    $ret_id = empty($_REQUEST['ret_id']) ? 0 : intval($_REQUEST['ret_id']);
    $refound_vcard = empty($_REQUEST['refound_vcard']) ? 0 : floatval($_REQUEST['refound_vcard']);

    if($ret_id > 0){
        $return_order = return_order_info($ret_id);
        $should_return = $return_order['should_return'] - $return_order['discount_amount'];
    }else{
        $order = order_info($order_id);
        $order_amount = $order['money_paid'] + $order['surplus'];

        if($order_amount > 0 && $order_amount > $order['shipping_fee']){
            $order_amount = $order_amount - $order['shipping_fee'];
        }else{
            $should_return = $order['total_fee'];
        }
    }

    $sql = "SELECT vc_id, use_val FROM " . $GLOBALS['ecs']->table('value_card_record') . " WHERE vc_id = '$vc_id' AND order_id = '$order_id' LIMIT 1";
    $value_card = $GLOBALS['db']->getRow($sql);

    if ($value_card) {
        if ($value_card['use_val'] > $should_return) {
            $value_card['use_val'] = $should_return;
        }
    }

    if($value_card && $refound_vcard > $value_card['use_val']){
        $refound_vcard = $value_card['use_val'];
    }

    $data = array(
        'refound_vcard' => !empty($refound_vcard) ? $refound_vcard : 0, //储值卡金额
    );

    clear_cache_files();
    make_json_result($data);
}

/*------------------------------------------------------ */
//-- 修改订单（处理提交）
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'step_post')
{
    /* 检查权限 */
    admin_priv('order_edit');

    /* 取得参数 step */
    $step_list = array('user', 'edit_goods', 'add_goods', 'goods', 'consignee', 'shipping', 'payment', 'other', 'money', 'invoice');
    $step = isset($_REQUEST['step']) && in_array($_REQUEST['step'], $step_list) ? $_REQUEST['step'] : 'user';

    /* 取得参数 order_id */
    $order_id = isset($_REQUEST['order_id']) ? intval($_REQUEST['order_id']) : 0;
    if ($order_id > 0)
    {
        $old_order = order_info($order_id);
    }

    /* 取得参数 step_act 添加还是编辑 */
    $step_act = isset($_REQUEST['step_act']) ? $_REQUEST['step_act'] : 'add';

    /* 插入订单信息 */
    if ('user' == $step)
    {
        /* 取得参数：user_id */
        $user_id = ($_POST['anonymous'] == 1) ? 0 : intval($_POST['user']);

        /* 插入新订单，状态为无效 */
        $order = array(
            'user_id'           => $user_id,
            'add_time'          => gmtime(),
            'order_status'      => OS_INVALID,
            'shipping_status'   => SS_UNSHIPPED,
            'pay_status'        => PS_UNPAYED,
            'from_ad'           => 0,
            'referer'           => $_LANG['admin']
        );

        do
        {
            $order['order_sn'] = get_order_sn();
            if ($db->autoExecute($ecs->table('order_info'), $order, 'INSERT', '', 'SILENT'))
            {
                break;
            }
            else
            {
                if ($db->errno() != 1062)
                {
                    die($db->error());
                }
            }
        }
        while (true); // 防止订单号重复

        $order_id = $db->insert_id();

        /* todo 记录日志 */
        admin_log($order['order_sn'], 'add', 'order');
        /* 记录log */
        $action_note = sprintf($_LANG['add_order_info'], $_SESSION['seller_name']);
        order_action($order['order_sn'], $order['order_status'], $order['shipping_status'], $order['pay_status'], $action_note, $_SESSION['admin_name']);

        /* 插入 pay_log */
        $sql = 'INSERT INTO ' . $ecs->table('pay_log') . " (order_id, order_amount, order_type, is_paid)" .
                " VALUES ('$order_id', 0, '" . PAY_ORDER . "', 0)";
        $db->query($sql);

        /* 下一步 */
        ecs_header("Location: order.php?act=" . $step_act . "&order_id=" . $order_id . "&step=goods\n");
        exit;
    }
    /* 编辑商品信息 */
    elseif ('edit_goods' == $step)
    {
        if (isset($_POST['rec_id']))
        {
            foreach ($_POST['rec_id'] AS $key => $rec_id)
            {
               $sql = "SELECT warehouse_id, area_id FROM " .$GLOBALS['ecs']->table('order_goods'). " WHERE rec_id = '$rec_id' LIMIT 1";
               $order_goods = $GLOBALS['db']->getRow($sql);

              $sql = "SELECT goods_number ".
                      'FROM ' . $GLOBALS['ecs']->table('goods') .
                      "WHERE goods_id =".$_POST['goods_id'][$key];
                /* 取得参数 */
              $goods_price = floatval($_POST['goods_price'][$key]);
              $goods_number = intval($_POST['goods_number'][$key]);
              $goods_attr = $_POST['goods_attr'][$key];
              $product_id = intval($_POST['product_id'][$key]);
              if($product_id)
                {

                   $sql = "SELECT product_number ".
                          'FROM ' . $GLOBALS['ecs']->table('products') .
                          " WHERE product_id =".$_POST['product_id'][$key];
                }
              $goods_number_all = $db->getOne($sql);
              if($goods_number_all>=$goods_number)
              {
                /* 修改 */
                $sql = "UPDATE " . $ecs->table('order_goods') .
                        " SET goods_price = '$goods_price', " .
                        "goods_number = '$goods_number', " .
                        "goods_attr = '$goods_attr', " .
                        "warehouse_id = '" .$order_goods['warehouse_id']. "', " .
                        "area_id = '" .$order_goods['area_id']. "' " .
                        "WHERE rec_id = '$rec_id' LIMIT 1";
                $db->query($sql);
              }
              else
              {
               sys_msg($_LANG['goods_num_err']);


              }
            }

            /* 更新商品总金额和订单总金额 */
            $goods_amount = order_amount($order_id);
            update_order($order_id, array('goods_amount' => $goods_amount));
            update_order_amount($order_id);
			// 有主订单更新主订单总价
			$parent_id = $GLOBALS['db']->getOne(" SELECT main_order_id FROM " .$GLOBALS['ecs']->table('order_info'). " WHERE order_id = '$order_id' ");
			if($parent_id){
				$order_ids = $GLOBALS['db']->getAll(" SELECT order_id FROM ".$GLOBALS['ecs']->table("order_info")." WHERE main_order_id = '$parent_id' ");
				if($order_ids){
					$main_goods_amount = 0;
					foreach($order_ids as $val){
						$main_goods_amount += order_amount($val['order_id']);
					}
				}
				update_order($parent_id, array('goods_amount' => $main_goods_amount));
				update_order_amount($parent_id);
			}

            /* 更新 pay_log */
            update_pay_log($order_id);

            /* todo 记录日志 */
            $sn = $old_order['order_sn'];
            $new_order = order_info($order_id);
            if ($old_order['total_fee'] != $new_order['total_fee'])
            {
                $sn .= ',' . sprintf($_LANG['order_amount_change'], $old_order['total_fee'], $new_order['total_fee']);
            }
            admin_log($sn, 'edit', 'order');
        }

        /* 跳回订单商品 */
        ecs_header("Location: order.php?act=" . $step_act . "&order_id=" . $order_id . "&step=goods\n");
        exit;
    }
    /* 添加商品 */
    elseif ('add_goods' == $step)
    {
        /* 取得参数 */
        $goods_id = intval($_POST['goodslist']);
        $warehouse_id = intval($_POST['warehouse_id']);
        $area_id = intval($_POST['area_id']);
        $model_attr = intval($_POST['model_attr']);
        $attr_price = $_POST['attr_price'];

        $goods_price = $_POST['add_price'] != 'user_input' ? floatval($_POST['add_price']) : floatval($_POST['input_price']);
        $goods_price = $goods_price + $attr_price;

        $sql = "SELECT user_id FROM " .$GLOBALS['ecs']->table('goods'). " WHERE goods_id = '$goods_id' LIMIT 0, 1";
        $goods_info = $GLOBALS['db']->getRow($sql);

        $goods_attr = '0';
        for ($i = 0; $i < $_POST['spec_count']; $i++)
        {
            if (is_array($_POST['spec_' . $i]))
            {
                $temp_array = $_POST['spec_' . $i];
                $temp_array_count = count($_POST['spec_' . $i]);
                for ($j = 0; $j < $temp_array_count; $j++)
                {
                    if($temp_array[$j]!==NULL)
                    {
                        $goods_attr .= ',' . $temp_array[$j];
                    }
                }
            }
            else
            {
                if($_POST['spec_' . $i]!==NULL)
                {
                    $goods_attr .= ',' . $_POST['spec_' . $i];
                }
            }
        }
        $goods_number = $_POST['add_number'];
        $attr_list = $goods_attr;

        $goods_attr = explode(',',$goods_attr);
        $k   =   array_search(0,$goods_attr);
        unset($goods_attr[$k]);

        //ecmoban模板堂 --zhuo start
        $attr_leftJoin = '';
        $select = '';
        if ($model_attr == 1) {
            $select = " wap.attr_price as warehouse_attr_price, ";
            $attr_leftJoin = 'LEFT JOIN ' . $GLOBALS['ecs']->table('warehouse_attr') . " AS wap ON g.goods_attr_id = wap.goods_attr_id AND wap.warehouse_id = '$warehouse_id' ";
        } elseif ($model_attr == 2) {
            $select = " waa.attr_price as area_attr_price, ";
            $attr_leftJoin = 'LEFT JOIN ' . $GLOBALS['ecs']->table('warehouse_area_attr') . " AS waa ON g.goods_attr_id = waa.goods_attr_id AND area_id = '$area_id' ";
        }

        $attr_value = "";
        if ($attr_list) {
            $where = "g.goods_attr_id in($attr_list)";
            $sql = "SELECT g.attr_value, " . $select . " g.attr_price " .
                    'FROM ' . $GLOBALS['ecs']->table('goods_attr') . " AS g " .
                    "LEFT JOIN" . $ecs->table('attribute') . " AS a ON g.attr_id = a.attr_id " .
                    $attr_leftJoin .
                    "WHERE $where ORDER BY a.sort_order, a.attr_id, g.goods_attr_id";
            $res = $db->query($sql);


            while ($row = $db->fetchRow($res)) {
                if ($model_attr == 1) {
                    $row['attr_price'] = $row['warehouse_attr_price'];
                } elseif ($model_attr == 2) {
                    $row['attr_price'] = $row['area_attr_price'];
                } else {
                    $row['attr_price'] = $row['attr_price'];
                }

                $attr_price = '';
                if ($row['attr_price'] > 0) {
                    $attr_price = ":[" . price_format($row['attr_price']) . "]";
                }
                $attr_value[] = $row['attr_value'] . $attr_price;
            }

            if ($attr_value) {
                $attr_value = implode(",", $attr_value);
            }
        }
        //ecmoban模板堂 --zhuo end

        //ecmoban模板堂 --zhuo start
        if($model_attr == 1){
                $table_products = "products_warehouse";
                $type_files = " and warehouse_id = '$warehouse_id'";
        }elseif($model_attr == 2){
                $table_products = "products_area";
                $type_files = " and area_id = '$area_id'";
        }else{
                $table_products = "products";
                $type_files = "";
        }

        $sql = "SELECT * FROM " .$GLOBALS['ecs']->table($table_products). " WHERE goods_id = '$goods_id'" .$type_files. " LIMIT 0, 1";
        $prod = $GLOBALS['db']->getRow($sql);
        //ecmoban模板堂 --zhuo end

        if (is_spec($goods_attr) && !empty($prod))
        {
            $product_info = get_products_info($goods_id, $goods_attr, $warehouse_id, $area_id); //ecmoban模板堂 --zhuo
        }

        //商品存在规格 是货品 检查该货品库存
        if (is_spec($goods_attr) && !empty($prod))
        {
            if (!empty($goods_attr))
            {
                /* 取规格的货品库存 */
                if ($goods_number > $product_info['product_number'])
                {
                    $url = "order.php?act=" . $step_act . "&order_id=" . $order_id . "&step=goods";

                    echo '<a href="'.$url.'">'.$_LANG['goods_num_err'] .'</a>';
                    exit;

                    return false;
                }
            }
        }

        if(is_spec($goods_attr) && !empty($prod))
        {
        /* 插入订单商品 */
            $sql = "INSERT INTO " . $ecs->table('order_goods') .
                        "(order_id, goods_id, goods_name, goods_sn, product_id, goods_number, market_price, " .
                        "goods_price, goods_attr, is_real, extension_code, parent_id, is_gift, goods_attr_id, model_attr, warehouse_id, area_id, ru_id) " .
                    "SELECT '$order_id', goods_id, goods_name, goods_sn, " .$product_info['product_id'].", ".
                        "'$goods_number', market_price, '$goods_price', '" .$attr_value . "', " .
                        "is_real, extension_code, 0, 0 , '".implode(',',$goods_attr)."', '$model_attr', '$warehouse_id', '$area_id', '" .$goods_info['user_id']. "' " .
                    "FROM " . $ecs->table('goods') .
                    " WHERE goods_id = '$goods_id' LIMIT 1";
        }
        else
        {
             $sql = "INSERT INTO " . $ecs->table('order_goods') .
                    " (order_id, goods_id, goods_name, goods_sn, " .
                    "goods_number, market_price, goods_price, goods_attr, " .
                    "is_real, extension_code, parent_id, is_gift, model_attr, warehouse_id, area_id, ru_id) " .
                "SELECT '$order_id', goods_id, goods_name, goods_sn, " .
                    "'$goods_number', market_price, '$goods_price', '" . $attr_value. "', " .
                    "is_real, extension_code, 0, 0, '$model_attr', '$warehouse_id', '$area_id', '" .$goods_info['user_id']. "' " .
                "FROM " . $ecs->table('goods') .
                " WHERE goods_id = '$goods_id' LIMIT 1";
        }

        $db->query($sql);

        /* 如果使用库存，且下订单时减库存，则修改库存 */
        if ($_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_PLACE)
        {
            //ecmoban模板堂 --zhuo start
            $model_inventory = get_table_date("goods", "goods_id = '$goods_id'", array('model_inventory'), 2);

            //（货品）
            if (!empty($product_info['product_id']))
            {
                if($model_attr == 1){
                    $sql = "UPDATE " . $GLOBALS['ecs']->table('products_warehouse') . "
                            SET product_number = product_number - " . $goods_number . "
                            WHERE product_id = " . $product_info['product_id'];
                }elseif($model_attr == 2){
                    $sql = "UPDATE " . $GLOBALS['ecs']->table('products_area') . "
                            SET product_number = product_number - " . $goods_number . "
                            WHERE product_id = " . $product_info['product_id'];
                }else{
                    $sql = "UPDATE " . $GLOBALS['ecs']->table('products') . "
                            SET product_number = product_number - " . $goods_number . "
                            WHERE product_id = " . $product_info['product_id'];
                }

            }else{
                if($model_inventory == 1){
                    $sql = "UPDATE " . $GLOBALS['ecs']->table('warehouse_goods') . "
                            SET region_number = region_number - " . $goods_number . "
                            WHERE goods_id = '$goods_id' AND region_id = '$warehouse_id'";
                }elseif($model_inventory == 2){
                    $sql = "UPDATE " . $GLOBALS['ecs']->table('warehouse_area_goods') . "
                            SET region_number = region_number - " . $goods_number . "
                            WHERE goods_id = '$goods_id' AND region_id = '$area_id'";
                }else{
                    $sql = "UPDATE " . $GLOBALS['ecs']->table('goods') . "
                            SET goods_number = goods_number - " . $goods_number . "
                            WHERE goods_id = '$goods_id'";
                }
            }
            //ecmoban模板堂 --zhuo end

            $db->query($sql);
        }

        /* 更新商品总金额和订单总金额 */
        update_order($order_id, array('goods_amount' => order_amount($order_id)));
        update_order_amount($order_id);
		// 有主订单更新主订单总价
        $parent_id = $GLOBALS['db']->getOne(" SELECT main_order_id FROM " .$GLOBALS['ecs']->table('order_info'). " WHERE order_id = '$order_id' ");
		if($parent_id){
			$order_ids = $GLOBALS['db']->getAll(" SELECT order_id FROM ".$GLOBALS['ecs']->table("order_info")." WHERE main_order_id = '$parent_id' ");
			if($order_ids){
				$main_goods_amount = 0;
				foreach($order_ids as $val){
					$main_goods_amount += order_amount($val['order_id']);
				}
			}
			update_order($parent_id, array('goods_amount' => $main_goods_amount));
			update_order_amount($parent_id);
		}

        /* 更新 pay_log */
        update_pay_log($order_id);

        /* todo 记录日志 */
        $sn = $old_order['order_sn'];
        $new_order = order_info($order_id);
        if ($old_order['total_fee'] != $new_order['total_fee'])
        {
            $sn .= ',' . sprintf($_LANG['order_amount_change'], $old_order['total_fee'], $new_order['total_fee']);
        }
        admin_log($sn, 'edit', 'order');

        /* 跳回订单商品 */
        ecs_header("Location: order.php?act=" . $step_act . "&order_id=" . $order_id . "&step=goods\n");
        exit;
    }
    /* 商品 */
    elseif ('goods' == $step)
    {
        /* 下一步 */
        if (isset($_POST['next']))
        {
            ecs_header("Location: order.php?act=" . $step_act . "&order_id=" . $order_id . "&step=consignee\n");
            exit;
        }
        /* 完成 */
        elseif (isset($_POST['finish']))
        {
            /* 初始化提示信息和链接 */
            $msgs   = array();
            $links  = array();

            /* 如果已付款，检查金额是否变动，并执行相应操作 */
            $order = order_info($order_id);
            handle_order_money_change($order, $msgs, $links);

            /* 显示提示信息 */
            if (!empty($msgs))
            {
                sys_msg(join(chr(13), $msgs), 0, $links);
            }
            else
            {
                /* 跳转到订单详情 */
                ecs_header("Location: order.php?act=info&order_id=" . $order_id . "\n");
                exit;
            }
        }
    }
    /* 保存收货人信息 */
    elseif ('consignee' == $step)
    {
        /* 保存订单 */
        $order = $_POST;
        $order['agency_id'] = get_agency_by_regions(array($order['country'], $order['province'], $order['city'], $order['district']));
        update_order($order_id, $order);

        /* 该订单所属办事处是否变化 */
        $agency_changed = $old_order['agency_id'] != $order['agency_id'];

        /* todo 记录日志 */
        $sn = $old_order['order_sn'];
        admin_log($sn, 'edit', 'order');

        if (isset($_POST['next']))
        {
            /* 下一步 */
            if (exist_real_goods($order_id))
            {
                /* 存在实体商品，去配送方式 */
                ecs_header("Location: order.php?act=" . $step_act . "&order_id=" . $order_id . "&step=shipping\n");
                exit;
            }
            else
            {
                /* 不存在实体商品，去支付方式 */
                ecs_header("Location: order.php?act=" . $step_act . "&order_id=" . $order_id . "&step=payment\n");
                exit;
            }
        }
        elseif (isset($_POST['finish']))
        {
            /* 如果是编辑且存在实体商品，检查收货人地区的改变是否影响原来选的配送 */
            if ('edit' == $step_act && exist_real_goods($order_id))
            {
                $order = order_info($order_id);

                /* 取得可用配送方式 */
                $region_id_list = array(
                    $order['country'], $order['province'], $order['city'], $order['district']
                );
                $shipping_list = available_shipping_list($region_id_list, $order['ru_id']);

                /* 判断订单的配送是否在可用配送之内 */
                $exist = false;
                foreach ($shipping_list AS $shipping)
                {
                    if ($shipping['shipping_id'] == $order['shipping_id'])
                    {
                        $exist = true;
                        break;
                    }
                }

                /* 如果不在可用配送之内，提示用户去修改配送 */
                if (!$exist)
                {
                    // 修改配送为空，配送费和保价费为0
                    update_order($order_id, array('shipping_id' => 0, 'shipping_name' => ''));
                    $links[] = array('text' => $_LANG['step']['shipping'], 'href' => 'order.php?act=edit&order_id=' . $order_id . '&step=shipping');
                    sys_msg($_LANG['continue_shipping'], 1, $links);
                }
            }

            /* 完成 */
            if ($agency_changed)
            {
                ecs_header("Location: order.php?act=list\n");
            }
            else
            {
                ecs_header("Location: order.php?act=info&order_id=" . $order_id . "\n");
            }
            exit;
        }
    }
    /* 保存配送信息 */
    elseif ('shipping' == $step)
    {
        /* 如果不存在实体商品，退出 */
        if (!$old_order['is_zc_order']) {
            if (!exist_real_goods($order_id)) {
                die('Hacking Attemp');
            }
        }

        /* 取得订单信息 */
        $order_info = order_info($order_id);
        $region_id_list = array($order_info['country'], $order_info['province'], $order_info['city'], $order_info['district']);

        /* 保存订单 */
        $shipping_id = intval($_POST['shipping']);
        $shipping = shipping_info($shipping_id);
        $shipping_name = $shipping['shipping_name'];

        $consignee = array(
            'country'       => $order_info['country'],
            'province'      => $order_info['province'],
            'city'          => $order_info['city'],
            'district'      => $order_info['district']
        );

        $goods_list = order_goods($order_id);
        $cart_goods = $goods_list;

        $shipping_fee = 0;
        if ($GLOBALS['_CFG']['freight_model'] == 0) {

            /* 商品单独设置运费价格 start */
            if ($cart_goods) {
                if (count($cart_goods) == 1) {

                    $cart_goods = array_values($cart_goods);

                    if (!empty($cart_goods[0]['freight']) && $cart_goods[0]['is_shipping'] == 0) {

                        if ($cart_goods[0]['freight'] == 1) {
                            $configure_value = $cart_goods[0]['shipping_fee'] * $cart_goods[0]['goods_number'];
                        } else {

                            $trow = get_goods_transport($cart_goods[0]['tid']);

                            if ($trow['freight_type']) {

                                $cart_goods[0]['user_id'] = $cart_goods[0]['ru_id'];
                                $transport_tpl = get_goods_transport_tpl($cart_goods[0], $region, $val, $cart_goods[0]['goods_number']);

                                $configure_value = isset($transport_tpl['shippingFee']) ? $transport_tpl['shippingFee'] : 0;
                            } else {

                                /**
                                 * 商品运费模板
                                 * 自定义
                                 */
                                $custom_shipping = get_goods_custom_shipping($cart_goods);

                                $transport = array('top_area_id', 'area_id', 'tid', 'ru_id', 'sprice');
                                $transport_where = " AND ru_id = '" . $cart_goods[0]['ru_id'] . "' AND tid = '" . $cart_goods[0]['tid'] . "'";
                                $goods_transport = $GLOBALS['ecs']->get_select_find_in_set(2, $consignee['city'], $transport, $transport_where, 'goods_transport_extend', 'area_id');

                                $ship_transport = array('tid', 'ru_id', 'shipping_fee');
                                $ship_transport_where = " AND ru_id = '" . $cart_goods[0]['ru_id'] . "' AND tid = '" . $cart_goods[0]['tid'] . "'";
                                $goods_ship_transport = $GLOBALS['ecs']->get_select_find_in_set(2, $shipping_id, $ship_transport, $ship_transport_where, 'goods_transport_express', 'shipping_id');

                                $goods_transport['sprice'] = isset($goods_transport['sprice']) ? $goods_transport['sprice'] : 0;
                                $goods_ship_transport['shipping_fee'] = isset($goods_ship_transport['shipping_fee']) ? $goods_ship_transport['shipping_fee'] : 0;

                                /* 是否免运费 start */
                                if ($custom_shipping && $custom_shipping[$cart_goods[0]['tid']]['amount'] >= $trow['free_money'] && $trow['free_money'] > 0) {
                                    $is_shipping = 1; /* 免运费 */
                                } else {
                                    $is_shipping = 0; /* 有运费 */
                                }
                                /* 是否免运费 end */

                                if ($is_shipping == 0) {
                                    if ($trow['type'] == 1) {
                                        $configure_value = $goods_transport['sprice'] * $cart_goods[0]['goods_number'] + $goods_ship_transport['shipping_fee'] * $cart_goods[0]['goods_number'];
                                    } else {
                                        $configure_value = $goods_transport['sprice'] + $goods_ship_transport['shipping_fee'];
                                    }
                                }
                            }
                        }
                    } else {
                        /* 有配送按配送区域计算运费 */
                        $configure_type = 1;
                    }
                } else {
                    $order_transpor = get_order_transport($cart_goods, $consignee, $shipping_id, $val['shipping_code']);

                    if ($order_transpor['freight']) {
                        /* 有配送按配送区域计算运费 */
                        $configure_type = 1;
                    }

                    $configure_value = isset($order_transpor['sprice']) ? $order_transpor['sprice'] : 0;
                }
            }
            /* 商品单独设置运费价格 end */

            $shipping_fee = $configure_value;
        }

        $order = array(
            'shipping_id' => $shipping_id,
            'shipping_name' => addslashes($shipping_name),
            'shipping_fee' => $shipping_fee
        );

        if (isset($_POST['insure']))
        {
            /* 计算保价费 */
            $order['insure_fee'] = shipping_insure_fee($shipping['shipping_code'], order_amount($order_id), $shipping['insure']);
        }
        else
        {
            $order['insure_fee'] = 0;
        }
        update_order($order_id, $order);
        update_order_amount($order_id);

        /* 更新 pay_log */
        update_pay_log($order_id);

        /* 清除首页缓存：发货单查询 */
        clear_cache_files('index.dwt');

        /* todo 记录日志 */
        $sn = $old_order['order_sn'];
        $new_order = order_info($order_id);
        if ($old_order['total_fee'] != $new_order['total_fee'])
        {
            $sn .= ',' . sprintf($_LANG['order_amount_change'], $old_order['total_fee'], $new_order['total_fee']);
        }
        admin_log($sn, 'edit', 'order');

        if (isset($_POST['next']))
        {
            /* 下一步 */
            ecs_header("Location: order.php?act=" . $step_act . "&order_id=" . $order_id . "&step=payment\n");
            exit;
        }
        elseif (isset($_POST['finish']))
        {
            /* 初始化提示信息和链接 */
            $msgs   = array();
            $links  = array();

            /* 如果已付款，检查金额是否变动，并执行相应操作 */
            $order = order_info($order_id);
            handle_order_money_change($order, $msgs, $links);

            /* 如果是编辑且配送不支持货到付款且原支付方式是货到付款 */
            if ('edit' == $step_act && $shipping['support_cod'] == 0)
            {
                $payment = payment_info($order['pay_id']);
                if ($payment['is_cod'] == 1)
                {
                    /* 修改支付为空 */
                    update_order($order_id, array('pay_id' => 0, 'pay_name' => ''));
                    $msgs[]     = $_LANG['continue_payment'];
                    $links[]    = array('text' => $_LANG['step']['payment'], 'href' => 'order.php?act=' . $step_act . '&order_id=' . $order_id . '&step=payment');
                }
            }

            /* 显示提示信息 */
            if (!empty($msgs))
            {
                sys_msg(join(chr(13), $msgs), 0, $links);
            }
            else
            {
                /* 完成 */
                ecs_header("Location: order.php?act=info&order_id=" . $order_id . "\n");
                exit;
            }
        }
    }
    /* 保存支付信息 */
    elseif ('payment' == $step)
    {
        /* 取得支付信息 */
        $pay_id = $_POST['payment'];
        $payment = payment_info($pay_id);

        /* 计算支付费用 */
        $order_amount = order_amount($order_id);
        if ($payment['is_cod'] == 1)
        {
            $order = order_info($order_id);
            $region_id_list = array(
                $order['country'], $order['province'], $order['city'], $order['district']
            );
            $shipping = shipping_info($order['shipping_id']);
            $shipping['pay_fee'] = isset($shipping['pay_fee']) ? $shipping['pay_fee'] : 0;
            $pay_fee = pay_fee($pay_id, $order_amount, $shipping['pay_fee']);
        }
        else
        {
            $pay_fee = pay_fee($pay_id, $order_amount);
        }

        /* 保存订单 */
        $order = array(
            'pay_id' => $pay_id,
            'pay_name' => addslashes($payment['pay_name']),
            'pay_fee' => $pay_fee
        );
        update_order($order_id, $order);
        update_order_amount($order_id);

        /* 更新 pay_log */
        update_pay_log($order_id);

        /* todo 记录日志 */
        $sn = $old_order['order_sn'];
        $new_order = order_info($order_id);
        if ($old_order['total_fee'] != $new_order['total_fee'])
        {
            $sn .= ',' . sprintf($_LANG['order_amount_change'], $old_order['total_fee'], $new_order['total_fee']);
        }
        admin_log($sn, 'edit', 'order');

        if (isset($_POST['next']))
        {
            /* 下一步 */
            ecs_header("Location: order.php?act=" . $step_act . "&order_id=" . $order_id . "&step=other\n");
            exit;
        }
        elseif (isset($_POST['finish']))
        {
            /* 初始化提示信息和链接 */
            $msgs   = array();
            $links  = array();

            /* 如果已付款，检查金额是否变动，并执行相应操作 */
            $order = order_info($order_id);
            handle_order_money_change($order, $msgs, $links);

            /* 显示提示信息 */
            if (!empty($msgs))
            {
                sys_msg(join(chr(13), $msgs), 0, $links);
            }
            else
            {
                /* 完成 */
                ecs_header("Location: order.php?act=info&order_id=" . $order_id . "\n");
                exit;
            }
        }
    }
    elseif ('other' == $step)
    {
        /* 保存订单 */
        $order = array();
        if (isset($_POST['pack']) && $_POST['pack'] > 0)
        {
            $pack               = pack_info($_POST['pack']);
            $order['pack_id']   = $pack['pack_id'];
            $order['pack_name'] = addslashes($pack['pack_name']);
            $order['pack_fee']  = $pack['pack_fee'];
        }
        else
        {
            $order['pack_id']   = 0;
            $order['pack_name'] = '';
            $order['pack_fee']  = 0;
        }
        if (isset($_POST['card']) && $_POST['card'] > 0)
        {
            $card               = card_info($_POST['card']);
            $order['card_id']   = $card['card_id'];
            $order['card_name'] = addslashes($card['card_name']);
            $order['card_fee']  = $card['card_fee'];
            $order['card_message'] = $_POST['card_message'];
        }
        else
        {
            $order['card_id']   = 0;
            $order['card_name'] = '';
            $order['card_fee']  = 0;
            $order['card_message'] = '';
        }
        $order['inv_type']      = $_POST['inv_type'];
        $order['inv_payee']     = $_POST['inv_payee'];
        $order['inv_content']   = $_POST['inv_content'];
        $order['how_oos']       = $_POST['how_oos'];
        $order['postscript']    = $_POST['postscript'];
        $order['to_buyer']      = $_POST['to_buyer'];
        update_order($order_id, $order);
        update_order_amount($order_id);

        /* 更新 pay_log */
        update_pay_log($order_id);

        /* todo 记录日志 */
        $sn = $old_order['order_sn'];
        admin_log($sn, 'edit', 'order');

        if (isset($_POST['next']))
        {
            /* 下一步 */
            ecs_header("Location: order.php?act=" . $step_act . "&order_id=" . $order_id . "&step=money\n");
            exit;
        }
        elseif (isset($_POST['finish']))
        {
            /* 完成 */
            ecs_header("Location: order.php?act=info&order_id=" . $order_id . "\n");
            exit;
        }
    }
    elseif ('money' == $step)
    {
        /* 取得订单信息 */
        $old_order = order_info($order_id);
        if ($old_order['user_id'] > 0)
        {
            /* 取得用户信息 */
            $user = user_info($old_order['user_id']);
        }

        /* 保存信息 */
        $order['goods_amount']  = $old_order['goods_amount'];
        $order['discount']      = isset($_POST['discount']) && floatval($_POST['discount']) >= 0 ? round(floatval($_POST['discount']), 2) : 0;
        $order['tax']           = round(floatval($_POST['tax']), 2);
        $order['shipping_fee']  = isset($_POST['shipping_fee']) && floatval($_POST['shipping_fee']) >= 0 ? round(floatval($_POST['shipping_fee']), 2) : 0;
        $order['insure_fee']    = isset($_POST['insure_fee']) && floatval($_POST['insure_fee']) >= 0 ? round(floatval($_POST['insure_fee']), 2) : 0;
        $order['pay_fee']       = floatval($_POST['pay_fee']) >= 0 ? round(floatval($_POST['pay_fee']), 2) : 0;
        $order['pack_fee']      = isset($_POST['pack_fee']) && floatval($_POST['pack_fee']) >= 0 ? round(floatval($_POST['pack_fee']), 2) : 0;
        $order['card_fee']      = isset($_POST['card_fee']) && floatval($_POST['card_fee']) >= 0 ? round(floatval($_POST['card_fee']), 2) : 0;
        $order['coupons']      = isset($_POST['coupons']) && floatval($_POST['coupons']) >= 0 ? round(floatval($_POST['coupons']), 2) : 0;

        $order['money_paid']    = $old_order['money_paid'];
        $order['surplus']       = 0;
        //$order['integral']      = 0;
        $order['integral']=intval($_POST['integral']) >= 0 ? intval($_POST['integral']) : 0;
        $order['integral_money']= 0;
        $order['bonus']      = isset($_POST['bonus']) && floatval($_POST['bonus']) >= 0 ? round(floatval($_POST['bonus']), 2) : 0;
        $order['bonus_id']      = 0;
        $order['bonus']         = isset($_POST['bonus']) && floatval($_POST['bonus']) >= 0 ? round(floatval($_POST['bonus']), 2) : 0;
        $_POST['bonus_id'] = isset($_POST['bonus_id']) && !empty($_POST['bonus_id']) ? intval($_POST['bonus_id']) : 0;

        /* 计算待付款金额 */
        $order['order_amount']  = $order['goods_amount'] - $order['discount']
                                + $order['tax']
                                + $order['shipping_fee']
                                + $order['insure_fee']
                                + $order['pay_fee']
                                + $order['pack_fee']
                                + $order['card_fee']
                                - $order['coupons']
                                - $old_order['use_val']
                                - $order['money_paid'];

        if ($order['order_amount'] > 0)
        {
            if ($old_order['user_id'] > 0)
            {
                /* 如果选择了红包，先使用红包支付 */
                if ($_POST['bonus_id'] > 0 && !isset($_POST['bonus']))
                {
                    /* todo 检查红包是否可用 */
                    $order['bonus_id']      = $_POST['bonus_id'];
                    $bonus                  = bonus_info($_POST['bonus_id']);
                    $order['bonus']         = $bonus['type_money'];

                    $order['order_amount']  -= $order['bonus'];
                }

                /* 使用红包之后待付款金额仍大于0 */
                if ($order['order_amount'] > 0)
                {
                    if($old_order['extension_code']!='exchange_goods')
                    {
                    /* 如果设置了积分，再使用积分支付 */
                       if (isset($_POST['integral']) && intval($_POST['integral']) > 0)
                         {
                           /* 检查积分是否足够 */
                           $order['integral']          = intval($_POST['integral']);
                           $order['integral_money']    = value_of_integral(intval($_POST['integral']));
                           if ($old_order['integral'] + $user['pay_points'] < $order['integral'])
                                {
                                   sys_msg($_LANG['pay_points_not_enough']);
                                }

                           $order['order_amount'] -= $order['integral_money'];
                         }
                    }
                    else
                    {
                      if (intval($_POST['integral']) > $user['pay_points']+$old_order['integral'])
                        {
                          sys_msg($_LANG['pay_points_not_enough']);
                        }

                    }
                    if ($order['order_amount'] > 0)
                    {
                        /* 如果设置了余额，再使用余额支付 */
                        if (isset($_POST['surplus']) && floatval($_POST['surplus']) >= 0)
                        {
                            /* 检查余额是否足够 */
                            $order['surplus'] = round(floatval($_POST['surplus']), 2);
                            if ($old_order['surplus'] + $user['user_money'] + $user['credit_line'] < $order['surplus'])
                            {
                                sys_msg($_LANG['user_money_not_enough']);
                            }

                            /* 如果红包和积分和余额足以支付，把待付款金额改为0，退回部分积分余额 */
                            $order['order_amount'] -= $order['surplus'];
                            if ($order['order_amount'] < 0)
                            {
                                $order['surplus']       += $order['order_amount'];
                                $order['order_amount']  = 0;
                            }
                        }
                    }
                    else
                    {
                        /* 如果红包和积分足以支付，把待付款金额改为0，退回部分积分 */
                        $order['integral_money']    += $order['order_amount'];
                        $order['integral']          = integral_of_value($order['integral_money']);
                        $order['order_amount']      = 0;
                    }
                }
                else
                {
                    /* 如果红包足以支付，把待付款金额设为0 */
                    $order['order_amount'] = 0;
                }
            }
        }

        update_order($order_id, $order);

        // 有主订单更新主订单总价
        $parent_id = $GLOBALS['db']->getOne(" SELECT main_order_id FROM " . $GLOBALS['ecs']->table('order_info') . " WHERE order_id = '$order_id' ");
        if ($parent_id) {
            $order_ids = $GLOBALS['db']->getAll(" SELECT order_id FROM " . $GLOBALS['ecs']->table("order_info") . " WHERE main_order_id = '$parent_id' ");
            if ($order_ids) {
                $main_goods_amount = 0;
                foreach ($order_ids as $val) {
                    $main_goods_amount += order_amount($val['order_id']);
                }
            }
            update_order($parent_id, array('goods_amount' => $main_goods_amount));
            update_order_amount($parent_id);
        }

        /* 更新 pay_log */
        update_pay_log($order_id);

        /* todo 记录日志 */
        $sn = $old_order['order_sn'];
        $new_order = order_info($order_id);
        if ($old_order['total_fee'] != $new_order['total_fee'])
        {
            //如果是编辑订单，且金额发生变化时，重新生成订单编号,防止微信支付失败
            if ($step_act == 'edit'){
                $new_order_sn = correct_order_sn($old_order['order_sn']);
                $sn = $new_order_sn;
                $old_order['order_sn'] = $new_order_sn;
            }

            $sn .= ',' . sprintf($_LANG['order_amount_change'], $old_order['total_fee'], $new_order['total_fee']);
        }
        admin_log($sn, 'edit', 'order');

        /* 如果余额、积分、红包有变化，做相应更新 */
        if ($old_order['user_id'] > 0)
        {
            $user_money_change = $old_order['surplus'] - $order['surplus'];
            if ($user_money_change != 0)
            {
                log_account_change($user['user_id'], $user_money_change, 0, 0, 0, sprintf($_LANG['change_use_surplus'], $old_order['order_sn']));
            }

            $pay_points_change = $old_order['integral'] - $order['integral'];
            if ($pay_points_change != 0)
            {
                log_account_change($user['user_id'], 0, 0, 0, $pay_points_change, sprintf($_LANG['change_use_integral'], $old_order['order_sn']));
            }

            if ($old_order['bonus_id'] != $order['bonus_id'])
            {
                if ($old_order['bonus_id'] > 0)
                {
                    $sql = "UPDATE " . $ecs->table('user_bonus') .
                            " SET used_time = 0, order_id = 0 " .
                            "WHERE bonus_id = '$old_order[bonus_id]' LIMIT 1";
                    $db->query($sql);
                }

                if ($order['bonus_id'] > 0)
                {
                    $sql = "UPDATE " . $ecs->table('user_bonus') .
                            " SET used_time = '" . gmtime() . "', order_id = '$order_id' " .
                            "WHERE bonus_id = '$order[bonus_id]' LIMIT 1";
                    $db->query($sql);
                }
            }
        }

        if (isset($_POST['finish']))
        {
            /* 完成 */
            if ($step_act == 'add')
            {
                /* 订单改为已确认，（已付款） */
                $arr['order_status'] = OS_CONFIRMED;
                $arr['confirm_time'] = gmtime();
                if ($order['order_amount'] <= 0)
                {
                    $arr['pay_status']  = PS_PAYED;
                    $arr['pay_time']    = gmtime();
                }
                update_order($order_id, $arr);
            }

            /* 初始化提示信息和链接 */
            $msgs   = array();
            $links  = array();

            /* 如果已付款，检查金额是否变动，并执行相应操作 */
            $order = order_info($order_id);
            handle_order_money_change($order, $msgs, $links);

            if ($step_act == 'add') {
                /* 记录log */
                $action_note = sprintf($_LANG['add_order_info'], $_SESSION['admin_name']);
                order_action($order['order_sn'], $order['order_status'], $order['shipping_status'], $order['pay_status'], $action_note, $_SESSION['admin_name']);
            }

            /* 显示提示信息 */
            if (!empty($msgs))
            {
                sys_msg(join(chr(13), $msgs), 0, $links);
            }
            else
            {
                ecs_header("Location: order.php?act=info&order_id=" . $order_id . "\n");
                exit;
            }
        }
    }
    /* 保存发货后的配送方式和发货单号 */
    elseif ('invoice' == $step)
    {
        /* 如果不存在实体商品，退出 */
        if (!$old_order['is_zc_order']) {
            if (!exist_real_goods($order_id)) {
                die('Hacking Attemp');
            }
        }

        /* 保存订单 */
        $shipping_id    = intval($_POST['shipping']);
        $shipping       = shipping_info($shipping_id);
        $invoice_no     = trim($_POST['invoice_no']);
        $invoice_no     = str_replace(',', '<br>', $invoice_no);
        $order = array(
            'shipping_id'   => $shipping_id,
            'shipping_name' => addslashes($shipping['shipping_name']),
            'invoice_no'    => $invoice_no
        );
        update_order($order_id, $order);

        /* todo 记录日志 */
        $sn = $old_order['order_sn'];
        admin_log($sn, 'edit', 'order');

        if (isset($_POST['finish']))
        {
            ecs_header("Location: order.php?act=info&order_id=" . $order_id . "\n");
            exit;
        }
    }
}

/**
 * 修改退换货订单  by Leah
 */ elseif ($_REQUEST['act'] == 'return_edit') {

    include_once(ROOT_PATH . 'includes/lib_transaction.php');
    /* 检查权限 */
    admin_priv('order_edit');
    $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
    $ret_id = isset($_GET['ret_id']) ? intval($_GET['ret_id']) : 0;



    /* 取得参数 step */
    $step_list = array('user', 'goods', 'consignee', 'back_shipping', 'payment', 'other', 'money');
    $step = isset($_GET['step']) && in_array($_GET['step'], $step_list) ? $_GET['step'] : 'user';
    $smarty->assign('step', $step);

    /* 取得参数 act */
    $act = $_GET['act'];
    $smarty->assign('ur_here', $_LANG['add_order']);
    $smarty->assign('step_act', $act);
    $smarty->assign('step', $act);

    $order = order_info($order_id);

    /* 取得订单信息 */
    if ($order_id > 0) {
        $return = get_return_detail($ret_id);
        $smarty->assign('return', $return);
        $smarty->assign('order', $order);
    }
    // 选择配送方式
    if ('back_shipping' == $step) {
        /* 取得可用的配送方式列表 */
        $region_id_list = array(
            $order['country'], $order['province'], $order['city'], $order['district']
        );
        $shipping_list = available_shipping_list($region_id_list, $order['ru_id']);

        /* 取得配送费用 */
        $total = order_weight_price($order_id);
        foreach ($shipping_list AS $key => $shipping) {

            $shipping_fee = shipping_fee($shipping['shipping_code'], unserialize($shipping['configure']), $total['weight'], $total['amount'], $total['number']); //计算运费
            $free_price = free_price($shipping['configure']);   //免费额度
            $shipping_list[$key]['shipping_fee'] = $shipping_fee;
            $shipping_list[$key]['format_shipping_fee'] = price_format($shipping_fee);
            $shipping_list[$key]['free_money'] = price_format($free_price['configure']['free_money']);
        }
        $smarty->assign('shipping_list', $shipping_list);
    }

    /* 显示模板 */
    assign_query_info();
    $smarty->display('order_step.dwt');
}
/**
 * 修改退换货订单快递信息
 * by leah
 */ elseif ($_REQUEST['act'] == 'edit_shipping') {

    $order_id = isset($_REQUEST['order_id']) ? intval($_REQUEST['order_id']) : 0;
    $ret_id = isset($_REQUEST['ret_id']) ? intval($_REQUEST['ret_id']) : 0;
    $rec_id = isset($_REQUEST['rec_id']) ? intval($_REQUEST['rec_id']) : 0;

    $shipping_id = isset($_REQUEST['shipping']) ? intval($_REQUEST['shipping']) : 0;
    $invoice_no = isset($_REQUEST['invoice_no']) ? $_REQUEST['invoice_no'] : '';


    $db->query("UPDATE " . $ecs->table('order_return') . " SET out_shipping_name = '$shipping_id' , out_invoice_no ='$invoice_no'" .
            "WHERE ret_id = '$ret_id'");

    $links[] = array('text' => $_LANG['return_sn'] . $_LANG['detail'], 'href' => 'order.php?act=return_info&ret_id=' . $ret_id . 'rec_id=' . $rec_id);
    sys_msg($_LANG['act_ok'], 0, $links);
}

/*------------------------------------------------------ */
//-- 修改订单（载入页面）
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'add' || $_REQUEST['act'] == 'edit')
{
    /* 检查权限 */
    admin_priv('order_edit');

    /* 取得参数 order_id */
    $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
    $smarty->assign('order_id', $order_id);

    /* 取得参数 step */
    $step_list = array('user', 'goods', 'consignee', 'shipping', 'payment', 'other', 'money');
    $step = isset($_GET['step']) && in_array($_GET['step'], $step_list) ? $_GET['step'] : 'user';
    $smarty->assign('step', $step);

    $warehouse_list = get_warehouse_list_goods();
    $smarty->assign('warehouse_list',			$warehouse_list); //仓库列表

    /* 取得参数 act */
    $act = $_GET['act'];

	if($act == 'add'){
		$smarty->assign('ur_here',$_LANG['add_order']);
	}else{
		$smarty->assign('ur_here',$_LANG['edit_order']);
	}

    $smarty->assign('step_act', $act);

    /* 取得订单信息 */
    if ($order_id > 0)
    {
        $order = order_info($order_id);

        /* 发货单格式化 */
        $order['invoice_no'] = str_replace('<br>', ',', $order['invoice_no']);

        /* 如果已发货，就不能修改订单了（配送方式和发货单号除外） */
        if ($order['shipping_status'] == SS_SHIPPED || $order['shipping_status'] == SS_RECEIVED)
        {
            if ($step != 'shipping')
            {
                sys_msg($_LANG['cannot_edit_order_shipped']);
            }
            else
            {
                $step = 'invoice';
                $smarty->assign('step', $step);
            }
        }

        $smarty->assign('order', $order);
    }
    else
    {
        if ($act != 'add' || $step != 'user')
        {
            die('invalid params');
        }
    }

    /* 选择会员 */
    if ('user' == $step)
    {
        // 无操作
    }

    /* 增删改商品 */
    elseif ('goods' == $step)
    {
        /* 取得订单商品 */
        $goods_list = order_goods($order_id);
        if (!empty($goods_list))
        {
            foreach ($goods_list AS $key => $goods)
            {
                /* 计算属性数 */
                $attr = $goods['goods_attr'];
                if ($attr == '')
                {
                    $goods_list[$key]['rows'] = 1;
                }
                else
                {
                    $goods_list[$key]['rows'] = count(explode(chr(13), $attr));
                }
            }
        }

        $smarty->assign('goods_list', $goods_list);

        /* 取得商品总金额 */
        $smarty->assign('goods_amount', order_amount($order_id));
    }

    // 设置收货人
    elseif ('consignee' == $step)
    {

        /* 查询是否存在实体商品 */
        $exist_real_goods = exist_real_goods($order_id);
		if($order['is_zc_order']){
			$smarty->assign('exist_real_goods', true);
		}else{
			$smarty->assign('exist_real_goods', $exist_real_goods);
		}

        /* 取得收货地址列表 */
        if ($order['user_id'] > 0)
        {
            $smarty->assign('address_list', address_list($order['user_id']));

            $address_id = isset($_REQUEST['address_id']) ? intval($_REQUEST['address_id']) : 0;
			/* 获得用户收货人信息 */
			$consignee = get_consignee_log($address_id,$order['user_id']);
			$country_list = get_regions_log(0,0);
			$province_list = get_regions_log(1,$consignee['country']);
			$city_list     = get_regions_log(2,$consignee['province']);
			$district_list = get_regions_log(3,$consignee['city']);

            if ($address_id > 0)
            {
                $address = address_info($address_id);
                if ($address)
                {
                    $order['consignee']     = $address['consignee'];
                    $order['country']       = $address['country'];
                    $order['province']      = $address['province'];
                    $order['city']          = $address['city'];
                    $order['district']      = $address['district'];
                    $order['street']        = $address['street'];
                    $order['email']         = $address['email'];
                    $order['address']       = $address['address'];
                    $order['zipcode']       = $address['zipcode'];
                    $order['tel']           = $address['tel'];
                    $order['mobile']        = $address['mobile'];
                    $order['sign_building'] = $address['sign_building'];
                    $order['best_time']     = $address['best_time'];
                    $smarty->assign('order', $order);
                }
            }
        }

        if ($exist_real_goods) {
            /* 取得国家 */
            $smarty->assign('country_list', get_regions());
            if ($order['country'] > 0) {
                /* 取得省份 */
                $smarty->assign('province_list', get_regions(1, $order['country']));
                if ($order['province'] > 0) {
                    /* 取得城市 */
                    $smarty->assign('city_list', get_regions(2, $order['province']));
                    if ($order['city'] > 0) {
                        /* 取得区域 */
                        $smarty->assign('district_list', get_regions(3, $order['city']));
                        if ($order['district'] > 0) {
                            /* 取得街道 */
                            $smarty->assign('street_list', get_regions(4, $order['district']));
                        }
                    }
                }
            }
        }
    }

    // 选择配送方式
    elseif ('shipping' == $step)
    {
        /* 如果不存在实体商品 */
        if (!$order['is_zc_order']) {
            if (!exist_real_goods($order_id)) {
                die('Hacking Attemp');
            }
        }

        /* 取得可用的配送方式列表 */
        $region_id_list = array(
            $order['country'], $order['province'], $order['city'], $order['district'], $order['street']
        );
        $shipping_list = available_shipping_list($region_id_list, $order['ru_id']);

        $consignee = array(
            'country'       => $order['country'],
            'province'      => $order['province'],
            'city'          => $order['city'],
            'district'      => $order['district'],
            'street'      => $order['street']
        );

        $goods_list = order_goods($order_id);
        $cart_goods = $goods_list;

        $shipping_fee = 0;
        /* 取得配送费用 */
        foreach ($shipping_list AS $key => $val)
        {
            if (substr($val['shipping_code'], 0, 5) != 'ship_') {
                if ($GLOBALS['_CFG']['freight_model'] == 0) {

                    /* 商品单独设置运费价格 start */
                    if ($cart_goods) {
                        if (count($cart_goods) == 1) {

                            $cart_goods = array_values($cart_goods);

                            if (!empty($cart_goods[0]['freight']) && $cart_goods[0]['is_shipping'] == 0) {

                                if ($cart_goods[0]['freight'] == 1) {
                                    $configure_value = $cart_goods[0]['shipping_fee'] * $cart_goods[0]['goods_number'];
                                } else {

                                    $trow = get_goods_transport($cart_goods[0]['tid']);

                                    if ($trow['freight_type']) {

                                        $cart_goods[0]['user_id'] = $cart_goods[0]['ru_id'];
                                        $transport_tpl = get_goods_transport_tpl($cart_goods[0], $region, $val, $cart_goods[0]['goods_number']);

                                        $configure_value = isset($transport_tpl['shippingFee']) ? $transport_tpl['shippingFee'] : 0;
                                    } else {

                                        /**
                                         * 商品运费模板
                                         * 自定义
                                         */
                                        $custom_shipping = get_goods_custom_shipping($cart_goods);

                                        $transport = array('top_area_id', 'area_id', 'tid', 'ru_id', 'sprice');
                                        $transport_where = " AND ru_id = '" . $cart_goods[0]['ru_id'] . "' AND tid = '" . $cart_goods[0]['tid'] . "'";
                                        $goods_transport = $GLOBALS['ecs']->get_select_find_in_set(2, $consignee['city'], $transport, $transport_where, 'goods_transport_extend', 'area_id');

                                        $ship_transport = array('tid', 'ru_id', 'shipping_fee');
                                        $ship_transport_where = " AND ru_id = '" . $cart_goods[0]['ru_id'] . "' AND tid = '" . $cart_goods[0]['tid'] . "'";
                                        $goods_ship_transport = $GLOBALS['ecs']->get_select_find_in_set(2, $val['shipping_id'], $ship_transport, $ship_transport_where, 'goods_transport_express', 'shipping_id');

                                        $goods_transport['sprice'] = isset($goods_transport['sprice']) ? $goods_transport['sprice'] : 0;
                                        $goods_ship_transport['shipping_fee'] = isset($goods_ship_transport['shipping_fee']) ? $goods_ship_transport['shipping_fee'] : 0;

                                        /* 是否免运费 start */
                                        if ($custom_shipping && $custom_shipping[$cart_goods[0]['tid']]['amount'] >= $trow['free_money'] && $trow['free_money'] > 0) {
                                            $is_shipping = 1; /* 免运费 */
                                        } else {
                                            $is_shipping = 0; /* 有运费 */
                                        }
                                        /* 是否免运费 end */

                                        if ($is_shipping == 0) {
                                            if ($trow['type'] == 1) {
                                                $configure_value = $goods_transport['sprice'] * $cart_goods[0]['goods_number'] + $goods_ship_transport['shipping_fee'] * $cart_goods[0]['goods_number'];
                                            } else {
                                                $configure_value = $goods_transport['sprice'] + $goods_ship_transport['shipping_fee'];
                                            }
                                        }
                                    }
                                }
                            } else {
                                /* 有配送按配送区域计算运费 */
                                $configure_type = 1;
                            }
                        } else {
                            $order_transpor = get_order_transport($cart_goods, $consignee, $val['shipping_id'], $val['shipping_code']);

                            if ($order_transpor['freight']) {
                                /* 有配送按配送区域计算运费 */
                                $configure_type = 1;
                            }

                            $configure_value = isset($order_transpor['sprice']) ? $order_transpor['sprice'] : 0;
                        }
                    }
                    /* 商品单独设置运费价格 end */

                    $shipping_fee = $configure_value;
                }

                $shipping_cfg = unserialize_config($val['configure']);

                $shipping_list[$key]['shipping_id'] = $val['shipping_id'];
                $shipping_list[$key]['shipping_name'] = $val['shipping_name'];
                $shipping_list[$key]['shipping_code'] = $val['shipping_code'];
                $shipping_list[$key]['format_shipping_fee'] = price_format($shipping_fee, false);
                $shipping_list[$key]['shipping_fee'] = $shipping_fee;
                $shipping_list[$key]['insure_formated'] = strpos($val['insure'], '%') === false ? price_format($val['insure'], false) : $val['insure'];
                $shipping_list[$key]['format_free_money'] = price_format($shipping_cfg['free_money'], false);
                $shipping_list[$key]['free_money'] = $shipping_cfg['free_money'];

                /* 当前的配送方式是否支持保价 */
                if ($val['shipping_id'] == $order['shipping_id']) {
                    $insure_disabled = ($val['insure'] == 0);
                    $cod_disabled = ($val['support_cod'] == 0);
                }

                $shipping_list[$key]['insure_disabled'] = $insure_disabled;
                $shipping_list[$key]['cod_disabled'] = $cod_disabled;
            }

            // 兼容过滤ecjia配送方式
            if (substr($val['shipping_code'], 0, 5) == 'ship_') {
                unset($shipping_list[$key]);
            }
        }

        $smarty->assign('shipping_list', $shipping_list);
    }

    // 选择支付方式
    elseif ('payment' == $step)
    {
        /* 取得可用的支付方式列表 */
        if (exist_real_goods($order_id))
        {
            /* 存在实体商品 */
            $region_id_list = array(
                $order['country'], $order['province'], $order['city'], $order['district'], $order['street']
            );
            $shipping_area = shipping_info($order['shipping_id']);

            $shipping_area['support_cod'] = isset($shipping_area['support_cod']) ? $shipping_area['support_cod'] : 0;
            $shipping_area['pay_fee'] = isset($shipping_area['pay_fee']) ? $shipping_area['pay_fee'] : 0;
            $pay_fee = ($shipping_area['support_cod'] == 1) ? $shipping_area['pay_fee'] : 0;

            $payment_list = available_payment_list($shipping_area['support_cod'], $pay_fee);
        }
        else
        {
            /* 不存在实体商品 */
            $payment_list = available_payment_list(false);
        }

        /* 过滤掉使用余额支付 */
        foreach ($payment_list as $key => $payment)
        {
            if ($payment['pay_code'] == 'balance')
            {
                unset($payment_list[$key]);
            }
        }
        $smarty->assign('payment_list', $payment_list);
    }

    // 选择包装、贺卡
    elseif ('other' == $step)
    {
        /* 查询是否存在实体商品 */
        $exist_real_goods = exist_real_goods($order_id);
		if($order['is_zc_order']){
			$smarty->assign('exist_real_goods', true);
		}else{
			$smarty->assign('exist_real_goods', $exist_real_goods);
		}

        if ($exist_real_goods)
        {
            /* 取得包装列表 */
            $smarty->assign('pack_list', pack_list());

            /* 取得贺卡列表 */
            $smarty->assign('card_list', card_list());
        }
    }

    // 费用
    elseif ('money' == $step)
    {
        /* 查询是否存在实体商品 */
        $exist_real_goods = exist_real_goods($order_id);
        if ($order['is_zc_order']) {
            $smarty->assign('exist_real_goods', true);
        } else {
            $smarty->assign('exist_real_goods', $exist_real_goods);
        }

        /* 取得用户信息 */
        if ($order['user_id'] > 0)
        {
            $user = user_info($order['user_id']);

            /* 计算可用余额 */
            $smarty->assign('available_user_money', $order['surplus'] + $user['user_money']);

            /* 计算可用积分 */
            $smarty->assign('available_pay_points', $order['integral'] + $user['pay_points']);

            /* 取得用户可用红包 */
            $user_bonus = user_bonus($order['user_id'], $order['goods_amount']);
            if ($order['bonus_id'] > 0)
            {
                $bonus = bonus_info($order['bonus_id']);
                $user_bonus[] = $bonus;
            }
            $smarty->assign('available_bonus', $user_bonus);
        }
    }

    // 发货后修改配送方式和发货单号
    elseif ('invoice' == $step)
    {
        /* 如果不存在实体商品 */
		if(!$order['is_zc_order']){
			if (!exist_real_goods($order_id))
			{
				die ('Hacking Attemp');
			}
		}

        /* 取得可用的配送方式列表 */
        $region_id_list = array(
            $order['country'], $order['province'], $order['city'], $order['district']
        );

        $shipping_list = available_shipping_list($region_id_list, $order['ru_id']);
        $smarty->assign('shipping_list', $shipping_list);
    }

    /* 显示模板 */
    assign_query_info();
    $smarty->display('order_step.dwt');
}

/*------------------------------------------------------ */
//-- 查询仓库地区
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'search_area'){
    check_authz_json('order_view');

    $warehouse_id = intval($_REQUEST['warehouse_id']);

    $sql = "SELECT region_id, region_name FROM " .$GLOBALS['ecs']->table('region_warehouse'). " WHERE region_type = 1 AND parent_id = '$warehouse_id'";
    $region_list = $GLOBALS['db']->getAll($sql);

    $select = '<select name="area_id">';
    $select .= '<option value="0">请选择</option>';
    if($region_list){
        foreach($region_list AS $key=>$row){
             $select .= '<option value="' .$row['region_id']. '">' .$row['region_name']. '</option>';
        }
    }
    $select .= '</select>';

    $result = $select;

    make_json_result($result);
}

/*------------------------------------------------------ */
//-- 处理
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'process')
{
    /* 取得参数 func */
    $func = isset($_GET['func']) ? $_GET['func'] : '';

    /* 删除订单商品 */
    if ('drop_order_goods' == $func)
    {
        /* 检查权限 */
        admin_priv('order_edit');

        /* 取得参数 */
        $rec_id = intval($_GET['rec_id']);
        $step_act = $_GET['step_act'];
        $order_id = intval($_GET['order_id']);

        /* 如果使用库存，且下订单时减库存，则修改库存 */
        if ($_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_PLACE)
        {
             $goods = $db->getRow("SELECT goods_id, goods_number FROM " . $ecs->table('order_goods') . " WHERE rec_id = " . $rec_id );
             $sql = "UPDATE " . $ecs->table('goods') .
                    " SET `goods_number` = goods_number + '" . $goods['goods_number'] . "' " .
                    " WHERE `goods_id` = '" . $goods['goods_id'] . "' LIMIT 1";
             $db->query($sql);
        }

        /* 删除 */
        $sql = "DELETE FROM " . $ecs->table('order_goods') .
                " WHERE rec_id = '$rec_id' LIMIT 1";
        $db->query($sql);

        /* 更新商品总金额和订单总金额 */
        update_order($order_id, array('goods_amount' => order_amount($order_id)));
        update_order_amount($order_id);
		// 有主订单更新主订单总价
        $parent_id = $GLOBALS['db']->getOne(" SELECT main_order_id FROM " .$GLOBALS['ecs']->table('order_info'). " WHERE order_id = '$order_id' ");
		if($parent_id){
			$order_ids = $GLOBALS['db']->getAll(" SELECT order_id FROM ".$GLOBALS['ecs']->table("order_info")." WHERE main_order_id = '$parent_id' ");
			if($order_ids){
				$main_goods_amount = 0;
				foreach($order_ids as $val){
					$main_goods_amount += order_amount($val['order_id']);
				}
			}
			update_order($parent_id, array('goods_amount' => $main_goods_amount));
			update_order_amount($parent_id);
		}

        /* 跳回订单商品 */
        ecs_header("Location: order.php?act=" . $step_act . "&order_id=" . $order_id . "&step=goods\n");
        exit;
    }

    /* 取消刚添加或编辑的订单 */
    elseif ('cancel_order' == $func)
    {
        $step_act = $_GET['step_act'];
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        if ($step_act == 'add')
        {
            /* 如果是添加，删除订单，返回订单列表 */
            if ($order_id > 0)
            {
                $sql = "DELETE FROM " . $ecs->table('order_info') .
                        " WHERE order_id = '$order_id' LIMIT 1";
                $db->query($sql);
            }
            ecs_header("Location: order.php?act=list\n");
            exit;
        }
        else
        {
            /* 如果是编辑，返回订单信息 */
            ecs_header("Location: order.php?act=info&order_id=" . $order_id . "\n");
            exit;
        }
    }

    /* 编辑订单时由于订单已付款且金额减少而退款 */
    elseif ('refund' == $func)
    {
        /* 处理退款 */
        $order_id       = $_REQUEST['order_id'];
        $refund_type    = intval($_REQUEST['refund']);
        $refund_note    = $_REQUEST['refund_note'];
        $refund_amount  = $_REQUEST['refund_amount'];
        $order          = order_info($order_id);
        order_refund($order, $refund_type, "【" .$order['order_sn']. "】" . $refund_note, $refund_amount);

        $update_order = array();
        if ($order['money_paid'] > 0 && $order['money_paid'] >= $refund_amount) {
            $update_order = array('order_amount' => 0, 'money_paid' => $order['money_paid'] - $refund_amount);
        } elseif ($order['surplus'] > 0 && $order['surplus'] >= $refund_amount) {
            $update_order = array('order_amount' => 0, 'surplus' => $order['surplus'] - $refund_amount);
        } else {
            if ($order['money_paid'] > 0 && $refund_amount > $order['money_paid']) {
                $money_paid = '-' . $order['money_paid'];
                $update_order = array('order_amount' => 0, 'surplus' => $refund_amount + $money_paid);
            }

            if ($order['surplus'] > 0 && $refund_amount > $order['surplus']) {
                $surplus = '-' . $order['surplus'];
                $update_order = array('order_amount' => 0, 'surplus' => $refund_amount + $surplus);
            }
        }

        /* 修改应付款金额为0，已付款金额减少 $refund_amount */
        update_order($order_id, $update_order);

        if($refund_type == 1){
            $refund_note = "【" .$_LANG['return_user_money']. "】" . $_LANG['shipping_refund'] . "，" . $refund_note;
        }elseif($refund_type == 2){
            $refund_note = "【" .$_LANG['create_user_account']. "】" . $_LANG['shipping_refund'] . "，" . $refund_note;
        }

        /* 记录log */
        $action_note = sprintf($refund_note, price_format($refund_amount));
        order_action($order['order_sn'], $order['order_status'], $order['shipping_status'], $order['pay_status'], $action_note, $_SESSION['admin_name']);

        /* 返回订单详情 */
        ecs_header("Location: order.php?act=info&order_id=" . $order_id . "\n");
        exit;
    }

    /* 载入退款页面 */
    elseif ('load_refund' == $func)
    {
        $refund_amount = floatval($_REQUEST['refund_amount']);
        $smarty->assign('refund_amount', $refund_amount);
        $smarty->assign('formated_refund_amount', price_format($refund_amount));

        $anonymous = $_REQUEST['anonymous'];
        $smarty->assign('anonymous', $anonymous); // 是否匿名

        $order_id = intval($_REQUEST['order_id']);
        $smarty->assign('order_id', $order_id); // 订单id

        /* 显示模板 */
        $smarty->assign('ur_here', $_LANG['refund']);
        assign_query_info();
        $smarty->display('order_refund.dwt');
    }

    else
    {
        die('invalid params');
    }
}

/*------------------------------------------------------ */
//-- 合并订单
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'merge')
{
    /* 检查权限 */
    admin_priv('order_os_edit');

    /* 模板赋值 */
    $smarty->assign('ur_here', $_LANG['04_merge_order']);
    $smarty->assign('action_link', array('href' => 'order.php?act=list', 'text' => $_LANG['02_order_list']));

    $store_list = get_common_store_list();
    $smarty->assign('store_list',        $store_list);

    /* 显示模板 */
    assign_query_info();
    $smarty->display('merge_order.dwt');
}

/*------------------------------------------------------ */
//-- 订单打印模板（载入页面）
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'templates')
{
    /* 检查权限 */
    admin_priv('order_print'); //ecmoban模板堂 --zhuo

    /* 读入订单打印模板文件 */
    $file_path    = ROOT_PATH. DATA_DIR . '/order_print.html';
    $file_content = file_get_contents($file_path);
    @fclose($file_content);

    /* 创建 html editor */
    create_html_editor2('FCKeditor1', 'fckeditor',$file_content);

    /* 模板赋值 */
    $smarty->assign('ur_here',      $_LANG['edit_order_templates']);
    $smarty->assign('action_link',  array('href' => 'order.php?act=list', 'text' => $_LANG['02_order_list']));
    $smarty->assign('act', 'edit_templates');

    /* 显示模板 */
    assign_query_info();
    $smarty->display('order_templates.dwt');
}
/*------------------------------------------------------ */
//-- 订单打印模板（提交修改）
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'edit_templates')
{
    /* 更新模板文件的内容 */
    $file_name = @fopen('../' . DATA_DIR . '/order_print.html', 'w+');
    @fwrite($file_name, stripslashes($_POST['FCKeditor1']));
    @fclose($file_name);

    /* 提示信息 */
    $link[] = array('text' => $_LANG['back_list'], 'href'=>'order.php?act=list');
    sys_msg($_LANG['edit_template_success'], 0, $link);
}

/*------------------------------------------------------ */
//-- 操作订单状态（载入页面）
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'operate')
{

    $order_id = '';

    $rec_id = ''; //by leah
    $ret_id = ''; //by Leah

    /* 检查权限 */
    admin_priv('order_os_edit');

    /* 取得订单id（可能是多个，多个sn）和操作备注（可能没有） */
    if(isset($_REQUEST['order_id']))
    {
        $order_id= $_REQUEST['order_id'];
        $rec_id = $_REQUEST['rec_id']; //by Leah
        $ret_id = $_REQUEST['ret_id']; //by Leah
    }
    $batch          = isset($_REQUEST['batch']); // 是否批处理
    $action_note    = isset($_REQUEST['action_note']) ? trim($_REQUEST['action_note']) : '';

    /* 确认 */
    if (isset($_POST['confirm']))
    {
        $require_note   = false;
        $action         = $_LANG['op_confirm'];
        $operation      = 'confirm';
    }

    /* ------------------------------------------------------ */
    //-- start 一键发货
    /* ------------------------------------------------------ */
    elseif (isset($_POST['to_shipping'])) {

        $invoice_no = empty($_REQUEST['invoice_no']) ? '' : trim($_REQUEST['invoice_no']);  //快递单号

        if(empty($invoice_no)){
            /* 操作失败 */
            $links[] = array('text' => $_LANG['not_invoice_no'], 'href' => 'order.php?act=info&order_id=' . $order_id);
            sys_msg($_LANG['act_false'], 0, $links);
        }

        /* 定义当前时间 */
        define('GMTIME_UTC', gmtime()); // 获取 UTC 时间戳

        $delivery_info = get_delivery_info($order_id);

        if (!empty($invoice_no) && !$delivery_info) {
            $order_id = intval(trim($order_id));

            /* 查询：根据订单id查询订单信息 */
            if (!empty($order_id)) {
                $order = order_info($order_id);
            } else {
                die('order does not exist');
            }
            /* 查询：根据订单是否完成 检查权限 */
            if (order_finished($order)) {
                admin_priv('order_view_finished');
            } else {
                admin_priv('order_view');
            }

            /* 查询：如果管理员属于某个办事处，检查该订单是否也属于这个办事处 */
            $sql = "SELECT agency_id FROM " . $ecs->table('admin_user') . " WHERE user_id = '$_SESSION[admin_id]'";
            $agency_id = $db->getOne($sql);
            if ($agency_id > 0) {
                if ($order['agency_id'] != $agency_id) {
                    sys_msg($_LANG['priv_error'], 0);
                }
            }
            /* 查询：取得用户名 */
            if ($order['user_id'] > 0) {
                $user = user_info($order['user_id']);
                if (!empty($user)) {
                    $order['user_name'] = $user['user_name'];
                }
            }
            /* 查询：取得区域名 */

            $order['region'] = $db->getOne($sql);

            /* 查询：其他处理 */
            $order['order_time'] = local_date($_CFG['time_format'], $order['add_time']);
            $order['invoice_no'] = $order['shipping_status'] == SS_UNSHIPPED || $order['shipping_status'] == SS_PREPARING ? $_LANG['ss'][SS_UNSHIPPED] : $order['invoice_no'];

            /* 查询：是否保价 */
            $order['insure_yn'] = empty($order['insure_fee']) ? 0 : 1;
            /* 查询：是否存在实体商品 */
            $exist_real_goods = exist_real_goods($order_id);


            /* 查询：取得订单商品 */
            $_goods = get_order_goods(array('order_id' => $order['order_id'], 'order_sn' => $order['order_sn']));

            $attr = $_goods['attr'];
            $goods_list = $_goods['goods_list'];
            unset($_goods);

            /* 查询：商品已发货数量 此单可发货数量 */
            if ($goods_list) {
                foreach ($goods_list as $key => $goods_value) {
                    if (!$goods_value['goods_id']) {
                        continue;
                    }

                    /* 超级礼包 */
                    if (($goods_value['extension_code'] == 'package_buy') && (count($goods_value['package_goods_list']) > 0)) {
                        $goods_list[$key]['package_goods_list'] = package_goods($goods_value['package_goods_list'], $goods_value['goods_number'], $goods_value['order_id'], $goods_value['extension_code'], $goods_value['goods_id']);
                        foreach ($goods_list[$key]['package_goods_list'] as $pg_key => $pg_value) {
                            $goods_list[$key]['package_goods_list'][$pg_key]['readonly'] = '';
                            /* 使用库存 是否缺货 */
                            if ($pg_value['storage'] <= 0 && $_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_SHIP) {
                                $goods_list[$key]['package_goods_list'][$pg_key]['send'] = $_LANG['act_good_vacancy'];
                                $goods_list[$key]['package_goods_list'][$pg_key]['readonly'] = 'readonly="readonly"';
                            }
                            /* 将已经全部发货的商品设置为只读 */
                            elseif ($pg_value['send'] <= 0) {
                                $goods_list[$key]['package_goods_list'][$pg_key]['send'] = $_LANG['act_good_delivery'];
                                $goods_list[$key]['package_goods_list'][$pg_key]['readonly'] = 'readonly="readonly"';
                            }
                        }
                    } else {
                        $goods_list[$key]['sended'] = $goods_value['send_number'];
                        $goods_list[$key]['sended'] = $goods_value['goods_number'];
                        $goods_list[$key]['send'] = $goods_value['goods_number'] - $goods_value['send_number'];
                        $goods_list[$key]['readonly'] = '';
                        /* 是否缺货 */
                        if ($goods_value['storage'] <= 0 && $_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_SHIP) {
                            $goods_list[$key]['send'] = $_LANG['act_good_vacancy'];
                            $goods_list[$key]['readonly'] = 'readonly="readonly"';
                        } elseif ($goods_list[$key]['send'] <= 0) {
                            $goods_list[$key]['send'] = $_LANG['act_good_delivery'];
                            $goods_list[$key]['readonly'] = 'readonly="readonly"';
                        }
                    }
                }
            }

            $suppliers_id = 0;

            $delivery['order_sn'] = trim($order['order_sn']);
            $delivery['add_time'] = trim($order['order_time']);
            $delivery['user_id'] = intval(trim($order['user_id']));
            $delivery['how_oos'] = trim($order['how_oos']);
            $delivery['shipping_id'] = trim($order['shipping_id']);
            $delivery['shipping_fee'] = trim($order['shipping_fee']);
            $delivery['consignee'] = trim($order['consignee']);
            $delivery['address'] = trim($order['address']);
            $delivery['country'] = intval(trim($order['country']));
            $delivery['province'] = intval(trim($order['province']));
            $delivery['city'] = intval(trim($order['city']));
            $delivery['district'] = intval(trim($order['district']));
            $delivery['sign_building'] = trim($order['sign_building']);
            $delivery['email'] = trim($order['email']);
            $delivery['zipcode'] = trim($order['zipcode']);
            $delivery['tel'] = trim($order['tel']);
            $delivery['mobile'] = trim($order['mobile']);
            $delivery['best_time'] = trim($order['best_time']);
            $delivery['postscript'] = trim($order['postscript']);
            $delivery['how_oos'] = trim($order['how_oos']);
            $delivery['insure_fee'] = floatval(trim($order['insure_fee']));
            $delivery['shipping_fee'] = floatval(trim($order['shipping_fee']));
            $delivery['agency_id'] = intval(trim($order['agency_id']));
            $delivery['shipping_name'] = trim($order['shipping_name']);

            /* 检查能否操作 */
            $operable_list = operable_list($order);

            /* 初始化提示信息 */
            $msg = '';

            /* 取得订单商品 */
            $_goods = get_order_goods(array('order_id' => $order_id, 'order_sn' => $delivery['order_sn']));
            $goods_list = $_goods['goods_list'];


            /* 检查此单发货商品库存缺货情况 */
            /* $goods_list已经过处理 超值礼包中商品库存已取得 */
            $virtual_goods = array();
            $package_virtual_goods = array();
            /* 生成发货单 */
            /* 获取发货单号和流水号 */
            $delivery['delivery_sn'] = get_delivery_sn();
            $delivery_sn = $delivery['delivery_sn'];

            /* 获取当前操作员 */
            $delivery['action_user'] = $_SESSION['admin_name'];

            /* 获取发货单生成时间 */
            $delivery['update_time'] = GMTIME_UTC;
            $delivery_time = $delivery['update_time'];
            $sql = "select add_time from " . $GLOBALS['ecs']->table('order_info') . " WHERE order_sn = '" . $delivery['order_sn'] . "'";
            $delivery['add_time'] = $GLOBALS['db']->GetOne($sql);
            /* 获取发货单所属供应商 */
            $delivery['suppliers_id'] = $suppliers_id;

            /* 设置默认值 */
            $delivery['status'] = 2; // 正常
            $delivery['order_id'] = $order_id;

            /* 过滤字段项 */
            $filter_fileds = array(
                'order_sn', 'add_time', 'user_id', 'how_oos', 'shipping_id', 'shipping_fee',
                'consignee', 'address', 'country', 'province', 'city', 'district', 'sign_building',
                'email', 'zipcode', 'tel', 'mobile', 'best_time', 'postscript', 'insure_fee',
                'agency_id', 'delivery_sn', 'action_user', 'update_time',
                'suppliers_id', 'status', 'order_id', 'shipping_name'
            );
            $_delivery = array();
            foreach ($filter_fileds as $value) {
                $_delivery[$value] = $delivery[$value];
            }

            /* 发货单入库 */
            $query = $db->autoExecute($ecs->table('delivery_order'), $_delivery, 'INSERT', '', 'SILENT');
            $delivery_id = $db->insert_id();

            if ($delivery_id) {

                $delivery_goods = array();

                //发货单商品入库
                if (!empty($goods_list)) {
                    foreach ($goods_list as $value) {
                        // 商品（实货）（虚货）
                        if (empty($value['extension_code']) || $value['extension_code'] == 'virtual_card' || $value['extension_code'] == 'presale' || substr($value['extension_code'],0,7) == 'seckill') {
                            $delivery_goods = array('delivery_id' => $delivery_id,
                                'goods_id' => $value['goods_id'],
                                'product_id' => $value['product_id'],
                                'product_sn' => $value['product_sn'],
                                'goods_id' => $value['goods_id'],
                                'goods_name' => $value['goods_name'],
                                'brand_name' => $value['brand_name'],
                                'goods_sn' => $value['goods_sn'],
                                'send_number' => $value['goods_number'],
                                'parent_id' => 0,
                                'is_real' => $value['is_real'],
                                'goods_attr' => $value['goods_attr']
                            );
                            /* 如果是货品 */
                            if (!empty($value['product_id'])) {
                                $delivery_goods['product_id'] = $value['product_id'];
                            }
                            $query = $db->autoExecute($ecs->table('delivery_goods'), $delivery_goods, 'INSERT', '', 'SILENT');
                            $sql = "UPDATE " . $GLOBALS['ecs']->table('order_goods') . "
                SET send_number = " . $value['goods_number'] . "
                WHERE order_id = '" . $value['order_id'] . "'
                AND goods_id = '" . $value['goods_id'] . "' ";
                            $GLOBALS['db']->query($sql, 'SILENT');
                        }
                        // 商品（超值礼包）
                        elseif ($value['extension_code'] == 'package_buy') {
                            foreach ($value['package_goods_list'] as $pg_key => $pg_value) {
                                $delivery_pg_goods = array('delivery_id' => $delivery_id,
                                    'goods_id' => $pg_value['goods_id'],
                                    'product_id' => $pg_value['product_id'],
                                    'product_sn' => $pg_value['product_sn'],
                                    'goods_name' => $pg_value['goods_name'],
                                    'brand_name' => '',
                                    'goods_sn' => $pg_value['goods_sn'],
                                    'send_number' => $pg_value['order_goods_number'],
                                    'parent_id' => $value['goods_id'], // 礼包ID
                                    'extension_code' => $value['extension_code'], // 礼包
                                    'is_real' => $pg_value['is_real']
                                );
                                $query = $db->autoExecute($ecs->table('delivery_goods'), $delivery_pg_goods, 'INSERT', '', 'SILENT');
                                $sql = "UPDATE " . $GLOBALS['ecs']->table('order_goods') . "
                SET send_number = " . $value['goods_number'] . "
                WHERE order_id = '" . $value['order_id'] . "'
                AND goods_id = '" . $value['goods_id'] . "' ";
                                $GLOBALS['db']->query($sql, 'SILENT');
                            }
                        }
                    }
                }
            } else {
                /* 操作失败 */
                $links[] = array('text' => $_LANG['order_info'], 'href' => 'order.php?act=info&order_id=' . $order_id);
                sys_msg($_LANG['act_false'], 1, $links);
            }
            unset($filter_fileds, $delivery, $_delivery, $order_finish);

            /* 定单信息更新处理 */
            if (true) {

                /* 标记订单为已确认 “发货中” */
                /* 更新发货时间 */
                $order_finish = get_order_finish($order_id);
                $shipping_status = SS_SHIPPED_ING;
                if ($order['order_status'] != OS_CONFIRMED && $order['order_status'] != OS_SPLITED && $order['order_status'] != OS_SPLITING_PART) {
                    $arr['order_status'] = OS_CONFIRMED;
                    $arr['confirm_time'] = GMTIME_UTC;
                }
                $arr['order_status'] = $order_finish ? OS_SPLITED : OS_SPLITING_PART; // 全部分单、部分分单
                $arr['shipping_status'] = $shipping_status;

                update_order($order_id, $arr);
            }

            /* 记录log */
            order_action($order['order_sn'], $arr['order_status'], $shipping_status, $order['pay_status'], $action_note, $_SESSION['admin_name']);

            /* 清除缓存 */
            clear_cache_files();

            /* 根据发货单id查询发货单信息 */
            if (!empty($delivery_id)) {
                $delivery_order = delivery_order_info($delivery_id);
            } elseif (!empty($order_sn)) {

                $delivery_id = $GLOBALS['db']->getOne("SELECT delivery_id FROM " . $ecs->table('delivery_order') . " WHERE order_sn = '$order_sn'");
                $delivery_order = delivery_order_info($delivery_id);
            } else {
                die('order does not exist');
            }

            /* 如果管理员属于某个办事处，检查该订单是否也属于这个办事处 */
            $sql = "SELECT agency_id FROM " . $ecs->table('admin_user') . " WHERE user_id = '" . $_SESSION['admin_id'] . "'";
            $agency_id = $db->getOne($sql);
            if ($agency_id > 0) {
                if ($delivery_order['agency_id'] != $agency_id) {
                    sys_msg($_LANG['priv_error']);
                }

                /* 取当前办事处信息 */
                $sql = "SELECT agency_name FROM " . $ecs->table('agency') . " WHERE agency_id = '$agency_id' LIMIT 0, 1";
                $agency_name = $db->getOne($sql);
                $delivery_order['agency_name'] = $agency_name;
            }

            /* 取得用户名 */
            if ($delivery_order['user_id'] > 0) {
                $user = user_info($delivery_order['user_id']);
                if (!empty($user)) {
                    $delivery_order['user_name'] = $user['user_name'];
                }
            }

            /* 取得区域名 */
            $sql = "SELECT concat(IFNULL(c.region_name, ''), '  ', IFNULL(p.region_name, ''), " .
                    "'  ', IFNULL(t.region_name, ''), '  ', IFNULL(d.region_name, '')) AS region " .
                    "FROM " . $ecs->table('order_info') . " AS o " .
                    "LEFT JOIN " . $ecs->table('region') . " AS c ON o.country = c.region_id " .
                    "LEFT JOIN " . $ecs->table('region') . " AS p ON o.province = p.region_id " .
                    "LEFT JOIN " . $ecs->table('region') . " AS t ON o.city = t.region_id " .
                    "LEFT JOIN " . $ecs->table('region') . " AS d ON o.district = d.region_id " .
                    "WHERE o.order_id = '" . $delivery_order['order_id'] . "'";
            $delivery_order['region'] = $db->getOne($sql);

            /* 是否保价 */
            $order['insure_yn'] = empty($order['insure_fee']) ? 0 : 1;

            /* 取得发货单商品 */
            $goods_sql = "SELECT *
                  FROM " . $ecs->table('delivery_goods') . "
                  WHERE delivery_id = " . $delivery_order['delivery_id'];
            $goods_list = $GLOBALS['db']->getAll($goods_sql);

            /* 是否存在实体商品 */
            $exist_real_goods = 0;
            if ($goods_list) {
                foreach ($goods_list as $value) {
                    if ($value['is_real']) {
                        $exist_real_goods++;
                    }
                }
            }

            /* 取得订单操作记录 */
            $act_list = array();
            $sql = "SELECT * FROM " . $ecs->table('order_action') . " WHERE order_id = '" . $delivery_order['order_id'] . "' AND action_place = 1 ORDER BY log_time DESC,action_id DESC";
            $res = $db->query($sql);
            while ($row = $db->fetchRow($res)) {
                $row['order_status'] = $_LANG['os'][$row['order_status']];
                $row['pay_status'] = $_LANG['ps'][$row['pay_status']];
                $row['shipping_status'] = ($row['shipping_status'] == SS_SHIPPED_ING) ? $_LANG['ss_admin'][SS_SHIPPED_ING] : $_LANG['ss'][$row['shipping_status']];
                $row['action_time'] = local_date($_CFG['time_format'], $row['log_time']);
                $act_list[] = $row;
            }

            /* 同步发货 */
            /* 判断支付方式是否支付宝 */
            $alipay = false;
            $order = order_info($delivery_order['order_id']);  //根据订单ID查询订单信息，返回数组$order
            $payment = payment_info($order['pay_id']);           //取得支付方式信息

            /* 根据发货单id查询发货单信息 */
            if (!empty($delivery_id)) {
                $delivery_order = delivery_order_info($delivery_id);
            } else {
                die('order does not exist');
            }

            /* 检查此单发货商品库存缺货情况  ecmoban模板堂 --zhuo start 下单减库存*/
            $delivery_stock_sql = "SELECT DG.rec_id AS dg_rec_id, OG.rec_id AS og_rec_id, G.model_attr, G.model_inventory, DG.goods_id, DG.delivery_id, DG.is_real, DG.send_number AS sums, G.goods_number AS storage, G.goods_name, DG.send_number," .
                                    " OG.goods_attr_id, OG.warehouse_id, OG.area_id, OG.ru_id, OG.order_id, OG.product_id FROM " . $GLOBALS['ecs']->table('delivery_goods') . " AS DG, " .
                                    $GLOBALS['ecs']->table('goods') . " AS G, " .
                                    $GLOBALS['ecs']->table('delivery_order') . " AS D, " .
                                    $GLOBALS['ecs']->table('order_goods') . " AS OG " .
                                    " WHERE DG.goods_id = G.goods_id AND DG.delivery_id = D.delivery_id AND D.order_id = OG.order_id AND DG.goods_sn = OG.goods_sn AND DG.product_id = OG.product_id AND DG.delivery_id = '$delivery_id' GROUP BY OG.rec_id ";

            $delivery_stock_result = $GLOBALS['db']->getAll($delivery_stock_sql);

            $virtual_goods = array();
            for($i=0; $i<count($delivery_stock_result); $i++){
                if($delivery_stock_result[$i]['model_attr'] == 1){
                        $table_products = "products_warehouse";
                        $type_files = " and warehouse_id = '" .$delivery_stock_result[$i]['warehouse_id']. "'";
                }elseif($delivery_stock_result[$i]['model_attr'] == 2){
                        $table_products = "products_area";
                        $type_files = " and area_id = '" .$delivery_stock_result[$i]['area_id']. "'";
                }else{
                        $table_products = "products";
                        $type_files = "";
                }

                $sql = "SELECT * FROM " .$GLOBALS['ecs']->table($table_products). " WHERE goods_id = '" .$delivery_stock_result[$i]['goods_id']. "'" .$type_files. " LIMIT 0, 1";
                $prod = $GLOBALS['db']->getRow($sql);

                /* 如果商品存在规格就查询规格，如果不存在规格按商品库存查询 */
                if(empty($prod)){
                    if($delivery_stock_result[$i]['model_inventory'] == 1){
                        $delivery_stock_result[$i]['storage'] = get_warehouse_area_goods($delivery_stock_result[$i]['warehouse_id'], $delivery_stock_result[$i]['goods_id'], 'warehouse_goods');
                    }elseif($delivery_stock_result[$i]['model_inventory'] == 2){
                        $delivery_stock_result[$i]['storage'] = get_warehouse_area_goods($delivery_stock_result[$i]['area_id'], $delivery_stock_result[$i]['goods_id'], 'warehouse_area_goods');
                    }
                }else{
                    $products = get_warehouse_id_attr_number($delivery_stock_result[$i]['goods_id'], $delivery_stock_result[$i]['goods_attr_id'], $delivery_stock_result[$i]['ru_id'], $delivery_stock_result[$i]['warehouse_id'], $delivery_stock_result[$i]['area_id'], $delivery_stock_result[$i]['model_attr']);
                    $delivery_stock_result[$i]['storage'] = $products['product_number'];
                }

                if (($delivery_stock_result[$i]['sums'] > $delivery_stock_result[$i]['storage'] || $delivery_stock_result[$i]['storage'] <= 0) && (($_CFG['use_storage'] == '1'  && $_CFG['stock_dec_time'] == SDT_SHIP) || ($_CFG['use_storage'] == '0' && $delivery_stock_result[$i]['is_real'] == 0)))
                {
                    /* 操作失败 */
                    $links[] = array('text' => $_LANG['order_info'], 'href' => 'order.php?act=delivery_info&delivery_id=' . $delivery_id);
                    sys_msg(sprintf($_LANG['act_good_vacancy'], $value['goods_name']), 1, $links);
                    break;
                }

                /* 虚拟商品列表 virtual_card*/
                if ($delivery_stock_result[$i]['is_real'] == 0)
                {
                    $virtual_goods[] = array(
                                   'goods_id' => $delivery_stock_result[$i]['goods_id'],
                                   'goods_name' => $delivery_stock_result[$i]['goods_name'],
                                   'num' => $delivery_stock_result[$i]['send_number']
                                   );
                }
            }
            //ecmoban模板堂 --zhuo end 下单减库存

            /* 发货 */
            /* 处理虚拟卡 商品（虚货） */
            if ($virtual_goods && is_array($virtual_goods) && count($virtual_goods) > 0) {
                foreach ($virtual_goods as $virtual_value) {
                    virtual_card_shipping($virtual_value, $order['order_sn'], $msg, 'split');
                }

                //虚拟卡缺货
                if(!empty($msg)){
                    $links[] = array('text' => $_LANG['delivery_sn'] . $_LANG['detail'], 'href' => 'order.php?act=delivery_info&delivery_id=' . $delivery_id);
                    sys_msg($msg, 1, $links);
                }
            }

            /* 如果使用库存，且发货时减库存，则修改库存 */
            if ($_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_SHIP) {

                foreach ($delivery_stock_result as $value) {

                    /* 商品（实货）、超级礼包（实货） ecmoban模板堂 --zhuo */
                    if ($value['is_real'] != 0) {
                        //（货品）
                        if (!empty($value['product_id'])) {
                            if ($value['model_attr'] == 1) {
                                $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('products_warehouse') . "
                                            SET product_number = product_number - " . $value['sums'] . "
                                            WHERE product_id = " . $value['product_id'];
                            } elseif ($value['model_attr'] == 2) {
                                $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('products_area') . "
                                            SET product_number = product_number - " . $value['sums'] . "
                                            WHERE product_id = " . $value['product_id'];
                            } else {
                                $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('products') . "
                                            SET product_number = product_number - " . $value['sums'] . "
                                            WHERE product_id = " . $value['product_id'];
                            }
                        } else {
                            if ($value['model_inventory'] == 1) {
                                $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('warehouse_goods') . "
                                            SET region_number = region_number - " . $value['sums'] . "
                                            WHERE goods_id = " . $value['goods_id'] . " AND region_id = " . $value['warehouse_id'];
                            } elseif ($value['model_inventory'] == 2) {
                                $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('warehouse_area_goods') . "
                                            SET region_number = region_number - " . $value['sums'] . "
                                            WHERE goods_id = " . $value['goods_id'] . " AND region_id = " . $value['area_id'];
                            } else {
                                $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('goods') . "
                                            SET goods_number = goods_number - " . $value['sums'] . "
                                            WHERE goods_id = " . $value['goods_id'];
                            }
                        }

                        $GLOBALS['db']->query($minus_stock_sql, 'SILENT');

                        //库存日志
                        $logs_other = array(
                            'goods_id' => $value['goods_id'],
                            'order_id' => $value['order_id'],
                            'use_storage' => $_CFG['stock_dec_time'],
                            'admin_id' => $_SESSION['admin_id'],
                            'number' => "- " . $value['sums'],
                            'model_inventory' => $value['model_inventory'],
                            'model_attr' => $value['model_attr'],
                            'product_id' => $value['product_id'],
                            'warehouse_id' => $value['warehouse_id'],
                            'area_id' => $value['area_id'],
                            'add_time' => gmtime()
                        );

                        $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('goods_inventory_logs'), $logs_other, 'INSERT');
                    }
                }
            }

            /* 修改发货单信息 */
            $invoice_no = trim($invoice_no);
            $_delivery['invoice_no'] = $invoice_no;
            $_delivery['status'] = 0; // 0，为已发货
            $query = $db->autoExecute($ecs->table('delivery_order'), $_delivery, 'UPDATE', "delivery_id = $delivery_id", 'SILENT');
            if (!$query) {
                /* 操作失败 */
                $links[] = array('text' => $_LANG['delivery_sn'] . $_LANG['detail'], 'href' => 'order.php?act=delivery_info&delivery_id=' . $delivery_id);
                sys_msg($_LANG['act_false'], 1, $links);
            }

            /* 标记订单为已确认 “已发货” */
            /* 更新发货时间 */
            $order_finish = get_all_delivery_finish($order_id);
            $shipping_status = ($order_finish == 1) ? SS_SHIPPED : SS_SHIPPED_PART;
            $arr['shipping_status'] = $shipping_status;
            $arr['shipping_time'] = GMTIME_UTC; // 发货时间
            $arr['invoice_no'] = !empty($invoice_no) ? $invoice_no : $order['invoice_no'];

            if(empty($order['pay_time'])){
                $arr['pay_time'] = gmtime();
            }
            update_order($order_id, $arr);

            /* 发货单发货记录log */
            order_action($order['order_sn'], OS_CONFIRMED, $shipping_status, $order['pay_status'], $action_note, $_SESSION['admin_name'], 1);

            /* 如果当前订单已经全部发货 */
            if ($order_finish) {
                /* 如果订单用户不为空，计算积分，并发给用户；发红包 */
                if ($order['user_id'] > 0) {
                    /* 取得用户信息 */
                    $user = user_info($order['user_id']);

                    /* 计算并发放积分 */
                    $integral = integral_to_give($order);
                    /* 如果已配送子订单的赠送积分大于0   减去已配送子订单积分 */
                    if (!empty($child_order)) {
                        $integral['custom_points'] = $integral['custom_points'] - $child_order['custom_points'];
                        $integral['rank_points'] = $integral['rank_points'] - $child_order['rank_points'];
                    }
                    log_account_change($order['user_id'], 0, 0, intval($integral['rank_points']), intval($integral['custom_points']), sprintf($_LANG['order_gift_integral'], $order['order_sn']));

                    /* 发放红包 */
                    send_order_bonus($order_id);

                    /* 发放优惠券 bylu */
                    send_order_coupons($order_id);
                }

                /* 发送邮件 */
                $cfg = $_CFG['send_ship_email'];
                if ($cfg == '1') {
                    $order['invoice_no'] = $invoice_no;
                    $tpl = get_mail_template('deliver_notice');
                    $smarty->assign('order', $order);
                    $smarty->assign('send_time', local_date($_CFG['time_format']));
                    $smarty->assign('shop_name', $_CFG['shop_name']);
                    $smarty->assign('send_date', local_date($GLOBALS['_CFG']['time_format'], gmtime()));
                    $smarty->assign('sent_date', local_date($GLOBALS['_CFG']['time_format'], gmtime()));
                    $smarty->assign('confirm_url', $ecs->url() . 'user.php?act=order_detail&order_id=' . $order['order_id']); //by wu
                    $smarty->assign('send_msg_url', $ecs->url() . 'user.php?act=message_list&order_id=' . $order['order_id']);
                    $content = $smarty->fetch('str:' . $tpl['template_content']);
                    if (!send_mail($order['consignee'], $order['email'], $tpl['template_subject'], $content, $tpl['is_html'])) {
                        $msg = $_LANG['send_mail_fail'];
                    }
                }

                /* 如果需要，发短信 */
                if ($GLOBALS['_CFG']['sms_order_shipped'] == '1' && $order['mobile'] != '') {

                    //短信接口参数
                    if ($order['ru_id']) {
                        $shop_name = get_shop_name($order['ru_id'], 1);
                    } else {
                        $shop_name = "";
                    }

                    $user_info = get_admin_user_info($order['user_id']);

                    $smsParams = array(
                        'shop_name' => $shop_name,
                        'shopname' => $shop_name,
                        'user_name' => $user_info['user_name'],
                        'username' => $user_info['user_name'],
                        'consignee' => $order['consignee'],
                        'order_sn' => $order['order_sn'],
                        'ordersn' => $order['order_sn'],
                        'invoice_no' => $invoice_no,
                        'invoiceno' => $invoice_no,
                        'mobile_phone' => $order['mobile'],
                        'mobilephone' => $order['mobile']
                    );

                    if ($GLOBALS['_CFG']['sms_type'] == 0) {

                        huyi_sms($smsParams, 'sms_order_shipped');

                    } elseif ($GLOBALS['_CFG']['sms_type'] >=1) {

                        $result = sms_ali($smsParams, 'sms_order_shipped'); //阿里大鱼短信变量传值，发送时机传值

                        if ($result) {
                            $resp = $GLOBALS['ecs']->ali_yu($result);
                        } else {
                            sys_msg('阿里大鱼短信配置异常', 1);
                        }
                    }
                }

                /* 更新商品销量 一键发货 */
                get_goods_sale($order_id);
            }

            /* 清除缓存 */
            clear_cache_files();

            /* 操作成功 */
            $links[] = array('text' => $_LANG['09_delivery_order'], 'href' => 'order.php?act=delivery_list');
            $links[] = array('text' => $_LANG['delivery_sn'] . $_LANG['detail'], 'href' => 'order.php?act=delivery_info&delivery_id=' . $delivery_id);
            sys_msg($_LANG['act_ok'], 0, $links);
        }
    }
    /* ------------------------------------------------------ */
    //-- end一键发货
    /* ------------------------------------------------------ */

    /* 付款 */
    elseif (isset($_POST['pay']))
    {
        /* 检查权限 */
        admin_priv('order_ps_edit');
        $require_note   = $_CFG['order_pay_note'] == 1;
        $action         = $_LANG['op_pay'];
        $operation      = 'pay';
    }
    /* 未付款 */
    elseif (isset($_POST['unpay']))
    {
        /* 检查权限 */
        admin_priv('order_ps_edit');

        $require_note   = $_CFG['order_unpay_note'] == 1;
        $order          = order_info($order_id);
        if ($order['pay_status'] > 0)
        {
            $show_refund = true;
        }
        $anonymous      = $order['user_id'] == 0;
        $action         = $_LANG['op_unpay'];
        $operation      = 'unpay';

        $refound_amount = $order['total_fee'] - $order['shipping_fee'];
        $smarty->assign('refound_amount',    $refound_amount);
        $smarty->assign('shipping_fee',    $order['shipping_fee']);
    }
    /* 配货 */
    elseif (isset($_POST['prepare']))
    {
        $require_note   = false;
        $action         = $_LANG['op_prepare'];
        $operation      = 'prepare';
    }
    elseif (isset($_POST['ships']))
    {

        $require_note   = $_CFG['order_invalid_note'] == 1;
        $action         = $_LANG['op_split'];
        $operation      = 'ships';

        $order          = order_info($order_id);
        $anonymous      = $order['user_id'] == 0;
    }
    /* 分单 */
    elseif (isset($_POST['ship']))
    {
        /* 查询：检查权限 */
        admin_priv('order_ss_edit');

        $order_id = intval(trim($order_id));
        $action_note = trim($action_note);

        /* 查询：根据订单id查询订单信息 */
        if (!empty($order_id))
        {
            $order = order_info($order_id);
        }
        else
        {
            die('order does not exist');
        }

        /* 查询：根据订单是否完成 检查权限 */
        if (order_finished($order))
        {
            admin_priv('order_view_finished');
        }
        else
        {
            admin_priv('order_view');
        }

        /* 查询：如果管理员属于某个办事处，检查该订单是否也属于这个办事处 */
        $sql = "SELECT agency_id FROM " . $ecs->table('admin_user') . " WHERE user_id = '$_SESSION[admin_id]'";
        $agency_id = $db->getOne($sql);
        if ($agency_id > 0)
        {
            if ($order['agency_id'] != $agency_id)
            {
                sys_msg($_LANG['priv_error'], 0);
            }
        }

        /* 查询：取得用户名 */
        if ($order['user_id'] > 0)
        {
            $user = user_info($order['user_id']);
            if (!empty($user))
            {
                $order['user_name'] = $user['user_name'];
            }
        }

        /* 查询：取得区域名 */
        $order['region'] = get_user_region_address($order['order_id']);

        /* 查询：其他处理 */
        $order['order_time']    = local_date($_CFG['time_format'], $order['add_time']);
        $order['invoice_no']    = $order['shipping_status'] == SS_UNSHIPPED || $order['shipping_status'] == SS_PREPARING ? $_LANG['ss'][SS_UNSHIPPED] : $order['invoice_no'];

        /* 查询：是否保价 */
        $order['insure_yn'] = empty($order['insure_fee']) ? 0 : 1;

        /* 查询：是否存在实体商品 */
        $exist_real_goods = exist_real_goods($order_id);

        /* 查询：取得订单商品 */
        $_goods = get_order_goods(array('order_id' => $order['order_id'], 'order_sn' =>$order['order_sn']));

        $attr = $_goods['attr'];
        $goods_list = $_goods['goods_list'];
        unset($_goods);

        /* 查询：商品已发货数量 此单可发货数量 */
        if ($goods_list)
        {
            foreach ($goods_list as $key=>$goods_value)
            {
                if (!$goods_value['goods_id'])
                {
                    continue;
                }

                /* 超级礼包 */
                if (($goods_value['extension_code'] == 'package_buy') && (count($goods_value['package_goods_list']) > 0))
                {
                    $goods_list[$key]['package_goods_list'] = package_goods($goods_value['package_goods_list'], $goods_value['goods_number'], $goods_value['order_id'], $goods_value['extension_code'], $goods_value['goods_id']);

                    foreach ($goods_list[$key]['package_goods_list'] as $pg_key => $pg_value)
                    {
                        $goods_list[$key]['package_goods_list'][$pg_key]['readonly'] = '';
                        /* 使用库存 是否缺货 */
                        if ($pg_value['storage'] <= 0 && $_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_SHIP)
                        {
                            //$goods_list[$key]['package_goods_list'][$pg_key]['send'] = $_LANG['act_good_vacancy'];
                            $goods_list[$key]['package_goods_list'][$pg_key]['readonly'] = 'readonly="readonly"';
                        }
                        /* 将已经全部发货的商品设置为只读 */
                        elseif ($pg_value['send'] <= 0)
                        {
                            //$goods_list[$key]['package_goods_list'][$pg_key]['send'] = $_LANG['act_good_delivery'];
                            $goods_list[$key]['package_goods_list'][$pg_key]['readonly'] = 'readonly="readonly"';
                        }
                    }
                }
                else
                {
                    $goods_list[$key]['sended'] = $goods_value['send_number'];
                    $goods_list[$key]['send'] = $goods_value['goods_number'] - $goods_value['send_number'];

                    $goods_list[$key]['readonly'] = '';
                    /* 是否缺货 */
                    if ($goods_value['storage'] <= 0 && $_CFG['use_storage'] == '1'  && $_CFG['stock_dec_time'] == SDT_SHIP)
                    {
                        $goods_list[$key]['send'] = $_LANG['act_good_vacancy'];
                        $goods_list[$key]['readonly'] = 'readonly="readonly"';
                    }
                    elseif ($goods_list[$key]['send'] <= 0)
                    {
                        $goods_list[$key]['send'] = $_LANG['act_good_delivery'];
                        $goods_list[$key]['readonly'] = 'readonly="readonly"';
                    }
                }
            }
        }

        /* 模板赋值 */
        $smarty->assign('order', $order);

        if ($order['is_zc_order']) {
            $smarty->assign('exist_real_goods', true);
        } else {
            $smarty->assign('exist_real_goods', $exist_real_goods);
        }

        $smarty->assign('goods_attr', $attr);
        $smarty->assign('goods_list', $goods_list);
        $smarty->assign('order_id', $order_id); // 订单id
        $smarty->assign('operation', 'split'); // 订单id
        $smarty->assign('action_note', $action_note); // 发货操作信息

        $suppliers_list = get_suppliers_list();
        $suppliers_list_count = count($suppliers_list);
        $smarty->assign('suppliers_name', suppliers_list_name()); // 取供货商名
        $smarty->assign('suppliers_list', ($suppliers_list_count == 0 ? 0 : $suppliers_list)); // 取供货商列表

        /* 显示模板 */
        $smarty->assign('ur_here', $_LANG['order_operate'] . $_LANG['op_split']);
        assign_query_info();
          /* 取得订单操作记录 */
        $act_list = array();
        $sql = "SELECT * FROM " . $ecs->table('order_action') . " WHERE order_id = '$order_id' ORDER BY log_time DESC,action_id DESC";
        $res = $db->query($sql);
        while ($row = $db->fetchRow($res))
        {
            $row['order_status']    = $_LANG['os'][$row['order_status']];
            $row['pay_status']      = $_LANG['ps'][$row['pay_status']];
            $row['shipping_status'] = $_LANG['ss'][$row['shipping_status']];
            $row['action_time']     = local_date($_CFG['time_format'], $row['log_time']);
            $act_list[] = $row;
        }
        $smarty->assign('action_list', $act_list);
        $smarty->display('order_delivery_info.dwt');
        exit;
    }
    /* 未发货 */
    elseif (isset($_POST['unship']))
    {
        /* 检查权限 */
        admin_priv('order_ss_edit');

        $require_note   = $_CFG['order_unship_note'] == 1;
        $action         = $_LANG['op_unship'];
        $operation      = 'unship';
    }
    /* 收货确认 */
    elseif (isset($_POST['receive']))
    {
        $require_note   = $_CFG['order_receive_note'] == 1;
        $action         = $_LANG['op_receive'];
        $operation      = 'receive';
    }
    /* 取消 */
    elseif (isset($_POST['cancel']))
    {
        $require_note   = $_CFG['order_cancel_note'] == 1;
        $action         = $_LANG['op_cancel'];
        $operation      = 'cancel';
        $show_cancel_note   = true;
        $order          = order_info($order_id);
        if ($order['pay_status'] > 0)
        {
            $show_refund = true;
        }
        $anonymous      = $order['user_id'] == 0;
    }
    /* 无效 */
    elseif (isset($_POST['invalid']))
    {
        $require_note   = $_CFG['order_invalid_note'] == 1;
        $action         = $_LANG['op_invalid'];
        $operation      = 'invalid';
    }
    /* 发货 */
    if (isset($_POST['shipping']))
    {
        $require_note   = false;
        $action         = $_LANG['op_ship'];
        $operation      = 'shipping';
		$op_shipping	= true;
    }
    /* 售后 */
    elseif (isset($_POST['after_service']))
    {
        $require_note   = true;
        $action         = $_LANG['op_after_service'];
        $operation      = 'after_service';
    }
    /* 退货 */
    elseif (isset($_POST['return']))
    {
        $sql = "SELECT ret_id FROM " . $ecs->table('order_return') . " WHERE order_id = '" . $order_id . "'";
        $ret_id = $db->getOne($sql);
        if ($ret_id > 0) {
            $links[] = array('text' => $_LANG['go_back'], 'href' => 'order.php?act=info&order_id='.$order_id);
            sys_msg("该订单存在退换货商品，不能退货", 0 ,$links);
        } else {
            $require_note = $_CFG['order_return_note'] == 1;
            $order = order_info($order_id);
            if ($order['pay_status'] > 0) {
                $show_refund = true;
            }
            $anonymous = $order['user_id'] == 0;
            $action = $_LANG['op_return'];
            $operation = 'return';
        }

        $sql = "SELECT vc_id, use_val FROM " .$GLOBALS['ecs']->table('value_card_record'). " WHERE order_id = '" .$order['order_id']. "' LIMIT 1";
        $value_card = $GLOBALS['db']->getRow($sql);

        $paid_amount = $order['money_paid'] + $order['surplus'];

        if ($paid_amount > 0 && $order['shipping_fee'] > 0 && $paid_amount >= $order['shipping_fee']) {
            $refound_amount = $paid_amount - $order['shipping_fee'];
        } else {
            $refound_amount = $paid_amount;
        }

        $smarty->assign('refound_amount',    $refound_amount);
        $smarty->assign('shipping_fee',    $order['shipping_fee']);
        $smarty->assign('value_card', $value_card);
        $smarty->assign('is_whole', 1);
    }

    /**
     * 同意申请
     * by ecmoban模板堂 --zhuo
     */ elseif (isset($_POST['agree_apply'])) {
        $require_note = false;
        $action = $_LANG['op_confirm'];
        $operation = 'agree_apply';
    }

    /* 退款
     * by Leah
     */
    elseif (isset($_POST['refound'])) {
        $require_note = $_CFG['order_return_note'] == 1;
        $order = order_info($order_id);


        $refound_amount = empty($_REQUEST['refound_amount']) ? 0 : floatval($_REQUEST['refound_amount']);
        $return_shipping_fee = empty($_REQUEST['return_shipping_fee']) ? 0 : floatval($_REQUEST['return_shipping_fee']);

        //判断运费退款是否大于实际运费退款金额
        $is_refound_shippfee = order_refound_shipping_fee($order_id, $ret_id);
        $is_refound_shippfee_amount = $is_refound_shippfee + $return_shipping_fee;

        if (($is_refound_shippfee_amount > $order['shipping_fee']) || ($return_shipping_fee == 0 && $is_refound_shippfee > 0)) {
            $return_shipping_fee = $order['shipping_fee'] - $is_refound_shippfee;
        } elseif ($return_shipping_fee == 0 && $is_refound_shippfee == 0) {
            $return_shipping_fee = $order['shipping_fee'];
        }

        // 判断退货单订单中是否只有一个商品   如果只有一个则退订单的全部积分   如果多个则按商品积分的比例来退  by kong
        $count_goods = $db->getAll(" SELECT rec_id ,goods_id FROM " . $ecs->table("order_goods") . " WHERE order_id = '$order_id'");
        if (count($count_goods) > 1) {

            foreach ($count_goods as $k => $v) {
                $all_goods_id[] = $v['goods_id'];
            }
            $count_integral = $db->getOne(" SELECT sum(integral) FROM" . $ecs->table("goods") . " WHERE  goods_id" . db_create_in($all_goods_id)); //获取该订单的全部可用积分
            $return_integral = $db->getOne(' SELECT g.integral FROM' . $ecs->table("goods") . " as g LEFT JOIN " . $ecs->table("order_return") . " as o on o.goods_id = g.goods_id  WHERE o.ret_id = '$ret_id'"); //退货商品的可用积分
            $count_integral = !empty($count_integral) ? $count_integral : 1;
            $return_ratio = $return_integral / $count_integral; //退还积分比例
            $return_price = (empty($order['pay_points']) ? '' : $order['pay_points']) * $return_ratio; //那比例最多返还的积分
        } else {
            $return_price = empty($order['pay_points']) ? '' : $order['pay_points']; //by kong 赋值支付积分
        }

        $goods_number = $GLOBALS['db']->getOne(" SELECT goods_number FROM " . $GLOBALS['ecs']->table("order_goods") . " WHERE rec_id = '$rec_id'"); //获取该商品的订单数量
        $return_number = $GLOBALS['db']->getOne(" SELECT return_number FROM " . $GLOBALS['ecs']->table("order_return_extend") . " WHERE ret_id = '$ret_id'"); //获取退货数量
        //*如果退货数量小于订单商品数量   则按比例返还*/
        if ($return_number < $goods_number) {
            $refound_pay_points = intval($return_price * ($return_number / $goods_number));
        } else {
            $refound_pay_points = intval($return_price);
        }

        if ($order['pay_status'] > 0) {
            $show_refund1 = true;
        }
        $anonymous = $order['user_id'] == 0;
        $action = $_LANG['op_return'];
        $operation = 'refound';
        $my_payment = payment_info($order['pay_id']);
        if ('wxpay' == $my_payment['pay_code'] || 'alipay' == $my_payment['pay_code']) {
            //微信支付可以原路退款
            $original_road_refund = true;
            $smarty->assign('original_road_refund', $original_road_refund);
        }

        $sql = "SELECT vc_id, use_val FROM " .$GLOBALS['ecs']->table('value_card_record'). " WHERE order_id = '" .$order['order_id']. "' LIMIT 1";
        $value_card = $GLOBALS['db']->getRow($sql);

        $return_order = return_order_info($ret_id);

        $should_return = $return_order['should_return'] - $return_order['discount_amount'];
        if ($value_card) {
            if ($value_card['use_val'] > $should_return) {
                $value_card['use_val'] = $should_return;
            }
        }

        $paid_amount = $order['money_paid'] + $order['surplus'];
        if($paid_amount > 0 && $paid_amount >= $order['shipping_fee']){
            $paid_amount = $paid_amount - $order['shipping_fee'];
        }

        if($refound_amount > $paid_amount){
            $refound_amount = $paid_amount;
        }

        $smarty->assign('refound_pay_points', $refound_pay_points); // by kong  页面赋值
        $smarty->assign('refound_amount', $refound_amount);
        $smarty->assign('shipping_fee', $return_shipping_fee);
        $smarty->assign('value_card', $value_card);

        /* 检测订单是否只有一个退货商品的订单 start */
        $is_whole = 0;
        $is_diff = get_order_return_rec($order['order_id']);
        if ($is_diff) {
            //整单退换货
            $return_count = return_order_info_byId($order['order_id'], 0);
            if ($return_count == 1) {
                $is_whole = 1;
            }
        }

        $smarty->assign('is_whole', $is_whole);
        /* 检测订单是否只有一个退货商品的订单 end */
    }
    /**
     * 收到退换货商品
     * by Leah
     */ elseif (isset($_POST['receive_goods'])) {
        $require_note = false;
        $action = $_LANG['op_confirm'];
        $operation = 'receive_goods';
    }
    /**
     * 换出商品 --  快递信息
     * by Leah
     */ elseif (isset($_POST['send_submit'])) {

        $shipping_id = $_POST['shipping_name'];
        $invoice_no = $_POST['invoice_no'];
        $action_note = $_POST['action_note'];
        $sql = "SELECT shipping_name FROM " . $ecs->table('shipping') . " WHERE shipping_id =" . $shipping_id;
        $shipping_name = $db->getOne($sql);
        $require_note = false;
        $action = $_LANG['op_confirm'];
        $operation = 'receive_goods';
        $db->query("UPDATE " . $ecs->table('order_return') . " SET out_shipping_name = '$shipping_id' ,out_invoice_no ='$invoice_no'" .
                "WHERE rec_id = '$rec_id'");
    }
    /**
     * 商品分单寄出
     * by Leah
     */ elseif (isset($_POST['swapped_out'])) {

        $require_note = false;
        $action = $_LANG['op_confirm'];
        $operation = 'swapped_out';
    }
    /**
     * 商品分单寄出  分单
     * by Leah
     */ elseif (isset($_POST['swapped_out_single'])) {

        $require_note = false;
        $action = $_LANG['op_confirm'];
        $operation = 'swapped_out_single';
    }
    /**
     * 完成退换货
     * by Leah
     */ elseif (isset($_POST['complete'])) {

        $require_note = false;
        $action = $_LANG['op_confirm'];
        $operation = 'complete';
    }
    /**
     * 拒绝申请
     * by Leah
     */ elseif (isset($_POST['refuse_apply'])) {

        $require_note = true;
        $action = $_LANG['refuse_apply'];
        $operation = 'refuse_apply';
    }

    /* 指派 */
    elseif (isset($_POST['assign']))
    {
        /* 取得参数 */
        $new_agency_id  = isset($_POST['agency_id']) ? intval($_POST['agency_id']) : 0;
        if ($new_agency_id == 0)
        {
            sys_msg($_LANG['js_languages']['pls_select_agency']);
        }

        /* 查询订单信息 */
        $order = order_info($order_id);

        /* 如果管理员属于某个办事处，检查该订单是否也属于这个办事处 */
        $sql = "SELECT agency_id FROM " . $ecs->table('admin_user') . " WHERE user_id = '$_SESSION[admin_id]'";
        $admin_agency_id = $db->getOne($sql);
        if ($admin_agency_id > 0)
        {
            if ($order['agency_id'] != $admin_agency_id)
            {
                sys_msg($_LANG['priv_error']);
            }
        }

        /* 修改订单相关所属的办事处 */
        if ($new_agency_id != $order['agency_id'])
        {
            $query_array = array('order_info', // 更改订单表的供货商ID
                                 'delivery_order', // 更改订单的发货单供货商ID
                                 'back_order'// 更改订单的退货单供货商ID
            );
            foreach ($query_array as $value)
            {
                $db->query("UPDATE " . $ecs->table($value) . " SET agency_id = '$new_agency_id' " .
                    "WHERE order_id = '$order_id'");

            }
        }

        /* 操作成功 */
        $links[] = array('href' => 'order.php?act=list&' . list_link_postfix(), 'text' => $_LANG['02_order_list']);
        sys_msg($_LANG['act_ok'], 0, $links);
    }
    /* 订单删除 */
    elseif (isset($_POST['remove']))
    {
        $require_note = false;
        $operation = 'remove';
        if (!$batch)
        {
            /* 检查能否操作 */
            $order = order_info($order_id);
            $operable_list = operable_list($order);
            if (!isset($operable_list['remove']))
            {
                die('Hacking attempt');
            }

            $return_order = return_order_info(0, '', $order['order_id']);
            if($return_order){
                sys_msg(sprintf($_LANG['order_remove_failure'], $order['order_sn']), 0, array(array('href'=>'order.php?act=list&' . list_link_postfix(), 'text' => $_LANG['return_list'])));
                exit;
            }

            /* 删除订单 */
            $db->query("DELETE FROM ".$ecs->table('order_info'). " WHERE order_id = '$order_id'");
            $db->query("DELETE FROM ".$ecs->table('order_goods'). " WHERE order_id = '$order_id'");
            $db->query("DELETE FROM ".$ecs->table('order_action'). " WHERE order_id = '$order_id'");
            $action_array = array('delivery', 'back');
            del_delivery($order_id, $action_array);

            /* todo 记录日志 */
            admin_log($order['order_sn'], 'remove', 'order');

            /* 返回 */
            sys_msg($_LANG['order_removed'], 0, array(array('href'=>'order.php?act=list&' . list_link_postfix(), 'text' => $_LANG['return_list'])));
        }
    }
    /* 发货单删除 */
    elseif (isset($_REQUEST['remove_invoice']))
    {
        // 删除发货单
        $delivery_id=  isset($_REQUEST['delivery_id'])  ?  $_REQUEST['delivery_id']  : $_REQUEST['checkboxes'];
        $delivery_id = is_array($delivery_id) ? $delivery_id : array($delivery_id);

        foreach($delivery_id as $value_is)
        {
            $value_is = intval(trim($value_is));

            // 查询：发货单信息
            $delivery_order = delivery_order_info($value_is);

            // 如果status不是退货
            if ($delivery_order['status'] != 1)
            {
                /* 处理退货 */
                delivery_return_goods($value_is, $delivery_order);
            }

            // 如果status是已发货并且发货单号不为空
            if ($delivery_order['status'] == 0 && $delivery_order['invoice_no'] != '')
            {
                /* 更新：删除订单中的发货单号 */
                del_order_invoice_no($delivery_order['order_id'], $delivery_order['invoice_no']);
            }

            // 更新：删除发货单
            $sql = "DELETE FROM ".$ecs->table('delivery_order'). " WHERE delivery_id = '$value_is'";
            $db->query($sql);
        }

        /* 返回 */
        sys_msg($_LANG['tips_delivery_del'], 0, array(array('href'=>'order.php?act=delivery_list' , 'text' => $_LANG['return_list'])));
    }
     /* 退货单删除 */
    elseif (isset($_REQUEST['remove_back']))
    {
        $back_id = isset($_REQUEST['back_id'])  ?  $_REQUEST['back_id'] : $_POST['checkboxes'];
        /* 删除退货单 */
        if(is_array($back_id))
        {
        foreach ($back_id as $value_is)
            {
                $sql = "DELETE FROM ".$ecs->table('back_order'). " WHERE back_id = '$value_is'";
                $db->query($sql);
            }
        }
        else
        {
            $sql = "DELETE FROM ".$ecs->table('back_order'). " WHERE back_id = '$back_id'";
            $db->query($sql);
        }
        /* 返回 */
        sys_msg($_LANG['tips_back_del'], 0, array(array('href'=>'order.php?act=back_list' , 'text' => $_LANG['return_list'])));
    }
    /* 批量打印订单 */
    elseif (isset($_POST['print']))
    {
        if (empty($_POST['order_id']))
        {
            sys_msg($_LANG['pls_select_order']);
        }

		//快递鸟、电子面单 start
        $url = 'tp_api.php?act=order_print&order_sn='.$_POST['order_id'].'&order_type=order';
        ecs_header("Location: $url\n");
        exit;
		//快递鸟、电子面单 end

        /* 赋值公用信息 */
        $smarty->assign('print_time',   local_date($_CFG['time_format']));
        $smarty->assign('action_user',  $_SESSION['admin_name']);

        $html = '';
        $order_sn_list = explode(',', $_POST['order_id']);
        foreach ($order_sn_list as $order_sn)
        {
            /* 取得订单信息 */
            $order = order_info(0, $order_sn);

            /*判断是否是商家商品  by kong*/
            if($order['ru_id'] > 0){
                 //判断是否是主订单
                $date = array('order_id');
                $order_child = count(get_table_date('order_info', "main_order_id = '$order[order_id]'", $date, 1));
                if($order_child > 0){
                    $smarty->assign('shop_url',     $ecs->url());
                    $smarty->assign('shop_name',    $_CFG['shop_name']);
                    $smarty->assign('shop_address', $_CFG['shop_address']);
                    $smarty->assign('service_phone',$_CFG['service_phone']);
                }else{
                    $smarty->assign('shop_url',     $ecs->url());
                    $sql="SELECT domain_name FROM ".$ecs->table("seller_domain")." WHERE ru_id = '".$order['ru_id']."' AND  is_enable = 1";//获取商家域名
                    $domain_name = $db->getOne($sql);
                    $smarty->assign('domain_name',    $domain_name);
                    $smarty->assign('shop_name',   get_shop_name($order['ru_id'], 1));
                    $seller_shopinfo=$db->getRow("SELECT shop_address ,kf_tel FROM".$ecs->table("seller_shopinfo")." WHERE ru_id = '$order[ru_id]' LIMIT 1");//获取商家地址，电话
                    $smarty->assign('shop_address', $seller_shopinfo['shop_address']);
                    $smarty->assign('service_phone',$seller_shopinfo['kf_tel']);
                }
            }else{
                $smarty->assign('shop_url',     $ecs->url());
                $smarty->assign('shop_name',    $_CFG['shop_name']);
                $smarty->assign('shop_address', $_CFG['shop_address']);
                $smarty->assign('service_phone',$_CFG['service_phone']);
            }
            // by kong end
            if (empty($order))
            {
                continue;
            }

            /* 根据订单是否完成检查权限 */
            if (order_finished($order))
            {
                if (!admin_priv('order_view_finished', '', false))
                {
                    continue;
                }
            }
            else
            {
                if (!admin_priv('order_view', '', false))
                {
                    continue;
                }
            }

            /* 如果管理员属于某个办事处，检查该订单是否也属于这个办事处 */
            $sql = "SELECT agency_id FROM " . $ecs->table('admin_user') . " WHERE user_id = '$_SESSION[admin_id]'";
            $agency_id = $db->getOne($sql);
            if ($agency_id > 0)
            {
                if ($order['agency_id'] != $agency_id)
                {
                    continue;
                }
            }

            /* 取得用户名 */
            if ($order['user_id'] > 0)
            {
                $user = user_info($order['user_id']);
                if (!empty($user))
                {
                    $order['user_name'] = $user['user_name'];
                }
            }

            /* 取得区域名 */
            $order['region'] = get_user_region_address($order['order_id']);

            /* 其他处理 */
            $order['order_time']    = local_date($_CFG['time_format'], $order['add_time']);
            $order['pay_time']      = $order['pay_time'] > 0 ?
                local_date($_CFG['time_format'], $order['pay_time']) : $_LANG['ps'][PS_UNPAYED];
            $order['shipping_time'] = $order['shipping_time'] > 0 ?
                local_date($_CFG['time_format'], $order['shipping_time']) : $_LANG['ss'][SS_UNSHIPPED];
            $order['status']        = $_LANG['os'][$order['order_status']] . ',' . $_LANG['ps'][$order['pay_status']] . ',' . $_LANG['ss'][$order['shipping_status']];
            $order['invoice_no']    = $order['shipping_status'] == SS_UNSHIPPED || $order['shipping_status'] == SS_PREPARING ? $_LANG['ss'][SS_UNSHIPPED] : $order['invoice_no'];

            /* 此订单的发货备注(此订单的最后一条操作记录) */
            $sql = "SELECT action_note FROM " . $ecs->table('order_action').
                   " WHERE order_id = '$order[order_id]' AND shipping_status = 1 ORDER BY log_time DESC";
            $order['invoice_note'] = $db->getOne($sql);

            /* 参数赋值：订单 */
            $smarty->assign('order', $order);

            /* 取得订单商品 */
            $goods_list = array();
            $goods_attr = array();
            $sql = "SELECT o.*, c.measure_unit, g.goods_unit, g.goods_number AS storage, o.goods_attr, IFNULL(b.brand_name, '') AS brand_name, g.bar_code " .
                    "FROM " . $ecs->table('order_goods') . " AS o ".
                    "LEFT JOIN " . $ecs->table('goods') . " AS g ON o.goods_id = g.goods_id " .
                    "LEFT JOIN " . $ecs->table('brand') . " AS b ON g.brand_id = b.brand_id " .
                    'LEFT JOIN ' . $GLOBALS['ecs']->table('category') . ' AS c ON g.cat_id = c.cat_id ' .
                    "WHERE o.order_id = '$order[order_id]' ";
            $res = $db->query($sql);
            while ($row = $db->fetchRow($res))
            {
                $products = get_warehouse_id_attr_number($row['goods_id'], $row['goods_attr_id'], $row['ru_id'], $row['warehouse_id'], $row['area_id'], $row['model_attr']);
                if($row['product_id']){
                    $row['bar_code'] = $products['bar_code'];
                }

                /* 虚拟商品支持 */
                if ($row['is_real'] == 0)
                {
                    /* 取得语言项 */
                    $filename = ROOT_PATH . 'plugins/' . $row['extension_code'] . '/languages/common_' . $_CFG['lang'] . '.php';
                    if (file_exists($filename))
                    {
                        include_once($filename);
                        if (!empty($_LANG[$row['extension_code'].'_link']))
                        {
                            $row['goods_name'] = $row['goods_name'] . sprintf($_LANG[$row['extension_code'].'_link'], $row['goods_id'], $order['order_sn']);
                        }
                    }
                }

                $row['formated_subtotal']       = price_format($row['goods_price'] * $row['goods_number']);
                $row['formated_goods_price']    = price_format($row['goods_price']);
				$row['measure_unit']			= $row['goods_unit'] ? $row['goods_unit'] : $row['measure_unit'];

                $goods_attr[] = explode(' ', trim($row['goods_attr'])); //将商品属性拆分为一个数组
                $goods_list[] = $row;
            }

            $attr = array();
            $arr  = array();
            foreach ($goods_attr AS $index => $array_val)
            {
                foreach ($array_val AS $value)
                {
                    $arr = explode(':', $value);//以 : 号将属性拆开
                    $attr[$index][] =  @array('name' => $arr[0], 'value' => $arr[1]);
                }
            }
            $smarty->assign('goods_attr', $attr);
            $smarty->assign('goods_list', $goods_list);
            $smarty->template_dir = '../' . DATA_DIR;
            $html .= $smarty->fetch('order_print.html') .
                '<div style="PAGE-BREAK-AFTER:always"></div>';
        }

        echo $html;
        exit;
    }
    /* 去发货 */
    elseif (isset($_POST['to_delivery']))
    {
        $url = 'order.php?act=delivery_list&order_sn='.$_REQUEST['order_sn']."&seller_order_list=".$_REQUEST['seller_order_list'];

        ecs_header("Location: $url\n");
        exit;
    }
    /* 批量发货 by wu */
    elseif (isset($_REQUEST['batch_delivery']))
    {
        /* 检查权限 */
        admin_priv('delivery_view');
        /* 定义当前时间 */
        define('GMTIME_UTC', gmtime()); // 获取 UTC 时间戳

        $delivery_id = isset($_REQUEST['delivery_id']) ? $_REQUEST['delivery_id'] : $_REQUEST['checkboxes'];
        $delivery_id = is_array($delivery_id) ? $delivery_id : array($delivery_id);
        $invoice_nos = isset($_REQUEST['invoice_no']) ? $_REQUEST['invoice_no'] : array();
        $action_note = isset($_REQUEST['action_note']) ? trim($_REQUEST['action_note']) : '';

        foreach ($delivery_id as $value_is) {
            $msg = '';
            $value_is = intval(trim($value_is));
            $delivery_info = get_table_date('delivery_order', "delivery_id='$value_is'", array('order_id', 'status'));

            //跳过已发货、退货订单
            if ($delivery_info['status'] != 2 || !isset($invoice_nos[$value_is])) {
                continue;
            }

            /* 取得参数 */
            $delivery = array();
            $order_id = $delivery_info['order_id'];        // 订单id
            $delivery_id = $value_is;        // 发货单id
            $delivery['invoice_no'] = $invoice_nos[$value_is];
            $action_note = $action_note;

            /* 根据发货单id查询发货单信息 */
            if (!empty($delivery_id)) {
                $delivery_order = delivery_order_info($delivery_id);
            } else {
                die('order does not exist');
            }

            /* 查询订单信息 */
            $order = order_info($order_id);
            /* 检查此单发货商品库存缺货情况  ecmoban模板堂 --zhuo start 下单减库存 */
            $delivery_stock_sql = "SELECT DG.rec_id AS dg_rec_id, OG.rec_id AS og_rec_id, G.model_attr, G.model_inventory, DG.goods_id, DG.delivery_id, DG.is_real, DG.send_number AS sums, G.goods_number AS storage, G.goods_name, DG.send_number," .
                    " OG.goods_attr_id, OG.warehouse_id, OG.area_id, OG.ru_id, OG.order_id, OG.product_id FROM " . $GLOBALS['ecs']->table('delivery_goods') . " AS DG, " .
                    $GLOBALS['ecs']->table('goods') . " AS G, " .
                    $GLOBALS['ecs']->table('delivery_order') . " AS D, " .
                    $GLOBALS['ecs']->table('order_goods') . " AS OG " .
                    " WHERE DG.goods_id = G.goods_id AND DG.delivery_id = D.delivery_id AND D.order_id = OG.order_id AND DG.goods_sn = OG.goods_sn AND DG.product_id = OG.product_id AND DG.delivery_id = '$delivery_id' GROUP BY OG.rec_id ";

            $delivery_stock_result = $GLOBALS['db']->getAll($delivery_stock_sql);

            $virtual_goods = array();
            for ($i = 0; $i < count($delivery_stock_result); $i++) {
                if ($delivery_stock_result[$i]['model_attr'] == 1) {
                    $table_products = "products_warehouse";
                    $type_files = " and warehouse_id = '" . $delivery_stock_result[$i]['warehouse_id'] . "'";
                } elseif ($delivery_stock_result[$i]['model_attr'] == 2) {
                    $table_products = "products_area";
                    $type_files = " and area_id = '" . $delivery_stock_result[$i]['area_id'] . "'";
                } else {
                    $table_products = "products";
                    $type_files = "";
                }

                $sql = "SELECT * FROM " . $GLOBALS['ecs']->table($table_products) . " WHERE goods_id = '" . $delivery_stock_result[$i]['goods_id'] . "'" . $type_files . " LIMIT 0, 1";
                $prod = $GLOBALS['db']->getRow($sql);

                /* 如果商品存在规格就查询规格，如果不存在规格按商品库存查询 */
                if (empty($prod)) {
                    if ($delivery_stock_result[$i]['model_inventory'] == 1) {
                        $delivery_stock_result[$i]['storage'] = get_warehouse_area_goods($delivery_stock_result[$i]['warehouse_id'], $delivery_stock_result[$i]['goods_id'], 'warehouse_goods');
                    } elseif ($delivery_stock_result[$i]['model_inventory'] == 2) {
                        $delivery_stock_result[$i]['storage'] = get_warehouse_area_goods($delivery_stock_result[$i]['area_id'], $delivery_stock_result[$i]['goods_id'], 'warehouse_area_goods');
                    }
                } else {
                    $products = get_warehouse_id_attr_number($delivery_stock_result[$i]['goods_id'], $delivery_stock_result[$i]['goods_attr_id'], $delivery_stock_result[$i]['ru_id'], $delivery_stock_result[$i]['warehouse_id'], $delivery_stock_result[$i]['area_id'], $delivery_stock_result[$i]['model_attr']);
                    $delivery_stock_result[$i]['storage'] = $products['product_number'];
                }

                if (($delivery_stock_result[$i]['sums'] > $delivery_stock_result[$i]['storage'] || $delivery_stock_result[$i]['storage'] <= 0) && (($_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_SHIP) || ($_CFG['use_storage'] == '0' && $delivery_stock_result[$i]['is_real'] == 0))) {
                    /* 操作失败 */
                    $links[] = array('text' => $_LANG['order_info'], 'href' => 'order.php?act=delivery_info&delivery_id=' . $delivery_id);
                    //sys_msg(sprintf($_LANG['act_good_vacancy'], $value['goods_name']), 1, $links);
                    break;
                }

                /* 虚拟商品列表 virtual_card */
                if ($delivery_stock_result[$i]['is_real'] == 0) {
                    $virtual_goods[] = array(
                        'goods_id' => $delivery_stock_result[$i]['goods_id'],
                        'goods_name' => $delivery_stock_result[$i]['goods_name'],
                        'num' => $delivery_stock_result[$i]['send_number']
                    );
                }
            }
            //ecmoban模板堂 --zhuo end 下单减库存

            /* 发货 */
            /* 处理虚拟卡 商品（虚货） */
            if ($virtual_goods && is_array($virtual_goods) && count($virtual_goods) > 0) {
                foreach ($virtual_goods as $virtual_value) {
                    virtual_card_shipping($virtual_value, $order['order_sn'], $msg, 'split');
                }

                //虚拟卡缺货
                if(!empty($msg)){
                    continue;
                }
            }

            /* 如果使用库存，且发货时减库存，则修改库存 */
            if ($_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_SHIP) {

                foreach ($delivery_stock_result as $value) {

                    /* 商品（实货）、超级礼包（实货） ecmoban模板堂 --zhuo */
                    if ($value['is_real'] != 0) {
                        //（货品）
                        if (!empty($value['product_id'])) {
                            if ($value['model_attr'] == 1) {
                                $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('products_warehouse') . "
                                                                                                       SET product_number = product_number - " . $value['sums'] . "
                                                                                                       WHERE product_id = " . $value['product_id'];
                            } elseif ($value['model_attr'] == 2) {
                                $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('products_area') . "
                                                                                                       SET product_number = product_number - " . $value['sums'] . "
                                                                                                       WHERE product_id = " . $value['product_id'];
                            } else {
                                $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('products') . "
                                                                                                       SET product_number = product_number - " . $value['sums'] . "
                                                                                                       WHERE product_id = " . $value['product_id'];
                            }
                        } else {
                            if ($value['model_inventory'] == 1) {
                                $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('warehouse_goods') . "
                                                                                                       SET region_number = region_number - " . $value['sums'] . "
                                                                                                       WHERE goods_id = " . $value['goods_id'] . " AND region_id = " . $value['warehouse_id'];
                            } elseif ($value['model_inventory'] == 2) {
                                $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('warehouse_area_goods') . "
                                                                                                       SET region_number = region_number - " . $value['sums'] . "
                                                                                                       WHERE goods_id = " . $value['goods_id'] . " AND region_id = " . $value['area_id'];
                            } else {
                                $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('goods') . "
                                                                                                       SET goods_number = goods_number - " . $value['sums'] . "
                                                                                                       WHERE goods_id = " . $value['goods_id'];
                            }
                        }

                        $GLOBALS['db']->query($minus_stock_sql, 'SILENT');

                        //库存日志
                        $logs_other = array(
                            'goods_id' => $value['goods_id'],
                            'order_id' => $value['order_id'],
                            'use_storage' => $_CFG['stock_dec_time'],
                            'admin_id' => $_SESSION['admin_id'],
                            'number' => "- " . $value['sums'],
                            'model_inventory' => $value['model_inventory'],
                            'model_attr' => $value['model_attr'],
                            'product_id' => $value['product_id'],
                            'warehouse_id' => $value['warehouse_id'],
                            'area_id' => $value['area_id'],
                            'add_time' => gmtime()
                        );

                        $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('goods_inventory_logs'), $logs_other, 'INSERT');
                    }
                }
            }

            /* 修改发货单信息 */
            $invoice_no = str_replace(',', '<br>', $delivery['invoice_no']);
            $invoice_no = trim($invoice_no, '<br>');
            $_delivery['invoice_no'] = $invoice_no;
            $_delivery['status'] = 0; // 0，为已发货
            $query = $db->autoExecute($ecs->table('delivery_order'), $_delivery, 'UPDATE', "delivery_id = $delivery_id", 'SILENT');
            if (!$query) {
                /* 操作失败 */
                $links[] = array('text' => $_LANG['delivery_sn'] . $_LANG['detail'], 'href' => 'order.php?act=delivery_info&delivery_id=' . $delivery_id);
                //sys_msg($_LANG['act_false'], 1, $links);
                continue;
            }

            /* 标记订单为已确认 “已发货” */
            /* 更新发货时间 */
            $order_finish = get_all_delivery_finish($order_id);
            $shipping_status = ($order_finish == 1) ? SS_SHIPPED : SS_SHIPPED_PART;
            $arr['shipping_status'] = $shipping_status;
            $arr['shipping_time'] = GMTIME_UTC; // 发货时间
            $arr['invoice_no'] = trim($invoice_no);
            update_order($order_id, $arr);

            /* by zxk jiesuan */
            //发货单记录
            deliver_order($order_id, $invoice_no, $order['shipping_id'], $order['shipping_name'], $order['shipping_fee'], GMTIME_UTC, $shipping_status);
            /* by zxk jiesuan */

            /* 发货单发货记录log */
            order_action($order['order_sn'], OS_CONFIRMED, $shipping_status, $order['pay_status'], $action_note, $_SESSION['admin_name'], 1);

            /* 如果当前订单已经全部发货 */
            if ($order_finish) {
                /* 如果订单用户不为空，计算积分，并发给用户；发红包 */
                if ($order['user_id'] > 0) {
                    /* 取得用户信息 */
                    $user = user_info($order['user_id']);

                    /* 计算并发放积分 */
                    $integral = integral_to_give($order);
                    /* 如果已配送子订单的赠送积分大于0   减去已配送子订单积分 */
                    if (!empty($child_order)) {
                        $integral['custom_points'] = $integral['custom_points'] - $child_order['custom_points'];
                        $integral['rank_points'] = $integral['rank_points'] - $child_order['rank_points'];
                    }
                    log_account_change($order['user_id'], 0, 0, intval($integral['rank_points']), intval($integral['custom_points']), sprintf($_LANG['order_gift_integral'], $order['order_sn']));

                    /* 发放红包 */
                    send_order_bonus($order_id);

                    /* 发放优惠券 bylu */
                    send_order_coupons($order_id);
                }

                /* 发送邮件 */
                $cfg = $_CFG['send_ship_email'];
                if ($cfg == '1') {
                    $order['invoice_no'] = $invoice_no;
                    $tpl = get_mail_template('deliver_notice');
                    $smarty->assign('order', $order);
                    $smarty->assign('send_time', local_date($_CFG['time_format']));
                    $smarty->assign('shop_name', $_CFG['shop_name']);
                    $smarty->assign('send_date', local_date($GLOBALS['_CFG']['time_format'], gmtime()));
                    $smarty->assign('sent_date', local_date($GLOBALS['_CFG']['time_format'], gmtime()));
                    //$smarty->assign('confirm_url', $ecs->url() . 'receive.php?id=' . $order['order_id'] . '&con=' . rawurlencode($order['consignee']));
                    $smarty->assign('confirm_url', $ecs->url() . 'user.php?act=order_detail&order_id=' . $order['order_id']); //by wu
                    $smarty->assign('send_msg_url', $ecs->url() . 'user.php?act=message_list&order_id=' . $order['order_id']);
                    $content = $smarty->fetch('str:' . $tpl['template_content']);
                    if (!send_mail($order['consignee'], $order['email'], $tpl['template_subject'], $content, $tpl['is_html'])) {
                        $msg = $_LANG['send_mail_fail'];
                    }
                }

                /* 如果需要，发短信 */
                if ($GLOBALS['_CFG']['sms_order_shipped'] == '1' && $order['mobile'] != '') {

                    //短信接口参数
                    if ($order['ru_id']) {
                        $shop_name = get_shop_name($order['ru_id'], 1);
                    } else {
                        $shop_name = "";
                    }

                    $user_info = get_admin_user_info($order['user_id']);

                    $smsParams = array(
                        'shop_name' => $shop_name,
                        'shopname' => $shop_name,
                        'user_name' => $user_info['user_name'],
                        'username' => $user_info['user_name'],
                        'consignee' => $order['consignee'],
                        'order_sn' => $order['order_sn'],
                        'ordersn' => $order['order_sn'],
                        'invoice_no' => $invoice_no,
                        'invoiceno' => $invoice_no,
                        'mobile_phone' => $order['mobile'],
                        'mobilephone' => $order['mobile']
                    );

                    if ($GLOBALS['_CFG']['sms_type'] == 0) {

                        huyi_sms($smsParams, 'sms_order_shipped');
                    } elseif ($GLOBALS['_CFG']['sms_type'] >= 1) {

                        $result = sms_ali($smsParams, 'sms_order_shipped'); //阿里大鱼短信变量传值，发送时机传值

                        if ($result) {
                            $resp = $GLOBALS['ecs']->ali_yu($result);
                        } else {
                            //sys_msg('阿里大鱼短信配置异常', 1);
                            continue;
                        }
                    }
                }

                /* 更新商品销量 批量发货 */
                get_goods_sale($order_id);
            }

            /* 清除缓存 */
            clear_cache_files();

            /* 操作成功 */
            $links[] = array('text' => $_LANG['09_delivery_order'], 'href' => 'order.php?act=delivery_list');
            $links[] = array('text' => $_LANG['delivery_sn'] . $_LANG['detail'], 'href' => 'order.php?act=delivery_info&delivery_id=' . $delivery_id);
            //sys_msg($_LANG['act_ok'], 0, $links);
            continue;
        }

        /* 返回 */
        sys_msg('批量发货成功', 0, array(array('href' => 'order.php?act=delivery_list', 'text' => $_LANG['return_list'])));
    }


    /*  @bylu 判断当前退款订单是否为白条支付订单(白条支付订单退款只能退到白条额度) start  */
    $sql="SELECT log_id FROM " .$ecs->table('baitiao_log'). " WHERE order_id = '$order_id'";
    $baitiao = $db->getOne($sql);

    if($baitiao){
        $smarty->assign('is_baitiao', $baitiao);
    }

    /*  @bylu  end  */


    /* 直接处理还是跳到详细页面 ecmoban模板堂 --zhuo ($require_note && $action_note == '')*/
    if ($require_note || isset($show_invoice_no) || isset($show_refund)||isset($op_shipping))
    {
        //处理批量发货时处理订单状态问题
        if (isset($op_shipping)) {
            $oid_array = explode(',', $order_id);
            foreach ($oid_array as $k => $v) {
                $sql = " SELECT order_status, shipping_status, pay_status FROM " . $ecs->table('order_info') . " WHERE order_sn ='$v' ";
                $row = $db->getRow($sql);
                if ($row['shipping_status'] != 0 || ($row['order_status'] != 1 && $row['order_status'] != 5) || $row['pay_status'] != 2) {
                    sys_msg('请检查订单状态', 1);
                    exit;
                }
            }
        }

        /* 模板赋值 */
        $smarty->assign('require_note', $require_note); // 是否要求填写备注
        $smarty->assign('action_note', $action_note);   // 备注
        $smarty->assign('show_cancel_note', isset($show_cancel_note)); // 是否显示取消原因
        $smarty->assign('show_invoice_no', isset($show_invoice_no)); // 是否显示发货单号
        $smarty->assign('show_refund', isset($show_refund)); // 是否显示退款
        $smarty->assign('show_refund1', isset($show_refund1)); // 是否显示退款 // by Leah
        $smarty->assign('anonymous', isset($anonymous) ? $anonymous : true); // 是否匿名
        $smarty->assign('order_id', $order_id); // 订单id
        $smarty->assign('oid_array', $oid_array); // 订单id数组
        $smarty->assign('rec_id', $rec_id); // 订单商品id    //by Leah
        $smarty->assign('ret_id', $ret_id); // 订单商品id   // by Leah
        $smarty->assign('batch', $batch);   // 是否批处理
        $smarty->assign('operation', $operation); // 操作
        $smarty->assign('show_shipping_sn', isset($op_shipping)); // 操作


        /* 显示模板 */
        $smarty->assign('ur_here', $_LANG['order_operate'] . $action);
        assign_query_info();
        $smarty->display('order_operate.dwt');
    }
    else
    {
        /* 直接处理 */
        if (!$batch) {
            // by　Leah S
            if ($_REQUEST['ret_id']) {
                ecs_header("Location: order.php?act=operate_post&order_id=" . $order_id .
                        "&operation=" . $operation . "&action_note=" . urlencode($action_note) . "&rec_id=" . $rec_id . "&ret_id=" . $ret_id . "\n");
                exit;
            } else {

                /* 一个订单 */
                ecs_header("Location: order.php?act=operate_post&order_id=" . $order_id .
                        "&operation=" . $operation . "&action_note=" . urlencode($action_note) . "\n");
                exit;
            }
            //by Leah E
        } else {
            /* 多个订单 */
            ecs_header("Location: order.php?act=batch_operate_post&order_id=" . $order_id .
                    "&operation=" . $operation . "&action_note=" . urlencode($action_note) . "\n");
            exit;
        }
    }
}

/*------------------------------------------------------ */
//-- 操作订单状态（处理批量提交）
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'batch_operate_post')
{
    /* 检查权限 */
    admin_priv('order_os_edit');

    /* 取得参数 */
    $order_id   = $_REQUEST['order_id'];        // 订单id（逗号格开的多个订单id）
    $operation  = $_REQUEST['operation'];       // 订单操作
    $action_note= $_REQUEST['action_note'];     // 操作备注

    $order_id_list = explode(',', $order_id);

    /* 初始化处理的订单sn */
    $sn_list = array();
    $sn_not_list = array();

    /* 确认 */
    if ('confirm' == $operation)
    {
        foreach($order_id_list as $id_order)
        {
            $sql = "SELECT * FROM " . $ecs->table('order_info') .
                " WHERE order_sn = '$id_order'" .
                " AND order_status = '" . OS_UNCONFIRMED . "'";
            $order = $db->getRow($sql);

            if($order)
            {
                 /* 检查能否操作 */
                $operable_list = operable_list($order);
                if (!isset($operable_list[$operation]))
                {
                    $sn_not_list[] = $id_order;
                    continue;
                }

                if($order['order_status'] == OS_RETURNED || $order['order_status'] == OS_RETURNED_PART){
                    continue;
                }

                $order_id = $order['order_id'];

                /* 标记订单为已确认 */
                update_order($order_id, array('order_status' => OS_CONFIRMED, 'confirm_time' => gmtime()));
                update_order_amount($order_id);

                /* 记录log */
                order_action($order['order_sn'], OS_CONFIRMED, SS_UNSHIPPED, PS_UNPAYED, $action_note, $_SESSION['admin_name']);

                /* 发送邮件 */
                if ($_CFG['send_confirm_email'] == '1')
                {
                    $tpl = get_mail_template('order_confirm');
                    $order['formated_add_time'] = local_date($GLOBALS['_CFG']['time_format'], $order['add_time']);
                    $smarty->assign('order', $order);
                    $smarty->assign('shop_name', $_CFG['shop_name']);
                    $smarty->assign('send_date', local_date($GLOBALS['_CFG']['time_format'], gmtime()));
                    $smarty->assign('sent_date', local_date($GLOBALS['_CFG']['time_format'], gmtime()));
                    $content = $smarty->fetch('str:' . $tpl['template_content']);
                    send_mail($order['consignee'], $order['email'], $tpl['template_subject'], $content, $tpl['is_html']);
                }

                $sn_list[] = $order['order_sn'];
            }
            else
            {
                $sn_not_list[] = $id_order;
            }
        }

        $sn_str = $_LANG['confirm_order'];
    }
    /* 无效 */
    elseif ('invalid' == $operation)
    {
        foreach($order_id_list as $id_order)
        {
            $sql = "SELECT * FROM " . $ecs->table('order_info') .
                " WHERE order_sn = $id_order" . order_query_sql('unpay_unship');

            $order = $db->getRow($sql);

            /*判断门店订单，获取门店id by kong */

            $store_order_id = get_store_id($order['order_id']);
            $store_id = ($store_order_id > 0) ? $store_order_id : 0;
            if($order)
            {
                 /* 检查能否操作 */
                $operable_list = operable_list($order);
                if (!isset($operable_list[$operation]))
                {
                    $sn_not_list[] = $id_order;
                    continue;
                }

                $order_id = $order['order_id'];

                /* 标记订单为“无效” */
                update_order($order_id, array('order_status' => OS_INVALID));

                /* 记录log */
                order_action($order['order_sn'], OS_INVALID, SS_UNSHIPPED, PS_UNPAYED, $action_note, $_SESSION['admin_name']);

                /* 如果使用库存，且下订单时减库存，则增加库存 */
                if ($_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_PLACE)
                {
                    change_order_goods_storage($order_id, false, SDT_PLACE, 2, $_SESSION['admin_id'],$store_id);
                }

                /* 发送邮件 */
                if ($_CFG['send_invalid_email'] == '1')
                {
                    $tpl = get_mail_template('order_invalid');
                    $smarty->assign('order', $order);
                    $smarty->assign('shop_name', $_CFG['shop_name']);
                    $smarty->assign('send_date', local_date($GLOBALS['_CFG']['time_format'], gmtime()));
                    $smarty->assign('sent_date', local_date($GLOBALS['_CFG']['time_format'], gmtime()));
                    $content = $smarty->fetch('str:' . $tpl['template_content']);
                    send_mail($order['consignee'], $order['email'], $tpl['template_subject'], $content, $tpl['is_html']);
                }

                /* 退还用户余额、积分、红包 */
                return_user_surplus_integral_bonus($order);

                $sn_list[] = $order['order_sn'];
            }
            else
            {
                $sn_not_list[] = $id_order;
            }
        }

        $sn_str = $_LANG['invalid_order'];
    }  elseif ('ships' == $operation) {
        /* 查询：检查权限 */

        foreach($order_id_list as $id_order) {
            $sql = "SELECT * FROM " . $ecs->table('order_info') .
                " WHERE order_sn = $id_order";

            $_order = $db->getRow($sql);

            /* 判断退换货订单申请是否通过 strat */
            $sql = "SELECT count(ret_id) FROM " . $ecs->table('order_return') . " WHERE order_id = '$_order[order_id]' AND return_status IN (-1, 0) AND refound_status = 0";
            //判断是否存在未处理的退换货申请
            $order_return_exist = $db->getOne($sql);

            if (intval($order_return_exist)) {
                $sn_not_list[] = $id_order;
                continue;
            }

            $order_id = $_order['order_id'];
            $delivery_info = get_delivery_info($order_id);

            //订单信息
            $order = order_info($order_id);

            /* 订单是否已全部分单检查 */
            if ($order['order_status'] == OS_SPLITED)
            {
                /* 操作失败 */
                $sn_not_list[] = $id_order;
                continue;
            }

            if(true){
                /* 查询：根据订单是否完成 检查权限 */
                if (order_finished($order))
                {
                    admin_priv('order_view_finished');
                }
                else
                {
                    admin_priv('order_view');
                }

                /* 查询：如果管理员属于某个办事处，检查该订单是否也属于这个办事处 */
                $sql = "SELECT agency_id FROM " . $ecs->table('admin_user') . " WHERE user_id = '$_SESSION[admin_id]'";
                $agency_id = $db->getOne($sql);
                if ($agency_id > 0)
                {
                    if ($order['agency_id'] != $agency_id)
                    {
                        $sn_not_list[] = $id_order;
                        continue;
                    }
                }

                /* 订单是否已全部分单检查 */
                if ($order['order_status'] == OS_SPLITED)
                {
                    /* 操作失败 */
                    $sn_not_list[] = $id_order;
                    continue;
                }

                /* 检查能否操作 */
                $operable_list = operable_list($order);
                if (!isset($operable_list['split']))
                {
                    /* 操作失败 */
                    $sn_not_list[] = $id_order;
                    continue;
                }

                /* 取得订单商品 */
                /* 查询：取得订单商品 */
                $_goods = get_order_goods(array('order_id' => $order['order_id'], 'order_sn' =>$order['order_sn']));
                $attr = $_goods['attr'];
                $goods_list = $_goods['goods_list'];

                /* 查询：商品已发货数量 此单可发货数量 */
                if ($goods_list)
                {
                    foreach ($goods_list as $key=>$goods_value) {
                        if (!$goods_value['goods_id']) {
                            $sn_not_list[] = $id_order;
                            continue;
                        }
                    }
                }


                $delivery['order_sn'] = $order['order_sn'];
                $delivery['add_time'] = $order['order_time'];
                $delivery['user_id'] = $order['user_id'];
                $delivery['how_oos'] = $order['how_oos'];
                $delivery['shipping_id'] = $order['shipping_id'];
                $delivery['shipping_fee'] = $order['shipping_fee'];
                $delivery['consignee'] = $order['consignee'];
                $delivery['address'] = $order['address'];
                $delivery['country'] = $order['country'];
                $delivery['province'] = $order['province'];

                $delivery['city'] = $order['city'];
                $delivery['district'] = $order['district'];
                $delivery['sign_building'] = $order['sign_building'];
                $delivery['email'] = $order['email'];
                $delivery['zipcode'] = $order['zipcode'];
                $delivery['tel'] = $order['tel'];
                $delivery['mobile'] = $order['mobile'];
                $delivery['best_time'] = $order['best_time'];
                $delivery['postscript'] = $order['postscript'];
                $delivery['how_oos'] = $order['how_oos'];

                $delivery['insure_fee'] = $order['insure_fee'];
                $delivery['shipping_fee'] = $order['shipping_fee'];
                $delivery['agency_id'] = $order['agency_id'];
                $delivery['shipping_name'] = $order['shipping_name'];

                //众筹发货 by wu start
                $sql = " SELECT is_zc_order FROM ".$GLOBALS['ecs']->table('order_info')." WHERE order_id = '$order_id' ";
                $is_zc_order = $GLOBALS['db']->getOne($sql);
                $is_zc_order = empty($is_zc_order)? 0:$is_zc_order;
                //众筹发货 by wu end

                /* 对上一步处理结果进行判断 兼容 上一步判断为假情况的处理 */
                if ($is_zc_order == 0 && (empty($goods_list)))
                {
                    /* 操作失败 */
                    $sn_not_list[] = $id_order;
                    continue;
                }


                /* 生成发货单 */
                /* 获取发货单号和流水号 */
                $delivery['delivery_sn'] = get_delivery_sn();
                $delivery_sn = $delivery['delivery_sn'];
                /* 获取当前操作员 */
                $delivery['action_user'] = $_SESSION['admin_name'];
                /* 获取发货单生成时间 */
                $delivery['update_time'] = gmtime();
                $delivery_time = $delivery['update_time'];
                $sql ="select add_time from ". $GLOBALS['ecs']->table('order_info') ." WHERE order_sn = '" . $delivery['order_sn'] . "'";
                $delivery['add_time'] =  $GLOBALS['db']->GetOne($sql);
                /* 获取发货单所属供应商 */
                $delivery['suppliers_id'] = $suppliers_id;
                /* 设置默认值 */
                $delivery['status'] = 2; // 正常
                $delivery['order_id'] = $order_id;
                /* 设置众筹标识 */
                $delivery['is_zc_order'] = $is_zc_order;

                /* 过滤字段项 */
                $filter_fileds = array(
                    'order_sn', 'add_time', 'user_id', 'how_oos', 'shipping_id', 'shipping_fee',
                    'consignee', 'address', 'country', 'province', 'city', 'district', 'sign_building',
                    'email', 'zipcode', 'tel', 'mobile', 'best_time', 'postscript', 'insure_fee',
                    'agency_id', 'delivery_sn', 'action_user', 'update_time',
                    'suppliers_id', 'status', 'order_id', 'shipping_name', 'is_zc_order'
                );
                $_delivery = array();
                foreach ($filter_fileds as $value)
                {
                    $_delivery[$value] = $delivery[$value];
                }

                /* 发货单入库 */
                $query = $db->autoExecute($ecs->table('delivery_order'), $_delivery, 'INSERT', '', 'SILENT');
                $delivery_id = $db->insert_id();
                if ($delivery_id) {
                    $delivery_goods = array();
                    //发货单商品入库
                    if (!empty($goods_list))
                    {
                        //分单操作
                        $split_action_note = "";

                        foreach ($goods_list as $value)
                        {
                            $send_number[$value['rec_id']] = $value['goods_number'];

                            // 商品（实货）（虚货）
                            if (empty($value['extension_code']) || $value['extension_code'] == 'group_buy' || $value['extension_code'] == 'sample' || $value['extension_code'] == 'wholesale' || $value['extension_code'] == 'virtual_card' || $value['extension_code'] == 'presale' || substr($value['extension_code'],0,7) == 'seckill')
                            {
                                $delivery_goods = array('delivery_id' => $delivery_id,
                                    'goods_id' => $value['goods_id'],
                                    'product_id' => $value['product_id'],
                                    'product_sn' => $value['product_sn'],
                                    'goods_id' => $value['goods_id'],
                                    'goods_name' => addslashes($value['goods_name']),
                                    'brand_name' => addslashes($value['brand_name']),
                                    'goods_sn' => $value['goods_sn'],
                                    'send_number' => $value['goods_number'],
                                    'parent_id' => 0,
                                    'is_real' => $value['is_real'],
                                    'goods_attr' => addslashes($value['goods_attr'])
                                );

                                /* 如果是货品 */
                                if (!empty($value['product_id']))
                                {
                                    $delivery_goods['product_id'] = $value['product_id'];
                                }

                                $query = $db->autoExecute($ecs->table('delivery_goods'), $delivery_goods, 'INSERT', '', 'SILENT');

                                //分单操作
                                $split_action_note .= sprintf($_LANG['split_action_note'], $value['goods_sn'], $send_number[$value['rec_id']]) . "<br/>";
                            }
                            // 商品（超值礼包）
                            elseif ($value['extension_code'] == 'package_buy')
                            {
                                foreach ($value['package_goods_list'] as $pg_key => $pg_value)
                                {
                                    $delivery_pg_goods = array('delivery_id' => $delivery_id,
                                        'goods_id' => $pg_value['goods_id'],
                                        'product_id' => $pg_value['product_id'],
                                        'product_sn' => $pg_value['product_sn'],
                                        'goods_name' => $pg_value['goods_name'],
                                        'brand_name' => '',
                                        'goods_sn' => $pg_value['goods_sn'],
                                        'send_number' => $value['goods_number'],
                                        'parent_id' => $value['goods_id'], // 礼包ID
                                        'extension_code' => $value['extension_code'], // 礼包
                                        'is_real' => $pg_value['is_real']
                                    );

                                    $send_number[$value['rec_id']] = $value['goods_number'];

                                    $query = $db->autoExecute($ecs->table('delivery_goods'), $delivery_pg_goods, 'INSERT', '', 'SILENT');
                                }

                                //分单操作
                                $split_action_note .= sprintf($_LANG['split_action_note'], $_LANG['14_package_list'], 1) . "<br/>";
                            }
                        }
                    }
                } else {
                    continue;
                }

                unset($filter_fileds, $delivery, $_delivery, $order_finish);
                /* 定单信息更新处理 */

                /* 定单信息更新处理 */
                if (true)
                {
                    /* 定单信息 */
                    $_sended = & $send_number;
                    foreach ($_goods['goods_list'] as $key => $value)
                    {
                        if ($value['extension_code'] != 'package_buy')
                        {
                            unset($_goods['goods_list'][$key]);
                        }
                    }
                    foreach ($goods_list as $key => $value)
                    {
                        if ($value['extension_code'] == 'package_buy')
                        {
                            unset($goods_list[$key]);
                        }
                    }
                    $_goods['goods_list'] = $goods_list + $_goods['goods_list'];
                    unset($goods_list);

                    /* 更新订单的虚拟卡 商品（虚货） */
                    $_virtual_goods = isset($virtual_goods['virtual_card']) ? $virtual_goods['virtual_card'] : '';
                    update_order_virtual_goods($order_id, $_sended, $_virtual_goods);

                    /* 更新订单的非虚拟商品信息 即：商品（实货）（货品）、商品（超值礼包）*/
                    update_order_goods($order_id, $_sended, $_goods['goods_list']);

                    /* 标记订单为已确认 “发货中” */
                    /* 更新发货时间 */
                    $order_finish = get_order_finish($order_id);
                    $shipping_status = SS_SHIPPED_ING;
                    if ($order['order_status'] != OS_CONFIRMED && $order['order_status'] != OS_SPLITED && $order['order_status'] != OS_SPLITING_PART)
                    {
                        $arr['order_status']    = OS_CONFIRMED;
                        $arr['confirm_time']    = GMTIME_UTC;
                    }
                    $arr['order_status'] = $order_finish ? OS_SPLITED : OS_SPLITING_PART; // 全部分单、部分分单
                    $arr['shipping_status']     = $shipping_status;
                    update_order($order_id, $arr);
                }

                /* 分单操作 */
                $action_note = $split_action_note . $action_note;

                /* 记录log */
                order_action($order['order_sn'], $arr['order_status'], $shipping_status, $order['pay_status'], $action_note, $_SESSION['admin_name']);
            }

        }

        $sn_str = $_LANG['split_order'];
        /* 清除缓存 */
        clear_cache_files();

    }
    elseif ('cancel' == $operation)
    {
        foreach($order_id_list as $id_order)
        {
            $sql = "SELECT * FROM " . $ecs->table('order_info') .
                " WHERE order_sn = $id_order" . order_query_sql('unpay_unship');

            $order = $db->getRow($sql);

            /*判断门店订单，获取门店id by kong */

            $store_order_id = get_store_id($order['order_id']);
            $store_id = ($store_order_id > 0) ? $store_order_id : 0;
            if($order)
            {
                 /* 检查能否操作 */
                $operable_list = operable_list($order);
                if (!isset($operable_list[$operation]))
                {
                    $sn_not_list[] = $id_order;
                    continue;
                }

                $order_id = $order['order_id'];

                /* 标记订单为“取消”，记录取消原因 */
                $cancel_note = trim($_REQUEST['cancel_note']);
                update_order($order_id, array('order_status' => OS_CANCELED, 'to_buyer' => $cancel_note));

                /* 记录log */
                order_action($order['order_sn'], OS_CANCELED, $order['shipping_status'], PS_UNPAYED, $action_note, $_SESSION['admin_name']);

                /* 如果使用库存，且下订单时减库存，则增加库存 */
                if ($_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_PLACE)
                {
                    change_order_goods_storage($order_id, false, SDT_PLACE, 3, $_SESSION['admin_id'],$store_id);
                }

                /* 发送邮件 */
                if ($_CFG['send_cancel_email'] == '1')
                {
                    $tpl = get_mail_template('order_cancel');
                    $smarty->assign('order', $order);
                    $smarty->assign('shop_name', $_CFG['shop_name']);
                    $smarty->assign('send_date', local_date($GLOBALS['_CFG']['time_format'], gmtime()));
                    $smarty->assign('sent_date', local_date($GLOBALS['_CFG']['time_format'], gmtime()));
                    $content = $smarty->fetch('str:' . $tpl['template_content']);
                    send_mail($order['consignee'], $order['email'], $tpl['template_subject'], $content, $tpl['is_html']);
                }

                /* 退还用户余额、积分、红包 */
                return_user_surplus_integral_bonus($order);

                $sn_list[] = $order['order_sn'];
             }
            else
            {
                $sn_not_list[] = $id_order;
            }
        }

        $sn_str = $_LANG['cancel_order'];
    }
    elseif ('remove' == $operation)
    {
        foreach ($order_id_list as $id_order)
        {
            /* 检查能否操作 */
            $order = order_info('', $id_order);
            $operable_list = operable_list($order);
            if (!isset($operable_list['remove']))
            {
                $sn_not_list[] = $id_order;
                continue;
            }

            $return_order = return_order_info(0, '', $order['order_id']);
            if($return_order){
                sys_msg(sprintf($_LANG['order_remove_failure'], $order['order_sn']), 0, array(array('href'=>'order.php?act=list&' . list_link_postfix(), 'text' => $_LANG['return_list'])));
                exit;
            }

            /* 删除订单 */
            $db->query("DELETE FROM ".$ecs->table('order_info'). " WHERE order_id = '$order[order_id]'");
            $db->query("DELETE FROM ".$ecs->table('order_goods'). " WHERE order_id = '$order[order_id]'");
            $db->query("DELETE FROM ".$ecs->table('order_action'). " WHERE order_id = '$order[order_id]'");
            $action_array = array('delivery', 'back');
            del_delivery($order['order_id'], $action_array);

            /* todo 记录日志 */
            admin_log($order['order_sn'], 'remove', 'order');

            $sn_list[] = $order['order_sn'];
        }

        $sn_str = $_LANG['remove_order'];
    }
	else{
        die('invalid params');
    }

    /* 取得备注信息 */
//    $action_note = $_REQUEST['action_note'];

    if(empty($sn_not_list))
    {
        $sn_list = empty($sn_list) ? '' : $_LANG['updated_order'] . join($sn_list, ',');
        $msg = $sn_list;
        $links[] = array('text' => $_LANG['return_list'], 'href' => 'order.php?act=list&' . list_link_postfix());
        sys_msg($msg, 0, $links);
    }
    else
    {
        $order_list_no_fail = array();
        $sql = "SELECT * FROM " . $ecs->table('order_info') .
                " WHERE order_sn " . db_create_in($sn_not_list);
        $res = $db->query($sql);
        while($row = $db->fetchRow($res))
        {
            $order_list_no_fail[$row['order_id']]['order_id'] = $row['order_id'];
            $order_list_no_fail[$row['order_id']]['order_sn'] = $row['order_sn'];
            $order_list_no_fail[$row['order_id']]['order_status'] = $row['order_status'];
            $order_list_no_fail[$row['order_id']]['shipping_status'] = $row['shipping_status'];
            $order_list_no_fail[$row['order_id']]['pay_status'] = $row['pay_status'];

            $order_list_fail = '';
            foreach(operable_list($row) as $key => $value)
            {
                if($key != $operation)
                {
                    $order_list_fail .= $_LANG['op_' . $key] . ',';
                }
            }
            $order_list_no_fail[$row['order_id']]['operable'] = $order_list_fail;
        }

        /* 模板赋值 */
        $smarty->assign('order_info', $sn_str);
        $smarty->assign('action_link', array('href' => 'order.php?act=list', 'text' => $_LANG['02_order_list']));
        $smarty->assign('order_list',   $order_list_no_fail);

        /* 显示模板 */
        assign_query_info();
        $smarty->display('order_operate_info.dwt');
    }
}

/*------------------------------------------------------ */
//-- 操作订单状态（处理提交）
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'operate_post')
{
    /* 检查权限 */
    admin_priv('order_os_edit');

    /* 取得参数 */
    $order_id   = intval(trim($_REQUEST['order_id']));        // 订单id
    $rec_id = empty($_REQUEST['rec_id']) ? 0 : $_REQUEST['rec_id'];     //by　Leah
    $ret_id = empty($_REQUEST['ret_id']) ? 0 : $_REQUEST['ret_id'];  //by Leah
    $return = '';   //by leah

    //by Leah S
    if ($ret_id) {
        $return = 1;
    }
    //by Leah E
    $operation = $_REQUEST['operation'];                 // 订单操作

    /* 查询订单信息 */
    $order = order_info($order_id);

     /*判断门店订单，获取门店id by kong */
    $store_order_id = get_store_id($order_id);
    $store_id = ($store_order_id > 0) ? $store_order_id : 0;

    /* 检查能否操作 */
    $operable_list = operable_list($order);
    if (!isset($operable_list[$operation]))
    {
        die('Hacking attempt');
    }

    /* 取得备注信息 */
    $action_note = $_REQUEST['action_note'];

    /* 初始化提示信息 */
    $msg = '';

    /* 确认 */
    if ('confirm' == $operation)
    {
        /* 标记订单为已确认 */
        update_order($order_id, array('order_status' => OS_CONFIRMED, 'confirm_time' => gmtime()));
        update_order_amount($order_id);

        /* 记录log */
        order_action($order['order_sn'], OS_CONFIRMED, SS_UNSHIPPED, PS_UNPAYED, $action_note, $_SESSION['admin_name']);

        /* 如果原来状态不是“未确认”，且使用库存，且下订单时减库存，则减少库存 */
        if ($order['order_status'] != OS_UNCONFIRMED && $_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_PLACE)
        {
            change_order_goods_storage($order_id, true, SDT_PLACE, 4, $_SESSION['admin_id'],$store_id);
        }

        /* 发送邮件 */
        $cfg = $_CFG['send_confirm_email'];
        if ($cfg == '1')
        {
            $tpl = get_mail_template('order_confirm');
            $smarty->assign('order', $order);
            $smarty->assign('shop_name', $_CFG['shop_name']);
            $smarty->assign('send_date', local_date($GLOBALS['_CFG']['time_format'], gmtime()));
            $smarty->assign('sent_date', local_date($GLOBALS['_CFG']['time_format'], gmtime()));
            $content = $smarty->fetch('str:' . $tpl['template_content']);
            if (!send_mail($order['consignee'], $order['email'], $tpl['template_subject'], $content, $tpl['is_html']))
            {
                $msg = $_LANG['send_mail_fail'];
            }
        }
    }
    /* 付款 */
    elseif ('pay' == $operation)
    {
        /* 检查权限 */
        admin_priv('order_ps_edit');

        /* 标记订单为已确认、已付款，更新付款时间和已支付金额，如果是货到付款，同时修改订单为“收货确认” */
        if ($order['order_status'] != OS_CONFIRMED)
        {
            $arr['order_status']    = OS_CONFIRMED;
            $arr['confirm_time']    = gmtime();
            if ($_CFG['sales_volume_time'] == SALES_PAY) {
                $arr['is_update_sale'] = 1;
            }
        }
        $arr['pay_time']    = gmtime();
        //预售定金处理
        if($order['extension_code'] == 'presale' && $order['pay_status'] == 0){
            $arr['pay_status']  = PS_PAYED_PART;
            $arr['money_paid']  = $order['money_paid'] + $order['order_amount'];
            $arr['order_amount']= $order['goods_amount'] + $order['shipping_fee'] + $order['insure_fee'] + $order['pay_fee'] + $order['tax'] + $order['pack_fee'] + $order['card_fee'] -
                                  $order['surplus'] - $order['money_paid'] - $order['integral_money'] - $order['bonus'] - $order['order_amount'] - $order['discount'] ;
        }else{
            $arr['pay_status']  = PS_PAYED;
            $arr['money_paid']  = $order['money_paid'] + $order['order_amount'];
            $arr['order_amount']= 0;
        }
        $payment = payment_info($order['pay_id']);
        if ($payment['is_cod'])
        {
            $arr['shipping_status'] = SS_RECEIVED;
            $order['shipping_status'] = SS_RECEIVED;
        }

        update_order($order_id, $arr);

        /* 众筹状态的更改 by wu */
        update_zc_project($order_id);

        //付款成功创建快照
        create_snapshot($order_id);

        /* 更新商品销量 ecmoban模板堂 --zhuo */
        get_goods_sale($order_id);

        //门店处理 付款成功发送短信
        $sql = 'SELECT store_id,pick_code,order_id FROM '.$GLOBALS['ecs']->table("store_order")." WHERE order_id = '$order_id' LIMIT 1";
        $stores_order = $GLOBALS['db']->getRow($sql);
        $user_mobile_phone = '';
        $sql = "SELECT mobile_phone,user_name FROM " . $GLOBALS['ecs']->table('users') . " WHERE user_id = '" . $order['user_id'] . "' LIMIT 1";
        $orderUsers = $GLOBALS['db']->getRow($sql);
        if ($stores_order['store_id'] > 0) {
            if ($order['mobile']) {
                $user_mobile_phone = $order['mobile'];
            } else {
                $user_mobile_phone = $orderUsers['mobile_phone'];
            }
        }
        if ($user_mobile_phone != '') {
            //门店短信处理
            $store_smsParams = '';
            $sql = "SELECT id, country, province, city, district, stores_address, stores_name, stores_tel FROM " . $GLOBALS['ecs']->table('offline_store') . " WHERE id = '" . $stores_order['store_id'] . "' LIMIT 1";
            $stores_info = $GLOBALS['db']->getRow($sql);
            $store_address = get_area_region_info($stores_info) . $stores_info['stores_address'];
            $user_name = !empty($orderUsers['user_name']) ? $orderUsers['user_name'] : '';
            //门店订单->短信接口参数
            $store_smsParams = array(
                'user_name' => $user_name,
                'username' => $user_name,
                'order_sn' => $order['order_sn'],
                'ordersn' => $order['order_sn'],
                'code' => $stores_order['pick_code'],
                'store_address' => $store_address,
                'storeaddress' => $store_address,
                'mobile_phone' => $user_mobile_phone,
                'mobilephone' => $user_mobile_phone
            );
            if ($GLOBALS['_CFG']['sms_type'] == 0) {
                if ($stores_order['store_id'] > 0 && !empty($store_smsParams)) {
                    huyi_sms($store_smsParams, 'store_order_code');
                }
            } elseif ($GLOBALS['_CFG']['sms_type'] >=1) {
                if ($stores_order['store_id'] > 0 && !empty($store_smsParams)) {
                    $store_result = sms_ali($store_smsParams, 'store_order_code'); //阿里大鱼短信变量传值，发送时机传值
                    $GLOBALS['ecs']->ali_yu($store_result);
                }
            }
        }

        $confirm_take_time = gmtime();
        if(($arr['order_status'] == OS_CONFIRMED || $arr['order_status'] == OS_SPLITED) && $arr['pay_status'] == PS_PAYED && $arr['shipping_status'] == SS_RECEIVED){

            /* 查询订单信息，检查状态 */
            $sql = "SELECT order_id, user_id, order_sn , order_status, shipping_status, pay_status, " .
                    "order_amount, goods_amount, tax, shipping_fee, insure_fee, pay_fee, pack_fee, card_fee, " .
                    "bonus, integral_money, coupons, discount, money_paid, surplus, confirm_take_time " .
                    "FROM " . $GLOBALS['ecs']->table('order_info') . " WHERE order_id = '$order_id'";

            $bill_order = $GLOBALS['db']->GetRow($sql);

            $seller_id = $GLOBALS['db']->getOne("SELECT ru_id FROM " .$GLOBALS['ecs']->table('order_goods'). " WHERE order_id = '$order_id'", true);
            $value_card = $GLOBALS['db']->getOne("SELECT use_val FROM " .$GLOBALS['ecs']->table('value_card_record'). " WHERE order_id = '$order_id'", true);

            $return_amount = get_order_return_amount($order_id);

            $other = array(
                'user_id'               => $bill_order['user_id'],
                'seller_id'             => $seller_id,
                'order_id'              => $bill_order['order_id'],
                'order_sn'              => $bill_order['order_sn'],
                'order_status'          => $bill_order['order_status'],
                'shipping_status'       => SS_RECEIVED,
                'pay_status'            => $bill_order['pay_status'],
                'order_amount'          => $bill_order['order_amount'],
                'return_amount'         => $return_amount,
                'goods_amount'          => $bill_order['goods_amount'],
                'tax'                   => $bill_order['tax'],
                'shipping_fee'          => $bill_order['shipping_fee'],
                'insure_fee'            => $bill_order['insure_fee'],
                'pay_fee'               => $bill_order['pay_fee'],
                'pack_fee'              => $bill_order['pack_fee'],
                'card_fee'              => $bill_order['card_fee'],
                'bonus'                 => $bill_order['bonus'],
                'integral_money'        => $bill_order['integral_money'],
                'coupons'               => $bill_order['coupons'],
                'discount'               => $bill_order['discount'],
                'value_card'            => $value_card,
                'money_paid'            => $bill_order['money_paid'],
                'surplus'               => $bill_order['surplus'],
                'confirm_take_time'     => $confirm_take_time
            );

            if($seller_id){
                $insert_id = get_order_bill_log($other);

                /* by zxk jisuan*/
                //确认收货 商家结算到可用金额
                order_confirm_change($bill_order['order_id'], $insert_id);
                /* by zxk jisuan*/
            }
        }

        /* 记录log */
        if($order['extension_code'] == 'presale' && $order['pay_status'] == 0){
            order_action($order['order_sn'], OS_CONFIRMED, $order['shipping_status'], PS_PAYED_PART, $action_note, $_SESSION['admin_name']);
            /* 更新 pay_log */
            update_pay_log($order_id);
        }else{
            order_action($order['order_sn'], OS_CONFIRMED, $order['shipping_status'], PS_PAYED, $action_note, $_SESSION['admin_name'], 0, $confirm_take_time);
        }
    }
    /* 设为未付款 */
    elseif ('unpay' == $operation)
    {
        /* 检查权限 */
        admin_priv('order_ps_edit');

        $return_amount = !empty($_REQUEST['refound_amount']) ? floatval($_REQUEST['refound_amount']) : 0; //退款金额
        $is_shipping = isset($_REQUEST['is_shipping']) && !empty($_REQUEST['is_shipping']) ? intval($_REQUEST['is_shipping']) : 0; //是否退运费
        $shippingFee = !empty($is_shipping) ? floatval($_REQUEST['shipping']) : 0; //退款运费金额

        $refund_amount = $return_amount + $shippingFee;

        if($shippingFee > $order['shipping_fee']){
            $shippingFee = $order['shipping_fee'];
        }

        $shipping_fee = $order['shipping_fee'] - $shippingFee;

        /* 退款金额 start */
        $order_amount = $order['money_paid'] + $order['surplus'];
        if($return_amount > $order_amount){
            $return_amount = $order_amount;
        }
        /* 退款金额 end */

        $order_amount = $order['money_paid'] + $order['surplus'] - $return_amount;

        /* 标记订单为未付款，更新付款时间和已付款金额 */
        $arr = array(
            'pay_status'    => PS_UNPAYED,
            'pay_time'      => 0,
            'money_paid'    => $order_amount,
            'surplus'       => 0,
            'shipping_fee'  => $shipping_fee,
            'order_amount'  => $return_amount
        );

        update_order($order_id, $arr);

        /* todo 处理退款 */
        $refund_type = isset($_REQUEST['refund']) && !empty($_REQUEST['refund']) ? trim($_REQUEST['refund']) : '';
        $refund_note = isset($_REQUEST['refund_note']) && !empty($_REQUEST['refund_note']) ? trim($_REQUEST['refund_note']) : '';

        if($refund_note){
            $refund_note = "【" .$_LANG['setorder_nopay']. "】【" .$order['order_sn']. "】" . $refund_note;
        }

        order_refund($order, $refund_type, $refund_note, $refund_amount);

        /* 记录log */
        order_action($order['order_sn'], OS_CONFIRMED, SS_UNSHIPPED, PS_UNPAYED, $action_note, $_SESSION['admin_name']);
    }
    /* 配货 */
    elseif ('prepare' == $operation)
    {
        /* 标记订单为已确认，配货中 */
        if ($order['order_status'] != OS_CONFIRMED)
        {
            $arr['order_status']    = OS_CONFIRMED;
            $arr['confirm_time']    = gmtime();
        }
        $arr['shipping_status']     = SS_PREPARING;
        update_order($order_id, $arr);

        /* 记录log */
        order_action($order['order_sn'], OS_CONFIRMED, SS_PREPARING, $order['pay_status'], $action_note, $_SESSION['admin_name']);

        /* 清除缓存 */
        clear_cache_files();
    }
    /* 分单确认 */
    elseif ('split' == $operation)
    {

        /* 检查权限 */
        admin_priv('order_ss_edit');

        /* 定义当前时间 */
        define('GMTIME_UTC', gmtime()); // 获取 UTC 时间戳

        $delivery_info = get_delivery_info($order_id);
        if(true){
            /* 获取表单提交数据 */
            $_REQUEST['send_number'] = isset($_REQUEST['send_number']) ? $_REQUEST['send_number'] : array(); //众筹发货 by wu
            $suppliers_id = isset($_REQUEST['suppliers_id']) ? intval(trim($_REQUEST['suppliers_id'])) : '0';
            array_walk($_REQUEST['delivery'], 'trim_array_walk');
            $delivery = $_REQUEST['delivery'];
            array_walk($_REQUEST['send_number'], 'trim_array_walk');
            array_walk($_REQUEST['send_number'], 'intval_array_walk');
            $send_number = $_REQUEST['send_number'];
            $action_note = isset($_REQUEST['action_note']) ? trim($_REQUEST['action_note']) : '';
            $delivery['user_id'] = intval($delivery['user_id']);
            $delivery['country'] = intval($delivery['country']);
            $delivery['province'] = intval($delivery['province']);
            $delivery['city'] = intval($delivery['city']);
            $delivery['district'] = intval($delivery['district']);
            $delivery['agency_id'] = intval($delivery['agency_id']);
            $delivery['insure_fee'] = floatval($delivery['insure_fee']);
            $delivery['shipping_fee'] = floatval($delivery['shipping_fee']);

            /* 订单是否已全部分单检查 */
            if ($order['order_status'] == OS_SPLITED)
            {
                /* 操作失败 */
                $links[] = array('text' => $_LANG['order_info'], 'href' => 'order.php?act=info&order_id=' . $order_id);
                sys_msg(sprintf($_LANG['order_splited_sms'], $order['order_sn'],
                        $_LANG['os'][OS_SPLITED], $_LANG['ss'][SS_SHIPPED_ING], $GLOBALS['_CFG']['shop_name']), 1, $links);
            }

            /* 取得订单商品 */
            $_goods = get_order_goods(array('order_id' => $order_id, 'order_sn' => $delivery['order_sn']));
            $goods_list = $_goods['goods_list'];

            /* 检查此单发货数量填写是否正确 合并计算相同商品和货品 */
            if (!empty($send_number) && !empty($goods_list))
            {
                $goods_no_package = array();
                foreach ($goods_list as $key => $value)
                {
                    /* 去除 此单发货数量 等于 0 的商品 */
                    if (!isset($value['package_goods_list']) || !is_array($value['package_goods_list']))
                    {
                        // 如果是货品则键值为商品ID与货品ID的组合
                        $_key = empty($value['product_id']) ? $value['goods_id'] : ($value['goods_id'] . '_' . $value['product_id']);

                        // 统计此单商品总发货数 合并计算相同ID商品或货品的发货数
                        if (empty($goods_no_package[$_key]))
                        {
                            $goods_no_package[$_key] = $send_number[$value['rec_id']];
                        }
                        else
                        {
                            $goods_no_package[$_key] += $send_number[$value['rec_id']];
                        }

                        //去除
                        if ($send_number[$value['rec_id']] <= 0)
                        {
                            unset($send_number[$value['rec_id']], $goods_list[$key]);
                            continue;
                        }
                    }
                    else
                    {
                        /* 组合超值礼包信息 */
                        $goods_list[$key]['package_goods_list'] = package_goods($value['package_goods_list'], $value['goods_number'], $value['order_id'], $value['extension_code'], $value['goods_id']);

                        /* 超值礼包 */
                        foreach ($value['package_goods_list'] as $pg_key => $pg_value)
                        {
                            // 如果是货品则键值为商品ID与货品ID的组合
                            $_key = empty($pg_value['product_id']) ? $pg_value['goods_id'] : ($pg_value['goods_id'] . '_' . $pg_value['product_id']);

                            //统计此单商品总发货数 合并计算相同ID产品的发货数
                            if (empty($goods_no_package[$_key]))
                            {
                                $goods_no_package[$_key] = $send_number[$value['rec_id']][$pg_value['g_p']];
                            }
                            //否则已经存在此键值
                            else
                            {
                                $goods_no_package[$_key] += $send_number[$value['rec_id']][$pg_value['g_p']];
                            }

                            //去除
                            if ($send_number[$value['rec_id']][$pg_value['g_p']] <= 0)
                            {
                                unset($send_number[$value['rec_id']][$pg_value['g_p']], $goods_list[$key]['package_goods_list'][$pg_key]);
                            }
                        }

                        if (count($goods_list[$key]['package_goods_list']) <= 0)
                        {
                            unset($send_number[$value['rec_id']], $goods_list[$key]);
                            continue;
                        }
                    }

                    /* 发货数量与总量不符 */
                    if (!isset($value['package_goods_list']) || !is_array($value['package_goods_list']))
                    {
                        $sended = order_delivery_num($order_id, $value['goods_id'], $value['product_id']);
                        if (($value['goods_number'] - $sended - $send_number[$value['rec_id']]) < 0)
                        {
                            /* 操作失败 */
                            $links[] = array('text' => $_LANG['order_info'], 'href' => 'order.php?act=info&order_id=' . $order_id);
                            sys_msg($_LANG['act_ship_num'], 1, $links);
                        }
                    }
                    else
                    {
                        /* 超值礼包 */
                        foreach ($goods_list[$key]['package_goods_list'] as $pg_key => $pg_value)
                        {
                            if (($pg_value['order_send_number'] - $pg_value['sended'] - $send_number[$value['rec_id']][$pg_value['g_p']]) < 0)
                            {
                                /* 操作失败 */
                                $links[] = array('text' => $_LANG['order_info'], 'href' => 'order.php?act=info&order_id=' . $order_id);
                                sys_msg($_LANG['act_ship_num'], 1, $links);
                            }
                        }
                    }
                }
            }

                    //众筹发货 by wu start
                    $sql = " SELECT is_zc_order FROM ".$GLOBALS['ecs']->table('order_info')." WHERE order_id = '$order_id' ";
                    $is_zc_order = $GLOBALS['db']->getOne($sql);
                    $is_zc_order = empty($is_zc_order)? 0:$is_zc_order;
                    //众筹发货 by wu end

            /* 对上一步处理结果进行判断 兼容 上一步判断为假情况的处理 */
            if ($is_zc_order == 0 && (empty($send_number) || empty($goods_list)))
            {
                /* 操作失败 */
                $links[] = array('text' => $_LANG['order_info'], 'href' => 'order.php?act=info&order_id=' . $order_id);
                sys_msg($_LANG['act_false'], 1, $links);
            }

            /* 检查此单发货商品库存缺货情况 */
            /* $goods_list已经过处理 超值礼包中商品库存已取得 */
            $virtual_goods = array();
            $package_virtual_goods = array();

            foreach ($goods_list as $key => $value)
            {
                // 商品（超值礼包）
                if ($value['extension_code'] == 'package_buy')
                {
                    foreach ($value['package_goods_list'] as $pg_key => $pg_value)
                    {
                        if ($pg_value['goods_number'] < $goods_no_package[$pg_value['g_p']] && (($_CFG['use_storage'] == '1'  && $_CFG['stock_dec_time'] == SDT_SHIP) || ($_CFG['use_storage'] == '0' && $pg_value['is_real'] == 0)))
                        {
                            /* 操作失败 */
                            $links[] = array('text' => $_LANG['order_info'], 'href' => 'order.php?act=info&order_id=' . $order_id);
                            sys_msg(sprintf($_LANG['act_good_vacancy'], $pg_value['goods_name']), 1, $links);
                        }

                        /* 商品（超值礼包） 虚拟商品列表 package_virtual_goods*/
                        if ($pg_value['is_real'] == 0)
                        {
                            $package_virtual_goods[] = array(
                                           'goods_id' => $pg_value['goods_id'],
                                           'goods_name' => $pg_value['goods_name'],
                                           'num' => $send_number[$value['rec_id']][$pg_value['g_p']]
                                           );
                        }
                    }
                }
                // 商品（虚货）
                elseif ($value['extension_code'] == 'virtual_card' || $value['is_real'] == 0)
                {
                    $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('virtual_card') . " WHERE goods_id = '" . $value['goods_id'] . "' AND is_saled = 0 ";
                    $num = $GLOBALS['db']->GetOne($sql);
                    if (($num < $goods_no_package[$value['goods_id']]) && !($_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_PLACE))
                    {
                        /* 操作失败 */
                        $links[] = array('text' => $_LANG['order_info'], 'href' => 'order.php?act=info&order_id=' . $order_id);
                        sys_msg(sprintf($GLOBALS['_LANG']['virtual_card_oos'] . '【' . $value['goods_name'] . '】'), 1, $links);
                    }

                    /* 虚拟商品列表 virtual_card*/
                    if ($value['extension_code'] == 'virtual_card')
                    {
                        $virtual_goods[$value['extension_code']][] = array('goods_id' => $value['goods_id'], 'goods_name' => $value['goods_name'], 'num' => $send_number[$value['rec_id']]);
                    }
                }
                // 商品（实货）、（货品）
                else
                {
                    //如果是货品则键值为商品ID与货品ID的组合
                    $_key = empty($value['product_id']) ? $value['goods_id'] : ($value['goods_id'] . '_' . $value['product_id']);
                    $num = $value['storage']; //ecmoban模板堂 --zhuo

                    if (($num < $goods_no_package[$_key]) && $_CFG['use_storage'] == '1'  && $_CFG['stock_dec_time'] == SDT_SHIP)
                    {
                        /* 操作失败 */
                        $links[] = array('text' => $_LANG['order_info'], 'href' => 'order.php?act=info&order_id=' . $order_id);
                        sys_msg(sprintf($_LANG['act_good_vacancy'], $value['goods_name']), 1, $links);
                    }
                }
            }

            /* 生成发货单 */
            /* 获取发货单号和流水号 */
            $delivery['delivery_sn'] = get_delivery_sn();
            $delivery_sn = $delivery['delivery_sn'];
            /* 获取当前操作员 */
            $delivery['action_user'] = $_SESSION['admin_name'];
            /* 获取发货单生成时间 */
            $delivery['update_time'] = GMTIME_UTC;
            $delivery_time = $delivery['update_time'];
            $sql ="select add_time from ". $GLOBALS['ecs']->table('order_info') ." WHERE order_sn = '" . $delivery['order_sn'] . "'";
            $delivery['add_time'] =  $GLOBALS['db']->GetOne($sql);
            /* 获取发货单所属供应商 */
            $delivery['suppliers_id'] = $suppliers_id;
            /* 设置默认值 */
            $delivery['status'] = 2; // 正常
            $delivery['order_id'] = $order_id;
                    /* 设置众筹标识 */
                    $delivery['is_zc_order'] = $is_zc_order;
            /* 过滤字段项 */
            $filter_fileds = array(
                                   'order_sn', 'add_time', 'user_id', 'how_oos', 'shipping_id', 'shipping_fee',
                                   'consignee', 'address', 'country', 'province', 'city', 'district', 'sign_building',
                                   'email', 'zipcode', 'tel', 'mobile', 'best_time', 'postscript', 'insure_fee',
                                   'agency_id', 'delivery_sn', 'action_user', 'update_time',
                                   'suppliers_id', 'status', 'order_id', 'shipping_name', 'is_zc_order'
                                   );
            $_delivery = array();
            foreach ($filter_fileds as $value)
            {
                $_delivery[$value] = $delivery[$value];
            }

            /* 发货单入库 */
            $query = $db->autoExecute($ecs->table('delivery_order'), $_delivery, 'INSERT', '', 'SILENT');
            $delivery_id = $db->insert_id();
            if ($delivery_id)
            {
                $delivery_goods = array();

                //发货单商品入库
                if (!empty($goods_list))
                {
                                    //分单操作
                                    $split_action_note = "";

                    foreach ($goods_list as $value)
                    {
                        // 商品（实货）（虚货）
                        if (empty($value['extension_code']) || $value['extension_code'] == 'virtual_card' || $value['extension_code'] == 'presale' || substr($value['extension_code'],0,7) == 'seckill')
                        {
                            $delivery_goods = array('delivery_id' => $delivery_id,
                                                    'goods_id' => $value['goods_id'],
                                                    'product_id' => $value['product_id'],
                                                    'product_sn' => $value['product_sn'],
                                                    'goods_id' => $value['goods_id'],
                                                    'goods_name' => addslashes($value['goods_name']),
                                                    'brand_name' => addslashes($value['brand_name']),
                                                    'goods_sn' => $value['goods_sn'],
                                                    'send_number' => $send_number[$value['rec_id']],
                                                    'parent_id' => 0,
                                                    'is_real' => $value['is_real'],
                                                    'goods_attr' => addslashes($value['goods_attr'])
                                                    );

                            /* 如果是货品 */
                            if (!empty($value['product_id']))
                            {
                                $delivery_goods['product_id'] = $value['product_id'];
                            }

                            $query = $db->autoExecute($ecs->table('delivery_goods'), $delivery_goods, 'INSERT', '', 'SILENT');

                                                    //分单操作
                                                    $split_action_note .= sprintf($_LANG['split_action_note'], $value['goods_sn'], $send_number[$value['rec_id']]) . "<br/>";
                        }
                        // 商品（超值礼包）
                        elseif ($value['extension_code'] == 'package_buy')
                        {
                            foreach ($value['package_goods_list'] as $pg_key => $pg_value)
                            {
                                $delivery_pg_goods = array('delivery_id' => $delivery_id,
                                                        'goods_id' => $pg_value['goods_id'],
                                                        'product_id' => $pg_value['product_id'],
                                                        'product_sn' => $pg_value['product_sn'],
                                                        'goods_name' => $pg_value['goods_name'],
                                                        'brand_name' => '',
                                                        'goods_sn' => $pg_value['goods_sn'],
                                                        'send_number' => $send_number[$value['rec_id']][$pg_value['g_p']],
                                                        'parent_id' => $value['goods_id'], // 礼包ID
                                                        'extension_code' => $value['extension_code'], // 礼包
                                                        'is_real' => $pg_value['is_real']
                                                        );
                                $query = $db->autoExecute($ecs->table('delivery_goods'), $delivery_pg_goods, 'INSERT', '', 'SILENT');
                            }

                                                    //分单操作
                                                    $split_action_note .= sprintf($_LANG['split_action_note'], $_LANG['14_package_list'], 1) . "<br/>";
                        }
                    }
                }
            }
            else
            {
                /* 操作失败 */
                $links[] = array('text' => $_LANG['order_info'], 'href' => 'order.php?act=info&order_id=' . $order_id);
                sys_msg($_LANG['act_false'], 1, $links);
            }
            unset($filter_fileds, $delivery, $_delivery, $order_finish);

            /* 定单信息更新处理 */
            if (true)
            {
                /* 定单信息 */
                $_sended = & $send_number;
                foreach ($_goods['goods_list'] as $key => $value)
                {
                    if ($value['extension_code'] != 'package_buy')
                    {
                        unset($_goods['goods_list'][$key]);
                    }
                }
                foreach ($goods_list as $key => $value)
                {
                    if ($value['extension_code'] == 'package_buy')
                    {
                        unset($goods_list[$key]);
                    }
                }
                $_goods['goods_list'] = $goods_list + $_goods['goods_list'];
                unset($goods_list);

                /* 更新订单的虚拟卡 商品（虚货） */
                $_virtual_goods = isset($virtual_goods['virtual_card']) ? $virtual_goods['virtual_card'] : '';
                update_order_virtual_goods($order_id, $_sended, $_virtual_goods);

                /* 更新订单的非虚拟商品信息 即：商品（实货）（货品）、商品（超值礼包）*/
                update_order_goods($order_id, $_sended, $_goods['goods_list']);

                /* 标记订单为已确认 “发货中” */
                /* 更新发货时间 */
                $order_finish = get_order_finish($order_id);
                $shipping_status = SS_SHIPPED_ING;
                if ($order['order_status'] != OS_CONFIRMED && $order['order_status'] != OS_SPLITED && $order['order_status'] != OS_SPLITING_PART)
                {
                    $arr['order_status']    = OS_CONFIRMED;
                    $arr['confirm_time']    = GMTIME_UTC;
                }
                $arr['order_status'] = $order_finish ? OS_SPLITED : OS_SPLITING_PART; // 全部分单、部分分单
                $arr['shipping_status']     = $shipping_status;
                update_order($order_id, $arr);
            }

                    /* 分单操作 */
                    $action_note = $split_action_note . $action_note;

            /* 记录log */
            order_action($order['order_sn'], $arr['order_status'], $shipping_status, $order['pay_status'], $action_note, $_SESSION['admin_name']);

            /* 清除缓存 */
            clear_cache_files();
        }
    }
    /* 设为未发货 */
    elseif ('unship' == $operation)
    {
        /* 检查权限 */
        admin_priv('order_ss_edit');

        /* 标记订单为“未发货”，更新发货时间, 订单状态为“确认” */
        update_order($order_id, array('shipping_status' => SS_UNSHIPPED, 'shipping_time' => 0, 'invoice_no' => '', 'order_status' => OS_CONFIRMED));

        /* 记录log */
        order_action($order['order_sn'], $order['order_status'], SS_UNSHIPPED, $order['pay_status'], $action_note, $_SESSION['admin_name']);

        /* 如果订单用户不为空，计算积分，并退回 */
        if ($order['user_id'] > 0)
        {
            /* 取得用户信息 */
            $user = user_info($order['user_id']);

            /* 计算并退回积分 */
            $integral = integral_to_give($order);
            log_account_change($order['user_id'], 0, 0, (-1) * intval($integral['rank_points']), (-1) * intval($integral['custom_points']), sprintf($_LANG['return_order_gift_integral'], $order['order_sn']));

            /* todo 计算并退回红包 */
            return_order_bonus($order_id);
        }

        /* 如果使用库存，则增加库存 */
        if ($_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_SHIP)
        {
            change_order_goods_storage($order['order_id'], false, SDT_SHIP, 5, $_SESSION['admin_id'],$store_id);
        }

        /* 删除发货单 */
        del_order_delivery($order_id);

        /* 将订单的商品发货数量更新为 0 */
        $sql = "UPDATE " . $GLOBALS['ecs']->table('order_goods') . "
                SET send_number = 0
                WHERE order_id = '$order_id'";
        $GLOBALS['db']->query($sql, 'SILENT');

        /* 清除缓存 */
        clear_cache_files();
    }
    /* 收货确认 */
    elseif ('receive' == $operation)
    {

        $confirm_take_time = gmtime();

        /* 标记订单为“收货确认”，如果是货到付款，同时修改订单为已付款 */
        $arr = array('shipping_status' => SS_RECEIVED, 'confirm_take_time' => $confirm_take_time);
        $payment = payment_info($order['pay_id']);
        if ($payment['is_cod'])
        {
            $arr['pay_status'] = PS_PAYED;
            $order['pay_status'] = PS_PAYED;
        }
        update_order($order_id, $arr);

        /* 更新商品销量 ecmoban模板堂 --zhuo */
        // $sql = 'SELECT goods_id,goods_number FROM ' . $GLOBALS['ecs']->table('order_goods') . ' WHERE order_id =' . $order_id;
        // $order_res = $GLOBALS['db']->getAll($sql);
        // foreach ($order_res as $idx => $val) {
            // $sql = 'SELECT SUM(og.goods_number) as goods_number ' .
                    // 'FROM ' . $GLOBALS['ecs']->table('goods') . ' AS g, ' .
                    // $GLOBALS['ecs']->table('order_info') . ' AS o, ' .
                    // $GLOBALS['ecs']->table('order_goods') . ' AS og ' .
                    // "WHERE g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 AND og.order_id = o.order_id AND og.goods_id = g.goods_id " .
                    // "AND (o.order_status = '" . OS_CONFIRMED . "' OR o.order_status = '" . OS_SPLITED . "') " .
                    // "AND (o.pay_status = '" . PS_PAYED . "') " .
                    // "AND (o.shipping_status = '" . SS_RECEIVED . "') AND g.goods_id=" . $val['goods_id'];

            // $sales_volume = $GLOBALS['db']->getOne($sql);
            // $sql = "update " . $ecs->table('goods') . " set sales_volume='$sales_volume' WHERE goods_id =" . $val['goods_id'];

            // $db->query($sql);
        // }

        /* 记录log */
        order_action($order['order_sn'], $order['order_status'], SS_RECEIVED, $order['pay_status'], $action_note, $_SESSION['admin_name'], 0, $confirm_take_time);

        $bill = array(
            'order_id' => $order['order_id']
        );
        $bill_order = get_bill_order($bill);

        if(!$bill_order){

            $seller_id = $GLOBALS['db']->getOne("SELECT ru_id FROM " .$GLOBALS['ecs']->table('order_goods'). " WHERE order_id = '" .$order['order_id']. "'", true);
            $value_card = $GLOBALS['db']->getOne("SELECT use_val FROM " .$GLOBALS['ecs']->table('value_card_record'). " WHERE order_id = '" .$order['order_id']. "'", true);

            $return_amount = get_order_return_amount($order['order_id']);

            $other = array(
                'user_id'               => $order['user_id'],
                'seller_id'             => $seller_id,
                'order_id'              => $order['order_id'],
                'order_sn'              => $order['order_sn'],
                'order_status'          => $order['order_status'],
                'shipping_status'       => SS_RECEIVED,
                'pay_status'            => $order['pay_status'],
                'order_amount'          => $order['order_amount'],
                'return_amount'         => $return_amount,
                'goods_amount'          => $order['goods_amount'],
                'tax'                   => $order['tax'],
                'shipping_fee'          => $order['shipping_fee'],
                'insure_fee'            => $order['insure_fee'],
                'pay_fee'               => $order['pay_fee'],
                'pack_fee'              => $order['pack_fee'],
                'card_fee'              => $order['card_fee'],
                'bonus'                 => $order['bonus'],
                'integral_money'        => $order['integral_money'],
                'coupons'               => $order['coupons'],
                'discount'               => $order['discount'],
                'value_card'            => $value_card,
                'money_paid'            => $order['money_paid'],
                'surplus'               => $order['surplus'],
                'confirm_take_time'     => $confirm_take_time
            );

            if($seller_id){
                $insert_id = get_order_bill_log($other);

                /* by zxk jisuan*/
                //确认收货 商家结算到可用金额
                order_confirm_change($order['order_id'], $insert_id);
                /* by zxk jisuan*/
            }
        }
    }

    /*
     * 收到退换货商品
     * by　ecmoban模板堂 --zhuo
     */
    elseif ('agree_apply' == $operation) {

        $arr = array('agree_apply' => 1); //收到用户退回商品
        $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('order_return'), $arr, 'UPDATE', "rec_id = '$rec_id'");

        /* 记录log TODO_LOG */
        return_action($ret_id, RF_AGREE_APPLY, '', $action_note);
    }

    /*
     * 收到退换货商品
     * by　Leah
     */
    elseif ('receive_goods' == $operation) {

        $arr = array('return_status' => 1); //收到用户退回商品


        $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('order_return'), $arr, 'UPDATE', "rec_id = '$rec_id'");
        $arr['pay_status'] = PS_PAYED;
        $order['pay_status'] = PS_PAYED;

        /* 记录log TODO_LOG */
        return_action($ret_id, RF_RECEIVE, '', $action_note);
    }
    /**
     * 换出商品寄出 ---- 分单
     * by Leah
     */ elseif ('swapped_out_single' == $operation) {

        $arr = array('return_status' => 2); //换出商品寄出

        $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('order_return'), $arr, 'UPDATE', "rec_id = '$rec_id'");
        return_action($ret_id, RF_SWAPPED_OUT_SINGLE, '', $action_note);
    }
    /**
     * 换出商品寄出
     * by leah
     */ elseif ('swapped_out' == $operation) {

        $arr = array('return_status' => 3); //换出商品寄出

        $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('order_return'), $arr, 'UPDATE', "rec_id = '$rec_id'");
        return_action($ret_id, RF_SWAPPED_OUT, '', $action_note);
    }
    /**
     * 拒绝申请
     * by leah
     */ elseif ('refuse_apply' == $operation) {

        $arr = array('return_status' => 6);

        $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('order_return'), $arr, 'UPDATE', "rec_id = '$rec_id'");
        return_action($ret_id, REFUSE_APPLY, '', $action_note);
    }
    /**
     * 完成退换货
     * by Leah
     */ elseif ('complete' == $operation) {

        $arr = array('return_status' => 4); //完成退换货

        $sql = "SELECT return_type FROM " .$ecs->table('order_return'). " WHERE rec_id = '$rec_id'";
        $return_type = $db->getOne($sql);

        if($return_type == 0){
            $return_note = FF_MAINTENANCE;
        }else if($return_type == 1){
            $return_note = FF_REFOUND;
        }else if($return_type == 2){
            $return_note = FF_EXCHANGE;
        }

        $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('order_return'), $arr, 'UPDATE', "rec_id = '$rec_id'");
        return_action($ret_id, RF_COMPLETE, $return_note, $action_note);
    }

    /* 取消 */
    elseif ('cancel' == $operation)
    {
        /* 标记订单为“取消”，记录取消原因 */
        $cancel_note = isset($_REQUEST['cancel_note']) ? trim($_REQUEST['cancel_note']) : '';
        $arr = array(
            'order_status'  => OS_CANCELED,
            'to_buyer'      => $cancel_note,
            'pay_status'    => PS_UNPAYED,
            'pay_time'      => 0,
            'money_paid'    => 0,
            'order_amount'  => $order['money_paid']
        );
        update_order($order_id, $arr);

        /* todo 处理退款 */
        if ($order['money_paid'] > 0 && $order['money_paid'] != PS_UNPAYED)
        {
            $refund_type = isset($_REQUEST['refund']) && !empty($_REQUEST['refund']) ? tirm($_REQUEST['refund']) : 1;
            $refund_note = isset($_REQUEST['refund_note']) && !empty($_REQUEST['refund_note']) ? tirm($_REQUEST['refund_note']) : '';

            if ($refund_note) {
                $refund_note = "【" . $_LANG['setorder_cancel'] . "】【" . $order['order_sn'] . "】" . $refund_note;
            }

            order_refund($order, $refund_type, $refund_note);
        }

        /* 记录log */
        order_action($order['order_sn'], OS_CANCELED, $order['shipping_status'], PS_UNPAYED, $action_note, $_SESSION['admin_name']);

        /* 如果使用库存，且下订单时减库存，则增加库存 */
        if ($_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_PLACE)
        {
            change_order_goods_storage($order_id, false, SDT_PLACE, 3, $_SESSION['admin_id'],$store_id);
        }

        /* 退还用户余额、积分、红包 */
        return_user_surplus_integral_bonus($order);

        /* 发送邮件 */
        $cfg = $_CFG['send_cancel_email'];
        if ($cfg == '1')
        {
            $tpl = get_mail_template('order_cancel');
            $smarty->assign('order', $order);
            $smarty->assign('shop_name', $_CFG['shop_name']);
            $smarty->assign('send_date', local_date($GLOBALS['_CFG']['time_format'], gmtime()));
            $smarty->assign('sent_date', local_date($GLOBALS['_CFG']['time_format'], gmtime()));
            $content = $smarty->fetch('str:' . $tpl['template_content']);
            if (!send_mail($order['consignee'], $order['email'], $tpl['template_subject'], $content, $tpl['is_html']))
            {
                $msg = $_LANG['send_mail_fail'];
            }
        }
    }
    /* 设为无效 */
    elseif ('invalid' == $operation)
    {
        /* 标记订单为“无效”、“未付款” */
        update_order($order_id, array('order_status' => OS_INVALID));

        /* 记录log */
        order_action($order['order_sn'], OS_INVALID, $order['shipping_status'], PS_UNPAYED, $action_note, $_SESSION['admin_name']);

        /* 如果使用库存，且下订单时减库存，则增加库存 */
        if ($_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_PLACE)
        {
            change_order_goods_storage($order_id, false, SDT_PLACE, 2, $_SESSION['admin_id'],$store_id);
        }

        /* 发送邮件 */
        $cfg = $_CFG['send_invalid_email'];
        if ($cfg == '1')
        {
            $tpl = get_mail_template('order_invalid');
            $smarty->assign('order', $order);
            $smarty->assign('shop_name', $_CFG['shop_name']);
            $smarty->assign('send_date', local_date($GLOBALS['_CFG']['time_format'], gmtime()));
            $smarty->assign('sent_date', local_date($GLOBALS['_CFG']['time_format'], gmtime()));
            $content = $smarty->fetch('str:' . $tpl['template_content']);
            if (!send_mail($order['consignee'], $order['email'], $tpl['template_subject'], $content, $tpl['is_html']))
            {
                $msg = $_LANG['send_mail_fail'];
            }
        }

        /* 退货用户余额、积分、红包 */
        return_user_surplus_integral_bonus($order);
    }

    /**
     * 退款
     * by  Leah
     */
    elseif ('refound' == $operation) {

        include_once(ROOT_PATH . 'includes/lib_transaction.php');
        //TODO
        /* 定义当前时间 */
        define('GMTIME_UTC', gmtime()); // 获取 UTC 时间戳

        $is_whole = 0;
        $is_diff = get_order_return_rec($order_id);

//        $is_all_return = get_order_return_ret_isall($order_id, $ret_id);
        if ($is_diff) {
            //整单退换货
            $return_count = return_order_info_byId($order_id, 0);
            if ($return_count == 1) {

                //退还红包
                $bonus = $order['bonus'];
                $sql = "UPDATE " . $GLOBALS['ecs']->table('user_bonus') . " SET used_time = '' , order_id = '' WHERE order_id = " . $order_id;
                $GLOBALS['db']->query($sql);

                /*  @author-bylu 退还优惠券 start  */
                unuse_coupons($order_id);

                $is_whole = 1;
            }
        }

        $kouling = isset($_REQUEST['kouling']) ? $_REQUEST['kouling'] : '';
        if (!$kouling) {
            sys_msg('口令不能为空');
        }

        //获取口令
        $sql = "SELECT kouling FROM " .$ecs->table('admin_user').
            " WHERE user_id = '".$_SESSION['admin_id'] ."'";
        $user_info = $db->getRow($sql);
        if (!$user_info || md5($kouling) !=  $user_info['kouling']) {
            sys_msg('口令错误');
        }

        if (in_array($order['shipping_status'], [SS_SHIPPED, SS_SHIPPED_PART, SS_RECEIVED]) && !$order['is_kuaidi_update']) {
            sys_msg('未更新快递费用不能退货');
        }

        /* 过滤数据 */
        $_REQUEST['refund'] = isset($_REQUEST['refund']) ? $_REQUEST['refund'] : ''; // 退款类型
        $_REQUEST['refund_amount'] = isset($_REQUEST['refund_amount']) ? $_REQUEST['refund_amount'] :
        $_REQUEST['action_note'] = isset($_REQUEST['action_note']) ? $_REQUEST['action_note'] : ''; //退款说明
        $_REQUEST['refound_pay_points'] = isset($_REQUEST['refound_pay_points']) ? $_REQUEST['refound_pay_points']:0;//退回积分  by kong

        $return_amount = isset($_REQUEST['refound_amount']) && !empty($_REQUEST['refound_amount']) ? floatval($_REQUEST['refound_amount']) : 0; //退款金额
        $is_shipping = isset($_REQUEST['is_shipping']) && !empty($_REQUEST['is_shipping']) ? intval($_REQUEST['is_shipping']) : 0; //是否退运费
        $shippingFee = !empty($is_shipping) ? floatval($_REQUEST['shipping']) : 0; //退款运费金额
        $refound_vcard = isset($_REQUEST['refound_vcard']) && !empty($_REQUEST['refound_vcard']) ? floatval($_REQUEST['refound_vcard']) : 0; //储值卡金额
        $vc_id = isset($_REQUEST['vc_id']) && !empty($_REQUEST['vc_id']) ? intval($_REQUEST['vc_id']) : 0; //储值卡金额

        $return_goods = get_return_order_goods1($rec_id); //退换货商品
        $return_info = return_order_info($ret_id);        //退换货订单

        /* todo 处理退款 */
        if ($order['pay_status'] != PS_UNPAYED) {
            $order_goods = get_order_goods($order);             //订单商品
            $refund_type = $_REQUEST['refund'];

            //判断商品退款是否大于实际商品退款金额
            $refound_fee = order_refound_fee($order_id, $ret_id); //已退金额
            $paid_amount = $order['money_paid'] + $order['surplus'] - $refound_fee;
            if ($return_amount > $paid_amount) {
                $return_amount = $paid_amount - $order['shipping_fee'];
            }

            //判断运费退款是否大于实际运费退款金额
            $is_refound_shippfee = order_refound_shipping_fee($order_id, $ret_id);
            $is_refound_shippfee_amount = $is_refound_shippfee + $shippingFee;

            if ($is_refound_shippfee_amount > $order['shipping_fee']) {
                $shippingFee = $order['shipping_fee'] - $is_refound_shippfee;
            }

            $refund_amount = $return_amount + $shippingFee;

            $get_order_arr = get_order_arr($return_info['return_number'], $return_info['rec_id'], $order_goods['goods_list'], $order);

            $refund_note = addslashes(trim($_REQUEST['refund_note']));
            if (!empty($_REQUEST['action_note'])) {
                if (3 == $refund_type) {
                    $order['log_id'] = zxk_get_paylog_id($order['order_id'], $pay_type = PAY_ORDER);
                    $order['should_return'] = $return_info['should_return'];
                    //添加ret_id 区分部分退货使用
                    $order['ret_id'] = $ret_id;
                    $data = order_refound($order, $refund_type, $refund_note, $refund_amount, $operation);
                    if ($order['pay_id'] == 17) {
                        if ($data && 'SUCCESS' == $data['return_code']) {
                            //退款成功
                            update_order($order_id, $get_order_arr);
                            /* by zxk jisuan */
                            //订单退款 确认收货后退货退款
                            order_refound_seller_has_confirm_account_change($order_id, $ret_id, $refund_amount);
                            /* by zxk jisuan */

                            /* by zxk jisuan */
                            //订单退款 退货修改商家金额
                            //整单退货
                            if (order_refound_is_all($return_info['return_number'], $return_info['rec_id'], $order_goods['goods_list'], $order) && in_array($order['shipping_status'], [SS_SHIPPED, SS_SHIPPED_PART])) {
                                order_refound_change($order_id);
                            }

                        } else {
                            sys_msg('原路返还退款失败，请选择别的退款方式');
                        }
                    } else {
                        if ($data) {
                            update_order($order_id, $get_order_arr);
                            /* by zxk jisuan */
                            //订单退款 确认收货后退货退款
                            order_refound_seller_has_confirm_account_change($order_id, $ret_id, $refund_amount);
                            /* by zxk jisuan */

                            /* by zxk jisuan */
                            //订单退款 退货修改商家金额
                            //整单退货
                            if (order_refound_is_all($return_info['return_number'], $return_info['rec_id'], $order_goods['goods_list'], $order) && in_array($order['shipping_status'], [SS_SHIPPED, SS_SHIPPED_PART])) {
                                order_refound_change($order_id);
                            }
                            /* by zxk jisuan */
                        } else {
                            sys_msg('原路返还退款失败，请选择别的退款方式');
                        }
                    }

                } else {
                    update_order($order_id, $get_order_arr);

                    $order['should_return'] = $return_info['should_return'];
                    order_refound($order, $refund_type, $refund_note, $refund_amount, $operation);

                    /* by zxk jisuan */
                    //订单退款 确认收货后退货退款
                    order_refound_seller_has_confirm_account_change($order_id, $ret_id, $refund_amount);
                    /* by zxk jisuan */

                    /* by zxk jisuan */
                    //订单退款 退货修改商家金额
                    //整单退货
                    if (order_refound_is_all($return_info['return_number'], $return_info['rec_id'], $order_goods['goods_list'], $order) && in_array($order['shipping_status'], [SS_SHIPPED, SS_SHIPPED_PART])) {
                        order_refound_change($order_id);
                    }
                    /* by zxk jisuan */
                }



                //标记order_return 表
                $return_status = array(
                    'refound_status' => 1,
                    'agree_apply' => 1,
                    'actual_return' => $refund_amount,
                    'return_shipping_fee' => $shippingFee,
                    'refund_type' => $refund_type,
                    'return_time' => gmtime()
                );
                $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('order_return'), $return_status, 'UPDATE', "ret_id = '$ret_id'");

                // 记录log
                order_action($order['order_sn'], OS_RETURNED_PART, $order['shipping_status'], $order['pay_status'], $action_note, $_SESSION['admin_name']);
            }
        }

         /* 判断是否需要退还积分  如果需要 则跟新退还日志   by kong */
        if ($_REQUEST['refound_pay_points'] > 0) {
            log_account_change($order['user_id'], 0, 0, 0, $_REQUEST['refound_pay_points'], " 订单退款，退回订单 " . $order['order_sn'] . " 购买的积分");
        }

        if($is_whole == 1){
            return_card_money($order_id, $ret_id, $return_info['return_sn']);
        }else{
            /* 退回订单消费储值卡金额 */
            get_return_vcard($order_id, $vc_id, $refound_vcard, $return_info['return_sn'], $ret_id);
        }


        /* 退回订单赠送的积分 */
        return_integral_rank($ret_id, $order['user_id'], $order['order_sn'], $rec_id, $_REQUEST['refound_pay_points']);

        /* 如果使用库存，则增加库存（不论何时减库存都需要） */
        if ($_CFG['use_storage'] == '1') {
            if ($_CFG['stock_dec_time'] == SDT_SHIP) {
                change_order_goods_storage($order_id, false, SDT_SHIP, 6, $_SESSION['admin_id'], $store_id);
            } elseif ($_CFG['stock_dec_time'] == SDT_PLACE) {
                change_order_goods_storage($order_id, false, SDT_PLACE, 6, $_SESSION['admin_id'], $store_id);
            }
        }

        /* 记录log */
        return_action($ret_id, '', FF_REFOUND, $action_note);
    }

    /* 退货 by Leah */
    elseif ('return' == $operation) {

        //TODO
        /* 定义当前时间 */
        define('GMTIME_UTC', gmtime()); // 获取 UTC 时间戳

        /* 过滤数据 */
        $_REQUEST['refund'] = isset($_REQUEST['refund']) ? $_REQUEST['refund'] : '';
        $_REQUEST['refund_note'] = isset($_REQUEST['refund_note']) ? $_REQUEST['refund'] : '';

        /* 手动修改退款金额 start */
        $return_amount = isset($_REQUEST['refound_amount']) && !empty($_REQUEST['refound_amount']) ? floatval($_REQUEST['refound_amount']) : 0; //退款金额
        $is_shipping = isset($_REQUEST['is_shipping']) && !empty($_REQUEST['is_shipping']) ? intval($_REQUEST['is_shipping']) : 0; //是否退运费
        $shipping_fee = !empty($is_shipping) ? floatval($_REQUEST['shipping']) : 0; //退款运费金额
        /* 手动修改退款金额 end */

        $refound_vcard = isset($_REQUEST['refound_vcard']) && !empty($_REQUEST['refound_vcard']) ? floatval($_REQUEST['refound_vcard']) : 0; //储值卡金额
        $vc_id = isset($_REQUEST['vc_id']) && !empty($_REQUEST['vc_id']) ? intval($_REQUEST['vc_id']) : 0; //储值卡金额

        $order_return_amount = $return_amount + $shipping_fee;

        /* 标记订单为“退货”、“未付款”、“未发货” */
        $arr = array('order_status' => OS_RETURNED,
            'pay_status' => PS_UNPAYED,
            'shipping_status' => SS_UNSHIPPED,
            'money_paid' => 0,
            'invoice_no' => '',
            'return_amount' => $order_return_amount,
            'order_amount' => $order_return_amount
        );

        update_order($order_id, $arr);

        /* todo 处理退款 */
        if ($order['pay_status'] != PS_UNPAYED) {

            $order['order_status'] = OS_RETURNED;
            $order['pay_status'] = PS_UNPAYED;
            $order['shipping_status'] = SS_UNSHIPPED;

            $refund_type = isset($_REQUEST['refund']) ? intval($_REQUEST['refund']) : 0;
            $refund_note = isset($_REQUEST['action_note']) ? addslashes(trim($_REQUEST['action_note'])) : '';
            $refund_note = "【" .$_LANG['refund']. "】" . "【" .$order['order_sn']. "】" . $refund_note;
            order_refund($order, $refund_type, $refund_note, $return_amount, $shipping_fee);

            /* 余额已放入冻结资金 */
            $order['surplus'] = 0;
        }

        /* 记录log */
        order_action($order['order_sn'], OS_RETURNED, SS_UNSHIPPED, PS_UNPAYED, $action_note, $_SESSION['admin_name']);

        /* 如果订单用户不为空，计算积分，并退回 */
        if ($order['user_id'] > 0) {
            /* 取得用户信息 */
            $user = user_info($order['user_id']);

            $sql = "SELECT  goods_number, send_number FROM" . $GLOBALS['ecs']->table('order_goods') . "
                WHERE order_id = '" . $order['order_id'] . "'";

            $goods_num = $db->query($sql);
            $goods_num = $db->fetchRow($goods_num);

            if ($goods_num['goods_number'] == $goods_num['send_number']) {
                /* 计算并退回积分 */
                $integral = integral_to_give($order);
                log_account_change($order['user_id'], 0, 0, (-1) * intval($integral['rank_points']), (-1) * intval($integral['custom_points']), sprintf($_LANG['return_order_gift_integral'], $order['order_sn']));
            }
            /* todo 计算并退回红包 */
            return_order_bonus($order_id);
        }

        /* 如果使用库存，则增加库存（不论何时减库存都需要） */
        if ($_CFG['use_storage'] == '1') {
            if ($_CFG['stock_dec_time'] == SDT_SHIP) {
                change_order_goods_storage($order['order_id'], false, SDT_SHIP, 6, $_SESSION['admin_id'],$store_id);
            } elseif ($_CFG['stock_dec_time'] == SDT_PLACE) {
                change_order_goods_storage($order['order_id'], false, SDT_PLACE, 6, $_SESSION['admin_id'],$store_id);
            }
        }

        /* 退回订单消费储值卡金额 */
        return_card_money($order_id);

        /* 退货用户余额、积分、红包 */
        return_user_surplus_integral_bonus($order);

        /* 获取当前操作员 */
        $delivery['action_user'] = $_SESSION['admin_name'];
        /* 添加退货记录 */
        $delivery_list = array();
        $sql_delivery = "SELECT *
                         FROM " . $ecs->table('delivery_order') . "
                         WHERE status IN (0, 2)
                         AND order_id = " . $order['order_id'];
        $delivery_list = $GLOBALS['db']->getAll($sql_delivery);
        if ($delivery_list) {
            foreach ($delivery_list as $list) {
                $sql_back = "INSERT INTO " . $ecs->table('back_order') . " (delivery_sn, order_sn, order_id, add_time, shipping_id, user_id, action_user, consignee, address, Country, province, City, district, sign_building, Email,Zipcode, Tel, Mobile, best_time, postscript, how_oos, insure_fee, shipping_fee, update_time, suppliers_id, return_time, agency_id, invoice_no) VALUES ";

                $sql_back .= " ( '" . $list['delivery_sn'] . "', '" . $list['order_sn'] . "',
                              '" . $list['order_id'] . "', '" . $list['add_time'] . "',
                              '" . $list['shipping_id'] . "', '" . $list['user_id'] . "',
                              '" . $delivery['action_user'] . "', '" . $list['consignee'] . "',
                              '" . $list['address'] . "', '" . $list['country'] . "', '" . $list['province'] . "',
                              '" . $list['city'] . "', '" . $list['district'] . "', '" . $list['sign_building'] . "',
                              '" . $list['email'] . "', '" . $list['zipcode'] . "', '" . $list['tel'] . "',
                              '" . $list['mobile'] . "', '" . $list['best_time'] . "', '" . $list['postscript'] . "',
                              '" . $list['how_oos'] . "', '" . $list['insure_fee'] . "',
                              '" . $list['shipping_fee'] . "', '" . $list['update_time'] . "',
                              '" . $list['suppliers_id'] . "', '" . GMTIME_UTC . "',
                              '" . $list['agency_id'] . "', '" . $list['invoice_no'] . "'
                              )";
                $GLOBALS['db']->query($sql_back, 'SILENT');
                $back_id = $GLOBALS['db']->insert_id();

                $sql_back_goods = "INSERT INTO " . $ecs->table('back_goods') . " (back_id, goods_id, product_id, product_sn, goods_name,goods_sn, is_real, send_number, goods_attr)
                                   SELECT '$back_id', goods_id, product_id, product_sn, goods_name, goods_sn, is_real, send_number, goods_attr
                                   FROM " . $ecs->table('delivery_goods') . "
                                   WHERE delivery_id = " . $list['delivery_id'];
                $GLOBALS['db']->query($sql_back_goods, 'SILENT');
            }
        }

        /* 修改订单的发货单状态为退货 */
        $sql_delivery = "UPDATE " . $ecs->table('delivery_order') . "
                         SET status = 1
                         WHERE status IN (0, 2)
                         AND order_id = " . $order['order_id'];
        $GLOBALS['db']->query($sql_delivery, 'SILENT');

        /* 将订单的商品发货数量更新为 0 */
        $sql = "UPDATE " . $GLOBALS['ecs']->table('order_goods') . "
                SET send_number = 0
                WHERE order_id = '$order_id'";
        $GLOBALS['db']->query($sql, 'SILENT');

        /* 清除缓存 */
        clear_cache_files();
    } elseif ('after_service' == $operation) {
        /* 记录log */
        order_action($order['order_sn'], $order['order_status'], $order['shipping_status'], $order['pay_status'], '[' . $_LANG['op_after_service'] . '] ' . $action_note, $_SESSION['admin_name']);
    } else {
        die('invalid params');
    }

    /**
     * by Leah s
     */
    if ($return) {

        $links[] = array('text' => $_LANG['order_info'], 'href' => 'order.php?act=return_info&ret_id=' . $ret_id . '&rec_id=' . $rec_id); //by Leah

        sys_msg($_LANG['act_ok'] . $msg, 0, $links);
    } else {
        /* 操作成功 */
        $links[] = array('text' => $_LANG['order_info'], 'href' => 'order.php?act=info&order_id=' . $order_id);
        sys_msg($_LANG['act_ok'] . $msg, 0, $links);
    }
     /**
     * by Leah e
     */
}

//ecmoban模板堂 --zhuo start
elseif ($_REQUEST['act'] == 'json')
{
    include_once(ROOT_PATH . 'includes/cls_json.php');
    $json = new JSON();

    $goods_id = isset($_REQUEST['goods_id'])?intval($_REQUEST['goods_id']) : 0;
    //ecmoban模板堂 --zhuo start
    $warehouse_id = isset($_REQUEST['warehouse_id'])?intval($_REQUEST['warehouse_id']):0;
    $area_id = isset($_REQUEST['area_id'])?intval($_REQUEST['area_id']):0;
    $user_id = isset($_REQUEST['user_id'])?intval($_REQUEST['user_id']):0;
    $model_attr = isset($_REQUEST['model_attr'])?intval($_REQUEST['model_attr']):0;
    $goods_number = isset($_REQUEST['goods_number'])?intval($_REQUEST['goods_number']):0;
    //ecmoban模板堂 --zhuo end

    $func = $_REQUEST['func'];
    if ($func == 'get_goods_info')
    {
        /* 取得商品信息 */

        $leftJoin = " left join " .$GLOBALS['ecs']->table('warehouse_goods'). " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
        $leftJoin .= " left join " .$GLOBALS['ecs']->table('warehouse_area_goods'). " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";

        $sql = "SELECT g.goods_id, c.cat_name, g.goods_sn, g.goods_name, b.brand_name, g.market_price, g.model_attr, g.user_id, " .
                'IF(g.model_price < 1, g.goods_number, IF(g.model_price < 2, wg.region_number, wag.region_number)) AS goods_number, ' .
                'IFNULL(IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)), g.shop_price) AS shop_price, ' .
                "IFNULL(IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)), g.promote_price) AS promote_price, " .
                "g.promote_start_date, g.promote_end_date, g.goods_brief, g.goods_type, g.is_promote " .
                "FROM " . $ecs->table('goods') . " AS g " .
                $leftJoin .
                "LEFT JOIN " . $ecs->table('brand') . " AS b ON g.brand_id = b.brand_id " .
                "LEFT JOIN " . $ecs->table('category') . " AS c ON g.cat_id = c.cat_id " .
                " WHERE g.goods_id = '$goods_id'";
        $goods = $db->getRow($sql);
        $today = gmtime();
        $goods['goods_price'] = ($goods['is_promote'] == 1 &&
        $goods['promote_start_date'] <= $today && $goods['promote_end_date'] >= $today) ?
        $goods['promote_price'] : $goods['shop_price'];

        $goods['warehouse_id'] = $warehouse_id;
        $goods['area_id'] = $area_id;

        /* 取得会员价格 */
        $sql = "SELECT p.user_price, r.rank_name " .
                "FROM " . $ecs->table('member_price') . " AS p, " .
                    $ecs->table('user_rank') . " AS r " .
                "WHERE p.user_rank = r.rank_id " .
                "AND p.goods_id = '$goods_id' ";
        $goods['user_price'] = $db->getAll($sql);

        //ecmoban模板堂 --zhuo satrt
	$attr_leftJoin = '';
	$select = '';
	if($goods['model_attr'] == 1){
		$select = " wap.attr_price as warehouse_attr_price, ";
		$attr_leftJoin = 'LEFT JOIN ' . $GLOBALS['ecs']->table('warehouse_attr') . " AS wap ON g.goods_attr_id = wap.goods_attr_id AND wap.warehouse_id = '$warehouse_id' ";
	}elseif($goods['model_attr'] == 2){
		$select = " waa.attr_price as area_attr_price, ";
		$attr_leftJoin = 'LEFT JOIN ' . $GLOBALS['ecs']->table('warehouse_area_attr') . " AS waa ON g.goods_attr_id = waa.goods_attr_id AND area_id = '$area_id' ";
	}
	//ecmoban模板堂 --zhuo end

        /* 取得商品属性 */
        $sql = "SELECT a.attr_id, a.attr_name, g.goods_attr_id, g.attr_value, " .
                $select .
                " g.attr_price, a.attr_input_type, a.attr_type " .
                "FROM " . $ecs->table('goods_attr') . " AS g " .
                "LEFT JOIN" . $ecs->table('attribute') . " AS a ON g.attr_id = a.attr_id " .
                $attr_leftJoin .
                "WHERE g.goods_id = '$goods_id' ORDER BY a.sort_order, a.attr_id, g.goods_attr_id";
        $goods['attr_list'] = array();

        $res = $db->query($sql);
        while ($row = $db->fetchRow($res))
        {
            //ecmoban模板堂 --zhuo satrt
            if($goods['model_attr'] == 1){
                $row['attr_price'] = $row['warehouse_attr_price'];
            }elseif($goods['model_attr'] == 2){
                $row['attr_price'] = $row['area_attr_price'];
            }else{
                $row['attr_price'] = $row['attr_price'];
            }
            //ecmoban模板堂 --zhuo end

            $goods['attr_list'][$row['attr_id']][] = $row;
        }
        $goods['attr_list'] = array_values($goods['attr_list']);

        //ecmoban模板堂 --zhuo start
        if($goods['attr_list']){
            foreach($goods['attr_list'] as $attr_key=>$attr_row){
                $goods_attr_id .= $attr_row[0]['goods_attr_id'] . ",";
                $attr_price += $attr_row[0]['attr_price'];
            }

            $goods_attr_id = substr($goods_attr_id, 0, -1);
            $goods['attr_price'] = $attr_price;
        }

        $products = get_warehouse_id_attr_number($goods_id, $goods_attr_id, $goods['user_id'], $warehouse_id, $area_id);
        $attr_number = $products['product_number'];

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

        if(empty($prod)){ //当商品没有属性库存时
            $attr_number = $goods['goods_number'];
        }

        $attr_number = !empty($attr_number) ? $attr_number : 0;

        $goods['goods_storage'] = $attr_number;
        //ecmoban模板堂 --zhuo end

        echo $json->encode($goods);

    }else if( $func == 'get_goods_attr_number'){

        $products = get_warehouse_id_attr_number($goods_id, $_REQUEST['attr'], $user_id, $warehouse_id, $area_id);
        $attr_number = $products['product_number'];

        if($model_attr == 1){
            $table_products = "products_warehouse";
            $type_files = " and warehouse_id = '$warehouse_id'";
        }elseif($model_attr == 2){
            $table_products = "products_area";
            $type_files = " and area_id = '$area_id'";
        }else{
            $table_products = "products";
            $type_files = "";
        }

        $sql = "SELECT * FROM " .$GLOBALS['ecs']->table($table_products). " WHERE goods_id = '$goods_id'" .$type_files. " LIMIT 0, 1";
        $prod = $GLOBALS['db']->getRow($sql);

        if(empty($prod)){ //当商品没有属性库存时
            $attr_number = $goods_number;
        }

        $attr_number = !empty($attr_number) ? $attr_number : 0;

	$attr_leftJoin = '';
	$select = '';
	if($model_attr == 1){
		$select = " wap.attr_price as warehouse_attr_price, ";
		$attr_leftJoin = 'LEFT JOIN ' . $GLOBALS['ecs']->table('warehouse_attr') . " AS wap ON g.goods_attr_id = wap.goods_attr_id AND wap.warehouse_id = '$warehouse_id' ";
	}elseif($model_attr == 2){
		$select = " waa.attr_price as area_attr_price, ";
		$attr_leftJoin = 'LEFT JOIN ' . $GLOBALS['ecs']->table('warehouse_area_attr') . " AS waa ON g.goods_attr_id = waa.goods_attr_id AND area_id = '$area_id' ";
	}

        $goodsAttr = '';
	if(isset($_REQUEST['attr']) && !empty($_REQUEST['attr'])){
            $goodsAttr = " and g.goods_attr_id in(" .$_REQUEST['attr']. ") ";
	}

        /* 取得商品属性 */
        $sql = "SELECT a.attr_id, a.attr_name, g.goods_attr_id, g.attr_value, " .
                $select .
                " g.attr_price, a.attr_input_type, a.attr_type " .
                "FROM " . $ecs->table('goods_attr') . " AS g " .
                "LEFT JOIN" . $ecs->table('attribute') . " AS a ON g.attr_id = a.attr_id " .
                $attr_leftJoin .
                "WHERE g.goods_id = '$goods_id' " .$goodsAttr. " ORDER BY a.sort_order, a.attr_id, g.goods_attr_id";
        $goods['attr_list'] = array();

        $res = $db->query($sql);
        while ($row = $db->fetchRow($res))
        {
            if($model_attr == 1){
                $row['attr_price'] = $row['warehouse_attr_price'];
            }elseif($model_attr == 2){
                $row['attr_price'] = $row['area_attr_price'];
            }else{
                $row['attr_price'] = $row['attr_price'];
            }

            $goods['attr_list'][$row['attr_id']][] = $row;
        }
        $goods['attr_list'] = array_values($goods['attr_list']);

        $goods['attr_price'] = 0;
        if($goods['attr_list']){
            foreach($goods['attr_list'] as $attr_key=>$attr_row){
                $attr_price += $attr_row[0]['attr_price'];
            }

            $goods['attr_price'] = $attr_price;
        }

        $goods['goods_id'] = $goods_id;
        $goods['warehouse_id'] = $warehouse_id;
        $goods['area_id'] = $area_id;
        $goods['user_id'] = $user_id;
        $goods['attr'] = $_REQUEST['attr'];
        $goods['model_attr'] = $model_attr;
        $goods['goods_number'] = $goods_number;
        $goods['goods_storage'] = $attr_number;

        echo $json->encode($goods);
    }
}

/*------------------------------------------------------ */
//-- 异步加载合并订单弹框
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'merge_order_list')
{

    $store_list = get_common_store_list();
    $smarty->assign('store_list',        $store_list);

    make_json_result($smarty->fetch('library/merge_order_list.lbi'));
}

/*------------------------------------------------------ */
//-- 合并订单查询现有订单列表
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'ajax_merge_order_list')
{

    /* 检查权限 */
    admin_priv('order_os_edit');

    include_once(ROOT_PATH . 'includes/cls_json.php');
    $json = new JSON();

    $merchant_id = empty($_POST['merchant_id']) ? '' : intval($_POST['merchant_id']);
    $store_search = empty($_POST['store_search']) ? -1 : intval($_POST['store_search']);

	if($store_search != 1){
		$merchant_id = 0;
	}

	$where = " AND (SELECT og.ru_id FROM " . $GLOBALS['ecs']->table('order_goods') .' as og' . " WHERE og.order_id = o.order_id LIMIT 1) = '$merchant_id' ";
	$where .= " AND (select count(*) from " .$GLOBALS['ecs']->table('order_info'). " as oi2 where oi2.main_order_id = o.order_id) = 0 ";  //主订单下有子订单时，则主订单不显示

	/* 取得满足条件的订单 */
	$sql = "SELECT o.order_sn, u.user_name " .
			"FROM " . $ecs->table('order_info') . " AS o " .
			"LEFT JOIN " . $ecs->table('users') . " AS u ON o.user_id = u.user_id " .
			" LEFT JOIN " .$GLOBALS['ecs']->table('order_goods'). " AS og ON o.order_id=og.order_id ".
			" LEFT JOIN " .$GLOBALS['ecs']->table('goods'). " AS g ON og.goods_id=g.goods_id ".
			"WHERE o.user_id > 0 " . $where .
			"AND o.extension_code = '' " . order_query_sql('unprocessed') . " GROUP BY o.order_id";
	$order_list = $db->getAll($sql);

	foreach($order_list as $k=>$v){
		$html .= "<li><a href='javascript:;' data-value = '".$v['order_sn']."'>".$v['order_sn']."[".$v['user_name']."]</a></li>";
	}

    make_json_result($html);
}
//ecmoban模板堂 --zhuo end

/*------------------------------------------------------ */
//-- 合并订单
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'ajax_merge_order')
{
    /* 检查权限 */
    admin_priv('order_os_edit');

    include_once(ROOT_PATH . 'includes/cls_json.php');
    $json = new JSON();

    $from_order_sn = empty($_POST['from_order_sn']) ? '' : json_str_iconv(substr($_POST['from_order_sn'], 1));
    $to_order_sn = empty($_POST['to_order_sn']) ? '' : json_str_iconv(substr($_POST['to_order_sn'], 1));

    $m_result = merge_order($from_order_sn, $to_order_sn);
    $result = array('error'=>0,  'content'=>'');
    if ($m_result === true)
    {
        $result['message'] = $GLOBALS['_LANG']['act_ok'];
    }
    else
    {
        $result['error'] = 1;
        $result['message'] = $m_result;
    }
    die($json->encode($result));
}

/*------------------------------------------------------ */
//-- 删除订单
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'remove_order')
{
    /* 检查权限 */
    admin_priv('order_edit');

    $order_id = intval($_REQUEST['id']);

    /* 检查权限 */
    check_authz_json('order_edit');

    /* 检查订单是否允许删除操作 */
    $order = order_info($order_id);
    $operable_list = operable_list($order);
    if (!isset($operable_list['remove']))
    {
        make_json_error('Hacking attempt');
        exit;
    }

    $return_order = return_order_info(0, '', $order['order_id']);
    if($return_order){
        make_json_error(sprintf($_LANG['order_remove_failure'], $order['order_sn']));
        exit;
    }

    $GLOBALS['db']->query("DELETE FROM ".$GLOBALS['ecs']->table('order_info'). " WHERE order_id = '$order_id'");
    $GLOBALS['db']->query("DELETE FROM ".$GLOBALS['ecs']->table('order_goods'). " WHERE order_id = '$order_id'");
    $GLOBALS['db']->query("DELETE FROM ".$GLOBALS['ecs']->table('order_action'). " WHERE order_id = '$order_id'");
    $action_array = array('delivery', 'back');
    del_delivery($order_id, $action_array);

    if ($GLOBALS['db'] ->errno() == 0)
    {
        $url = 'order.php?act=query&' . str_replace('act=remove_order', '', $_SERVER['QUERY_STRING']);

        ecs_header("Location: $url\n");
        exit;
    }
    else
    {
        make_json_error($GLOBALS['db']->errorMsg());
    }
}

/*------------------------------------------------------ */
//-- 根据关键字和id搜索用户
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'search_users')
{
    include_once(ROOT_PATH . 'includes/cls_json.php');
    $json = new JSON();

    $id_name = empty($_GET['id_name']) ? '' : json_str_iconv(trim($_GET['id_name']));

    $result = array('error'=>0, 'message'=>'', 'content'=>'');
    if ($id_name != '')
    {
        $sql = "SELECT user_id, user_name FROM " . $GLOBALS['ecs']->table('users') .
                " WHERE user_id LIKE '%" . mysql_like_quote($id_name) . "%'" .
                " OR user_name LIKE '%" . mysql_like_quote($id_name) . "%'" .
                " LIMIT 20";
        $res = $GLOBALS['db']->query($sql);

         $result['userlist'] = array();
        while ($row = $GLOBALS['db']->fetchRow($res))
        {
             $result['userlist'][] = array('user_id' => $row['user_id'], 'user_name' => $row['user_name']);
        }
    }
    else
    {
        $result['error'] = 1;
        $result['message'] = 'NO KEYWORDS!';
    }

    die($json->encode($result));
}

/*------------------------------------------------------ */
//-- 根据关键字搜索商品
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'search_goods')
{
    include_once(ROOT_PATH . 'includes/cls_json.php');
    $json = new JSON();

    $keyword = empty($_GET['keyword']) ? '' : json_str_iconv(trim($_GET['keyword']));
    $order_id = empty($_GET['order_id']) ? '' : intval($_GET['order_id']);
    $warehouse_id = empty($_GET['warehouse_id']) ? '' : intval($_GET['warehouse_id']);
    $area_id = empty($_GET['area_id']) ? '' : intval($_GET['area_id']);

    if ($goods_count) {
        $sql = "SELECT ru_id FROM " . $GLOBALS['ecs']->table('order_goods') . " WHERE order_id = '$order_id'";
        $ru_id = $GLOBALS['db']->getAll($sql);
    } else {
        $ru_id = array(
            '0' => array('ru_id' => $adminru['ru_id'])
        );
    }

    $where = '';
    if($ru_id){

        foreach ($ru_id as $key=>$row){
            $ru_str .= $row['ru_id'] . ",";
        }

        $ru_str = substr($ru_str, 0, -1);
        $ru_str = explode(',', $ru_str);
        $ru_str = array_unique($ru_str);
        $ru_str = implode(',', $ru_str);

        $where = " AND user_id IN($ru_str)";
    }

    $result = array('error'=>0, 'message'=>'', 'content'=>'');

    if ($keyword != '')
    {
        $sql = "SELECT goods_id, goods_name, goods_sn, user_id FROM " . $GLOBALS['ecs']->table('goods') .
                " WHERE is_delete = 0" .
                " AND is_on_sale = 1" .
                $where .
                " AND is_alone_sale = 1" .
                " AND (goods_id LIKE '%" . mysql_like_quote($keyword) . "%'" .
                " OR goods_name LIKE '%" . mysql_like_quote($keyword) . "%'" .
                " OR goods_sn LIKE '%" . mysql_like_quote($keyword) . "%')" .
                " LIMIT 20";
        $res = $GLOBALS['db']->query($sql);

        $result['goodslist'] = array();
        while ($row = $GLOBALS['db']->fetchRow($res))
        {
            $result['warehouse_id'] = $warehouse_id;
            $result['area_id'] = $area_id;
            $result['goodslist'][] = array('goods_id' => $row['goods_id'], 'name' => $row['goods_id'] . '  ' . $row['goods_name'] . '  ' . $row['goods_sn'], 'user_id' => $row['user_id']);
        }
    }
    else
    {
        $result['error'] = 1;
        $result['message'] = 'NO KEYWORDS';
    }
    die($json->encode($result));
}

/*------------------------------------------------------ */
//-- 编辑收货单号
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'edit_invoice_no')
{
    /* 检查权限 */
    check_authz_json('order_edit');

    $no = empty($_POST['val']) ? 'N/A' : json_str_iconv(trim($_POST['val']));
    $no = $no=='N/A' ? '' : $no;
    $order_id = empty($_POST['id']) ? 0 : intval($_POST['id']);

    if ($order_id == 0)
    {
        make_json_error('NO ORDER ID');
        exit;
    }

    $sql = 'UPDATE ' . $GLOBALS['ecs']->table('order_info') . " SET invoice_no='$no' WHERE order_id = '$order_id'";
    if ($GLOBALS['db']->query($sql))
    {
        if (empty($no))
        {
            make_json_result('N/A');
        }
        else
        {
            make_json_result(stripcslashes($no));
        }
    }
    else
    {
        make_json_error($GLOBALS['db']->errorMsg());
    }
}

/*------------------------------------------------------ */
//-- 编辑付款备注
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'edit_pay_note')
{
    /* 检查权限 */
    check_authz_json('order_edit');

    $no = empty($_POST['val']) ? 'N/A' : json_str_iconv(trim($_POST['val']));
    $no = $no=='N/A' ? '' : $no;
    $order_id = empty($_POST['id']) ? 0 : intval($_POST['id']);

    if ($order_id == 0)
    {
        make_json_error('NO ORDER ID');
        exit;
    }

    $sql = 'UPDATE ' . $GLOBALS['ecs']->table('order_info') . " SET pay_note='$no' WHERE order_id = '$order_id'";
    if ($GLOBALS['db']->query($sql))
    {
        if (empty($no))
        {
            make_json_result('N/A');
        }
        else
        {
            make_json_result(stripcslashes($no));
        }
    }
    else
    {
        make_json_error($GLOBALS['db']->errorMsg());
    }
}

/*------------------------------------------------------ */
//-- 获取订单商品信息
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'get_goods_info')
{
    /* 取得订单商品 */
    $order_id = isset($_REQUEST['order_id'])?intval($_REQUEST['order_id']):0;
    if (empty($order_id))
    {
        make_json_response('', 1, $_LANG['error_get_goods_info']);
    }
    $goods_list = array();
    $goods_attr = array();
    $sql = "SELECT o.*, g.goods_thumb, oi.extension_id, oi.extension_code as oi_extension_code,o.extension_code as o_extension_code, g.brand_id, g.user_id AS ru_id, g.goods_number AS storage, g.model_inventory, o.goods_attr, oi.order_sn " .
            "FROM " . $ecs->table('order_goods') . " AS o ".
            "LEFT JOIN " . $ecs->table('goods') . " AS g ON o.goods_id = g.goods_id " .
			"LEFT JOIN " . $ecs->table('order_info') . " AS oi ON oi.order_id = o.order_id " .
            "WHERE o.order_id = '{$order_id}' ";
    $res = $db->query($sql);
    while ($row = $db->fetchRow($res))
    {
        /* 虚拟商品支持 */
        if ($row['is_real'] == 0)
        {
            /* 取得语言项 */
            $filename = ROOT_PATH . 'plugins/' . $row['oi_extension_code'] . '/languages/common_' . $_CFG['lang'] . '.php';
            if (file_exists($filename))
            {
                include_once($filename);
                if (!empty($_LANG[$row['oi_extension_code'].'_link']))
                {
                    $row['goods_name'] = $row['goods_name'] . sprintf($_LANG[$row['oi_extension_code'].'_link'], $row['goods_id'], $order['order_sn']);
                }
            }
        }
        if($row['model_inventory'] == 1){
            $row['storage'] = get_warehouse_area_goods($row['warehouse_id'], $row['goods_id'], 'warehouse_goods');
        }elseif($row['model_inventory'] == 2){
            $row['storage'] = get_warehouse_area_goods($row['area_id'], $row['goods_id'], 'warehouse_area_goods');
        }
        if($row['model_attr'] == 1){
                $table_products = "products_warehouse";
                $type_files = " and warehouse_id = '" .$row['warehouse_id']. "'";
        }elseif($row['model_attr'] == 2){
                $table_products = "products_area";
                $type_files = " and area_id = '" .$row['area_id']. "'";
        }else{
                $table_products = "products";
                $type_files = "";
        }

        $sql = "SELECT * FROM " .$GLOBALS['ecs']->table($table_products). " WHERE goods_id = '" .$row['goods_id']. "'" .$type_files. " LIMIT 0, 1";
        $prod = $GLOBALS['db']->getRow($sql);

        $products = get_warehouse_id_attr_number($row['goods_id'], $row['goods_attr_id'], $row['ru_id'], $row['warehouse_id'], $row['area_id'], $row['model_attr']);
        $row['goods_storage'] = $products['product_number'];

        if(empty($prod)){ //当商品没有属性库存时
                $row['goods_storage'] = $row['storage'];
        }
		$row['extension_id'] = $row['extension_id'];
        $row['storage'] = !empty($row['goods_storage']) ? $row['goods_storage'] : 0;

        $brand = get_goods_brand_info($row['brand_id']);
        $row['brand_name']       = $brand['brand_name'];

        if ($row['o_extension_code'] == 'package_buy')
        {
			$row['goods_thumb'] = "data/gallery_album/package_goods_default.jpg";
        }

        $row['formated_subtotal']       = price_format($row['goods_price'] * $row['goods_number']);
        $row['formated_goods_price']    = price_format($row['goods_price']);

        //图片显示
        $row['goods_thumb'] = get_image_path($row['goods_id'], $row['goods_thumb'], true);

        $trade_id = find_snapshot($row['order_sn'], $row['goods_id']);
        if ($trade_id) {
            $row['trade_url'] = "../trade_snapshot.php?act=trade&tradeId=" . $trade_id . "&snapshot=true";
        }

        $goods_attr[] = explode(' ', trim($row['goods_attr'])); //将商品属性拆分为一个数组
        $goods_list[] = $row;
    }
    $attr = array();
    $arr  = array();
    foreach ($goods_attr AS $index => $array_val)
    {
        foreach ($array_val AS $value)
        {
            $arr = explode(':', $value);//以 : 号将属性拆开
            $attr[$index][] =  @array('name' => $arr[0], 'value' => $arr[1]);
        }
    }
    $smarty->assign('goods_attr', $attr);
    $smarty->assign('goods_list', $goods_list);
    $str = $smarty->fetch('show_order_goods.dwt');
    $goods[] = array('order_id' => $order_id, 'str' => $str);
    make_json_result($goods);
}

/**
 * 修改收货时间
 * by Leah
 */ elseif ($_REQUEST['act'] == 'update_info') {

    $sign_time = local_strtotime($_REQUEST['time']);

    $order_id = $_REQUEST['order_id'];
    $sql = 'UPDATE ' . $ecs->table('order_info') . 'set sign_time =' . $sign_time . ' WHERE order_id =' . $order_id;
    $db->query($sql);
}

/*------------------------------------------------------ */
//-- 设置抢单页面
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'set_grab_order')
{
	$order_id = empty($_REQUEST['order_id'])? 0:intval($_REQUEST['order_id']);
	$result = array('error'=>0, 'message'=>'', 'content'=>'');

	$smarty->assign('order_id', $order_id);
	$store_list = get_store_list($order_id);
	$store_order_info = get_store_order_info($order_id, 'order_id');
	if(!empty($store_order_info))
	{
		$grab_store_arr = explode(',', $store_order_info['grab_store_list']);
		foreach($store_list as $key=>$val)
		{
			$store_list[$key]['is_check'] = 0;
			if(in_array($val['id'], $grab_store_arr))
			{
				$store_list[$key]['is_check'] = 1;
			}
		}
	}

	$smarty->assign('store_list', $store_list);
	$result['content'] = $smarty->fetch('library/set_grab_order.lbi');
	die(json_encode($result));
}

/*------------------------------------------------------ */
//-- 设置抢单
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'set_grab')
{
	$order_id = empty($_REQUEST['order_id'])? 0:intval($_REQUEST['order_id']);
	$ru_id = get_ru_id($order_id);
	if(count($_REQUEST['checkboxes']) > 0)
	{
		$grab_store_list = implode(',', $_REQUEST['checkboxes']);
		$store_order_info = get_store_order_info($order_id, 'order_id');
		if(empty($store_order_info))
		{
			$sql = " INSERT INTO ".$GLOBALS['ecs']->table('store_order')." (order_id, store_id, ru_id, is_grab_order, grab_store_list) ".
				" VALUES ('$order_id', '0', '$ru_id', '1', '$grab_store_list') ";
		}
		else
		{
			$sql = " UPDATE ".$GLOBALS['ecs']->table('store_order')." SET grab_store_list = '$grab_store_list' WHERE order_id = '$order_id' ";
		}
		$GLOBALS['db']->query($sql);
	}
	sys_msg($_LANG['set_success']);
}

/*------------------------------------------------------ */
//-- 部分发货弹窗 by wu
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'part_ship')
{
	check_authz_json('order_ss_edit');

	$rec_id = empty($_REQUEST['rec_id'])? 0:intval($_REQUEST['rec_id']);
	//查询数据
    $sql = "SELECT o.*, g.model_inventory, g.model_attr AS model_attr, g.suppliers_id AS suppliers_id, g.goods_number AS storage, g.goods_thumb, o.goods_attr, IFNULL(b.brand_name, '') AS brand_name, p.product_sn, g.bar_code  " .
            "FROM " . $GLOBALS['ecs']->table('order_goods') . " AS o ".
            "LEFT JOIN " . $GLOBALS['ecs']->table('products') . " AS p ON o.product_id = p.product_id " .
            "LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON o.goods_id = g.goods_id " .
            "LEFT JOIN " . $GLOBALS['ecs']->table('brand') . " AS b ON g.brand_id = b.brand_id " .
            "WHERE o.rec_id = '$rec_id' ";
	$row = $GLOBALS['db']->getRow($sql);
	//剩余发货数量
	$row['left_number'] = $row['goods_number'] - $row['send_number'];
	//ecmoban模板堂 --zhuo start
	if($row['product_id'] > 0){
		$products = get_warehouse_id_attr_number($row['goods_id'], $row['goods_attr_id'], $row['ru_id'], $row['warehouse_id'], $row['area_id'], $row['model_attr']);
		$row['storage'] = $products['product_number'];
	}else{
		if($row['model_inventory'] == 1){
			$row['storage'] = get_warehouse_area_goods($row['warehouse_id'], $row['goods_id'], 'warehouse_goods');
		}elseif($row['model_inventory'] == 2){
			$row['storage'] = get_warehouse_area_goods($row['area_id'], $row['goods_id'], 'warehouse_area_goods');
		}
	}
	//ecmoban模板堂 --zhuo end
	if ($row['extension_code'] == 'package_buy')
	{
		$row['storage'] = '';
	}
	//订单商品
	$order_goods = $row;
	$smarty->assign('order_goods', $order_goods);
	//订单详情
	$order = order_info($order_goods['order_id']);
	$smarty->assign('order', $order);
	$smarty->assign('operation', 'split');
	$content = $smarty->fetch('library/order_part_ship.lbi');
	make_json_result($content);
}

/*------------------------------------------------------ */
//-- 批量发货弹窗 by wu
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'batch_ship')
{
	check_authz_json('order_ss_edit');

	$delivery_ids = empty($_REQUEST['delivery_ids'])? '':trim($_REQUEST['delivery_ids']);
	$sql = " SELECT delivery_id, delivery_sn, order_sn, order_id, shipping_id, shipping_name, ".
		" consignee, mobile, email, country, province, city, district, address ".
		" FROM ".$GLOBALS['ecs']->table('delivery_order')." WHERE delivery_id IN ($delivery_ids) AND status = 2 ";
	$delivery_orders = $GLOBALS['db']->getAll($sql);
	//按快递分组
	$new_array = array();
	foreach($delivery_orders as $key=>$val){
		$new_array[$val['shipping_name']][] = $val;
	}
	//统计同快递各发货单数量
	foreach($new_array as $key=>$val){
		//处理数据
		foreach($val as $k=>$v){
			$val[$k]['region'] = get_user_region_address($v['order_id']);
		}
		$arr = array();
		$arr['count'] = count($val);
        foreach($val as $k => $v) {
            $val[$k]['invoice_no'] = get_table_date('order_info', 'order_id='.$v['order_id'], array('invoice_no'), 2);
        }
		$arr['list'] = $val;
		$new_array[$key] = $arr;
	}



	$smarty->assign('delivery_orders', $new_array);
	$content = $smarty->fetch('library/order_batch_ship.lbi');
	make_json_result($content);
} elseif ($_REQUEST['act'] == 'reissue') {

}

/**
 *
 * @param type $c_id
 * @return type
 * by　Leah
 */
function cause_info($c_id) {

    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('return_cause') . " WHERE cause_id = " . $c_id;

    $res = $GLOBALS['db']->getRow($sql);

    if ($res) {

        return $res;
    } else {

        return array();
    }
}

/**
 * 取得状态列表
 * @param   string  $type   类型：all | order | shipping | payment
 */
function get_status_list($type = 'all')
{
    global $_LANG;

    $list = array();

    if ($type == 'all' || $type == 'order')
    {
        $pre = $type == 'all' ? 'os_' : '';
        foreach ($_LANG['os'] AS $key => $value)
        {
            $list[$pre . $key] = $value;
        }
    }

    if ($type == 'all' || $type == 'shipping')
    {
        $pre = $type == 'all' ? 'ss_' : '';
        foreach ($_LANG['ss'] AS $key => $value)
        {
            $list[$pre . $key] = $value;
        }
    }

    if ($type == 'all' || $type == 'payment')
    {
        $pre = $type == 'all' ? 'ps_' : '';
        foreach ($_LANG['ps'] AS $key => $value)
        {
            $list[$pre . $key] = $value;
        }
    }
    return $list;
}

/**
 * 退回余额、积分、红包（取消、无效、退货时），把订单使用余额、积分、红包、优惠券设为0
 * @param   array   $order  订单信息
 */
function return_user_surplus_integral_bonus($order)
{
    /* 处理余额、积分、红包 */
    if ($order['user_id'] > 0 && $order['surplus'] > 0)
    {
        $surplus = $order['money_paid'] < 0 ? $order['surplus'] + $order['money_paid'] : $order['surplus'];
        log_account_change($order['user_id'], $surplus, 0, 0, 0, sprintf($GLOBALS['_LANG']['return_order_surplus'], $order['order_sn']), ACT_OTHER, 1);
        $GLOBALS['db']->query("UPDATE ". $GLOBALS['ecs']->table('order_info') . " SET `order_amount` = '0' WHERE `order_id` =". $order['order_id']);
    }

    if ($order['user_id'] > 0 && $order['integral'] > 0)
    {
        log_account_change($order['user_id'], 0, 0, 0, $order['integral'], sprintf($GLOBALS['_LANG']['return_order_integral'], $order['order_sn']), ACT_OTHER, 1);
    }

    if ($order['bonus_id'] > 0)
    {
        unuse_bonus($order['bonus_id']);
    }


    /*  @author-bylu 退优惠券 start  */
    if ($order['order_id'] > 0)
    {
        unuse_coupons($order['order_id']);
    }
    /*  @author-bylu  end  */


	/*退储值卡 start*/
	if($order['order_id'] > 0){
		return_card_money($order['order_id']);
	}
	/*退储值卡 end*/


    /* 修改订单 */
    $arr = array(
        'bonus_id'  => 0,
        'bonus'     => 0,
        'integral'  => 0,
        'integral_money'    => 0,
        'surplus'   => 0
    );
    update_order($order['order_id'], $arr);
}

/**
 * 更新订单总金额
 * @param   int     $order_id   订单id
 * @return  bool
 */
function update_order_amount($order_id)
{
    include_once(ROOT_PATH . 'includes/lib_order.php');
    //更新订单总金额
    $sql = "UPDATE " . $GLOBALS['ecs']->table('order_info') .
            " SET order_amount = " . order_due_field() .
            " WHERE order_id = '$order_id' LIMIT 1";

    return $GLOBALS['db']->query($sql);
}

/**
 * 返回某个订单可执行的操作列表，包括权限判断
 * @param   array   $order      订单信息 order_status, shipping_status, pay_status
 * @param   bool    $is_cod     支付方式是否货到付款
 * @return  array   可执行的操作  confirm, pay, unpay, prepare, ship, unship, receive, cancel, invalid, return, drop
 * 格式 array('confirm' => true, 'pay' => true)
 */
function operable_list($order)
{
    /* 取得订单状态、发货状态、付款状态 */
    $os = $order['order_status'];
    $ss = $order['shipping_status'];
    $ps = $order['pay_status'];

    /* 佣金账单状态 0 未出账 1 出账 2 结账 */
    $chargeoff_status = $order['chargeoff_status'];

    /* 取得订单操作权限 */
    $actions = $_SESSION['action_list'];
    if ($actions == 'all')
    {
        $priv_list  = array('os' => true, 'ss' => true, 'ps' => true, 'edit' => true);
    }
    else
    {
        $actions    = ',' . $actions . ',';
        $priv_list  = array(
            'os'    => strpos($actions, ',order_os_edit,') !== false,
            'ss'    => strpos($actions, ',order_ss_edit,') !== false,
            'ps'    => strpos($actions, ',order_ps_edit,') !== false,
            'edit'  => strpos($actions, ',order_edit,') !== false
        );
    }

    /* 取得订单支付方式是否货到付款 */
    $payment = payment_info($order['pay_id']);
    $is_cod  = $payment['is_cod'] == 1;

    /* 根据状态返回可执行操作 */
    $list = array();
    if (OS_UNCONFIRMED == $os)
    {
        /* 状态：未确认 => 未付款、未发货 */
        if ($priv_list['os'])
        {
            $list['confirm']    = true; // 确认
            $list['invalid']    = true; // 无效
            $list['cancel']     = true; // 取消
            if ($is_cod)
            {
                /* 货到付款 */
                if ($priv_list['ss'])
                {
                    $list['prepare'] = true; // 配货
                    $list['split'] = true; // 分单
                }
            }
            else
            {
                /* 不是货到付款 */
                if ($priv_list['ps'])
                {
                    $list['pay'] = true;  // 付款
                }
            }
        }
    }
    elseif(OS_RETURNED_PART == $os || $chargeoff_status > 0){
        if (OS_RETURNED_PART == $os && $chargeoff_status == 0) {
            //修复部分退款 不能发货问题
            $list['prepare'] = true; // 配货
            $list['split'] = true; // 分单
        }
        /* 状态：未付款 */
        if ($priv_list['ps'] < 2) {
            $list['pay'] = true; // 付款
        }

        if ($ss != SS_RECEIVED) {
            /* 状态：部分退货 */
            $list['receive'] = true; // 收货确认
        }
    }
    elseif (OS_CONFIRMED == $os || OS_SPLITED == $os || OS_SPLITING_PART == $os)
    {
        /* 状态：已确认 */
        if (PS_UNPAYED == $ps || PS_PAYED_PART == $ps)
        {
            /* 状态：已确认、未付款 */
            if (SS_UNSHIPPED == $ss || SS_PREPARING == $ss)
            {
                /* 状态：已确认、未付款、未发货（或配货中） */
                if ($priv_list['os'])
                {
                    $list['cancel'] = true; // 取消
                    $list['invalid'] = true; // 无效
                }
                if ($is_cod)
                {
                    /* 货到付款 */
                    if ($priv_list['ss'])
                    {
                        if (SS_UNSHIPPED == $ss)
                        {
                            $list['prepare'] = true; // 配货
                        }
                        $list['split'] = true; // 分单
                    }
                }
                else
                {
                    /* 不是货到付款 */
                    if ($priv_list['ps'])
                    {
                        $list['pay'] = true; // 付款
                    }
                }
            }
            /* 状态：已确认、未付款、发货中 */
            elseif (SS_SHIPPED_ING == $ss || SS_SHIPPED_PART == $ss)
            {
                // 部分分单
                if (OS_SPLITING_PART == $os)
                {
                    $list['split'] = true; // 分单
                }
                $list['to_delivery'] = true; // 去发货
            }
            else
            {
                /* 状态：已确认、未付款、已发货或已收货 => 货到付款 */
                if ($priv_list['ps'])
                {
                    $list['pay'] = true; // 付款
                }
                if ($priv_list['ss'])
                {
                    if (SS_SHIPPED == $ss)
                    {
                        $list['receive'] = true; // 收货确认
                    }
                    $list['unship'] = true; // 设为未发货
                    if ($priv_list['os'])
                    {
                        $list['return'] = true; // 退货
                    }
                }
            }
        }
        else
        {
            /* 状态：已确认、已付款和付款中 */
            if (SS_UNSHIPPED == $ss || SS_PREPARING == $ss)
            {
                /* 状态：已确认、已付款和付款中、未发货（配货中） => 不是货到付款 */
                if ($priv_list['ss'])
                {
                    if (SS_UNSHIPPED == $ss)
                    {
                        $list['prepare'] = true; // 配货
                    }
                    $list['split'] = true; // 分单
                }
                if ($priv_list['ps'])
                {
                    $list['unpay'] = true; // 设为未付款
                    if ($priv_list['os'])
                    {
                        //$list['cancel'] = true; // 取消  暂时注释 liu
                    }
                }
            }
            /* 状态：已确认、未付款、发货中 */
            elseif (SS_SHIPPED_ING == $ss || SS_SHIPPED_PART == $ss)
            {
                // 部分分单
                if (OS_SPLITING_PART == $os)
                {
                    $list['split'] = true; // 分单
                }
                $list['to_delivery'] = true; // 去发货
            }
            else
            {
                /* 状态：已确认、已付款和付款中、已发货或已收货 */
                if ($priv_list['ss'])
                {
                    if (SS_SHIPPED == $ss)
                    {
                        $list['receive'] = true; // 收货确认
                    }
                    if (!$is_cod)
                    {
                        $list['unship'] = true; // 设为未发货
                    }
                }
                if ($priv_list['ps'] && $is_cod)
                {
                    $list['unpay']  = true; // 设为未付款
                }
                if ($priv_list['os'] && $priv_list['ss'] && $priv_list['ps'])
                {
                    $list['return'] = true; // 退货（包括退款）
                }
            }
        }
    }
    elseif (OS_CANCELED == $os)
    {
        /* 状态：取消 */
        if ($priv_list['os'])
        {
           // $list['confirm'] = true; 暂时注释 liu
        }
        if ($priv_list['edit'])
        {
            $list['remove'] = true;
        }
    }
    elseif (OS_INVALID == $os)
    {
        /* 状态：无效 */
        if ($priv_list['os'])
        {
            //$list['confirm'] = true; 暂时注释 liu
        }
        if ($priv_list['edit'])
        {
            $list['remove'] = true;
        }
    }
    elseif (OS_RETURNED == $os)
    {
        /* 状态：退货 */
        if ($priv_list['os'])
        {
            $list['confirm'] = true;
        }
    }

    if ((OS_CONFIRMED == $os || OS_SPLITED == $os || OS_SHIPPED_PART == $os) && PS_PAYED == $ps && (SS_UNSHIPPED == $ss || SS_SHIPPED_PART == $ss))
    {
        /* 状态：（已确认、已分单）、已付款和未发货 */
        if ($priv_list['os'] && $priv_list['ss'] && $priv_list['ps']) {
            $list['return'] = true; // 退货（包括退款）
        }
    }

    /* 修正发货操作 */
    if (!empty($list['split']))
    {
        /* 如果是团购活动且未处理成功，不能发货 */
        if ($order['extension_code'] == 'group_buy')
        {
            include_once(ROOT_PATH . 'includes/lib_goods.php');
            $group_buy = group_buy_info(intval($order['extension_id']));
            if ($group_buy['status'] != GBS_SUCCEED)
            {
                unset($list['split']);
                unset($list['to_delivery']);
            }
        }

        /* 如果部分发货 不允许 取消 订单 */
        if (order_deliveryed($order['order_id']))
        {
            $list['return'] = true; // 退货（包括退款）
            unset($list['cancel']); // 取消
        }
    }

    /* 同意申请 */
    /*
     * by Leah
     */
    $list['after_service'] = true;
    $list['receive_goods'] = true;
    $list['agree_apply'] = true;
    $list['refound'] = true;
    $list['swapped_out_single'] = true;
    $list['swapped_out'] = true;
    $list['complete'] = true;
    $list['refuse_apply'] = true;
    /*
     * by Leah
     */

    return $list;
}

/**
 * 处理编辑订单时订单金额变动
 * @param   array   $order  订单信息
 * @param   array   $msgs   提示信息
 * @param   array   $links  链接信息
 */
function handle_order_money_change($order, &$msgs, &$links)
{
    $order_id = $order['order_id'];
    if ($order['pay_status'] == PS_PAYED || $order['pay_status'] == PS_PAYING)
    {
        /* 应付款金额 */
        $money_dues = $order['order_amount'];
        if ($money_dues > 0)
        {
            /* 修改订单为未付款 */
            update_order($order_id, array('pay_status' => PS_UNPAYED, 'pay_time' => 0));
            $msgs[]     = $GLOBALS['_LANG']['amount_increase'];
            $links[]    = array('text' => $GLOBALS['_LANG']['order_info'], 'href' => 'order.php?act=info&order_id=' . $order_id);
        }
        elseif ($money_dues < 0)
        {
            $anonymous  = $order['user_id'] > 0 ? 0 : 1;
            $msgs[]     = $GLOBALS['_LANG']['amount_decrease'];
            $links[]    = array('text' => $GLOBALS['_LANG']['refund'], 'href' => 'order.php?act=process&func=load_refund&anonymous=' .
                $anonymous . '&order_id=' . $order_id . '&refund_amount=' . abs($money_dues));
        }
    }
}

/**
 *  获取订单列表信息
 *
 * @access  public
 * @param
 *
 * @return void
 */
function order_list($page = 0)
{
    //ecmoban模板堂 --zhuo start
    $adminru = get_admin_ru_id();
    $ruCat = '';
    $no_main_order = '';
    $noTime = gmtime();
    //ecmoban模板堂 --zhuo end

    $result = get_filter();
    if ($result === false)
    {
        /* 过滤信息 */
        $filter['keywords'] = empty($_REQUEST['keywords']) ? '' : trim($_REQUEST['keywords']);
        $filter['order_sn'] = empty($_REQUEST['order_sn']) ? '' : trim($_REQUEST['order_sn']);
        $filter['consignee'] = empty($_REQUEST['consignee']) ? '' : trim($_REQUEST['consignee']);
        $filter['address'] = empty($_REQUEST['address']) ? '' : trim($_REQUEST['address']);

        if (!empty($_GET['is_ajax']) && $_GET['is_ajax'] == 1)
        {
            $filter['keywords'] = json_str_iconv($filter['keywords']);
            $filter['order_sn'] = json_str_iconv($filter['order_sn']);
            $filter['consignee'] = json_str_iconv($filter['consignee']);
            $filter['address'] = json_str_iconv($filter['address']);
        }

        $filter['email'] = empty($_REQUEST['email']) ? '' : trim($_REQUEST['email']);
        $filter['zipcode'] = empty($_REQUEST['zipcode']) ? '' : trim($_REQUEST['zipcode']);
        $filter['tel'] = empty($_REQUEST['tel']) ? '' : trim($_REQUEST['tel']);
        $filter['mobile'] = empty($_REQUEST['mobile']) ? 0 : trim($_REQUEST['mobile']);
        $filter['country'] = empty($_REQUEST['order_country']) ? 0 : intval($_REQUEST['order_country']);
        $filter['province'] = empty($_REQUEST['order_province']) ? 0 : intval($_REQUEST['order_province']);
        $filter['city'] = empty($_REQUEST['order_city']) ? 0 : intval($_REQUEST['order_city']);
        $filter['district'] = empty($_REQUEST['order_district']) ? 0 : intval($_REQUEST['order_district']);
        $filter['street'] = empty($_REQUEST['order_street']) ? 0 : intval($_REQUEST['order_street']);
        $filter['shipping_id'] = empty($_REQUEST['shipping_id']) ? 0 : intval($_REQUEST['shipping_id']);
        $filter['pay_id'] = empty($_REQUEST['pay_id']) ? 0 : intval($_REQUEST['pay_id']);
        $filter['order_status'] = isset($_REQUEST['order_status']) ? intval($_REQUEST['order_status']) : -1;
        $filter['shipping_status'] = isset($_REQUEST['shipping_status']) ? intval($_REQUEST['shipping_status']) : -1;
        $filter['pay_status'] = isset($_REQUEST['pay_status']) ? intval($_REQUEST['pay_status']) : -1;
        $filter['order_type'] = isset($_REQUEST['order_type']) ? intval($_REQUEST['order_type']) : 0;
        $filter['user_id'] = empty($_REQUEST['user_id']) ? 0 : intval($_REQUEST['user_id']);
        $filter['user_name'] = empty($_REQUEST['user_name']) ? '' : trim($_REQUEST['user_name']);
        $filter['composite_status'] = isset($_REQUEST['composite_status']) ? intval($_REQUEST['composite_status']) : -1;
        $filter['group_buy_id'] = isset($_REQUEST['group_buy_id']) ? intval($_REQUEST['group_buy_id']) : 0;
        $filter['presale_id'] = isset($_REQUEST['presale_id']) ? intval($_REQUEST['presale_id']) : 0; // 预售id
        $filter['sample_id'] = isset($_REQUEST['sample_id']) ? intval($_REQUEST['sample_id']) : 0;
        $filter['wholesale_id'] = isset($_REQUEST['wholesale_id']) ? intval($_REQUEST['wholesale_id']) : 0; // 批发
        $filter['store_id'] = isset($_REQUEST['store_id']) ? intval($_REQUEST['store_id']) : 0; // 门店id
        $filter['order_cat'] = isset($_REQUEST['order_cat']) ? trim($_REQUEST['order_cat']) : '';

        $filter['seller_order_list'] = isset($_REQUEST['seller_order_list']) && !empty($_REQUEST['seller_order_list']) ? 1 : 0;  //商家和自营订单标识

        $filter['source'] = empty($_REQUEST['source']) ? '' : trim($_REQUEST['source']); //来源起始页

        $filter['sort_by'] = empty($_REQUEST['sort_by']) ? 'add_time' : trim($_REQUEST['sort_by']);
        $filter['sort_order'] = empty($_REQUEST['sort_order']) ? 'DESC' : trim($_REQUEST['sort_order']);

        $filter['start_time'] = empty($_REQUEST['start_time']) ? '' : (strpos($_REQUEST['start_time'], '-') > 0 ?  local_strtotime($_REQUEST['start_time']) : $_REQUEST['start_time']);
        $filter['end_time'] = empty($_REQUEST['end_time']) ? '' : (strpos($_REQUEST['end_time'], '-') > 0 ?  local_strtotime($_REQUEST['end_time']) : $_REQUEST['end_time']);

        //确认收货时间 bylu:
        $filter['start_take_time'] = empty($_REQUEST['start_take_time']) ? '' : (strpos($_REQUEST['start_take_time'], '-') > 0 ?  local_strtotime($_REQUEST['start_take_time']) : $_REQUEST['start_take_time']);
        $filter['end_take_time'] = empty($_REQUEST['end_take_time']) ? '' : (strpos($_REQUEST['end_take_time'], '-') > 0 ?  local_strtotime($_REQUEST['end_take_time']) : $_REQUEST['end_take_time']);

        //管理员查询的权限 -- 店铺查询 start
        $filter['store_search'] = !isset($_REQUEST['store_search']) ? -1 : intval($_REQUEST['store_search']);
        $filter['merchant_id'] = isset($_REQUEST['merchant_id']) ? intval($_REQUEST['merchant_id']) : 0;
        $filter['store_keyword'] = isset($_REQUEST['store_keyword']) ? trim($_REQUEST['store_keyword']) : '';
        $filter['store_type'] = isset($_REQUEST['store_type']) ? trim($_REQUEST['store_type']) : '';

        //众筹订单 by wu
        $filter['pid'] = !isset($_REQUEST['pid']) ? 0 : intval($_REQUEST['pid']);
        $filter['gid'] = !isset($_REQUEST['gid']) ? 0 : intval($_REQUEST['gid']);

        $where = ' WHERE 1 ';

        if($filter['keywords']){
            $where  .= " AND (o.order_sn LIKE '%" .$filter['keywords']. "%'";
            $where  .= " OR (iog.goods_name LIKE '%" .$filter['keywords']. "%' OR iog.goods_sn LIKE '%" .$filter['keywords']. "%'))";
        }

        $no_main_order = " and (select count(*) from " .$GLOBALS['ecs']->table('order_info'). " as oi2 where oi2.main_order_id = o.order_id) = 0 ";  //主订单下有子订单时，则主订单不显示

        if($filter['order_type'] == 1){
            $no_main_order = " and (select count(*) from " .$GLOBALS['ecs']->table('order_info'). " as oi2 where oi2.main_order_id = o.order_id) <> 0 ";  //主订单
        }

        $leftJoin = '';
        $store_search = -1;
        $store_where = '';
        $store_search_where = '';
        if ($filter['store_search'] > -1) {
            if ($adminru['ru_id'] == 0) {
                if ($filter['store_search'] > 0) {
                    if ($filter['store_type']) {
                        $store_search_where = "AND msi.shopNameSuffix = '" . $filter['store_type'] . "'";
                    }

                    $no_main_order = " and (SELECT count(*) FROM " . $GLOBALS['ecs']->table('order_info') . " AS oi2 where oi2.main_order_id = o.order_id) = 0 ";  //主订单下有子订单时，则主订单不显示
                    if ($filter['store_search'] == 1) {
                        $where .= " AND (SELECT og.ru_id FROM " . $GLOBALS['ecs']->table('order_goods') . ' AS og' . " WHERE og.order_id = o.order_id LIMIT 1) = '" . $filter['merchant_id'] . "' ";
                    } elseif ($filter['store_search'] == 2) {
                        $store_where .= " AND msi.rz_shopName LIKE '%" . mysql_like_quote($filter['store_keyword']) . "%'";
                    } elseif ($filter['store_search'] == 3) {
                        $store_where .= " AND msi.shoprz_brandName LIKE '%" . mysql_like_quote($filter['store_keyword']) . "%' " . $store_search_where;
                    }

                    if ($filter['store_search'] > 1) {
                        $where .= " AND (SELECT og.ru_id FROM " . $GLOBALS['ecs']->table('order_goods') . ' AS og, ' .
                                $GLOBALS['ecs']->table('merchants_shop_information') . ' AS msi ' .
                                " WHERE og.order_id = o.order_id AND msi.user_id = og.ru_id $store_where LIMIT 1) > 0 ";
                    }
                } else {
                    $store_search = 0;
                }
            }
        }
        //管理员查询的权限 -- 店铺查询 end
        //门店订单 by kong 20160727 start
        if($filter['store_id'] > 0){
            $leftJoin .= " LEFT JOIN".$GLOBALS['ecs']->table('store_order')." AS sto ON sto.order_id = o.order_id ";
            $where .= " AND sto.store_id  = '".$filter['store_id']."'";
        }

        if ($filter['order_sn'])
        {
            $where .= " AND o.order_sn LIKE '%" . mysql_like_quote($filter['order_sn']) . "%'";
        }
        if ($filter['consignee'])
        {
            $where .= " AND o.consignee LIKE '%" . mysql_like_quote($filter['consignee']) . "%'";
        }
        if ($filter['email'])
        {
            $where .= " AND o.email LIKE '%" . mysql_like_quote($filter['email']) . "%'";
        }
        if ($filter['address'])
        {
            $where .= " AND o.address LIKE '%" . mysql_like_quote($filter['address']) . "%'";
        }
        if ($filter['zipcode'])
        {
            $where .= " AND o.zipcode LIKE '%" . mysql_like_quote($filter['zipcode']) . "%'";
        }
        if ($filter['tel'])
        {
            $where .= " AND o.tel LIKE '%" . mysql_like_quote($filter['tel']) . "%'";
        }
        if ($filter['mobile'])
        {
            $where .= " AND o.mobile LIKE '%" .mysql_like_quote($filter['mobile']) . "%'";
        }
        if ($filter['country'])
        {
            $where .= " AND o.country = '$filter[country]'";
        }
        if ($filter['province'])
        {
            $where .= " AND o.province = '$filter[province]'";
        }
        if ($filter['city'])
        {
            $where .= " AND o.city = '$filter[city]'";
        }
        if ($filter['district'])
        {
            $where .= " AND o.district = '$filter[district]'";
        }
        if ($filter['street'])
        {
            $where .= " AND o.street = '$filter[street]'";
        }
        if ($filter['shipping_id'])
        {
            $where .= " AND o.shipping_id  = '$filter[shipping_id]'";
        }
        if ($filter['pay_id'])
        {
            $where .= " AND o.pay_id  = '$filter[pay_id]'";
        }
        if ($filter['order_status'] != -1)
        {
            $where .= " AND o.order_status  = '$filter[order_status]'";
        }
        if ($filter['shipping_status'] != -1)
        {
            $where .= " AND o.shipping_status = '$filter[shipping_status]'";
        }
        if ($filter['pay_status'] != -1)
        {
            $where .= " AND o.pay_status = '$filter[pay_status]'";
        }
        if ($filter['user_id'])
        {
            $where .= " AND o.user_id = '$filter[user_id]'";
        }
        if ($filter['user_name'])
        {
            $where .= " AND (SELECT u.user_id FROM " .$GLOBALS['ecs']->table('users'). " AS u WHERE u.user_name LIKE '%" . mysql_like_quote($filter['user_name']) . "%' LIMIT 1) = o.user_id";
        }
        if ($filter['start_time'])
        {
            $where .= " AND o.add_time >= '$filter[start_time]'";
        }
        if ($filter['end_time'])
        {
            $where .= " AND o.add_time <= '$filter[end_time]'";
        }

        if ($filter['order_cat']) {
            switch ($filter['order_cat']) {
                case 'stages':
                    $leftJoin .= " LEFT JOIN " . $GLOBALS['ecs']->table('baitiao_log') . " AS b ON b.order_id = o.order_id ";
                    $where .= " AND b.order_id > 0 ";
                    break;
                case 'zc':
                    $where .= " AND o.is_zc_order = 1 ";
                    break;
                case 'store':
                    $leftJoin .= " LEFT JOIN " . $GLOBALS['ecs']->table('store_order') . " AS s ON s.order_id = o.order_id ";
                    $where .= " AND s.order_id > 0 ";
                    break;
                case 'other':
                    $where .= " AND length(o.extension_code) > 0 ";
                    break;
                case 'dbdd':
                    $where .= " AND o.extension_code = 'snatch' ";
                    break;
                case 'msdd':
                    $where .= " AND o.extension_code = 'seckill' ";
                    break;
                case 'tgdd':
                    $where .= " AND o.extension_code = 'group_buy' ";
                    break;
                case 'pmdd':
                    $where .= " AND o.extension_code = 'auction' ";
                    break;
                case 'jfdd':
                    $where .= " AND o.extension_code = 'exchange_goods' ";
                    break;
                case 'ysdd':
                    $where .= " AND o.extension_code = 'presale' ";
                    break;
                case 'ymdd':
                    $where .= " AND o.extension_code = 'sample' ";
                    break;
                case 'ypdd':
                    $where .= " AND o.extension_code = 'sample' ";
                    break;
                case 'xhdd':
                    $where .= " AND o.extension_code = 'wholesale' ";
                    break;
                default:
            }
        }

        $alias = "o.";
        //综合状态
        switch ($filter['composite_status']) {
            case CS_AWAIT_PAY :
                $where .= order_query_sql('await_pay', $alias);
                break;

            case CS_AWAIT_SHIP :
                $where .= order_query_sql('await_ship', $alias);
                break;

            case CS_FINISHED :
                $where .= order_query_sql('finished', $alias);
                break;
            //确认收货 bylu;
            case CS_CONFIRM_TAKE :
                $where .= order_query_sql('confirm_take', $alias);
                break;

            case PS_PAYING :
                if ($filter['composite_status'] != -1) {
                    $where .= " AND o.pay_status = '$filter[composite_status]' ";
                }
                break;
            case OS_SHIPPED_PART :
                if ($filter['composite_status'] != -1) {
                    $where .= " AND o.shipping_status  = '$filter[composite_status]'-2 ";
                }
                break;
            case 201 :
                $where .= order_query_sql('presale_two_yes');
                break;
            case 202 :
                $where .= order_query_sql('presale_two_no');
                break;
            case 203 :
                //退货退款
                $where .= " AND {$alias}order_status " . db_create_in(array(OS_RETURNED, OS_RETURNED_PART));
                break;
            default:
                if ($filter['composite_status'] != -1) {
                    $where .= " AND o.order_status = '$filter[composite_status]' ";
                }
        }

        /* 团购订单 */
        if ($filter['group_buy_id']) {
            $has_user_id = get_table_date('goods_activity', 'act_id='.$filter[group_buy_id], array('user_id'), 2);
            if ($has_user_id) {
                $_REQUEST['seller_order_list'] = 1;
                $GLOBALS['smarty']->assign('common_tabs', array('info' => isset($_REQUEST['seller_order_list']) ? 1 : 0 , 'url' => 'order.php?act=list'));
            }
            $where .= " AND o.extension_code = 'group_buy' AND o.extension_id = '$filter[group_buy_id]' ";
        }
        /* 预售订单 */
        if ($filter['presale_id']) {
            $has_user_id = get_table_date('presale_activity', 'act_id='.$filter[presale_id], array('user_id'), 2);
            if ($has_user_id) {
                $_REQUEST['seller_order_list'] = 1;
                $GLOBALS['smarty']->assign('common_tabs', array('info' => isset($_REQUEST['seller_order_list']) ? 1 : 0 , 'url' => 'order.php?act=list'));

            }
            $where .= " AND o.extension_code = 'presale' AND o.extension_id = '$filter[presale_id]' ";

            $GLOBALS['smarty']->assign('presale_id', 1);
        }
        /* 样品订单 */
        if ($filter['sample_id']) {
            $has_user_id = get_table_date('sample_activity', 'act_id='.$filter[sample_id], array('user_id'), 2);
            if ($has_user_id) {
                $_REQUEST['seller_order_list'] = 1;
                $GLOBALS['smarty']->assign('common_tabs', array('info' => isset($_REQUEST['seller_order_list']) ? 1 : 0 , 'url' => 'order.php?act=list'));

            }
            $where .= " AND o.extension_code = 'sample' AND o.extension_id = '$filter[sample_id]' ";
        }


        /* 批发订单 */
        if ($filter['wholesale_id']) {
            $has_user_id = get_table_date('wholesale', 'act_id='.$filter[wholesale_id], array('user_id'), 2);
            if ($has_user_id) {
                $_REQUEST['seller_order_list'] = 1;
                $GLOBALS['smarty']->assign('common_tabs', array('info' => isset($_REQUEST['seller_order_list']) ? 1 : 0 , 'url' => 'order.php?act=list'));

            }
            $where .= " AND o.extension_code = 'wholesale' AND o.extension_id = '$filter[wholesale_id]' ";
        }

        /* 众筹订单 by wu */
        if ($filter['pid']) {
            $sql = " SELECT id FROM " . $GLOBALS['ecs']->table('zc_goods') . " WHERE pid = '$filter[pid]' ";
            $goods_ids = $GLOBALS['db']->getCol($sql);
            $where .= " AND o.is_zc_order = 1 AND o.zc_goods_id " . db_create_in($goods_ids);
        }
        if ($filter['gid'])
        {
            $where .= " AND o.is_zc_order = 1 AND o.zc_goods_id = '$filter[gid]' ";
        }

        if (!($filter['group_buy_id'] || $filter['presale_id'] || $filter['sample_id'] || $filter['wholesale_id'])) {
            if ($filter['seller_order_list'])
            {
                $where .= " AND (SELECT og.ru_id FROM " . $GLOBALS['ecs']->table('order_goods') . ' as og' . " WHERE og.order_id = o.order_id LIMIT 1) > 0 ";
            }else{
                $where .= " AND (SELECT og.ru_id FROM " . $GLOBALS['ecs']->table('order_goods') . ' as og' . " WHERE og.order_id = o.order_id LIMIT 1) = 0 ";
            }
        }


        /* 如果管理员属于某个办事处，只列出这个办事处管辖的订单 */
        $sql = "SELECT agency_id FROM " . $GLOBALS['ecs']->table('admin_user') . " WHERE user_id = '$_SESSION[admin_id]' AND action_list <> 'all'";
        $agency_id = $GLOBALS['db']->getOne($sql);

        if ($agency_id > 0)
        {
            $where .= " AND o.agency_id = '$agency_id' ";
        }

        /* 分页大小 */
        $filter['page'] = empty($_REQUEST['page']) || (intval($_REQUEST['page']) <= 0) ? 1 : intval($_REQUEST['page']);
        if($page > 0){
            $filter['page'] = $page;
        }
        if (isset($_REQUEST['page_size']) && intval($_REQUEST['page_size']) > 0)
        {
            $filter['page_size'] = intval($_REQUEST['page_size']);
        }
        elseif (isset($_COOKIE['ECSCP']['page_size']) && intval($_COOKIE['ECSCP']['page_size']) > 0)
        {
            $filter['page_size'] = intval($_COOKIE['ECSCP']['page_size']);
        }
        else
        {
            $filter['page_size'] = 15;
        }

        $where_store = '';
        if (empty($filter['start_take_time']) || empty($filter['end_take_time'])) {
            if ($store_search == 0 && $adminru['ru_id'] == 0) {
                $where_store = " AND (SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('order_goods') . " AS og " . " WHERE o.order_id = og.order_id AND og.ru_id = 0 LIMIT 1) > 0 " .
                        " AND (SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('order_info') . " AS oi2 WHERE oi2.main_order_id = o.order_id) = 0";
            }
        }

        if(!empty($filter['start_take_time']) || !empty($filter['end_take_time']))
        {
            $where_action = '';
            if ($filter['start_take_time']) {
                $where_action .= " AND oa.log_time >= '$filter[start_take_time]'";
            }
            if ($filter['end_take_time']) {
                $where_action .= " AND oa.log_time <= '$filter[end_take_time]'";
            }

            $where_action .= order_take_query_sql('finished', "oa.");

            $where .= " AND (SELECT COUNT(*) FROM " .$GLOBALS['ecs']->table('order_action'). " AS oa WHERE o.order_id = oa.order_id $where_action) > 0";
        }

        $groupBy = "";
        /* 记录总数 */
        if(!empty($filter['keywords']))
        {

            $leftJoin .= " LEFT JOIN " . $GLOBALS['ecs']->table('order_goods') . " AS iog ON iog.order_id = o.order_id ";

            $sql = "SELECT o.order_id FROM " . $GLOBALS['ecs']->table('order_info') . " AS o " .
                    $leftJoin .
                    $where . $where_store . $no_main_order . " GROUP BY o.order_id";

            $record_count = count($GLOBALS['db']->getAll($sql));

            $groupBy = " GROUP BY o.order_id ";
        }
        else
        {
            $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('order_info') . " AS o " .
                    $leftJoin .
                    $where . $where_store . $no_main_order;

            $record_count = $GLOBALS['db']->getOne($sql);
        }

        $filter['record_count']   = $record_count;
        $filter['page_count']     = $filter['record_count'] > 0 ? ceil($filter['record_count'] / $filter['page_size']) : 1;

        /* 查询 */
        $sql = "SELECT ifnull(bai.is_stages,0) is_stages, o.order_id, o.main_order_id, o.order_sn, o.add_time, o.order_status, o.shipping_status, o.pay_status, o.order_amount, o.money_paid, o.is_delete," .
                "o.shipping_fee, o.insure_fee, o.pay_fee, o.surplus,o.tax, o.integral_money, o.bonus, o.discount, o.coupons," .
                "o.shipping_time, o.confirm_take_time, o.auto_delivery_time, o.consignee, o.address, o.email, o.tel, o.mobile, o.extension_code as o_extension_code, o.extension_id, o.is_zc_order, o.pay_id, " .
                "o.pay_name, o.referer, o.froms, o.user_id, o.chargeoff_status, o.confirm_take_time, o.shipping_id, o.shipping_name, " .
                "(" . order_amount_field('o.') . ") AS total_fee, (o.goods_amount - o.discount + o.tax + o.shipping_fee + o.insure_fee + o.pay_fee + o.pack_fee + o.card_fee) AS total_fee_order" .
                " FROM " . $GLOBALS['ecs']->table('order_info') . " AS o " .
                " LEFT JOIN " . $GLOBALS['ecs']->table('baitiao_log') . " AS bai ON o.order_id=bai.order_id " . //这里连上白条日志表,查询当前订单是否是白条订单 bylu;
                $leftJoin .
                $where . $where_store . $no_main_order . $groupBy .
                " ORDER BY $filter[sort_by] $filter[sort_order] " .
                " LIMIT " . ($filter['page'] - 1) * $filter['page_size'] . ",$filter[page_size]";


        foreach (array('order_sn', 'consignee', 'email', 'address', 'zipcode', 'tel', 'user_name') AS $val)
        {
            $filter[$val] = stripslashes($filter[$val]);
        }

        set_filter($filter, $sql);
    }
    else
    {
        $sql    = $result['sql'];
        $filter = $result['filter'];
    }


    $row = $GLOBALS['db']->getAll($sql);

    /* 格式话数据 */
    foreach ($row AS $key => $value)
    {
        /* 处理确认收货时间 start */
        if ($value['shipping_status'] == 2 && empty($value['confirm_take_time'])) {
            $sql = "SELECT MAX(log_time) AS log_time FROM " . $GLOBALS['ecs']->table('order_action') . " WHERE order_id = '" . $value['order_id'] . "' AND shipping_status = '" . SS_RECEIVED . "'";
            $confirm_take_time = $GLOBALS['db']->getOne($sql, true);

            $log_other = array(
                'confirm_take_time' => $confirm_take_time
            );

            $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('order_info'), $log_other, 'UPDATE', "order_id = '" . $value['order_id'] . "'");

            $value['confirm_take_time'] = $confirm_take_time;
        }
        /* 处理确认收货时间 end */

        /* 查询更新支付状态 start */
        if (($value['order_status'] == OS_UNCONFIRMED || $value['order_status'] == OS_CONFIRMED || $value['order_status'] == OS_SPLITED) && $value['pay_status'] == PS_UNPAYED) {

            $pay_log = get_pay_log($value['order_id'], 1);
            if ($pay_log && $pay_log['is_paid'] == 0) {
                $payment = payment_info($value['pay_id']);

                $file_pay = ROOT_PATH . 'includes/modules/payment/' . $payment['pay_code'] . '.php';
                if ($payment && file_exists($file_pay)) {
                    /* 调用相应的支付方式文件 */
                    include_once($file_pay);

                    /* 取得在线支付方式的支付按钮 */
                    if (class_exists($payment['pay_code'])) {

                        $pay_obj = new $payment['pay_code'];
                        $is_callable = array($pay_obj, 'query');

                        /* 判断类对象方法是否存在 */
                        if (is_callable($is_callable)) {

                            $order_other = array(
                                'order_sn' => $value['order_sn'],
                                'log_id' => $pay_log['log_id']
                            );

                            $pay_obj->query($order_other);

                            $sql = "SELECT order_status, shipping_status, pay_status, pay_time FROM " . $GLOBALS['ecs']->table('order_info') . " WHERE order_id = '" . $value['order_id'] . "' LIMIT 1";
                            $order_info = $GLOBALS['db']->getRow($sql);
                            if ($order_info) {
                                $value['order_status'] = $order_info['order_status'];
                                $value['shipping_status'] = $order_info['shipping_status'];
                                $value['pay_status'] = $order_info['pay_status'];
                                $value['pay_time'] = $order_info['pay_time'];
                            }
                        }
                    }
                }
            }
        }
        /* 查询更新支付状态 end */

        /* 增加账单订单信息 start */
        if (($value['order_status'] == OS_CONFIRMED || $value['order_status'] == OS_SPLITED) && $value['pay_status'] == PS_PAYED && $value['shipping_status'] == SS_RECEIVED) {

            $bill_info = array(
                'order_id' => $value['order_id']
            );
            $bill_order_info = get_bill_order($bill_info);

            if (!$bill_order_info) {

                /* 查询订单信息，检查状态 */
                $sql = "SELECT order_id, user_id, order_sn , order_status, shipping_status, pay_status, " .
                        "order_amount, goods_amount, tax, shipping_fee, insure_fee, pay_fee, pack_fee, card_fee, " .
                        "bonus, integral_money, coupons, discount, money_paid, surplus, confirm_take_time " .
                        "FROM " . $GLOBALS['ecs']->table('order_info') . " WHERE order_id = '" . $value['order_id'] . "'";

                $bill_order = $GLOBALS['db']->GetRow($sql);

                $seller_id = $GLOBALS['db']->getOne("SELECT ru_id FROM " . $GLOBALS['ecs']->table('order_goods') . " WHERE order_id = '" . $value['order_id'] . "'", true);
                $value_card = $GLOBALS['db']->getOne("SELECT use_val FROM " . $GLOBALS['ecs']->table('value_card_record') . " WHERE order_id = '" . $value['order_id'] . "'", true);

                $return_amount = get_order_return_amount($value['order_id']);

                $other = array(
                    'user_id' => $bill_order['user_id'],
                    'seller_id' => $seller_id,
                    'order_id' => $bill_order['order_id'],
                    'order_sn' => $bill_order['order_sn'],
                    'order_status' => $bill_order['order_status'],
                    'shipping_status' => SS_RECEIVED,
                    'pay_status' => $bill_order['pay_status'],
                    'order_amount' => $bill_order['order_amount'],
                    'return_amount' => $return_amount,
                    'goods_amount' => $bill_order['goods_amount'],
                    'tax' => $bill_order['tax'],
                    'shipping_fee' => $bill_order['shipping_fee'],
                    'insure_fee' => $bill_order['insure_fee'],
                    'pay_fee' => $bill_order['pay_fee'],
                    'pack_fee' => $bill_order['pack_fee'],
                    'card_fee' => $bill_order['card_fee'],
                    'bonus' => $bill_order['bonus'],
                    'integral_money' => $bill_order['integral_money'],
                    'coupons' => $bill_order['coupons'],
                    'discount' => $bill_order['discount'],
                    'value_card' => $value_card,
                    'money_paid' => $bill_order['money_paid'],
                    'surplus' => $bill_order['surplus'],
                    'confirm_take_time' => $value['confirm_take_time']
                );

                if ($seller_id) {
                    $insert_id = get_order_bill_log($other);

                    /* by zxk jisuan*/
                    //确认收货 商家结算到可用金额
                    order_confirm_change($bill_order['order_id'], $insert_id);
                    /* by zxk jisuan*/
                }
            }
        }
        /* 增加账单订单信息 end */


        /* by zxk jiesuan */
        //删除生成账单
//        //账单编号
//        if ($value['chargeoff_status'] == 1 || $value['chargeoff_status'] == 2) {
//            //by zxk 账单添加退款单限制
//            $bill = $GLOBALS['db']->getRow(" SELECT scb.id, scb.bill_sn, scb.seller_id, scb.proportion, sbo.commission_model FROM " . $GLOBALS['ecs']->table('seller_bill_order') . " AS sbo " .
//                    "LEFT JOIN " . $GLOBALS['ecs']->table('seller_commission_bill') . " AS scb ON sbo.bill_id = scb.id " .
//                    "WHERE sbo.order_id = '" . $value['order_id'] . "'  AND sbo.return_rec_id = 0");
//
//            $row[$key]['bill_id'] = $bill['id'];
//            $row[$key]['bill_sn'] = $bill['bill_sn'];
//            $row[$key]['seller_id'] = $bill['seller_id'];
//            $row[$key]['proportion'] = $bill['proportion'];
//            $row[$key]['commission_model'] = $bill['commission_model'];
//        }
        /* by zxk jiesuan */

        //查商家ID
        $sql = "SELECT ru_id, extension_code AS iog_extension_code FROM " . $GLOBALS['ecs']->table('order_goods') . " WHERE order_id = '" . $value['order_id'] . "' ";
        $order_goods = $GLOBALS['db']->getAll($sql);

        $value['ru_id'] = reset($order_goods)['ru_id'];
        if(count($order_goods) > 1){
            $iog_extension_codes = array_column($order_goods, 'iog_extension_code');
            $row[$key]['iog_extension_codes'] = array_unique($iog_extension_codes);
        }else{
            $row[$key]['iog_extension_code'] = reset($order_goods)['iog_extension_code'];
        }

        //自营商家查询
        $value['self_run'] = $GLOBALS['db']->getOne(" SELECT self_run FROM " . $GLOBALS['ecs']->table('merchants_shop_information') . " WHERE user_id = '" . $value['ru_id'] . "'", true);
        //查会员名称
        $sql = " SELECT user_name FROM ".$GLOBALS['ecs']->table('users')." WHERE user_id = '".$value['user_id']."'";
        $value['buyer'] = $GLOBALS['db']->getOne($sql, true);
        $row[$key]['buyer'] = !empty($value['buyer']) ? $value['buyer'] : $GLOBALS['_LANG']['anonymous'];

        //判断是否为门店订单 by wu
        $is_store_order = $GLOBALS['db']->getOne(" SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('store_order') . " WHERE order_id = '" . $value['order_id'] . "' ", true);
        $row[$key]['is_store_order'] = empty($is_store_order) ? 0 : 1;

        //判断是否为退换货订单 start
        $sql = "SELECT ret_id FROM " . $GLOBALS['ecs']->table('order_return') . " WHERE order_id =" . $value['order_id'];
        $row[$key]['is_order_return'] = $GLOBALS['db']->getOne($sql);
        //判断是否为退换货订单 end

        if (file_exists(MOBILE_DRP)) {
            $is_drp_order = $GLOBALS['db']->getOne(" SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('order_goods') . " WHERE order_id = '" . $value['order_id'] . "' AND is_distribution = 1 AND drp_money > 0 ", true);
            $row[$key]['is_drp_order'] = empty($is_drp_order) ? 0 : 1;
        } else {
            $row[$key]['is_drp_order'] = 0;
        }

        $row[$key]['formated_order_amount'] = price_format($value['order_amount']);
        $row[$key]['formated_money_paid'] = price_format($value['money_paid']);
        $row[$key]['formated_total_fee'] = price_format($value['total_fee']);
        $row[$key]['short_order_time'] = local_date($GLOBALS['_CFG']['time_format'], $value['add_time']);
        $row[$key]['confirm_take_time'] = local_date($GLOBALS['_CFG']['time_format'], $value['confirm_take_time']);

        $row[$key]['formated_total_fee_order'] = price_format($value['total_fee_order']);

        /* 取得区域名 */
        $row[$key]['region'] = get_user_region_address($value['order_id']);

        //ecmoban模板堂 --zhuo start
        $row[$key]['user_name'] = get_shop_name($value['ru_id'], 1);
		$row[$key]['self_run'] = $value['self_run'];

        $order_id = $value['order_id'];
        $date = array('order_id');

        $order_child = count(get_table_date('order_info', "main_order_id='$order_id'", $date, 1));
        $row[$key]['order_child'] = $order_child;

        $date = array('order_sn');
        $child_list = get_table_date('order_info', "main_order_id='$order_id'", $date, 1);
        $row[$key]['child_list'] = $child_list;


        //by zxk 获取订单商品列表
        $order = array(
            'order_id' => $value['order_id'],
            'order_sn' => $value['order_sn']
        );

        $goods = get_order_goods($order);
        $row[$key]['goods_list'] = $goods['goods_list'];

        if(!empty($child_list)){
            $row[$key]['shop_name'] = $GLOBALS["_LANG"]['to_order_sn2'];
        }else{
            $row[$key]['shop_name'] = $row[$key]['user_name'];
        }

        //ecmoban模板堂 --zhuo end
        if ($value['order_status'] == OS_INVALID || $value['order_status'] == OS_CANCELED)
        {
            /* 如果该订单为无效或取消则显示删除链接 */
            $row[$key]['can_remove'] = 1;
        }
        else
        {
            $row[$key]['can_remove'] = 0;
        }
    }

    $arr = array('orders' => $row, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);

    return $arr;
}

/**
 * 取得供货商列表
 * @return array    二维数组
 */
function get_suppliers_list()
{
    $sql = 'SELECT *
            FROM ' . $GLOBALS['ecs']->table('suppliers') . '
            WHERE is_check = 1
            ORDER BY suppliers_name ASC';
    $res = $GLOBALS['db']->getAll($sql);

    if (!is_array($res))
    {
        $res = array();
    }

    return $res;
}

/**
 * 取得订单商品
 * @param   array     $order  订单数组
 * @return array
 */
function get_order_goods($order)
{
    $goods_list = array();
    $goods_attr = array();

    $sql = "SELECT o.*, g.model_inventory, g.model_attr AS model_attr, g.suppliers_id AS suppliers_id, g.goods_number AS storage, g.goods_thumb, o.goods_attr, IFNULL(b.brand_name, '') AS brand_name, p.product_sn, g.bar_code  " .
            "FROM " . $GLOBALS['ecs']->table('order_goods') . " AS o ".
            "LEFT JOIN " . $GLOBALS['ecs']->table('products') . " AS p ON o.product_id = p.product_id " .
            "LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON o.goods_id = g.goods_id " .
            "LEFT JOIN " . $GLOBALS['ecs']->table('brand') . " AS b ON g.brand_id = b.brand_id " .
            "WHERE o.order_id = '$order[order_id]' ";

    $res = $GLOBALS['db']->query($sql);

    while ($row = $GLOBALS['db']->fetchRow($res))
    {
        // 虚拟商品支持
        if ($row['is_real'] == 0)
        {
            /* 取得语言项 */
            $filename = ROOT_PATH . 'plugins/' . $row['extension_code'] . '/languages/common_' . $GLOBALS['_CFG']['lang'] . '.php';
            if (file_exists($filename))
            {
                include_once($filename);
                if (!empty($GLOBALS['_LANG'][$row['extension_code'].'_link']))
                {
                    $row['goods_name'] = $row['goods_name'] . sprintf($GLOBALS['_LANG'][$row['extension_code'].'_link'], $row['goods_id'], $order['order_sn']);
                }
            }
        }

        //ecmoban模板堂 --zhuo start
        if($row['product_id'] > 0){
            $products = get_warehouse_id_attr_number($row['goods_id'], $row['goods_attr_id'], $row['ru_id'], $row['warehouse_id'], $row['area_id'], $row['model_attr']);
            $row['storage'] = $products['product_number'];
        }else{
            if($row['model_inventory'] == 1){
                $row['storage'] = get_warehouse_area_goods($row['warehouse_id'], $row['goods_id'], 'warehouse_goods');
            }elseif($row['model_inventory'] == 2){
                $row['storage'] = get_warehouse_area_goods($row['area_id'], $row['goods_id'], 'warehouse_area_goods');
            }
        }
        //ecmoban模板堂 --zhuo end

        //图片显示
        $row['goods_thumb'] = get_image_path($row['goods_id'], $row['goods_thumb'], true);

        $row['formated_subtotal']       = price_format($row['goods_price'] * $row['goods_number']);
        $row['formated_goods_price']    = price_format($row['goods_price']);

        $goods_attr[] = explode(' ', trim($row['goods_attr'])); //将商品属性拆分为一个数组

        if ($row['extension_code'] == 'package_buy')
        {
            $row['storage'] = '';
            $row['brand_name'] = '';
            $row['package_goods_list'] = get_package_goods_list($row['goods_id']);

            $activity = get_goods_activity_info($row['goods_id'], array('act_id', 'activity_thumb'));
            if($activity){
                if((strpos($activity['activity_thumb'], 'http://') === false && strpos($activity['activity_thumb'], 'https://') === false)){
                    $row['goods_thumb'] = "../" .$activity['activity_thumb'];
                }else{
                    $row['goods_thumb'] = $activity['activity_thumb'];
                }
            }
        }

        //处理货品id
        $row['product_id'] = empty($row['product_id']) ? 0 : $row['product_id'];

        $goods_list[] = $row;
    }

    $attr = array();
    $arr  = array();
    foreach ($goods_attr AS $index => $array_val)
    {
        foreach ($array_val AS $value)
        {
            $arr = explode(':', $value);//以 : 号将属性拆开
            $attr[$index][] =  @array('name' => $arr[0], 'value' => $arr[1]);
        }
    }

    return array('goods_list' => $goods_list, 'attr' => $attr);
}

/**
 * 取得礼包列表
 * @param   integer     $package_id  订单商品表礼包类商品id
 * @return array
 */
function get_package_goods_list($package_id)
{
    $sql = "SELECT pg.goods_id, g.goods_name, (CASE WHEN pg.product_id > 0 THEN p.product_number ELSE g.goods_number END) AS goods_number, p.goods_attr, p.product_id, pg.goods_number AS
            order_goods_number, g.goods_sn, g.is_real, p.product_sn
            FROM " . $GLOBALS['ecs']->table('package_goods') . " AS pg
                LEFT JOIN " .$GLOBALS['ecs']->table('goods') . " AS g ON pg.goods_id = g.goods_id
                LEFT JOIN " . $GLOBALS['ecs']->table('products') . " AS p ON pg.product_id = p.product_id
            WHERE pg.package_id = '$package_id'";
    $resource = $GLOBALS['db']->query($sql);
    if (!$resource)
    {
        return array();
    }

    $row = array();

    /* 生成结果数组 取存在货品的商品id 组合商品id与货品id */
    $good_product_str = '';
    while ($_row = $GLOBALS['db']->fetch_array($resource))
    {
        if ($_row['product_id'] > 0)
        {
            /* 取存商品id */
            $good_product_str .= ',' . $_row['goods_id'];

            /* 组合商品id与货品id */
            $_row['g_p'] = $_row['goods_id'] . '_' . $_row['product_id'];
        }
        else
        {
            /* 组合商品id与货品id */
            $_row['g_p'] = $_row['goods_id'];
        }

        //生成结果数组
        $row[] = $_row;
    }
    $good_product_str = trim($good_product_str, ',');

    /* 释放空间 */
    unset($resource, $_row, $sql);

    /* 取商品属性 */
    if ($good_product_str != '')
    {
        $sql = "SELECT ga.goods_attr_id, ga.attr_value, ga.attr_price, a.attr_name
                FROM " .$GLOBALS['ecs']->table('goods_attr'). " AS ga, " .$GLOBALS['ecs']->table('attribute'). " AS a
                WHERE a.attr_id = ga.attr_id
                AND a.attr_type = 1
                AND goods_id IN ($good_product_str) ORDER BY a.sort_order, a.attr_id, ga.goods_attr_id";
        $result_goods_attr = $GLOBALS['db']->getAll($sql);

        $_goods_attr = array();
        foreach ($result_goods_attr as $value)
        {
            $_goods_attr[$value['goods_attr_id']] = $value;
        }
    }

    /* 过滤货品 */
    $format[0] = "%s:%s[%d] <br>";
    $format[1] = "%s--[%d]";
    foreach ($row as $key => $value)
    {
        if ($value['goods_attr'] != '')
        {
            $goods_attr_array = explode('|', $value['goods_attr']);

            $goods_attr = array();
            foreach ($goods_attr_array as $_attr)
            {
                $goods_attr[] = sprintf($format[0], $_goods_attr[$_attr]['attr_name'], $_goods_attr[$_attr]['attr_value'], $_goods_attr[$_attr]['attr_price']);
            }

            $row[$key]['goods_attr_str'] = implode('', $goods_attr);
        }

        $row[$key]['goods_name'] = sprintf($format[1], $value['goods_name'], $value['order_goods_number']);
    }

    return $row;
}

/**
 * 订单单个商品或货品的已发货数量
 *
 * @param   int     $order_id       订单 id
 * @param   int     $goods_id       商品 id
 * @param   int     $product_id     货品 id
 *
 * @return  int
 */
function order_delivery_num($order_id, $goods_id, $product_id = 0)
{
    $sql = 'SELECT SUM(G.send_number) AS sums
            FROM ' . $GLOBALS['ecs']->table('delivery_goods') . ' AS G, ' . $GLOBALS['ecs']->table('delivery_order') . ' AS O
            WHERE O.delivery_id = G.delivery_id
            AND O.status = 0
            AND O.order_id = ' . $order_id . '
            AND G.extension_code <> "package_buy"
            AND G.goods_id = ' . $goods_id;

    $sql .= ($product_id > 0) ? " AND G.product_id = '$product_id'" : '';

    $sum = $GLOBALS['db']->getOne($sql);

    if (empty($sum))
    {
        $sum = 0;
    }

    return $sum;
}

/**
 * 判断订单是否已发货（含部分发货）
 * @param   int     $order_id  订单 id
 * @return  int     1，已发货；0，未发货
 */
function order_deliveryed($order_id)
{
    $return_res = 0;

    if (empty($order_id))
    {
        return $return_res;
    }

    $sql = 'SELECT COUNT(delivery_id)
            FROM ' . $GLOBALS['ecs']->table('delivery_order') . '
            WHERE order_id = \''. $order_id . '\'
            AND status = 0';
    $sum = $GLOBALS['db']->getOne($sql);

    if ($sum)
    {
        $return_res = 1;
    }

    return $return_res;
}

/**
 * 更新订单商品信息
 * @param   int     $order_id       订单 id
 * @param   array   $_sended        Array(‘商品id’ => ‘此单发货数量’)
 * @param   array   $goods_list
 * @return  Bool
 */
function update_order_goods($order_id, $_sended, $goods_list = array())
{
    if (!is_array($_sended) || empty($order_id))
    {
        return false;
    }

    foreach ($_sended as $key => $value)
    {
        // 超值礼包
        if (is_array($value))
        {
            if (!is_array($goods_list))
            {
                $goods_list = array();
            }

            foreach ($goods_list as $goods)
            {
                if (($key != $goods['rec_id']) || (!isset($goods['package_goods_list']) || !is_array($goods['package_goods_list'])))
                {
                    continue;
                }

                $goods['package_goods_list'] = package_goods($goods['package_goods_list'], $goods['goods_number'], $goods['order_id'], $goods['extension_code'], $goods['goods_id']);
                $pg_is_end = true;

                foreach ($goods['package_goods_list'] as $pg_key => $pg_value)
                {
                    if ($pg_value['order_send_number'] != $pg_value['sended'])
                    {
                        $pg_is_end = false; // 此超值礼包，此商品未全部发货

                        break;
                    }
                }

                // 超值礼包商品全部发货后更新订单商品库存
                if ($pg_is_end)
                {
                    $sql = "UPDATE " . $GLOBALS['ecs']->table('order_goods') . "
                            SET send_number = goods_number
                            WHERE order_id = '$order_id'
                            AND goods_id = '" . $goods['goods_id'] . "' ";

                    $GLOBALS['db']->query($sql, 'SILENT');
                }
            }
        }
        // 商品（实货）（货品）
        elseif (!is_array($value))
        {
            /* 检查是否为商品（实货）（货品） */
            foreach ($goods_list as $goods)
            {
                if ($goods['rec_id'] == $key && $goods['is_real'] == 1)
                {
                    $sql = "UPDATE " . $GLOBALS['ecs']->table('order_goods') . "
                            SET send_number = send_number + $value
                            WHERE order_id = '$order_id'
                            AND rec_id = '$key' ";
                    $GLOBALS['db']->query($sql, 'SILENT');
                    break;
                }
            }
        }
    }

    return true;
}

/**
 * 更新订单虚拟商品信息
 * @param   int     $order_id       订单 id
 * @param   array   $_sended        Array(‘商品id’ => ‘此单发货数量’)
 * @param   array   $virtual_goods  虚拟商品列表
 * @return  Bool
 */
function update_order_virtual_goods($order_id, $_sended, $virtual_goods)
{
    if (!is_array($_sended) || empty($order_id))
    {
        return false;
    }
    if (empty($virtual_goods))
    {
        return true;
    }
    elseif (!is_array($virtual_goods))
    {
        return false;
    }

    foreach ($virtual_goods as $goods)
    {
        $sql = "UPDATE ".$GLOBALS['ecs']->table('order_goods'). "
                SET send_number = send_number + '" . $goods['num'] . "'
                WHERE order_id = '" . $order_id . "'
                AND goods_id = '" . $goods['goods_id'] . "' ";
        if (!$GLOBALS['db']->query($sql, 'SILENT'))
        {
            return false;
        }
    }

    return true;
}

/**
 * 订单中的商品是否已经全部发货
 * @param   int     $order_id  订单 id
 * @return  int     1，全部发货；0，未全部发货
 */
function get_order_finish($order_id)
{
    $return_res = 0;

    if (empty($order_id))
    {
        return $return_res;
    }

    $sql = 'SELECT COUNT(rec_id)
            FROM ' . $GLOBALS['ecs']->table('order_goods') . '
            WHERE order_id = \'' . $order_id . '\'
            AND goods_number > send_number';

    $sum = $GLOBALS['db']->getOne($sql);
    if (empty($sum))
    {
        $return_res = 1;
    }
    return $return_res;
}

/**
 * 判断订单的发货单是否全部发货
 * @param   int     $order_id  订单 id
 * @return  int     1，全部发货；0，未全部发货；-1，部分发货；-2，完全没发货；
 */
function get_all_delivery_finish($order_id)
{
    $return_res = 0;

    if (empty($order_id))
    {
        return $return_res;
    }

    /* 未全部分单 */
    if (!get_order_finish($order_id))
    {
        return $return_res;
    }
    /* 已全部分单 */
    else
    {
        // 是否全部发货
        $sql = "SELECT COUNT(delivery_id)
                FROM " . $GLOBALS['ecs']->table('delivery_order') . "
                WHERE order_id = '$order_id'
                AND status = 2 ";
        $sum = $GLOBALS['db']->getOne($sql);
        // 全部发货
        if (empty($sum))
        {
            $return_res = 1;
        }
        // 未全部发货
        else
        {
            /* 订单全部发货中时：当前发货单总数 */
            $sql = "SELECT COUNT(delivery_id)
            FROM " . $GLOBALS['ecs']->table('delivery_order') . "
            WHERE order_id = '$order_id'
            AND status <> 1 ";
            $_sum = $GLOBALS['db']->getOne($sql);
            if ($_sum == $sum)
            {
                $return_res = -2; // 完全没发货
            }
            else
            {
                $return_res = -1; // 部分发货
            }
        }
    }
    return $return_res;
}

function trim_array_walk(&$array_value)
{
    if (is_array($array_value))
    {
        array_walk($array_value, 'trim_array_walk');
    }else{
        $array_value = trim($array_value);
    }
}

function intval_array_walk(&$array_value)
{
    if (is_array($array_value))
    {
        array_walk($array_value, 'intval_array_walk');
    }else{
        $array_value = intval($array_value);
    }
}

/**
 * 删除发货单(不包括已退货的单子)
 * @param   int     $order_id  订单 id
 * @return  int     1，成功；0，失败
 */
function del_order_delivery($order_id)
{
    $return_res = 0;

    if (empty($order_id))
    {
        return $return_res;
    }

    $sql = 'DELETE O, G
            FROM ' . $GLOBALS['ecs']->table('delivery_order') . ' AS O, ' . $GLOBALS['ecs']->table('delivery_goods') . ' AS G
            WHERE O.order_id = \'' . $order_id . '\'
            AND O.status = 0
            AND O.delivery_id = G.delivery_id';
    $query = $GLOBALS['db']->query($sql, 'SILENT');

    if ($query)
    {
        $return_res = 1;
    }

    return $return_res;
}

/**
 * 删除订单所有相关单子
 * @param   int     $order_id      订单 id
 * @param   int     $action_array  操作列表 Array('delivery', 'back', ......)
 * @return  int     1，成功；0，失败
 */
function del_delivery($order_id, $action_array)
{
    $return_res = 0;

    if (empty($order_id) || empty($action_array))
    {
        return $return_res;
    }

    $query_delivery = 1;
    $query_back = 1;
    if (in_array('delivery', $action_array))
    {
        $sql = 'DELETE O, G
                FROM ' . $GLOBALS['ecs']->table('delivery_order') . ' AS O, ' . $GLOBALS['ecs']->table('delivery_goods') . ' AS G
                WHERE O.order_id = \'' . $order_id . '\'
                AND O.delivery_id = G.delivery_id';
        $query_delivery = $GLOBALS['db']->query($sql, 'SILENT');
    }
    if (in_array('back', $action_array))
    {
        $sql = 'DELETE O, G
                FROM ' . $GLOBALS['ecs']->table('back_order') . ' AS O, ' . $GLOBALS['ecs']->table('back_goods') . ' AS G
                WHERE O.order_id = \'' . $order_id . '\'
                AND O.back_id = G.back_id';
        $query_back = $GLOBALS['db']->query($sql, 'SILENT');
    }

    if ($query_delivery && $query_back)
    {
        $return_res = 1;
    }

    return $return_res;
}

/**
 *  获取发货单列表信息
 *
 * @access  public
 * @param
 *
 * @return void
 */
function delivery_list()
{
    $where = 'WHERE 1 ';
    //ecmoban模板堂 --zhuo start
    $adminru = get_admin_ru_id();
    if($adminru['ru_id'] > 0){
            $where .= " AND (SELECT og.ru_id FROM " . $GLOBALS['ecs']->table('order_goods') .' as og' . " WHERE og.order_id = do.order_id LIMIT 1) = '" .$adminru['ru_id']. "' ";
    }
    //ecmoban模板堂 --zhuo end

    $result = get_filter();
    if ($result === false)
    {
        $aiax = isset($_GET['is_ajax']) ? $_GET['is_ajax'] : 0;

        /* 过滤信息 */
        $filter['delivery_sn'] = empty($_REQUEST['delivery_sn']) ? '' : trim($_REQUEST['delivery_sn']);
        $filter['order_sn'] = empty($_REQUEST['order_sn']) ? '' : trim($_REQUEST['order_sn']);
        $filter['order_id'] = empty($_REQUEST['order_id']) ? 0 : intval($_REQUEST['order_id']);
		$filter['goods_id'] = empty($_REQUEST['goods_id']) ? 0 : intval($_REQUEST['goods_id']);
        if ($aiax == 1 && !empty($_REQUEST['consignee']))
        {
            $_REQUEST['consignee'] = json_str_iconv($_REQUEST['consignee']);
        }

		$filter['seller_order_list'] = isset($_REQUEST['seller_order_list']) && !empty($_REQUEST['seller_order_list']) ? 1 : 0;  //商家和自营订单标识
        $filter['consignee'] = empty($_REQUEST['consignee']) ? '' : trim($_REQUEST['consignee']);
        $filter['status'] = isset($_REQUEST['status']) ? $_REQUEST['status'] : -1;

        $filter['sort_by'] = empty($_REQUEST['sort_by']) ? 'do.update_time' : trim($_REQUEST['sort_by']);
        $filter['sort_order'] = empty($_REQUEST['sort_order']) ? 'DESC' : trim($_REQUEST['sort_order']);

        if ($filter['order_sn'])
        {
            $where .= " AND do.order_sn LIKE '%" . mysql_like_quote($filter['order_sn']) . "%'";
        }
        if ($filter['goods_id'])
        {
            $where .= " AND (SELECT dg.goods_id FROM ".$GLOBALS['ecs']->table('delivery_goods')." AS dg WHERE dg.delivery_id = do.delivery_id LIMIT 1) = '" .$filter['goods_id']. "' ";
        }
        if ($filter['consignee'])
        {
            $where .= " AND do.consignee LIKE '%" . mysql_like_quote($filter['consignee']) . "%'";
        }
        if ($filter['status'] >= 0)
        {
            $where .= " AND do.status = '" . mysql_like_quote($filter['status']) . "'";
        }
        if ($filter['delivery_sn'])
        {
            $where .= " AND do.delivery_sn LIKE '%" . mysql_like_quote($filter['delivery_sn']) . "%'";
        }

        /* 获取管理员信息 */
        $admin_info = admin_info();

        /* 如果管理员属于某个办事处，只列出这个办事处管辖的发货单 */
        if ($admin_info['agency_id'] > 0)
        {
            $where .= " AND do.agency_id = '" . $admin_info['agency_id'] . "' ";
        }

        /* 如果管理员属于某个供货商，只列出这个供货商的发货单 */
        if ($admin_info['suppliers_id'] > 0)
        {
            $where .= " AND do.suppliers_id = '" . $admin_info['suppliers_id'] . "' ";
        }

        /* 分页大小 */
        $filter['page'] = empty($_REQUEST['page']) || (intval($_REQUEST['page']) <= 0) ? 1 : intval($_REQUEST['page']);

        if (isset($_REQUEST['page_size']) && intval($_REQUEST['page_size']) > 0)
        {
            $filter['page_size'] = intval($_REQUEST['page_size']);
        }
        elseif (isset($_COOKIE['ECSCP']['page_size']) && intval($_COOKIE['ECSCP']['page_size']) > 0)
        {
            $filter['page_size'] = intval($_COOKIE['ECSCP']['page_size']);
        }
        else
        {
            $filter['page_size'] = 15;
        }

        if ($filter['seller_order_list'])
        {
            $where .= " AND (SELECT og.ru_id FROM " . $GLOBALS['ecs']->table('order_goods') .' as og' . " WHERE og.order_id = do.order_id LIMIT 1) > 0 ";
        }else{
            $where .= " AND (SELECT og.ru_id FROM " . $GLOBALS['ecs']->table('order_goods') .' as og' . " WHERE og.order_id = do.order_id LIMIT 1) = 0 ";
        }

        /* 记录总数 */
        $sql = "SELECT COUNT(DISTINCT delivery_id) FROM " . $GLOBALS['ecs']->table('delivery_order') . " as do " . $where;
        $filter['record_count']   = $GLOBALS['db']->getOne($sql);
        $filter['page_count']     = $filter['record_count'] > 0 ? ceil($filter['record_count'] / $filter['page_size']) : 1;

        /* 查询 */
        $sql = "SELECT DISTINCT do.delivery_id, do.delivery_sn, do.order_sn, do.order_id, do.add_time, do.action_user, do.consignee, do.country,
                       do.province, do.city, do.district, do.tel, do.status, do.update_time, do.email, do.suppliers_id
                FROM " . $GLOBALS['ecs']->table("delivery_order") . " as do " .
                $where .
                " ORDER BY " . $filter['sort_by'] . " " . $filter['sort_order']. "
                LIMIT " . ($filter['page'] - 1) * $filter['page_size'] . ", " . $filter['page_size'] . " ";

        set_filter($filter, $sql);
    }
    else
    {
        $sql    = $result['sql'];

        $filter = $result['filter'];
    }

    /* 获取供货商列表 */
    $suppliers_list = get_suppliers_list();
    $_suppliers_list = array();
    foreach ($suppliers_list as $value)
    {
        $_suppliers_list[$value['suppliers_id']] = $value['suppliers_name'];
    }

    $row = $GLOBALS['db']->getAll($sql);

    /* 格式化数据 */
    foreach ($row AS $key => $value)
    {
        $row[$key]['add_time'] = local_date($GLOBALS['_CFG']['time_format'], $value['add_time']);
        $row[$key]['update_time'] = local_date($GLOBALS['_CFG']['time_format'], $value['update_time']);
        if ($value['status'] == 1)
        {
            $row[$key]['status_name'] = $GLOBALS['_LANG']['delivery_status'][1];
        }
        elseif ($value['status'] == 2)
        {
            $row[$key]['status_name'] = $GLOBALS['_LANG']['delivery_status'][2];
        }
        else
        {
        $row[$key]['status_name'] = $GLOBALS['_LANG']['delivery_status'][0];
        }
        $row[$key]['suppliers_name'] = isset($_suppliers_list[$value['suppliers_id']]) ? $_suppliers_list[$value['suppliers_id']] : '';

        $_goods_thumb = get_image_path($row[$key]['goods_id'], $row[$key]['goods_thumb'], true);
        $row[$key]['goods_thumb'] = $_goods_thumb;

        $sql = "SELECT ru_id FROM " .$GLOBALS['ecs']->table('order_goods'). " WHERE order_id = '" .$value['order_id']. "' LIMIT 0,1";
        $ru_id = $GLOBALS['db']->getOne($sql);
        $row[$key]['ru_name'] = get_shop_name($ru_id, 1); //ecmoban模板堂 --zhuo
    }

    $arr = array('delivery' => $row, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);

    return $arr;
}

/**
 *  获取退货单列表信息
 *
 * @access  public
 * @param
 *
 * @return void
 */
function back_list()
{
    $where = 'WHERE 1 ';
    //ecmoban模板堂 --zhuo start
    $adminru = get_admin_ru_id();
    if($adminru['ru_id'] > 0){
        $where .= " AND (SELECT og.ru_id FROM " . $GLOBALS['ecs']->table('order_goods') .' as og' . " WHERE og.order_id = bo.order_id LIMIT 1) = '" .$adminru['ru_id']. "' ";
    }
    //ecmoban模板堂 --zhuo end

	//取消获取cookie信息 by wu
    //$result = get_filter();
	$result = false;

    if ($result === false)
    {
        $aiax = isset($_GET['is_ajax']) ? $_GET['is_ajax'] : 0;

        /* 过滤信息 */
        $filter['delivery_sn'] = empty($_REQUEST['delivery_sn']) ? '' : trim($_REQUEST['delivery_sn']);
        $filter['order_sn'] = empty($_REQUEST['order_sn']) ? '' : trim($_REQUEST['order_sn']);
        $filter['order_id'] = empty($_REQUEST['order_id']) ? 0 : intval($_REQUEST['order_id']);
        if ($aiax == 1 && !empty($_REQUEST['consignee']))
        {
            $_REQUEST['consignee'] = json_str_iconv($_REQUEST['consignee']);
        }

		$filter['seller_order_list'] = isset($_REQUEST['seller_order_list']) && !empty($_REQUEST['seller_order_list']) ? 1 : 0;  //商家和自营订单标识
        $filter['consignee'] = empty($_REQUEST['consignee']) ? '' : trim($_REQUEST['consignee']);

        $filter['sort_by'] = empty($_REQUEST['sort_by']) ? 'bo.update_time' : trim($_REQUEST['sort_by']);
        $filter['sort_order'] = empty($_REQUEST['sort_order']) ? 'DESC' : trim($_REQUEST['sort_order']);

        if ($filter['order_sn'])
        {
            $where .= " AND bo.order_sn LIKE '%" . mysql_like_quote($filter['order_sn']) . "%'";
        }
        if ($filter['consignee'])
        {
            $where .= " AND bo.consignee LIKE '%" . mysql_like_quote($filter['consignee']) . "%'";
        }
        if ($filter['delivery_sn'])
        {
            $where .= " AND bo.delivery_sn LIKE '%" . mysql_like_quote($filter['delivery_sn']) . "%'";
        }

        /* 获取管理员信息 */
        $admin_info = admin_info();

        /* 如果管理员属于某个办事处，只列出这个办事处管辖的发货单 */
        if ($admin_info['agency_id'] > 0)
        {
            $where .= " AND bo.agency_id = '" . $admin_info['agency_id'] . "' ";
        }

        /* 如果管理员属于某个供货商，只列出这个供货商的发货单 */
        if ($admin_info['suppliers_id'] > 0)
        {
            $where .= " AND bo.suppliers_id = '" . $admin_info['suppliers_id'] . "' ";
        }

        /* 分页大小 */
        $filter['page'] = empty($_REQUEST['page']) || (intval($_REQUEST['page']) <= 0) ? 1 : intval($_REQUEST['page']);

        if (isset($_REQUEST['page_size']) && intval($_REQUEST['page_size']) > 0)
        {
            $filter['page_size'] = intval($_REQUEST['page_size']);
        }
        elseif (isset($_COOKIE['ECSCP']['page_size']) && intval($_COOKIE['ECSCP']['page_size']) > 0)
        {
            $filter['page_size'] = intval($_COOKIE['ECSCP']['page_size']);
        }
        else
        {
            $filter['page_size'] = 15;
        }

        if ($filter['seller_order_list'])
        {
            $where .= " AND (SELECT og.ru_id FROM " . $GLOBALS['ecs']->table('order_goods') .' as og' . " WHERE og.order_id = bo.order_id LIMIT 1) > 0 ";
        }else{
            $where .= " AND (SELECT og.ru_id FROM " . $GLOBALS['ecs']->table('order_goods') .' as og' . " WHERE og.order_id = bo.order_id LIMIT 1) = 0 ";
        }

        /* 记录总数 */
        $sql = "SELECT COUNT(DISTINCT back_id) FROM " . $GLOBALS['ecs']->table('back_order') ." as bo " . $where;
        $filter['record_count']   = $GLOBALS['db']->getOne($sql);
        $filter['page_count']     = $filter['record_count'] > 0 ? ceil($filter['record_count'] / $filter['page_size']) : 1;

        /* 查询 */
        $sql = "SELECT DISTINCT bo.back_id, bo.delivery_sn, bo.order_sn, bo.order_id, bo.add_time, bo.action_user, bo.consignee, bo.country,
                       bo.province, bo.city, bo.district, bo.tel, bo.status, bo.update_time, bo.email, bo.return_time
                FROM " . $GLOBALS['ecs']->table("back_order")  ." as bo ".
                $where .
                " ORDER BY " . $filter['sort_by'] . " " . $filter['sort_order']. "
                LIMIT " . ($filter['page'] - 1) * $filter['page_size'] . ", " . $filter['page_size'] . " ";

        set_filter($filter, $sql);
    }
    else
    {
        $sql    = $result['sql'];
        $filter = $result['filter'];
    }

    $row = $GLOBALS['db']->getAll($sql);

    /* 格式化数据 */
    foreach ($row AS $key => $value)
    {
        $row[$key]['return_time'] = local_date($GLOBALS['_CFG']['time_format'], $value['return_time']);
        $row[$key]['add_time'] = local_date($GLOBALS['_CFG']['time_format'], $value['add_time']);
        $row[$key]['update_time'] = local_date($GLOBALS['_CFG']['time_format'], $value['update_time']);
        if ($value['status'] == 1)
        {
            $row[$key]['status_name'] = $GLOBALS['_LANG']['delivery_status'][1];
        }
        else
        {
        $row[$key]['status_name'] = $GLOBALS['_LANG']['delivery_status'][0];
        }
    }
    $arr = array('back' => $row, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);

    return $arr;
}

/**
 * 取得发货单信息
 * @param   int     $delivery_order   发货单id（如果delivery_order > 0 就按id查，否则按sn查）
 * @param   string  $delivery_sn      发货单号
 * @return  array   发货单信息（金额都有相应格式化的字段，前缀是formated_）
 */
function delivery_order_info($delivery_id, $delivery_sn = '')
{
    $return_order = array();
    if (empty($delivery_id) || !is_numeric($delivery_id))
    {
        return $return_order;
    }

    $where = '';
    /* 获取管理员信息 */
    $admin_info = admin_info();

    /* 如果管理员属于某个办事处，只列出这个办事处管辖的发货单 */
    if ($admin_info['agency_id'] > 0)
    {
        $where .= " AND agency_id = '" . $admin_info['agency_id'] . "' ";
    }

    /* 如果管理员属于某个供货商，只列出这个供货商的发货单 */
    if ($admin_info['suppliers_id'] > 0)
    {
        $where .= " AND suppliers_id = '" . $admin_info['suppliers_id'] . "' ";
    }

    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('delivery_order');
    if ($delivery_id > 0)
    {
        $sql .= " WHERE delivery_id = '$delivery_id'";
    }
    else
    {
        $sql .= " WHERE delivery_sn = '$delivery_sn'";
    }

    $sql .= $where;
    $sql .= " LIMIT 0, 1";
    $delivery = $GLOBALS['db']->getRow($sql);
    if ($delivery)
    {
        /* 格式化金额字段 */
        $delivery['formated_insure_fee']     = price_format($delivery['insure_fee'], false);
        $delivery['formated_shipping_fee']   = price_format($delivery['shipping_fee'], false);

        /* 格式化时间字段 */
        $delivery['formated_add_time']       = local_date($GLOBALS['_CFG']['time_format'], $delivery['add_time']);
        $delivery['formated_update_time']    = local_date($GLOBALS['_CFG']['time_format'], $delivery['update_time']);

        $return_order = $delivery;
    }

    return $return_order;
}

/**
 * 取得退货单信息
 * @param   int     $back_id   退货单 id（如果 back_id > 0 就按 id 查，否则按 sn 查）
 * @return  array   退货单信息（金额都有相应格式化的字段，前缀是 formated_ ）
 */
function back_order_info($back_id)
{
    $return_order = array();
    if (empty($back_id) || !is_numeric($back_id))
    {
        return $return_order;
    }

    $where = '';
    /* 获取管理员信息 */
    $admin_info = admin_info();

    /* 如果管理员属于某个办事处，只列出这个办事处管辖的发货单 */
    if ($admin_info['agency_id'] > 0)
    {
        $where .= " AND agency_id = '" . $admin_info['agency_id'] . "' ";
    }

    /* 如果管理员属于某个供货商，只列出这个供货商的发货单 */
    if ($admin_info['suppliers_id'] > 0)
    {
        $where .= " AND suppliers_id = '" . $admin_info['suppliers_id'] . "' ";
    }

    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('back_order') . "
            WHERE back_id = '$back_id'
            $where
            LIMIT 0, 1";
    $back = $GLOBALS['db']->getRow($sql);
    if ($back)
    {
        /* 格式化金额字段 */
        $back['formated_insure_fee']     = price_format($back['insure_fee'], false);
        $back['formated_shipping_fee']   = price_format($back['shipping_fee'], false);

        /* 格式化时间字段 */
        $back['formated_add_time']       = local_date($GLOBALS['_CFG']['time_format'], $back['add_time']);
        $back['formated_update_time']    = local_date($GLOBALS['_CFG']['time_format'], $back['update_time']);
        $back['formated_return_time']    = local_date($GLOBALS['_CFG']['time_format'], $back['return_time']);

        $return_order = $back;
    }

    return $return_order;
}

/**
 * 超级礼包发货数处理
 * @param   array   超级礼包商品列表
 * @param   int     发货数量
 * @param   int     订单ID
 * @param   varchar 虚拟代码
 * @param   int     礼包ID
 * @return  array   格式化结果
 */
function package_goods(&$package_goods, $goods_number, $order_id, $extension_code, $package_id)
{
    $return_array = array();

    if (count($package_goods) == 0 || !is_numeric($goods_number))
    {
        return $return_array;
    }

    foreach ($package_goods as $key=>$value)
    {
        $return_array[$key] = $value;
        $return_array[$key]['order_send_number'] = $value['order_goods_number'] * $goods_number;
        $return_array[$key]['sended'] = package_sended($package_id, $value['goods_id'], $order_id, $extension_code, $value['product_id']);
        $return_array[$key]['send'] = ($value['order_goods_number'] * $goods_number) - $return_array[$key]['sended'];
        $return_array[$key]['storage'] = $value['goods_number'];

        if ($return_array[$key]['send'] <= 0)
        {
            $return_array[$key]['send'] = $GLOBALS['_LANG']['act_good_delivery'];
            $return_array[$key]['readonly'] = 'readonly="readonly"';
        }

        /* 是否缺货 */
        if ($return_array[$key]['storage'] <= 0 && $GLOBALS['_CFG']['use_storage'] == '1')
        {
            $return_array[$key]['send'] = $GLOBALS['_LANG']['act_good_vacancy'];
            $return_array[$key]['readonly'] = 'readonly="readonly"';
        }
    }
    return $return_array;
}

/**
 * 获取超级礼包商品已发货数
 *
 * @param       int         $package_id         礼包ID
 * @param       int         $goods_id           礼包的产品ID
 * @param       int         $order_id           订单ID
 * @param       varchar     $extension_code     虚拟代码
 * @param       int         $product_id         货品id
 *
 * @return  int     数值
 */
function package_sended($package_id, $goods_id, $order_id, $extension_code, $product_id = 0)
{
    if (empty($package_id) || empty($goods_id) || empty($order_id) || empty($extension_code))
    {
        return false;
    }

    $sql = "SELECT SUM(DG.send_number)
            FROM " . $GLOBALS['ecs']->table('delivery_goods') . " AS DG, " . $GLOBALS['ecs']->table('delivery_order') . " AS o
            WHERE o.delivery_id = DG.delivery_id
            AND o.status IN (0, 2)
            AND o.order_id = '$order_id'
            AND DG.parent_id = '$package_id'
            AND DG.goods_id = '$goods_id'
            AND DG.extension_code = '$extension_code'";
    $sql .= ($product_id > 0) ? " AND DG.product_id = '$product_id'" : '';

    $send = $GLOBALS['db']->getOne($sql);

    return empty($send) ? 0 : $send;
}

/**
 * 改变订单中商品库存
 * @param   int     $order_id  订单 id
 * @param   array   $_sended   Array(‘商品id’ => ‘此单发货数量’)
 * @param   array   $goods_list
 * @return  Bool
 */
function change_order_goods_storage_split($order_id, $_sended, $goods_list = array())
{
    /* 参数检查 */
    if (!is_array($_sended) || empty($order_id))
    {
        return false;
    }

    foreach ($_sended as $key => $value)
    {
        // 商品（超值礼包）
        if (is_array($value))
        {
            if (!is_array($goods_list))
            {
                $goods_list = array();
            }
            foreach ($goods_list as $goods)
            {
                if (($key != $goods['rec_id']) || (!isset($goods['package_goods_list']) || !is_array($goods['package_goods_list'])))
                {
                    continue;
                }

                // 超值礼包无库存，只减超值礼包商品库存
                foreach ($goods['package_goods_list'] as $package_goods)
                {
                    if (!isset($value[$package_goods['goods_id']]))
                    {
                        continue;
                    }

                    // 减库存：商品（超值礼包）（实货）、商品（超值礼包）（虚货）
                    $sql = "UPDATE " . $GLOBALS['ecs']->table('goods') ."
                            SET goods_number = goods_number - '" . $value[$package_goods['goods_id']] . "'
                            WHERE goods_id = '" . $package_goods['goods_id'] . "' ";
                    $GLOBALS['db']->query($sql);
                }
            }
        }
        // 商品（实货）
        elseif (!is_array($value))
        {
            /* 检查是否为商品（实货） */
            foreach ($goods_list as $goods)
            {
                if ($goods['rec_id'] == $key && $goods['is_real'] == 1)
                {
                    $sql = "UPDATE " . $GLOBALS['ecs']->table('goods') . "
                            SET goods_number = goods_number - '" . $value . "'
                            WHERE goods_id = '" . $goods['goods_id'] . "' ";
                    $GLOBALS['db']->query($sql, 'SILENT');
                    break;
                }
            }
        }
    }

    return true;
}

/**
 *  超值礼包虚拟卡发货、跳过修改订单商品发货数的虚拟卡发货
 *
 * @access  public
 * @param   array      $goods      超值礼包虚拟商品列表数组
 * @param   string      $order_sn   本次操作的订单
 *
 * @return  boolen
 */
function package_virtual_card_shipping($goods, $order_sn)
{
    if (!is_array($goods))
    {
        return false;
    }

    /* 包含加密解密函数所在文件 */
    include_once(ROOT_PATH . 'includes/lib_code.php');

    // 取出超值礼包中的虚拟商品信息
    foreach ($goods as $virtual_goods_key => $virtual_goods_value)
    {
        /* 取出卡片信息 */
        $sql = "SELECT card_id, card_sn, card_password, end_date, crc32
                FROM ".$GLOBALS['ecs']->table('virtual_card')."
                WHERE goods_id = '" . $virtual_goods_value['goods_id'] . "'
                AND is_saled = 0
                LIMIT " . $virtual_goods_value['num'];
        $arr = $GLOBALS['db']->getAll($sql);
        /* 判断是否有库存 没有则推出循环 */
        if (count($arr) == 0)
        {
            continue;
        }

        $card_ids = array();
        $cards = array();

        foreach ($arr as $virtual_card)
        {
            $card_info = array();

            /* 卡号和密码解密 */
            if ($virtual_card['crc32'] == 0 || $virtual_card['crc32'] == crc32(AUTH_KEY))
            {
                $card_info['card_sn'] = decrypt($virtual_card['card_sn']);
                $card_info['card_password'] = decrypt($virtual_card['card_password']);
            }
            elseif ($virtual_card['crc32'] == crc32(OLD_AUTH_KEY))
            {
                $card_info['card_sn'] = decrypt($virtual_card['card_sn'], OLD_AUTH_KEY);
                $card_info['card_password'] = decrypt($virtual_card['card_password'], OLD_AUTH_KEY);
            }
            else
            {
                return false;
            }
            $card_info['end_date'] = date($GLOBALS['_CFG']['date_format'], $virtual_card['end_date']);
            $card_ids[] = $virtual_card['card_id'];
            $cards[] = $card_info;
        }

        /* 标记已经取出的卡片 */
        $sql = "UPDATE ".$GLOBALS['ecs']->table('virtual_card')." SET ".
           "is_saled = 1 ,".
           "order_sn = '$order_sn' ".
           "WHERE " . db_create_in($card_ids, 'card_id');
        if (!$GLOBALS['db']->query($sql))
        {
            return false;
        }

        /* 获取订单信息 */
        $sql = "SELECT order_id, order_sn, consignee, email FROM ".$GLOBALS['ecs']->table('order_info'). " WHERE order_sn = '$order_sn'";
        $order = $GLOBALS['db']->GetRow($sql);

        $cfg = $GLOBALS['_CFG']['send_ship_email'];
        if ($cfg == '1')
        {
            /* 发送邮件 */
            $GLOBALS['smarty']->assign('virtual_card',                   $cards);
            $GLOBALS['smarty']->assign('order',                          $order);
            $GLOBALS['smarty']->assign('goods',                          $virtual_goods_value);

            $GLOBALS['smarty']->assign('send_time', local_date($GLOBALS['_CFG']['time_format'], gmtime()));
            $GLOBALS['smarty']->assign('shop_name', $GLOBALS['_CFG']['shop_name']);
            $GLOBALS['smarty']->assign('send_date', local_date($GLOBALS['_CFG']['time_format'], gmtime()));
            $GLOBALS['smarty']->assign('sent_date', local_date($GLOBALS['_CFG']['time_format'], gmtime()));

            $tpl = get_mail_template('virtual_card');
            $content = $GLOBALS['smarty']->fetch('str:' . $tpl['template_content']);
            send_mail($order['consignee'], $order['email'], $tpl['template_subject'], $content, $tpl['is_html']);
        }
    }

    return true;
}

/**
 * 删除发货单时进行退货
 *
 * @access   public
 * @param    int     $delivery_id      发货单id
 * @param    array   $delivery_order   发货单信息数组
 *
 * @return  void
 */
function delivery_return_goods($delivery_id, $delivery_order)
{
    /* 查询：取得发货单商品 */
    $goods_sql = "SELECT *
                 FROM " . $GLOBALS['ecs']->table('delivery_goods') . "
                 WHERE delivery_id = " . $delivery_order['delivery_id'];
    $goods_list = $GLOBALS['db']->getAll($goods_sql);
    /* 更新： */
    foreach ($goods_list as $key=>$val)
    {
        $sql = "UPDATE " . $GLOBALS['ecs']->table('order_goods') .
               " SET send_number = send_number-'".$goods_list[$key]['send_number']. "'".
               " WHERE order_id = '".$delivery_order['order_id']."' AND goods_id = '".$goods_list[$key]['goods_id']."' LIMIT 1";
        $GLOBALS['db']->query($sql);
    }
    $sql = "UPDATE " . $GLOBALS['ecs']->table('order_info') .
           " SET shipping_status = '0' , order_status = 1".
           " WHERE order_id = '".$delivery_order['order_id']."' LIMIT 1";
    $GLOBALS['db']->query($sql);
}

/**
 * 删除发货单时删除其在订单中的发货单号
 *
 * @access   public
 * @param    int      $order_id              定单id
 * @param    string   $delivery_invoice_no   发货单号
 *
 * @return  void
 */
function del_order_invoice_no($order_id, $delivery_invoice_no)
{
    /* 查询：取得订单中的发货单号 */
    $sql = "SELECT invoice_no
            FROM " . $GLOBALS['ecs']->table('order_info') . "
            WHERE order_id = '$order_id'";
    $order_invoice_no = $GLOBALS['db']->getOne($sql);

    /* 如果为空就结束处理 */
    if (empty($order_invoice_no))
    {
        return;
    }

    /* 去除当前发货单号 */
    $order_array = explode('<br>', $order_invoice_no);
    $delivery_array = explode('<br>', $delivery_invoice_no);

    foreach ($order_array as $key => $invoice_no)
    {
        if ($ii = array_search($invoice_no, $delivery_array))
        {
            unset($order_array[$key], $delivery_array[$ii]);
        }
    }

    $arr['invoice_no'] = implode('<br>', $order_array);
    update_order($order_id, $arr);
}

/**
 * 获取站点根目录网址
 *
 * @access  private
 * @return  Bool
 */
function get_site_root_url()
{
    return 'http://' . $_SERVER['HTTP_HOST'] . str_replace('/' . ADMIN_PATH . '/order.php', '', PHP_SELF);

}

//ecmoban模板堂 --zhuo start
function download_orderlist($result) {
    if(empty($result)) {
        return i("没有符合您要求的数据！^_^");
    }

    $data = i('订单号,商品名称,商品类目,货号,规格,退货规格,商家名称,下单会员,下单时间,确认收货时间,退单金额,收货人,联系电话,地址,总金额,配送费用,保价费用,支付费用,余额金额,积分金额,红包金额,发票税额,折扣金额,优惠券金额,应付金额,确认状态,付款状态,发货状态,订单来源,支付方式'."\n");
    $count = count($result);
    for ($i = 0; $i < $count; $i++) {
        $order_sn = i('#'.$result[$i]['order_sn']); //订单号前加'#',避免被四舍五入 by wu
        //商品名称
        $goods_name = i($result[$i]['goods_list'][0]['goods_name']);
        //货号
        $goods_sn = i($result[$i]['goods_list'][0]['goods_sn']);

        //规格
        $guige = '';

        foreach ($result[$i]['goods_list'] as $goods) {
            //去除换行
            $guige .= str_replace(array("\r\n", "\r", "\n"), ' ', $goods['goods_attr']).' '.$goods['goods_number'] ."件    ";
        }
        $guige = i($guige);

        //退货规格
        $order_status = $result[$i]['order_status'];

        $return_amount = 0;
        $return_guige = '';
        if (in_array($order_status, [OS_RETURNED, OS_RETURNED_PART])) {
            //退单金额
            $return_goods = get_order_return_goods($result[$i]['order_id']);
            foreach ($return_goods as $re) {
                //去除换行
                $return_guige .= str_replace(array("\r\n", "\r", "\n"), ' ', $re['goods_attr']).' '.$re['return_number'] ."件    ";
            }
            $return_guige = i($return_guige);
            $return_amount = get_order_return_amount($result[$i]['order_id']);
        }

        $goods_id = $result[$i]['goods_list'][0]['goods_id'];
        $cat_id =   $GLOBALS['db']->getOne("SELECT cat_id FROM " .$GLOBALS['ecs']->table('goods'). " WHERE goods_id = '" .$goods_id. "'");
        $cat_arr = get_parent_cats($cat_id);
        $cat_name_array = array_column($cat_arr, 'cat_name');
        $cat_name_array = array_reverse($cat_name_array);
        $cat_name = i(implode('>', $cat_name_array));

        $order_user = i($result[$i]['buyer']);
        $order_time = i($result[$i]['short_order_time']);
        //确认收货时间
        $confirm_take_time = i($result[$i]['confirm_take_time']);

        $consignee = i($result[$i]['consignee']);
        $tel = !empty($result[$i]['mobile']) ? i($result[$i]['mobile']) : i($result[$i]['tel']);
        $address = i(addslashes(str_replace(",","，","[" . $result[$i]['region'] ."] ". $result[$i]['address'])));
        $order_amount = i($result[$i]['order_amount']);
        $shipping_fee = i($result[$i]['shipping_fee']);//配送费用
        $insure_fee = i($result[$i]['insure_fee']);//保价费用
        $pay_fee = i($result[$i]['pay_fee']);//支付费用
        $surplus = i($result[$i]['surplus']);//余额费用
        $integral_money = i($result[$i]['integral_money']);//积分金额
        $bonus = i($result[$i]['bonus']);//红包金额
        $tax = i($result[$i]['tax']);//发票税额
        $discount = i($result[$i]['discount']);//折扣金额
        $coupons = i($result[$i]['coupons']);//优惠券金额

        //by zxk  去除代码
        $order_status =strip_tags($GLOBALS['_LANG']['os'][$result[$i]['order_status']]);
        $order_status = i($order_status);

        $seller_name = i($result[$i]['user_name']); //商家名称
        $pay_status = i($GLOBALS['_LANG']['ps'][$result[$i]['pay_status']]);
        $shipping_status = i($GLOBALS['_LANG']['ss'][$result[$i]['shipping_status']]);
        $froms = i($result[$i]['froms']);
        $pay_name = i($result[$i]['pay_name']);
        $data .= $order_sn . ',' . $goods_name  . ','. $cat_name  . ','. $goods_sn  . ','. $guige . $return_guige  . ',' . ','. $seller_name  . ',' . $order_user . ',' .
                $order_time . ',' .$confirm_take_time . ','.$return_amount . ',' . $consignee . ',' . $tel . ',' .
                $address . ',' . i($result[$i]['total_fee']) . ',' .
                $shipping_fee . ',' . $insure_fee . ',' .
                $pay_fee . ',' . $surplus . ',' .
                $integral_money . ',' . $bonus . ',' .
                $tax . ',' . $discount . ',' . $coupons . ',' .
                $order_amount . ',' . $order_status . ',' .
                $pay_status . ',' . $shipping_status . ',' . $froms . ',' . $pay_name . "\n";
    }
    return $data;
}
function i($strInput) {
    return iconv('utf-8','gb2312',$strInput);//页面编码为utf-8时使用，否则导出的中文为乱码
}

//ecmoban模板堂 --zhuo end
/*
*
*/
function get_cause_cat_level($parent_id = 0)
{
	$res = array();

	$sql =  "SELECT c.cause_id, c.cause_name, c.sort_order, c.is_show ,c.parent_id , COUNT(s.cause_id) AS has_children ".
			'FROM ' . $GLOBALS['ecs']->table('return_cause') . " AS c ".
			"LEFT JOIN " . $GLOBALS['ecs']->table('return_cause') . " AS s ON s.parent_id=c.cause_id ".
			" WHERE c.parent_id = '$parent_id' GROUP BY c.cause_id ".
			'ORDER BY c.parent_id, c.sort_order ASC';

	$res = $GLOBALS['db']->getAll($sql);
    if($res){
        foreach($res as $k=>$row){
            $res[$k]['level'] = $level;
        }

    }

    return $res;
}

/* 通过订单号获取订单ID */
function order_id($sn){
	$sql = " SELECT order_id FROM ".$GLOBALS['ecs']->table('order_info')." WHERE order_sn = '$sn' ";
	$one = $GLOBALS['db']->getOne($sql);
	return $one;
}

//ecmoban模板堂 --zhuo start
/**
 * 取得收货人地址列表
 * @param   int     $user_id    用户编号
 * @return  array
 */
function get_consignee_log($address_id = 0,$user_id = 0)
{
    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('user_address') .
            " WHERE user_id = '$user_id' and address_id = '$address_id'";

    return $GLOBALS['db']->getRow($sql);
}

/**
 * 获得指定国家的所有省份
 *
 * @access      public
 * @param       int     country    国家的编号
 * @return      array
 */
function get_regions_log($type = 0, $parent = 0)
{
    $sql = 'SELECT region_id, region_name FROM ' . $GLOBALS['ecs']->table('region') .
            " WHERE region_type = '$type' AND parent_id = '$parent'";

    return $GLOBALS['db']->GetAll($sql);
}

function get_order_payment($pay_id){
    //获取支付方式code
    $sql = "SELECT pay_code FROM " .$GLOBALS['ecs']->table('payment'). " WHERE pay_id = '" .$order['pay_id']. "'";
    $pay_code = $GLOBALS['db']->getOne($sql, true);

    return $pay_code;
}

/**
 * curl 获取
 */
function curlGet($url, $timeout = 5, $header = "") {
    $defaultHeader = '$header = "User-Agent:Mozilla/5.0 (Windows; U; Windows NT 5.1; zh-CN; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12\r\n";
        $header.="Accept:text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n";
        $header.="Accept-language: zh-cn,zh;q=0.5\r\n";
        $header.="Accept-Charset: utf-8;q=0.7,*;q=0.7\r\n";';
    $header = empty($header) ? $defaultHeader : $header;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);    // https请求 不验证证书和hosts
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array($header)); //模拟的header头
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

/**
 * 获得指定分类的所有上级分类
 *
 * @access  public
 * @param   integer $cat    分类编号
 * @return  array
 */
function get_parent_cats($cat)
{
    if ($cat == 0)
    {
        return array();
    }

    $arr = $GLOBALS['db']->GetAll('SELECT cat_id, cat_name, parent_id FROM ' . $GLOBALS['ecs']->table('category'));

    if (empty($arr))
    {
        return array();
    }

    $index = 0;
    $cats  = array();

    while (1)
    {
        foreach ($arr AS $row)
        {
            if ($cat == $row['cat_id'])
            {
                $cat = $row['parent_id'];

                $cats[$index]['cat_id']   = $row['cat_id'];
                $cats[$index]['cat_name'] = $row['cat_name'];
                $cats[$index]['parent_id'] = $row['parent_id'];

                $sql = "SELECT c.cat_id, c.cat_name FROM " .$GLOBALS['ecs']->table('category'). " AS c WHERE parent_id = '" .$row['cat_id']. "' AND is_show = '1'";
                $cats[$index]['cat_tree'] = $GLOBALS['db']->getAll($sql);

                $index++;
                break;
            }
        }

        if ($index == 0 || $cat == 0)
        {
            break;
        }
    }

    return $cats;
}

/**
 * 取得上次未支付的pay_lig_id
 *
 * @access  public
 * @param   array     $surplus_id  余额记录的ID
 * @param   array     $pay_type    支付的类型：预付款/订单支付
 *
 * @return  int
 */
function zxk_get_paylog_id($surplus_id, $pay_type = PAY_SURPLUS)
{
    $sql = 'SELECT log_id FROM' .$GLOBALS['ecs']->table('pay_log').
        " WHERE order_id = '$surplus_id' AND order_type = '$pay_type' AND is_paid = 1";

    return $GLOBALS['db']->getOne($sql);
}

function timing_cancel_order() {
    $t = gmtime() - 24 * 3600;

    $adminru = get_admin_ru_id();

    $merchant_id =  $adminru['ru_id'];
    $where = " AND oi.order_status = 0 AND oi.pay_status =0  AND oi.shipping_status = 0 AND oi.pay_time = 0 AND oi.add_time < ".$t;
    $sql = "SELECT oi.order_id FROM " .$GLOBALS['ecs']->table('order_info') ." as oi".
        " WHERE 1 and oi.is_delete = 0 " .
        " and (select count(*) from " .$GLOBALS['ecs']->table('order_info'). " as oi_2 where oi_2.main_order_id = oi.order_id) = 0 " .  //主订单下有子订单时，则主订单不显示
        $where . " group by oi.order_id";
    $record_count = $GLOBALS['db']->getAll($sql);
    $order_ids = array_column($record_count, 'order_id');

    if ($order_ids) {
        foreach ($order_ids as $order_id) {
            system_cancel_oder($order_id);
        }
    }
}

function system_cancel_oder($order_id) {
    /* 查询订单信息，检查状态 */
    $sql = "SELECT user_id, order_id, order_sn , surplus , integral , bonus_id, order_status, shipping_status, pay_status FROM " .$GLOBALS['ecs']->table('order_info') ." WHERE order_id = '$order_id'";
    $order = $GLOBALS['db']->GetRow($sql);

    if (empty($order))
    {
        $GLOBALS['err']->add($GLOBALS['_LANG']['order_exist']);
        return false;
    }

    // 订单状态只能是“未确认”或“已确认”
    if ($order['order_status'] != OS_UNCONFIRMED && $order['order_status'] != OS_CONFIRMED)
    {
        $GLOBALS['err']->add($GLOBALS['_LANG']['current_os_not_unconfirmed']);

        return false;
    }

    //订单一旦确认，不允许用户取消
    if ( $order['order_status'] == OS_CONFIRMED)
    {
        $GLOBALS['err']->add($GLOBALS['_LANG']['current_os_already_confirmed']);

        return false;
    }

    // 发货状态只能是“未发货”
    if ($order['shipping_status'] != SS_UNSHIPPED)
    {
        $GLOBALS['err']->add($GLOBALS['_LANG']['current_ss_not_cancel']);

        return false;
    }

    // 如果付款状态是“已付款”、“付款中”，不允许取消，要取消和商家联系
    if ($order['pay_status'] != PS_UNPAYED)
    {
        $GLOBALS['err']->add($GLOBALS['_LANG']['current_ps_not_cancel']);

        return false;
    }

    //将用户订单设置为取消
    $sql = "UPDATE ".$GLOBALS['ecs']->table('order_info') ." SET order_status = '".OS_CANCELED."' WHERE order_id = '$order_id'";
    if ($GLOBALS['db']->query($sql))
    {
        /* 记录log */
        order_action($order['order_sn'], OS_CANCELED, $order['shipping_status'], PS_UNPAYED, '系统自动取消','system');

        /* 退回订单消费储值卡金额 */
        return_card_money($order_id);

        /* 退货用户余额、积分、红包 */
        if ($order['user_id'] > 0 && $order['surplus'] > 0)
        {
            $change_desc = sprintf($GLOBALS['_LANG']['return_surplus_on_cancel'], $order['order_sn']);
            log_account_change($order['user_id'], $order['surplus'], 0, 0, 0, $change_desc);
        }
        if ($order['user_id'] > 0 && $order['integral'] > 0)
        {
            $change_desc = sprintf($GLOBALS['_LANG']['return_integral_on_cancel'], $order['order_sn']);
            log_account_change($order['user_id'], 0, 0, 0, $order['integral'], $change_desc);
        }
        if ($order['user_id'] > 0 && $order['bonus_id'] > 0)
        {
            change_user_bonus($order['bonus_id'], $order['order_id'], false);
        }

        /* 如果使用库存，且下订单时减库存，则增加库存 */
        if ($GLOBALS['_CFG']['use_storage'] == '1' && $GLOBALS['_CFG']['stock_dec_time'] == SDT_PLACE)
        {
            change_order_goods_storage($order['order_id'], false, 1, 3);
        }

        /* 修改订单 */
        $arr = array(
            'bonus_id'  => 0,
            'bonus'     => 0,
            'integral'  => 0,
            'integral_money'    => 0,
            'surplus'   => 0
        );
        update_order($order['order_id'], $arr);

        return true;
    }
}
