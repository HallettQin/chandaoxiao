<?php

/**
 * ECSHOP 缺货处理管理程序
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
admin_priv('booking');
/*------------------------------------------------------ */
//-- 列出所有订购信息
/*------------------------------------------------------ */
if ($_REQUEST['act']=='list_all')
{
    $smarty->assign('ur_here',      $_LANG['list_all']);
    $smarty->assign('full_page',    1);

    $list = get_bookinglist();
	
	//ecmoban模板堂 --zhuo start
	$adminru = get_admin_ru_id();
	$ruCat = '';
	if($adminru['ru_id'] == 0){
		$smarty->assign('priv_ru',   1);
	}else{
		$smarty->assign('priv_ru',   0);
	}
	//ecmoban模板堂 --zhuo end

    /* 订单内平台、店铺区分 */
    $smarty->assign('common_tabs', array('info' => isset($_REQUEST['seller_order_list']) ? 1 : 0 , 'url' => 'goods_booking.php?act=list_all'));    
    $smarty->assign('booking_list', $list['item']);
    $smarty->assign('filter',       $list['filter']);
    $smarty->assign('record_count', $list['record_count']);
    $smarty->assign('page_count',   $list['page_count']);

    $sort_flag  = sort_flag($list['filter']);
    $smarty->assign($sort_flag['tag'], $sort_flag['img']);

    assign_query_info();
    $smarty->display('booking_list.dwt');
}

/*------------------------------------------------------ */
//-- 翻页、排序
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'query')
{
    $list = get_bookinglist();
	
	//ecmoban模板堂 --zhuo start
	$adminru = get_admin_ru_id();
	$ruCat = '';
	if($adminru['ru_id'] == 0){
		$smarty->assign('priv_ru',   1);
	}else{
		$smarty->assign('priv_ru',   0);
	}
	//ecmoban模板堂 --zhuo end

    $smarty->assign('booking_list', $list['item']);
    $smarty->assign('filter',       $list['filter']);
    $smarty->assign('record_count', $list['record_count']);
    $smarty->assign('page_count',   $list['page_count']);

    $sort_flag  = sort_flag($list['filter']);
    $smarty->assign($sort_flag['tag'], $sort_flag['img']);

    make_json_result($smarty->fetch('booking_list.dwt'), '',
        array('filter' => $list['filter'], 'page_count' => $list['page_count']));
}

/*------------------------------------------------------ */
//-- 删除缺货登记
/*------------------------------------------------------ */

if ($_REQUEST['act'] == 'remove')
{
    check_authz_json('booking');

    $id = intval($_GET['id']);

    $db->query("DELETE FROM " .$ecs->table('booking_goods'). " WHERE rec_id='$id'");

    $url = 'goods_booking.php?act=query&' . str_replace('act=remove', '', $_SERVER['QUERY_STRING']);

    ecs_header("Location: $url\n");
    exit;
}

/*------------------------------------------------------ */
//-- 显示详情
/*------------------------------------------------------ */
if ($_REQUEST['act']=='detail')
{
    $id = intval($_REQUEST['id']);

    $smarty->assign('send_fail',    !empty($_REQUEST['send_ok']));
    $smarty->assign('booking',      get_booking_info($id));
    $smarty->assign('ur_here',      $_LANG['detail']);
    $smarty->assign('action_link',  array('text' => $_LANG['06_undispose_booking'], 'href'=>'goods_booking.php?act=list_all'));
    $smarty->display('booking_info.dwt');
}

/*------------------------------------------------------ */
//-- 处理提交数据
/*------------------------------------------------------ */
if ($_REQUEST['act'] =='update')
{
    /* 权限判断 */
    admin_priv('booking');

    $dispose_note = !empty($_POST['dispose_note']) ? trim($_POST['dispose_note']) : '';

    $sql = "UPDATE  ".$ecs->table('booking_goods').
            " SET is_dispose='1', dispose_note='$dispose_note', ".
                    "dispose_time='" .gmtime(). "', dispose_user='".$_SESSION['admin_name']."'".
            " WHERE rec_id='$_REQUEST[rec_id]'";
    $db->query($sql);

    /* 邮件通知处理流程 */
    if (!empty($_POST['send_email_notice']) or isset($_POST['remail']))
    {
        //获取邮件中的必要内容
        $sql = 'SELECT bg.email, bg.link_man, bg.goods_id, g.goods_name ' .
               'FROM ' .$ecs->table('booking_goods'). ' AS bg, ' .$ecs->table('goods'). ' AS g ' .
               "WHERE bg.goods_id = g.goods_id AND bg.rec_id='$_REQUEST[rec_id]'";
        $booking_info = $db->getRow($sql);

        /* 设置缺货回复模板所需要的内容信息 */
        $template    = get_mail_template('goods_booking');
        $goods_link = $ecs->url() . 'goods.php?id=' . $booking_info['goods_id'];

        $smarty->assign('user_name',   $booking_info['link_man']);
        $smarty->assign('goods_link', $goods_link);
        $smarty->assign('goods_name', $booking_info['goods_name']);
        $smarty->assign('dispose_note', $dispose_note);
        $smarty->assign('shop_name',   "<a href='".$ecs->url()."'>" . $_CFG['shop_name'] . '</a>');
        $smarty->assign('send_date',   local_date($GLOBALS['_CFG']['time_format'], gmtime()));

        $content = $smarty->fetch('str:' . $template['template_content']);

        /* 发送邮件 */
        if (send_mail($booking_info['link_man'], $booking_info['email'], $template['template_subject'], $content, $template['is_html']))
        {
            $send_ok = 0;
        }
        else
        {
            $send_ok = 1;
        }
    }

    ecs_header("Location: ?act=detail&id=".$_REQUEST['rec_id']."&send_ok=$send_ok\n");
    exit;
}

/**
 * 获取订购信息
 *
 * @access  public
 *
 * @return array
 */
function get_bookinglist()
{
	
	//ecmoban模板堂 --zhuo start
	$adminru = get_admin_ru_id();
	$ruCat = '';
	if($adminru['ru_id'] > 0){
		$ruCat = " and g.user_id = '" .$adminru['ru_id']. "'";
	}
	//ecmoban模板堂 --zhuo end
	
    /* 查询条件 */
    $filter['keywords']   = empty($_REQUEST['keywords']) ? '' : trim($_REQUEST['keywords']);
    if (isset($_REQUEST['is_ajax']) && $_REQUEST['is_ajax'] == 1)
    {
        $filter['keywords'] = json_str_iconv($filter['keywords']);
    }
	
    $filter['seller_order_list'] = isset($_REQUEST['seller_order_list']) && !empty($_REQUEST['seller_order_list']) ? 1 : 0;  //商家和自营订单标识
    $filter['dispose']    = empty($_REQUEST['dispose']) ? 0 : intval($_REQUEST['dispose']);
    $filter['sort_by']    = empty($_REQUEST['sort_by']) ? 'sort_order' : trim($_REQUEST['sort_by']);
    $filter['sort_order'] = empty($_REQUEST['sort_order']) ? 'DESC' : trim($_REQUEST['sort_order']);

	//ecmoban模板堂 --zhuo start
	$sql = "select user_id from " .$GLOBALS['ecs']->table('merchants_shop_information'). " where shoprz_brandName LIKE '%" . mysql_like_quote($filter['keywords']) . "%' OR shopNameSuffix LIKE '%" . mysql_like_quote($filter['keywords']) . "%'";
	$row = $GLOBALS['db']->getAll($sql);
	if(empty($row)){
		$user_id = 0;
	}else{
		foreach($row as $v){
			$user[] = $v['user_id'];
		}
		$user_id = implode(',',$user);
	}
	$where_user = '';
	if($user_id){
		$where_user = " OR (g.user_id in(" .$user_id. "))";
	}
	//ecmoban模板堂 --zhuo end  
    
    $where = (!empty($_REQUEST['keywords'])) ? " AND (g.goods_name LIKE '%" . mysql_like_quote($filter['keywords']) . "%' "  .$where_user . ")" : '';
    $where .= (!empty($_REQUEST['dispose'])) ? " AND bg.is_dispose = '$filter[dispose]' " : '';
    if ($filter['seller_order_list'])
    {    
        $where .= " AND g.user_id > 0 ";
    }else{	
        $where .= " AND g.user_id = 0 ";
    }  	
	$where .= $ruCat;

    $sql = 'SELECT COUNT(*) FROM ' .$GLOBALS['ecs']->table('booking_goods'). ' AS bg, '.
            $GLOBALS['ecs']->table('goods'). ' AS g '.
            "WHERE bg.goods_id = g.goods_id $where";
    $filter['record_count'] = $GLOBALS['db']->getOne($sql);

    /* 分页大小 */
    $filter = page_and_size($filter);

    /* 获取活动数据 */
    $sql = 'SELECT bg.rec_id, bg.link_man, g.goods_id, g.goods_name, g.user_id, bg.goods_number, bg.booking_time, bg.is_dispose '.
            'FROM ' .$GLOBALS['ecs']->table('booking_goods'). ' AS bg, ' .$GLOBALS['ecs']->table('goods'). ' AS g '.
            "WHERE bg.goods_id = g.goods_id $where " .
            "ORDER BY $filter[sort_by] $filter[sort_order] ".
            "LIMIT ". $filter['start'] .", $filter[page_size]";
    $row = $GLOBALS['db']->getAll($sql);
    foreach ($row AS $key => $val)
    {
        $row[$key]['booking_time'] = local_date($GLOBALS['_CFG']['time_format'], $val['booking_time']);
		
		$data = array('shoprz_brandName', 'shop_class_keyWords', 'shopNameSuffix');
		$shop_info = get_table_date('merchants_shop_information', "user_id = '" .$val['user_id']. "'", $data);
        $row[$key]['user_name'] = $shop_info['shoprz_brandName'].$shop_info['shopNameSuffix'];
    }

    $filter['keywords'] = stripslashes($filter['keywords']);
    $arr = array('item' => $row, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);

    return $arr;
}

/**
 * 获得缺货登记的详细信息
 *
 * @param   integer     $id
 *
 * @return  array
 */
function get_booking_info($id)
{
    global $ecs, $db, $_CFG, $_LANG;

    $sql ="SELECT bg.rec_id, bg.user_id, IFNULL(u.user_name, '$_LANG[guest_user]') AS user_name, ".
                "bg.link_man, g.goods_name, bg.goods_id, bg.goods_number, ".
                "bg.booking_time, bg.goods_desc,bg.dispose_user, bg.dispose_time, bg.email, ".
                "bg.tel, bg.dispose_note ,bg.dispose_user, bg.dispose_time,bg.is_dispose  ".
            "FROM " . $ecs->table('booking_goods')." AS bg ".
            "LEFT JOIN " . $ecs->table('goods') . " AS g ON g.goods_id=bg.goods_id ".
            "LEFT JOIN " . $ecs->table('users') . " AS u ON u.user_id=bg.user_id ".
            "WHERE bg.rec_id ='$id'";

    $res = $db->GetRow($sql);

    /* 格式化时间 */
    $res['booking_time'] = local_date($_CFG['time_format'],$res['booking_time']);
    if (!empty($res['dispose_time']))
    {
        $res['dispose_time'] = local_date($_CFG['time_format'],$res['dispose_time']);
    }

    return $res;
}

?>