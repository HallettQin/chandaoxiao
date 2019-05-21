<?php

/**
 * ECSHOP 销售明细列表程序
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
require_once(ROOT_PATH . 'languages/' .$_CFG['lang']. '/' .ADMIN_PATH. '/statistic.php');
$smarty->assign('lang', $_LANG);
$smarty->assign('menus',$_SESSION['menus']);
$smarty->assign('primary_cat',     $_LANG['06_stats']);
if (isset($_REQUEST['act']) && ($_REQUEST['act'] == 'query' ||  $_REQUEST['act'] == 'download'))
{
    /* 检查权限 */
    check_authz_json('sale_order_stats');
    if (strstr($_REQUEST['start_date'], '-') === false)
    {
        $_REQUEST['start_date'] = local_date('Y-m-d H:i:s', $_REQUEST['start_date']);
        $_REQUEST['end_date'] = local_date('Y-m-d H:i:s', $_REQUEST['end_date']);
    }
    /*------------------------------------------------------ */
    //--Excel文件下载
    /*------------------------------------------------------ */
    if ($_REQUEST['act'] == 'download')
    {
        $file_name = str_replace(" ", "--", $_REQUEST['start_date'].'_'.$_REQUEST['end_date'] . '_sale');
        $goods_sales_list = get_sale_list(false);
		
        header("Content-type: application/vnd.ms-excel; charset=utf-8");
        header("Content-Disposition: attachment; filename=$file_name.xls");

        /* 文件标题 */
        echo ecs_iconv(EC_CHARSET, 'GB2312', $_REQUEST['start_date']. $_LANG['to'] .$_REQUEST['end_date']. $_LANG['sales_list']) . "\t\n";

        /* 商品名称,订单号,商品数量,销售价格,销售日期 */
		echo ecs_iconv(EC_CHARSET, 'GB2312', '商家名称') . "\t";
		echo ecs_iconv(EC_CHARSET, 'GB2312', '货号') . "\t";
        echo ecs_iconv(EC_CHARSET, 'GB2312', $_LANG['goods_name']) . "\t";
        echo ecs_iconv(EC_CHARSET, 'GB2312', $_LANG['order_sn']) . "\t";
        echo ecs_iconv(EC_CHARSET, 'GB2312', $_LANG['amount']) . "\t";
        echo ecs_iconv(EC_CHARSET, 'GB2312', $_LANG['sell_price']) . "\t";
		echo ecs_iconv(EC_CHARSET, 'GB2312', '总金额') . "\t";
        echo ecs_iconv(EC_CHARSET, 'GB2312', $_LANG['sell_date']) . "\t\n";

        foreach ($goods_sales_list['sale_list_data'] AS $key => $value)
        {
			echo ecs_iconv(EC_CHARSET, 'GB2312', $value['shop_name']) . "\t";
			echo ecs_iconv(EC_CHARSET, 'GB2312', $value['goods_sn']) . "\t";
            echo ecs_iconv(EC_CHARSET, 'GB2312', $value['goods_name']) . "\t";
            echo ecs_iconv(EC_CHARSET, 'GB2312', '[ ' . $value['order_sn'] . ' ]') . "\t";
            echo ecs_iconv(EC_CHARSET, 'GB2312', $value['goods_num']) . "\t";
            echo ecs_iconv(EC_CHARSET, 'GB2312', $value['sales_price']) . "\t";
			echo ecs_iconv(EC_CHARSET, 'GB2312', $value['total_fee']) . "\t";
            echo ecs_iconv(EC_CHARSET, 'GB2312', $value['sales_time']) . "\t";
            echo "\n";
        }
        exit;
    }
    $sale_list_data = get_sale_list();
	
	//分页
	$page_count_arr = seller_page($sale_list_data,$_REQUEST['page']);
    $smarty->assign('page_count_arr',$page_count_arr);	
	
    $smarty->assign('goods_sales_list', $sale_list_data['sale_list_data']);
    $smarty->assign('filter',       $sale_list_data['filter']);
    $smarty->assign('record_count', $sale_list_data['record_count']);
    $smarty->assign('page_count',   $sale_list_data['page_count']);
    make_json_result($smarty->fetch('sale_list.dwt'), '', array('filter' => $sale_list_data['filter'], 'page_count' => $sale_list_data['page_count']));
}
/*------------------------------------------------------ */
//--商品明细列表
/*------------------------------------------------------ */
else
{

    /* 权限判断 */
    admin_priv('sale_order_stats');
	
	$smarty->assign('current','sale_list');
	
    /* 时间参数 */
    if (!isset($_REQUEST['start_date']))
    {
        $start_date = local_strtotime('-7 days');
    }
    if (!isset($_REQUEST['end_date']))
    {
        $end_date = local_strtotime('today');
    }
    
    $sale_list_data = get_sale_list();
	
	//分页
	$page_count_arr = seller_page($sale_list_data,$_REQUEST['page']);
    $smarty->assign('page_count_arr',$page_count_arr);	
	
    /* 赋值到模板 */
    $smarty->assign('filter',       $sale_list_data['filter']);
    $smarty->assign('record_count', $sale_list_data['record_count']);
    $smarty->assign('page_count',   $sale_list_data['page_count']);
    $smarty->assign('goods_sales_list', $sale_list_data['sale_list_data']);
    $smarty->assign('ur_here',          $_LANG['sell_stats']);
    $smarty->assign('full_page',        1);
    $smarty->assign('start_date',       local_date('Y-m-d H:i:s', $start_date));
    $smarty->assign('end_date',         local_date('Y-m-d H:i:s', $end_date));
    $smarty->assign('ur_here',      $_LANG['sale_list']);
    $smarty->assign('cfg_lang',     $_CFG['lang']);
    $smarty->assign('action_link',  array('text' => $_LANG['down_sales'],'href'=>'#download', 'class' => 'icon-download-alt'));
	
	/* 载入订单状态、付款状态、发货状态 */
    $smarty->assign('os_list', get_status_list('order'));
    $smarty->assign('ss_list', get_status_list('shipping'));

    /* 显示页面 */
    assign_query_info();
    $smarty->display('sale_list.dwt');
}
/*------------------------------------------------------ */
//--获取销售明细需要的函数
/*------------------------------------------------------ */
/**
 * 取得销售明细数据信息
 * @param   bool  $is_pagination  是否分页
 * @return  array   销售明细数据
 */
function get_sale_list($is_pagination = true){
	
    /* 时间参数 */
    $filter['start_date'] = empty($_REQUEST['start_date']) ? local_strtotime('-7 days') : local_strtotime($_REQUEST['start_date']);
    $filter['end_date'] = empty($_REQUEST['end_date']) ? local_strtotime('today') : local_strtotime($_REQUEST['end_date']);
	$filter['goods_sn'] = empty($_REQUEST['goods_sn']) ? '' : trim($_REQUEST['goods_sn']);
	$filter['sort_by'] = empty($_REQUEST['sort_by']) ? 'og.goods_number' : trim($_REQUEST['sort_by']);
	
	$filter['order_status'] = !empty($_REQUEST['order_status']) ? explode(',', $_REQUEST['order_status']) : '';
	$filter['shipping_status'] = !empty($_REQUEST['shipping_status']) ? explode(',', $_REQUEST['shipping_status']) : '';
	$filter['time_type'] = !empty($_REQUEST['time_type']) ? intval($_REQUEST['time_type']) : 0;

    /* 查询数据的条件 */ //og.order_id = oi.order_id". order_query_sql('finished', 'oi.') . " AND 
    $where = " WHERE 1 ";
	
	$where .= " and (select count(*) from " .$GLOBALS['ecs']->table('order_info'). " as oi2 where oi2.main_order_id = oi.order_id) = 0 AND oi.order_id = og.order_id ";  //主订单下有子订单时，则主订单不显示
			 
	//ecmoban模板堂 --zhuo start
	$adminru = get_admin_ru_id();
	$leftJoin = '';
	if($adminru['ru_id'] > 0){
		$where .= " and og.ru_id = '" .$adminru['ru_id']. "'";
	}
	
	if($filter['goods_sn']){
		$where .= " AND og.goods_sn = '" .$filter['goods_sn']. "'";
	}
	//ecmoban模板堂 --zhuo end
	
	if($filter['time_type'] == 1){
		$where .= " AND oi.add_time >= '".$filter['start_date']."' AND oi.add_time < '" . ($filter['end_date'] + 86400) . "'";
	}else{
		$where .= " AND oi.shipping_time >= '".$filter['start_date']."' AND oi.shipping_time <= '" . ($filter['end_date'] + 86400) . "'";
	}
	
	if(!empty($filter['order_status'])){ //多选
		$order_status = implode(',', $filter['order_status']);
		$where .= " AND oi.order_status in($order_status)";
	}
	
	if(!empty($filter['shipping_status'])){ //多选
		$shipping_status = implode(',', $filter['shipping_status']);
		$where .= " AND oi.shipping_status in($shipping_status)";
	}		 
    
    $sql = "SELECT COUNT(og.goods_id) FROM " .
           $GLOBALS['ecs']->table('order_info') . ' AS oi,'.
           $GLOBALS['ecs']->table('order_goods') . ' AS og '.$leftJoin.
           $where . $on;
    $filter['record_count'] = $GLOBALS['db']->getOne($sql);

    /* 分页大小 */
    $filter = page_and_size($filter);

    $sql = 'SELECT og.goods_id, og.goods_sn, og.goods_name, og.goods_number AS goods_num, og.ru_id, og.goods_price '.
           'AS sales_price, oi.add_time AS sales_time, oi.order_id, oi.order_sn, (og.goods_number * og.goods_price) AS total_fee '.
           "FROM " . $GLOBALS['ecs']->table('order_goods')." AS og, ".$GLOBALS['ecs']->table('order_info')." AS oi ".$leftJoin.
           $where.$on. " ORDER BY $filter[sort_by] DESC";
		      
    if ($is_pagination)
    {
        $sql .= " LIMIT " . $filter['start'] . ', ' . $filter['page_size'];
    }

    $sale_list_data = $GLOBALS['db']->getAll($sql);

    foreach ($sale_list_data as $key => $item)
    {
        $sale_list_data[$key]['shop_name'] = get_shop_name($sale_list_data[$key]['ru_id'], 1); //ecmoban模板堂 --zhuo	
        $sale_list_data[$key]['sales_price'] = $sale_list_data[$key]['sales_price'];
		$sale_list_data[$key]['total_fee'] = $sale_list_data[$key]['total_fee'];
        $sale_list_data[$key]['sales_time']  = local_date($GLOBALS['_CFG']['time_format'], $sale_list_data[$key]['sales_time']);
    }
    $arr = array('sale_list_data' => $sale_list_data, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);
    return $arr;
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
?>