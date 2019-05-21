<?php

/**
 * ECSHOP 降价通知管理程序
 * ============================================================================
 * 版权所有 2016-2018 产供销网络科技(广州)有限公司，并保留所有权利。
 * 网站地址: http://www.chandaoxiao.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author:  Hallett
*/

define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');

/* act操作项的初始化 */
if (empty($_REQUEST['act']))
{
    $_REQUEST['act'] = 'list';
}
else
{
    $_REQUEST['act'] = trim($_REQUEST['act']);
}

//ecmoban模板堂 --zhuo start
$adminru = get_admin_ru_id();
if($adminru['ru_id'] == 0){
    $smarty->assign('priv_ru',   1);
}else{
    $smarty->assign('priv_ru',   0);
} 	
//ecmoban模板堂 --zhuo end

$smarty->assign('menu_select',array('action' => '02_cat_and_goods', 'current' => 'sale_notice'));

/*------------------------------------------------------ */
//-- 获取列表
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'list')
{
    /* 检查权限 */
    admin_priv('sale_notice');

    $smarty->assign('ur_here',      $_LANG['sale_notice']);
    $smarty->assign('full_page',    1);

    $list = sale_notice_list($adminru['ru_id']);

    foreach($list['item'] as $key=>$val){
    	$list['item'][$key]['goods_link'] = $ecs->url()."goods.php?id=".$val['goods_id'];
    }

    $smarty->assign('notice_list', $list['item']);
    $smarty->assign('filter',       $list['filter']);
    $smarty->assign('record_count', $list['record_count']);
    $smarty->assign('page_count',   $list['page_count']);

    $sort_flag  = sort_flag($list['filter']);
    $smarty->assign($sort_flag['tag'], $sort_flag['img']);

    assign_query_info();
    $smarty->display('sale_notice_list.dwt');
}

/*------------------------------------------------------ */
//-- 翻页、搜索、排序
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'query')
{
    $list = sale_notice_list($adminru['ru_id']);

    foreach($list['item'] as $key=>$val){
    	$list['item'][$key]['goods_link'] = $ecs->url()."goods.php?id=".$val['goods_id'];
    }


    $smarty->assign('notice_list', $list['item']);
    $smarty->assign('filter',       $list['filter']);
    $smarty->assign('record_count', $list['record_count']);
    $smarty->assign('page_count',   $list['page_count']);

    $sort_flag  = sort_flag($list['filter']);
    $smarty->assign($sort_flag['tag'], $sort_flag['img']);

    make_json_result($smarty->fetch('sale_notice_list.dwt'), '',
        array('filter' => $list['filter'], 'page_count' => $list['page_count']));
}

/*------------------------------------------------------ */
//-- (通知详情)
/*------------------------------------------------------ */
if ($_REQUEST['act']=='view')
{
    /* 检查权限 */
    admin_priv('sale_notice');

    $detail = array();

    /* 获取评论详细信息并进行字符处理 */
    $sql = "SELECT a.*,b.user_name,c.goods_name FROM " .$ecs->table('sale_notice'). " as a LEFT JOIN ".$ecs->table('users')." AS b ON a.user_id=b.user_id".
		   " LEFT JOIN ".$ecs->table('goods')." AS c ON a.goods_id=c.goods_id WHERE a.id = '$_REQUEST[id]'";
    $detail = $db->getRow($sql);
    $detail['user_name']  = htmlspecialchars($detail['user_name']);
    $detail['add_time'] = local_date($_CFG['time_format'], $detail['add_time']);



    /* 模板赋值 */
    $smarty->assign('detail',          $detail);
    $smarty->assign('send_fail',   !empty($_REQUEST['send_ok']));

    $smarty->assign('ur_here',      $_LANG['sale_notice']);
    $smarty->assign('action_link',  array('text' => '降价通知列表',
    'href' => 'sale_notice.php?act=list'));

    /* 页面显示 */
    assign_query_info();
    $smarty->display('sale_notice_info.dwt');
}
/*------------------------------------------------------ */
//-- 处理 降价通知
/*------------------------------------------------------ */
if ($_REQUEST['act']=='action')
{
    admin_priv('sale_notice');
    include_once('../includes/cls_sms.php');

    $detail = array();
    $id = !empty($_REQUEST[id]) ? intval($_REQUEST[id]) : 0;
    /* 获取详细信息 */
    $sql = "SELECT a.*,b.user_name,c.goods_name,c.shop_price, c.goods_id FROM " . $ecs->table('sale_notice') . " as a LEFT JOIN " . $ecs->table('users') . " AS b ON a.user_id=b.user_id" .
            " LEFT JOIN " . $ecs->table('goods') . " AS c ON a.goods_id=c.goods_id WHERE a.id = '$id'";
    $detail = $db->getRow($sql);
    
    if ($_POST[mark]) {
        $sql = "UPDATE " . $ecs->table('sale_notice') . " SET mark = '$_POST[mark]' WHERE id = '$id'";
        $db->query($sql);
    }

    /* 邮件通知处理流程 */
    if (isset($_POST['remail']) && !empty($detail[email])) {

        /* 设置留言回复模板所需要的内容信息 */
        $template = get_mail_template('sale_notice');

        $smarty->assign('user_name', $_POST['user_name']);
        $smarty->assign('goods_name', $detail['goods_name']);
        $smarty->assign('goods_link', $ecs->url() . "goods.php?id=" . $detail['goods_id']);
        $smarty->assign('send_date', local_date($GLOBALS['_CFG']['time_format'], gmtime()));

        $content = $smarty->fetch('str:' . $template['template_content']);
        $send_type = 1;
        /* 发送邮件 */
        if (send_mail($detail['user_name'], $detail['email'], $template['template_subject'], $content, $template['is_html'])) {
            $sql = "UPDATE " . $ecs->table('sale_notice') . " SET status = 1, send_type=1 WHERE id = '$id'";
            $db->query($sql);
            $send_ok = 1;
            notice_log($detail['goods_id'], $detail['email'], $send_ok, $send_type);
        } else {
            $sql = "UPDATE " . $ecs->table('sale_notice') . " SET status = 3, send_type=1 WHERE id = '$id'";
            $db->query($sql);
            $send_ok = 0;
            notice_log($detail['goods_id'], $detail['email'], $send_ok, $send_type);
            /* 提示信息 */
            $link[] = array('text' => '返回降价列表', 'href' => 'sale_notice.php?act=list');
            sys_msg('发送失败', 0, $link);
        }
    }

    /* 短信通知处理流程 */
    if (isset($_POST['resms']) && !empty($detail['cellphone']) && $_CFG['sms_price_notice'] == 1) {
        
        $url = base64_encode($ecs->url() . 'mobile/index.php?r=goods&id=');
        $msg = sprintf($GLOBALS['_LANG']['sale_notice_sms'], $detail['goods_name'], $detail['shop_price']);
        
        //阿里大鱼短信接口参数
        $smsParams = array(
            'user_name' => $detail['user_name'],
            'username' => $detail['user_name'],
            'order_msg' => $msg ? $msg : '',
            'ordermsg' => $msg ? $msg : '',
            'mobile_phone' => $detail['cellphone'] ? $detail['cellphone'] : '',
            'mobilephone' => $detail['cellphone'] ? $detail['cellphone'] : ''
        );

        if ($GLOBALS['_CFG']['sms_type'] == 0) {
            
            $send_result = huyi_sms($smsParams, 'sms_price_notic');
            
            if (isset($send_result) && $send_result) {
                $res = true;
            } else {
                $res = false;
            }
        } elseif ($GLOBALS['_CFG']['sms_type'] >=1) {
            
            $result = sms_ali($smsParams, 'sms_price_notic'); //阿里大鱼短信变量传值，发送时机传值
            
            if ($result) {
                $resp = $GLOBALS['ecs']->ali_yu($result);
                
                if($resp->code == 0)
                {
                        $res = true;
                }else{
                        $res = false;
                }
            } else {
                sys_msg('阿里大鱼短信配置异常', 1);
            }
        }

        //记录日志
        $send_type = 2;
        if ($res) {
            $sql = "UPDATE " . $ecs->table('sale_notice') . " SET status = 1, send_type=2 WHERE id = '$id'";
            $db->query($sql);
            $send_ok = 1;
            notice_log($detail['goods_id'], $detail['cellphone'], $send_ok, $send_type);

            /* 提示信息 */
            $link[] = array('text' => '返回降价列表', 'href' => 'sale_notice.php?act=list');
            sys_msg('发送成功', 0, $link);
        } else {
            $sql = "UPDATE " . $ecs->table('sale_notice') . " SET status = 3, send_type=2 WHERE id = '$id'";
            $db->query($sql);
            $send_ok = 0;
            notice_log($detail['goods_id'], $detail['cellphone'], $send_ok, $send_type);
            /* 提示信息 */
            $link[] = array('text' => '返回降价列表', 'href' => 'sale_notice.php?act=list');
            sys_msg('发送失败', 0, $link);
        }
    }

    /* 清除缓存 */
    clear_cache_files();

    /* 记录管理员操作 */
    admin_log('处理降价通知', 'edit', 'sale_notice');

    ecs_header("Location: sale_notice.php?act=list");
    exit;
}

/*------------------------------------------------------ */
//-- 批量删除降价通知申请
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'batch')
{
    admin_priv('sale_notice');
    $action = isset($_POST['sel_action']) ? trim($_POST['sel_action']) : 'deny';

    if (isset($_POST['checkboxes']))
    {
        switch ($action)
        {
            case 'remove':
                $db->query("DELETE FROM " . $ecs->table('sale_notice') . " WHERE " . db_create_in($_POST['checkboxes'], 'id'));
                break;

           default :
               break;
        }

        clear_cache_files();
        $action = ($action == 'remove') ? 'remove' : 'edit';
        admin_log('', $action, 'adminlog');

        $link[] = array('text' => $_LANG['back_list'], 'href' => 'sale_notice.php?act=list');
        sys_msg(sprintf($_LANG['batch_drop_success'], count($_POST['checkboxes'])), 0, $link);
    }
    else
    {
        /* 提示信息 */
        $link[] = array('text' => $_LANG['back_list'], 'href' => 'sale_notice.php?act=list');
        sys_msg('返回降价列表', 0, $link);
    }
}

/**
 * 获取列表
 * @access  public
 * @return  array
 */
function sale_notice_list($ru_id)
{
    /* 查询条件 */
    $filter['keywords']     = empty($_REQUEST['keywords']) ? 0 : trim($_REQUEST['keywords']);
    if (isset($_REQUEST['is_ajax']) && $_REQUEST['is_ajax'] == 1)
    {
        $filter['keywords'] = json_str_iconv($filter['keywords']);
    }
    $filter['sort_by']      = empty($_REQUEST['sort_by']) ? 'id' : trim($_REQUEST['sort_by']);
    $filter['sort_order']   = empty($_REQUEST['sort_order']) ? 'DESC' : trim($_REQUEST['sort_order']);
    $filter['send_status']      = empty($_REQUEST['send_status']) ? '' : intval($_REQUEST['send_status']);

    $where = (!empty($filter['keywords'])) ? " AND a.email LIKE '%" . mysql_like_quote($filter['keywords']) . "%' " : '';
	$where .= (!empty($filter['send_status'])) ? " AND a.status = '{$filter['send_status']}' " : '';
        
    if($ru_id > 0){
        $where .= " AND g.user_id = '$ru_id'";
    }    

	//$sql = "SELECT count(*) FROM " .$GLOBALS['ecs']->table('sale_notice'). " as a, ".$GLOBALS['ecs']->table('goods')." AS g WHERE 1=1 $where";
    $sql="SELECT count(*) FROM " .$GLOBALS['ecs']->table('sale_notice'). " AS a, ".$GLOBALS['ecs']->table('users').
			" AS b, ".$GLOBALS['ecs']->table('goods')." AS g WHERE 1=1 $where AND a.user_id=b.user_id AND a.goods_id=g.goods_id ";//     修复记录与数量不一致
    $filter['record_count'] = $GLOBALS['db']->getOne($sql);

    /* 分页大小 */
    $filter = page_and_size($filter);

    /* 获取数据 */
    $arr = array();
    $sql  = "SELECT a.*,b.user_name,g.goods_name,g.shop_price, g.user_id as ru_id FROM " .$GLOBALS['ecs']->table('sale_notice'). " AS a, ".$GLOBALS['ecs']->table('users').
			" AS b, ".$GLOBALS['ecs']->table('goods')." AS g WHERE 1=1 $where AND a.user_id=b.user_id AND a.goods_id=g.goods_id " .
            " ORDER BY $filter[sort_by] $filter[sort_order] ".
            " LIMIT ". $filter['start'] .", $filter[page_size]";
    $res  = $GLOBALS['db']->query($sql);

	$statusArr = array(1=>'已发送' , 2=>'未发送' , 3=>'系统发送失败');
	$send_typeArr = array(1=>'邮件' , 2=>'短信');
    while ($row = $GLOBALS['db']->fetchRow($res))
    {
        $row['status'] = $statusArr[$row['status']];
        $row['send_type'] = $send_typeArr[$row['send_type']];
        $row['add_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['add_time']);
        $row['shop_name'] = get_shop_name($row['ru_id'], 1);

        $arr[] = $row;
    }
    $filter['keywords'] = stripslashes($filter['keywords']);
    $arr = array('item' => $arr, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);

    return $arr;
}

?>