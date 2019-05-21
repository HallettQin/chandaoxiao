<?php

/**
 * ECSHOP 订单统计
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

$adminru = get_admin_ru_id();
$smarty->assign('ru_id', $adminru['ru_id']);

/* act操作项的初始化 */
if (empty($_REQUEST['act']))
{
    $_REQUEST['act'] = 'list';
}
else
{
    $_REQUEST['act'] = trim($_REQUEST['act']);
}

 /* 时间参数 */
if (isset($_POST['start_date']) && !empty($_POST['end_date']))
{
    $start_date = local_strtotime($_POST['start_date']);
    $end_date = local_strtotime($_POST['end_date']);
    if ($start_date == $end_date)
    {
        $end_date   =   $start_date + 86400;
    }
}
else
{
    $today      = strtotime(local_date('Y-m-d'));   //本地时间
    $start_date = $today - 86400 * 6;
    $end_date   = $today + 86400;               //至明天零时
}

/*------------------------------------------------------ */
//--订单统计
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'list')
{
	admin_priv('sale_order_stats');
	
    /* 取得订单转化率数据 ecmoban模板堂 --zhuo */
    $order_general = get_order_general(1);
    $order_general['total_turnover'] = floatval($order_general['total_turnover']);
    
    $order_general2 = get_order_general();
    $order_total = floatval($order_general2['total_turnover']);
    $smarty->assign('order_total', $order_total);
    
    /* 取得商品总点击数量 */
    $where = " AND user_id = '{$adminru['ru_id']}' ";
    $sql = 'SELECT SUM(click_count) FROM ' .$ecs->table('goods') .' WHERE is_delete = 0' . $where;
    $click_count = floatval($db->getOne($sql));

    /* 每千个点击的订单数 */
    $click_ordernum = $click_count > 0 ? round(($order_general['total_order_num'] * 1000)/$click_count,2) : 0;

    /* 每千个点击的购物额 */
    $click_turnover = $click_count > 0 ? round(($order_general['total_turnover'] * 1000)/$click_count,2) : 0;

    /* 时区 */
    $timezone = isset($_SESSION['timezone']) ? $_SESSION['timezone'] : $GLOBALS['_CFG']['timezone'];

    /* 时间参数 */
    $is_multi = empty($_POST['is_multi']) ? false : true;

    $start_date_arr = array();
    $end_date_arr = array();
    if(!empty($_POST['year_month']))
    {
        $tmp = $_POST['year_month'];

        for ($i = 0; $i < count($tmp); $i++)
        {
            if (!empty($tmp[$i]))
            {
                $tmp_time = local_strtotime($tmp[$i] . '-1');
                $start_date_arr[] = $tmp_time;
                $end_date_arr[]   = local_strtotime($tmp[$i] . '-' . date('t', $tmp_time));
            }
        }
    }
    else
    {
        $tmp_time = local_strtotime(local_date('Y-m-d'));
        $start_date_arr[] = local_strtotime(local_date('Y-m') . '-1');
        $end_date_arr[]   = local_strtotime(local_date('Y-m') . '-31');;
    }
    
    /* 按月份交叉查询 */
    if ($is_multi)
    {
		/* 订单概况 */
		$order_data = array();
        foreach($start_date_arr AS $k => $val)
        {
            $seriesName = local_date('Y-m',$val);
            $order_info = get_orderinfo($start_date_arr[$k], $end_date_arr[$k]);
			$order_data[0][] = $seriesName; //月份
			$order_data[1][] = $order_info['confirmed_num']; //已确认
			$order_data[2][] = $order_info['succeed_num']; //已成交
			$order_data[3][] = $order_info['unconfirmed_num']; //未确认
			$order_data[4][] = $order_info['invalid_num']; //无效或取消
        }

		/* 配送方式 */
		$ship_data = array();
        $ship_res1 = $ship_res2 = get_shipping_type($start_date, $end_date, $adminru['ru_id']);
        if($ship_res1){
            $ship_arr = get_to_array($ship_res1, $ship_res2, 'shipping_id', 'ship_arr', 'ship_name');           
            foreach($ship_arr as $row)
            {
				$ship_data[0][] = $row['ship_name'];
                $ship_data[1][] = array(
					'value' => count($row['ship_arr']),
					'name' => $row['ship_name']
				);
            }
        }		
		
		/* 支付方式 */
		$pay_data = array();
        $pay_item1 = $pay_item2 = get_pay_type($start_date, $end_date, $adminru['ru_id']);
        if($pay_item1){
            $pay_arr = get_to_array($pay_item1, $pay_item2, 'pay_id', 'pay_arr', 'pay_name');           
			foreach($pay_arr as $row)
			{
				$pay_data[0][] = $row['pay_name'];
                $pay_data[1][] = array(
					'value' => count($row['pay_arr']),
					'name' => $row['pay_name']
				);				
			}
        }
		
		/* 配送地区 */
		$area_data = array();
        $countries = !empty($_POST['country']) ? intval($_POST['country']) : 1;
        $pro = !empty($_POST['province']) ? intval($_POST['province']) : 0;
        $order_area = get_area($countries, $pro);
		if($order_area){
			foreach ($order_area AS $row) {
				$area_data[0][] = $row['region_name'];
				$area_data[1][] = array(
					'value' => $row['area_num'],
					'name' => $row['region_name']
				);			
			}			
		}
	}else{
		/* 订单概况 */
		$order_data = array();
		$order_data['order'] = get_statistical_data($start_date, $end_date, 'order');
		$order_data['sale'] = get_statistical_data($start_date, $end_date, 'sale');
		
		/* 配送方式 */
		$ship_data = array();
        $ship_res1 = $ship_res2 = get_shipping_type($start_date, $end_date, $adminru['ru_id']);
        if($ship_res1){
            $ship_arr = get_to_array($ship_res1, $ship_res2, 'shipping_id', 'ship_arr', 'ship_name');           
            foreach($ship_arr as $row)
            {
				$ship_data[0][] = $row['ship_name'];
                $ship_data[1][] = array(
					'value' => count($row['ship_arr']),
					'name' => $row['ship_name']
				);
            }
        }		
		
		/* 支付方式 */
		$pay_data = array();
        $pay_item1 = $pay_item2 = get_pay_type($start_date, $end_date, $adminru['ru_id']);
        if($pay_item1){
            $pay_arr = get_to_array($pay_item1, $pay_item2, 'pay_id', 'pay_arr', 'pay_name');           
			foreach($pay_arr as $row)
			{
				$pay_data[0][] = $row['pay_name'];
                $pay_data[1][] = array(
					'value' => count($row['pay_arr']),
					'name' => $row['pay_name']
				);				
			}
        }
		
		/* 配送地区 */
		$area_data = array();
        $countries = !empty($_POST['country']) ? intval($_POST['country']) : 1;
        $pro = !empty($_POST['province']) ? intval($_POST['province']) : 0;
        $order_area = get_area($countries, $pro);
		if($order_area){
			foreach ($order_area AS $row) {
				$area_data[0][] = $row['region_name'];
				$area_data[1][] = array(
					'value' => $row['area_num'],
					'name' => $row['region_name']
				);			
			}			
		}
	}	
	
	/* 统计数据 */
	$smarty->assign('order_data',          json_encode($order_data));
	$smarty->assign('ship_data',           json_encode($ship_data));
	$smarty->assign('pay_data',            json_encode($pay_data));
	$smarty->assign('area_data',           json_encode($area_data));

    /* 赋值到模板 */
    $smarty->assign('order_general',       $order_general);
    $smarty->assign('total_turnover',      price_format($order_general['total_turnover']));
    $smarty->assign('click_count',         $click_count);         //商品总点击数
    $smarty->assign('click_ordernum',      $click_ordernum);      //每千点订单数
    $smarty->assign('click_turnover',      price_format($click_turnover));  //每千点购物额

    $smarty->assign('is_multi',            $is_multi);

    $smarty->assign('ur_here',             $_LANG['report_order']);
    $smarty->assign('start_date',          local_date($_CFG['date_format'], $start_date));
    $smarty->assign('end_date',            local_date($_CFG['date_format'], $end_date));

    for ($i = 0; $i < 5; $i++)
    {
        if (isset($start_date_arr[$i]))
        {
            $start_date_arr[$i] = local_date('Y-m', $start_date_arr[$i]);
        }
        else
        {
            $start_date_arr[$i] = null;
        }
    }
    $smarty->assign('start_date_arr', $start_date_arr);

    if (!$is_multi)
    {
        $filename = local_date('Ymd', $start_date) . '_' . local_date('Ymd', $end_date);
        $smarty->assign('action_link',  array('text' => $_LANG['down_order_statistics'], 'href' => 'order_stats.php?act=download&start_date=' . $start_date . '&end_date=' . $end_date . '&filename=' . $filename));
    }

    assign_query_info();
    $smarty->display('order_stats.dwt');	
}

/*------------------------------------------------------ */
//--订单统计
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'list_old')
{
    admin_priv('sale_order_stats');

    /* 随机的颜色数组 */
    $color_array = array('63f13e','3dfa7a','1439dc','deef88','ba7488','ab0d33','32aafe','e04493','76369b','ce6218','2cf9cd','f92724','08a9df','b2d3dc','81847d','aaedc3','c12e54','cee9c8','33536e','f1c9aa','722e82','0cff8a','40200c','202fcd','9b056d','74c0b5','c46c1a','25e95c','c8f2b5','f97998','e9dea5','41eee1','9de54c','e63e4e');
    /* 取得订单转化率数据 ecmoban模板堂 --zhuo */
    $order_general = get_order_general(1);
    $order_general['total_turnover'] = floatval($order_general['total_turnover']);
    
    $order_general2 = get_order_general();
    $order_total = floatval($order_general2['total_turnover']);
    $smarty->assign('order_total', $order_total);
    
    /* 取得商品总点击数量 */
    $sql = 'SELECT SUM(click_count) FROM ' .$ecs->table('goods') .' WHERE is_delete = 0';
    $click_count = floatval($db->getOne($sql));

    /* 每千个点击的订单数 */
    $click_ordernum = $click_count > 0 ? round(($order_general['total_order_num'] * 1000)/$click_count,2) : 0;

    /* 每千个点击的购物额 */
    $click_turnover = $click_count > 0 ? round(($order_general['total_turnover'] * 1000)/$click_count,2) : 0;

    /* 时区 */
    $timezone = isset($_SESSION['timezone']) ? $_SESSION['timezone'] : $GLOBALS['_CFG']['timezone'];

    /* 时间参数 */
    $is_multi = empty($_POST['is_multi']) ? false : true;

    $start_date_arr = array();
    $end_date_arr = array();
    if(!empty($_POST['year_month']))
    {
        $tmp = $_POST['year_month'];

        for ($i = 0; $i < count($tmp); $i++)
        {
            if (!empty($tmp[$i]))
            {
                $tmp_time = local_strtotime($tmp[$i] . '-1');
                $start_date_arr[] = $tmp_time;
                $end_date_arr[]   = local_strtotime($tmp[$i] . '-' . date('t', $tmp_time));
            }
        }
    }
    else
    {
        $tmp_time = local_strtotime(local_date('Y-m-d'));
        $start_date_arr[] = local_strtotime(local_date('Y-m') . '-1');
        $end_date_arr[]   = local_strtotime(local_date('Y-m') . '-31');;
    }
    
    /* 按月份交叉查询 */
    if ($is_multi)
    {
        /* 订单概况 */
        $order_general_xml = "<chart caption='$_LANG[order_circs]' shownames='1' showvalues='0' decimals='0' outCnvBaseFontSize='12' baseFontSize='12' >";
        $order_general_xml .= "<categories><category label='$_LANG[confirmed]' />" .
                                "<category label='$_LANG[succeed]' />" .
                                "<category label='$_LANG[unconfirmed]' />" .
                                "<category label='$_LANG[invalid]' /></categories>";
        foreach($start_date_arr AS $k => $val)
        {
            $seriesName = local_date('Y-m',$val);
            $order_info = get_orderinfo($start_date_arr[$k], $end_date_arr[$k]);
            $order_general_xml .= "<dataset seriesName='$seriesName' color='$color_array[$k]' showValues='0'>";
            $order_general_xml .= "<set value='$order_info[confirmed_num]' />";
            $order_general_xml .= "<set value='$order_info[succeed_num]' />";
            $order_general_xml .= "<set value='$order_info[unconfirmed_num]' />";
            $order_general_xml .= "<set value='$order_info[invalid_num]' />";
            $order_general_xml .= "</dataset>";
        }
        $order_general_xml .= "</chart>";

        /* 支付方式 */
        $pay_xml = "<chart caption='$_LANG[pay_method]' shownames='1' showvalues='0' decimals='0' outCnvBaseFontSize='12' baseFontSize='12' >";

        $payment = array();
        $payment_count = array();
        
        foreach($start_date_arr AS $k => $val)
        {
            //ecmoban模板堂 --zhuo start
            $pay_res1 = get_pay_type_top($start_date_arr[$k], $adminru['ru_id'], 1);
            $pay_res2 = get_pay_type_top($start_date_arr[$k], $adminru['ru_id']);
            if($pay_res1){
                $pay_arr = get_to_array($pay_res1, $pay_res2, 'pay_id', 'pay_arr', 'pay_name', 'pay_time');       
                foreach($pay_arr as $row)
                {
                   $payment[$row['pay_name']] = null;

                   $paydate = local_date('Y-m', $row['pay_time']);

                   $payment_count[$row['pay_name']][$paydate] = count($row['pay_arr']);
                }
            }
            //ecmoban模板堂 --zhuo end 
        }

        $pay_xml .= "<categories>";
        foreach ($payment AS $k => $val)
        {
            $pay_xml .= "<category label='$k' />";
        }
        $pay_xml .= "</categories>";

        foreach($start_date_arr AS $k => $val)
        {
            $date = local_date('Y-m', $start_date_arr[$k]);
            $pay_xml .= "<dataset seriesName='$date' color='$color_array[$k]' showValues='0'>";
            foreach ($payment AS $k => $val)
            {
                $count = 0;
                if (!empty($payment_count[$k][$date]))
                {
                  $count = $payment_count[$k][$date];
                }

                $pay_xml .= "<set value='$count' name='$date' />";
            }
            $pay_xml .= "</dataset>";
        }
        $pay_xml .= "</chart>";

        /* 地区分布 */
        $countries = !empty($_POST['country']) ? intval($_POST['country']) : 1;
        $pro = !empty($_POST['province']) ? intval($_POST['province']) : 0;
        $order_area = get_area($countries, $pro);

        $smarty->assign('countries', get_regions());
        if ($_CFG['shop_country'] > 0) {
            $smarty->assign('provinces', get_regions(1, $_CFG['shop_country']));
            if ($_CFG['shop_province']) {
                $smarty->assign('cities', get_regions(2, $_CFG['shop_province']));
            }
        }
        $order_area_xml = "<graph caption='" . $_LANG['tab_area'] . "' decimalPrecision='2' showPercentageValues='0' showNames='1' showValues='1' showPercentageInLabel='0' pieYScale='45' pieBorderAlpha='40' pieFillAlpha='70' pieSliceDepth='15' pieRadius='100' outCnvBaseFontSize='13' baseFontSize='12'>";
        foreach ($order_area AS $val => $k) {
            $order_area_xml .= "<set value='" . $val['area_num'] . "' name='" . $val['region_name'] . "' color='" . $color_array[$k] . "' />";
        }
        $order_area_xml .= "</graph>";

        /* 配送方式 */
        $ship = array();
        $ship_count = array();

        $ship_xml = "<chart caption='$_LANG[shipping_method]' shownames='1' showvalues='0' decimals='0' outCnvBaseFontSize='12' baseFontSize='12' >";

        foreach($start_date_arr AS $k => $val)
        {
            //ecmoban模板堂 --zhuo start
            $ship_res1 = get_shipping_type($start_date, $end_date, $adminru['ru_id'], 1);
            $ship_res2 = get_shipping_type($start_date, $end_date, $adminru['ru_id']);
            if($ship_res1){
                $ship_arr = get_to_array($ship_res1, $ship_res2, 'shipping_id', 'ship_arr', 'ship_name', 'shipping_time');           
                foreach($ship_arr as $row)
                 {
                    $ship[$row['ship_name']] = null;

                    $shipdate = local_date('Y-m', $row['shipping_time']);

                    $ship_count[$row['ship_name']][$shipdate] = count($row['ship_arr']);
                 }
            }     
            //ecmoban模板堂 --zhuo end
        }

        $ship_xml .= "<categories>";
        foreach ($ship AS $k => $val)
        {
            $ship_xml .= "<category label='$k' />";
        }
        $ship_xml .= "</categories>";

        foreach($start_date_arr AS $k => $val)
        {
            $date = local_date('Y-m', $start_date_arr[$k]);

            $ship_xml .= "<dataset seriesName='$date' color='$color_array[$k]' showValues='0'>";
            foreach ($ship AS $k => $val)
            {
                $count = 0;
                if (!empty($ship_count[$k][$date]))
                {
                    $count = $ship_count[$k][$date];
                }
                $ship_xml .= "<set value='$count' name='$date' />";
            }
            $ship_xml .= "</dataset>";
        }
        $ship_xml .= "</chart>";
    }
    /* 按时间段查询 */
    else
    {
        /* 订单概况 */
        $order_info = get_orderinfo($start_date, $end_date);

        $order_general_xml = "<graph caption='".$_LANG['order_circs']."' decimalPrecision='2' showPercentageValues='0' showNames='1' showValues='1' showPercentageInLabel='0' pieYScale='45' pieBorderAlpha='40' pieFillAlpha='70' pieSliceDepth='15' pieRadius='100' outCnvBaseFontSize='13' baseFontSize='12'>";

        $order_general_xml .= "<set value='" .$order_info['confirmed_num']. "' name='" . $_LANG['confirmed'] . "' color='".$color_array[5]."' />";

        $order_general_xml .= "<set value='" .$order_info['succeed_num']."' name='" . $_LANG['succeed'] . "' color='".$color_array[0]."' />";

        $order_general_xml .= "<set value='" .$order_info['unconfirmed_num']. "' name='" . $_LANG['unconfirmed'] . "' color='".$color_array[1]."'  />";

        $order_general_xml .= "<set value='" .$order_info['invalid_num']. "' name='" . $_LANG['invalid'] . "' color='".$color_array[4]."' />";
        $order_general_xml .= "</graph>";

        /* 支付方式 */
        $pay_xml = "<graph caption='" . $_LANG['pay_method'] . "' decimalPrecision='2' showPercentageValues='0' showNames='1' numberPrefix='' showValues='1' showPercentageInLabel='0' pieYScale='45' pieBorderAlpha='40' pieFillAlpha='70' pieSliceDepth='15' pieRadius='100' outCnvBaseFontSize='13' baseFontSize='12'>";
        
        //ecmoban模板堂 --zhuo satrt
        $pay_item1 = get_pay_type($start_date, $end_date, $adminru['ru_id']);
        $pay_item2 = get_pay_type($start_date, $end_date, $adminru['ru_id']);
        if($pay_item1){
            $pay_arr = get_to_array($pay_item1, $pay_item2, 'pay_id', 'pay_arr', 'pay_name');           
            foreach($pay_arr as $row)
            {
                $pay_xml .= "<set value='".count($row['pay_arr'])."' name='".strip_tags($row['pay_name'])."' color='".$color_array[mt_rand(0,7)]."'/>";
            }
        }
        //ecmoban模板堂 --zhuo end
        
        $pay_xml .= "</graph>";
		
	
        /* 地区分布 */
        $countries = !empty($_POST['country']) ? intval($_POST['country']) : 1;
        $pro = !empty($_POST['province']) ? intval($_POST['province']) : 0;
        $order_area = get_area($countries, $pro);
        
        $smarty->assign('countries', get_regions());
        $smarty->assign('provinces', get_regions(1, $_CFG['shop_country']));
        $smarty->assign('cities', get_regions(2, $_CFG['shop_province']));
        
        $order_area_xml = "<graph caption='" . $_LANG['tab_area'] . "' decimalPrecision='2' showPercentageValues='0' showNames='1' showValues='1' showPercentageInLabel='0' pieYScale='45' pieBorderAlpha='40' pieFillAlpha='70' pieSliceDepth='15' pieRadius='100' outCnvBaseFontSize='13' baseFontSize='12'>";
        foreach ($order_area AS $val) {
            $order_area_xml .= "<set value='" . $val['area_num'] . "' name='" . $val['region_name'] . "' color='" . $color_array[mt_rand(0,7)] . "' />";
        }
        $order_area_xml .= "</graph>";

        /* 支付方式 */
        $pay_xml = "<graph caption='" . $_LANG['pay_method'] . "' decimalPrecision='2' showPercentageValues='0' showNames='1' numberPrefix='' showValues='1' showPercentageInLabel='0' pieYScale='45' pieBorderAlpha='40' pieFillAlpha='70' pieSliceDepth='15' pieRadius='100' outCnvBaseFontSize='13' baseFontSize='12'>";
        
        //ecmoban模板堂 --zhuo satrt
        $pay_item1 = get_pay_type($start_date, $end_date, $adminru['ru_id']);
        $pay_item2 = get_pay_type($start_date, $end_date, $adminru['ru_id']);
        if($pay_item1){
            $pay_arr = get_to_array($pay_item1, $pay_item2, 'pay_id', 'pay_arr', 'pay_name');           
            foreach($pay_arr as $row)
            {
                $pay_xml .= "<set value='".count($row['pay_arr'])."' name='".strip_tags($row['pay_name'])."' color='".$color_array[mt_rand(0,7)]."'/>";
            }
        }
        //ecmoban模板堂 --zhuo end
        
        $pay_xml .= "</graph>";
			   
        /* 配送方式 */
        $ship_xml = "<graph caption='".$_LANG['shipping_method']."' decimalPrecision='2' showPercentageValues='0' showNames='1' numberPrefix='' showValues='1' showPercentageInLabel='0' pieYScale='45' pieBorderAlpha='40' pieFillAlpha='70' pieSliceDepth='15' pieRadius='100' outCnvBaseFontSize='13' baseFontSize='12'>";
        
        //ecmoban模板堂 --zhuo satrt
        $ship_res1 = get_shipping_type($start_date, $end_date, $adminru['ru_id'], 1);
        $ship_res2 = get_shipping_type($start_date, $end_date, $adminru['ru_id']);
        if($ship_res1){
            $ship_arr = get_to_array($ship_res1, $ship_res2, 'shipping_id', 'ship_arr', 'ship_name');           
            foreach($ship_arr as $row)
            {
                $ship_xml .= "<set value='".count($row['ship_arr'])."' name='".$row['ship_name']."' color='".$color_array[mt_rand(0,7)]."' />";
            }
        }
        //ecmoban模板堂 --zhuo end

        $ship_xml .= "</graph>";

    }
    /* 赋值到模板 */
    $smarty->assign('order_general',       $order_general);
    $smarty->assign('total_turnover',      price_format($order_general['total_turnover']));
    $smarty->assign('click_count',         $click_count);         //商品总点击数
    $smarty->assign('click_ordernum',      $click_ordernum);      //每千点订单数
    $smarty->assign('click_turnover',      price_format($click_turnover));  //每千点购物额

    $smarty->assign('is_multi',            $is_multi);
    
    $smarty->assign('order_general_xml',   $order_general_xml);
    $smarty->assign('ship_xml',            $ship_xml);
    $smarty->assign('order_area_xml',      $order_area_xml);
    $smarty->assign('pay_xml',             $pay_xml);
    
    $region_name = get_goods_region_name($_CFG['shop_city']);
    $smarty->assign('region_name',             $region_name);

    $smarty->assign('ur_here',             $_LANG['report_order']);
    $smarty->assign('start_date',          local_date($_CFG['date_format'], $start_date));
    $smarty->assign('end_date',            local_date($_CFG['date_format'], $end_date));

    for ($i = 0; $i < 5; $i++)
    {
        if (isset($start_date_arr[$i]))
        {
            $start_date_arr[$i] = local_date('Y-m', $start_date_arr[$i]);
        }
        else
        {
            $start_date_arr[$i] = null;
        }
    }
    $smarty->assign('start_date_arr', $start_date_arr);

    if (!$is_multi)
    {
        $filename = local_date('Ymd', $start_date) . '_' . local_date('Ymd', $end_date);
        $smarty->assign('action_link',  array('text' => $_LANG['down_order_statistics'], 'href' => 'order_stats.php?act=download&start_date=' . $start_date . '&end_date=' . $end_date . '&filename=' . $filename));
    }

    assign_query_info();
    $smarty->display('order_stats.dwt');
}

elseif ($_REQUEST['act'] == 'area'){
    /* 随机的颜色数组 */
    $color_array = array('33FF66', 'FF6600', '3399FF', '009966', 'CC3399', 'FFCC33', '6699CC', 'CC3366');
    $countries = !empty($_POST['country']) ? intval($_POST['country']) : 1;
    $pro = !empty($_POST['province']) ? intval($_POST['province']) : 0;
    $order_area = get_area($countries, $pro);
    $smarty->assign('countries', get_regions());
    if ($_CFG['shop_country'] > 0) {
        $smarty->assign('provinces', get_regions(1, $_CFG['shop_country']));
        if ($_CFG['shop_province']) {
            $smarty->assign('cities', get_regions(2, $_CFG['shop_province']));
        }
    }
    $order_area_xml = "<graph caption='" . $_LANG['tab_area'] . "' decimalPrecision='2' showPercentageValues='0' showNames='1' showValues='1' showPercentageInLabel='0' pieYScale='45' pieBorderAlpha='40' pieFillAlpha='70' pieSliceDepth='15' pieRadius='100' outCnvBaseFontSize='13' baseFontSize='12'>";
    foreach ($order_area as $k => $val) {
        $order_area_xml .= "<set value='" . $val['area_num'] . "' name='" . $val['region_name'] . "' color='" . $color_array[$k] . "' />";
    }
    $order_area_xml .= "</graph>";
    echo $order_area_xml;
}

elseif ($_REQUEST['act'] == 'download')
{
    $filename = !empty($_REQUEST['filename']) ? trim($_REQUEST['filename']) : '';
    header("Content-type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=$filename.xls");
    $start_date = empty($_REQUEST['start_date']) ? strtotime('-20 day') : intval($_REQUEST['start_date']);
    $end_date   = empty($_REQUEST['end_date']) ? time() : intval($_REQUEST['end_date']);
    /* 订单概况 */
    $order_info = get_orderinfo($start_date, $end_date);
    $data = $_LANG['order_circs'] . "\n";
    $data .= "$_LANG[confirmed] \t $_LANG[succeed] \t $_LANG[unconfirmed] \t $_LANG[invalid] \n";
    $data .= "$order_info[confirmed_num] \t $order_info[succeed_num] \t $order_info[unconfirmed_num] \t $order_info[invalid_num]\n";
    $data .= "\n$_LANG[pay_method]\n";
    
    $pay_arr = array();
    $ship_arr = array();
    /* 支付方式 */
    //ecmoban模板堂 --zhuo satrt
    $pay_item1 = get_pay_type($start_date, $end_date, $adminru['ru_id']);
    $pay_item2 = get_pay_type($start_date, $end_date, $adminru['ru_id']);
    if($pay_item1){
        $pay_arr = get_to_array($pay_item1, $pay_item2, 'pay_id', 'pay_arr', 'pay_name');           
    }
    //ecmoban模板堂 --zhuo end
    
    foreach ($pay_arr AS $val)
    {
        $data .= $val['pay_name'] . "\t";
    }
    $data .= "\n";
    foreach ($pay_arr AS $val)
    {
        $data .= count($val['pay_arr']) . "\t";
    }
    
    //ecmoban模板堂 --zhuo satrt
    $ship_res1 = get_shipping_type($start_date, $end_date, $adminru['ru_id']);
    $ship_res2 = get_shipping_type($start_date, $end_date, $adminru['ru_id']);
    if($ship_res1){
        $ship_arr = get_to_array($ship_res1, $ship_res2, 'shipping_id', 'ship_arr', 'ship_name');           
    }
    //ecmoban模板堂 --zhuo end
    
    $data .= "\n$_LANG[shipping_method]\n";
    foreach ($ship_arr AS $val)
    {
        $data .= $val['ship_name'] . "\t";
    }
    $data .= "\n";
    foreach ($ship_arr AS $val)
    {
        $data .= count($val['ship_arr']) . "\t";
    }

    echo ecs_iconv(EC_CHARSET, 'GB2312', $data) . "\t";
    exit;

}





/*------------------------------------------------------ */
//--订单统计需要的函数
/*------------------------------------------------------ */
 /**
  * 取得订单概况数据(包括订单的几种状态)
  * @param       $start_date    开始查询的日期
  * @param       $end_date      查询的结束日期
  * @return      $order_info    订单概况数据
  */
 function get_orderinfo($start_date, $end_date)
 {
    $order_info = array();
    $adminru = get_admin_ru_id();
    
    /* 未确认订单数 */
    $sql = 'SELECT o.order_id FROM ' .$GLOBALS['ecs']->table('order_info'). " as o " . 
           " WHERE o.order_status = '" .OS_UNCONFIRMED. "' AND o.add_time >= '$start_date'".
           " AND o.add_time < '" . ($end_date + 86400) . "'" .
            " AND (SELECT og.ru_id FROM " . $GLOBALS['ecs']->table('order_goods') .' as og' . " WHERE og.order_id = o.order_id limit 0, 1) = '" .$adminru['ru_id']. "' " .
            " AND (select count(*) from " .$GLOBALS['ecs']->table('order_info'). " as oi2 where oi2.main_order_id = o.order_id) = 0";  //主订单下有子订单时，则主订单不显示
    
    $order_info['unconfirmed_num'] = count($GLOBALS['db']->getAll($sql));

    /* 已确认订单数 */
    $sql = 'SELECT o.order_id FROM ' .$GLOBALS['ecs']->table('order_info'). " as o " . 
           " WHERE o.order_status = '" .OS_CONFIRMED. "' AND o.shipping_status NOT ". db_create_in(array(SS_SHIPPED, SS_RECEIVED)) . " AND o.pay_status NOT" . db_create_in(array(PS_PAYED, PS_PAYING)) ." AND o.add_time >= '$start_date'".
           " AND o.add_time < '" . ($end_date + 86400) . "'" .
           " AND (SELECT og.ru_id FROM " . $GLOBALS['ecs']->table('order_goods') .' as og' . " WHERE og.order_id = o.order_id limit 0, 1) = '" .$adminru['ru_id']. "' " .
            " AND (select count(*) from " .$GLOBALS['ecs']->table('order_info'). " as oi2 where oi2.main_order_id = o.order_id) = 0";  //主订单下有子订单时，则主订单不显示
    $order_info['confirmed_num'] = count($GLOBALS['db']->getAll($sql));

    /* 已成交订单数 */
    $sql = 'SELECT o.order_id FROM ' .$GLOBALS['ecs']->table('order_info') . ' as o ' .
           " WHERE 1 " . order_query_sql('finished', 'o.') .
           " AND o.add_time >= '$start_date' AND o.add_time < '" . ($end_date + 86400) . "'" . 
            " AND (SELECT og.ru_id FROM " . $GLOBALS['ecs']->table('order_goods') .' as og' . " WHERE og.order_id = o.order_id limit 0, 1) = '" .$adminru['ru_id']. "' " .
            " AND (select count(*) from " .$GLOBALS['ecs']->table('order_info'). " as oi2 where oi2.main_order_id = o.order_id) = 0";  //主订单下有子订单时，则主订单不显示
    
    $order_info['succeed_num'] = count($GLOBALS['db']->getAll($sql));

    /* 无效或已取消订单数 */
    $sql = "SELECT o.order_id FROM " .$GLOBALS['ecs']->table('order_info') . ' as o ' .
           " WHERE o.order_status " .db_create_in(array(OS_CANCELED, OS_INVALID)).
           " AND o.add_time >= '$start_date' AND o.add_time < '" . ($end_date + 86400) . "'" . 
            " AND (SELECT og.ru_id FROM " . $GLOBALS['ecs']->table('order_goods') .' as og' . " WHERE og.order_id = o.order_id limit 0, 1) = '" .$adminru['ru_id']. "' " .
            " AND (select count(*) from " .$GLOBALS['ecs']->table('order_info'). " as oi2 where oi2.main_order_id = o.order_id) = 0";  //主订单下有子订单时，则主订单不显示
    
    $order_info['invalid_num'] = count($GLOBALS['db']->getAll($sql));
    
    return $order_info;
 }
 
 function get_order_general($type = 0){
     $adminru = get_admin_ru_id();
      
     /* 计算订单各种费用之和的语句 */
     $total_fee = "SUM(" . order_amount_field('o.') . ") AS total_turnover ";
    
     $where = '';
     if($type == 1){
         $where = " AND (SELECT og.ru_id FROM " . $GLOBALS['ecs']->table('order_goods') .' as og' . " WHERE og.order_id = o.order_id limit 0, 1) = '" .$adminru['ru_id']. "' ";
     }
     
     $sql = "SELECT count(*) as total_order_num, " .$total_fee. " FROM " . $GLOBALS['ecs']->table('order_info'). ' as o' . 
           " WHERE 1 " . order_query_sql('finished', 'o.') .
             $where.
            " AND (select count(*) from " .$GLOBALS['ecs']->table('order_info'). " as oi2 where oi2.main_order_id = o.order_id) = 0";  //主订单下有子订单时，则主订单不显示;
   
     return $GLOBALS['db']->getRow($sql); 
 }
 
//multi--One start
 function get_pay_type_top($start_date, $ru_id, $type = 0){

     //当月的开始日期和结束日期
     $day = local_date('Y-m-d', $start_date);
     $time = getthemonth($day); 
     
     $start_date = local_strtotime($time[0]);
     $end_date = local_strtotime($time[1]);
     
     $res = get_pay_type($start_date, $end_date, $ru_id, $type);
     
     return $res;
 }
 
 function get_shipping_type_top($start_date, $ru_id, $type = 0){

     //当月的开始日期和结束日期
     $day = local_date('Y-m-d', $start_date);
     $time = getthemonth($day); 
     
     $start_date = local_strtotime($time[0]);
     $end_date = local_strtotime($time[1]);
     
     $res = get_shipping_type($start_date, $end_date, $ru_id, $type);
     
     return $res;
 }
//multi--One end  

 //multi--Two start
 function get_pay_type($start_date, $end_date, $ru_id){
        
     $sql = 'SELECT i.pay_id, p.pay_name, i.pay_time ' .
           'FROM ' .$GLOBALS['ecs']->table('payment'). ' AS p, ' .$GLOBALS['ecs']->table('order_info'). ' AS i '.
           "WHERE p.pay_id = i.pay_id " . order_query_sql('finished') .
           "AND i.add_time >= '$start_date' AND i.add_time <= '$end_date' ".
           " AND (SELECT og.ru_id FROM " . $GLOBALS['ecs']->table('order_goods') .' as og' . " WHERE og.order_id = i.order_id limit 0, 1) = '$ru_id' " .
            " AND (select count(*) from " .$GLOBALS['ecs']->table('order_info'). " as oi2 where oi2.main_order_id = i.order_id) = 0 " .  //主订单下有子订单时，则主订单不显示;            
           "ORDER BY i.add_time DESC";
     
     return $GLOBALS['db']->getAll($sql);
 }
 
 function get_shipping_type($start_date, $end_date, $ru_id){
     
     $sql = 'SELECT sp.shipping_id, sp.shipping_name AS ship_name, i.shipping_time ' .
               'FROM ' .$GLOBALS['ecs']->table('shipping'). ' AS sp, ' .$GLOBALS['ecs']->table('order_info'). ' AS i ' .
               'WHERE sp.shipping_id = i.shipping_id ' . order_query_sql('finished') .
               "AND i.add_time >= '$start_date' AND i.add_time <= '$end_date' " .
                " AND (SELECT og.ru_id FROM " . $GLOBALS['ecs']->table('order_goods') .' as og' . " WHERE og.order_id = i.order_id limit 0, 1) = '$ru_id' " .
                " AND (select count(*) from " .$GLOBALS['ecs']->table('order_info'). " as oi2 where oi2.main_order_id = i.order_id) = 0 " .  //主订单下有子订单时，则主订单不显示;  
               "ORDER BY i.add_time DESC";
     
     return $GLOBALS['db']->getAll($sql);
 }
  //multi--Two end
 
 //转为二维数组
 function get_to_array($arr1, $arr2, $str1 = '', $str2 = '', $str3 = '', $str4 = ''){
     $ship_arr = array();
     foreach($arr1 as $key1=>$row1){
        foreach($arr2 as $key2=>$row2){
            if($row1["{$str1}"] == $row2["{$str1}"]){
                $ship_arr[$row1["{$str1}"]]["{$str2}"][$key2] = $row2;
                $ship_arr[$row1["{$str1}"]]["{$str3}"] = $row1["{$str3}"];
                if(!empty($str4)){
                    $ship_arr[$row1["{$str1}"]]["{$str4}"] = $row1["{$str4}"];
                }
            }
        }
     }
     
     return $ship_arr;
 }
 
 function getthemonth($date)  
{  
    $firstday = local_date('Y-m-01', local_strtotime($date));  
    $lastday = local_date('Y-m-d', local_strtotime("$firstday +1 month -1 day"));  
    return array($firstday,$lastday);  
}


function get_area($countries, $pro = 0)
{
    $where = " AND (select count(*) from " . $GLOBALS['ecs']->table('order_info') . " as oi2 where oi2.main_order_id = i.order_id) = 0 ";  //主订单下有子订单时，则主订单不显示
    $where .= " AND i.order_status " . db_create_in(array(OS_CONFIRMED, OS_SPLITED)) . "  AND i.shipping_status = '" . SS_RECEIVED . "' AND i.pay_status " . db_create_in(array(PS_PAYED, PS_PAYING));
    if ($countries == 1 && $pro == 0) {
        $sql = "SELECT COUNT(*) AS area_num ,r.region_name AS region_name FROM " . $GLOBALS['ecs']->table('order_info') . " AS i " .
                " LEFT JOIN " . $GLOBALS['ecs']->table('region') . " AS r ON r.region_id = i.province" .
                " WHERE i.country = '$countries' " . $where . " GROUP BY i.province";
    } elseif ($countries == 1 && $pro > 0) {
        $sql = "SELECT COUNT(*) AS area_num ,r.region_name AS region_name FROM " . $GLOBALS['ecs']->table('order_info') . " AS i " .
                " LEFT JOIN " . $GLOBALS['ecs']->table('region') . " AS r ON r.region_id = i.city" .
                " WHERE i.province = '$pro' " . $where . " GROUP BY i.city";
    }
    $res = $GLOBALS['db']->getAll($sql);
    return $res;
}
?>